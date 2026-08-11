<?php

namespace App\Actions;

use App\Models\DocumentLink;
use App\Models\LearningCourse;
use App\Models\LearningLesson;
use App\Models\LearningOfflinePackage;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentIntegrityVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use ZipArchive;

class GenerateLearningOfflinePackage
{
    public function __construct(private DocumentIntegrityVerifier $integrityVerifier, private AuditLogger $auditLogger) {}

    public function handle(LearningCourse $course, User $actor): LearningOfflinePackage
    {
        $course = LearningCourse::query()->with([
            'modules' => fn ($query) => $query->orderBy('sequence'),
            'modules.lessons' => fn ($query) => $query->orderBy('sequence'),
            'modules.lessons.questions:id,learning_lesson_id,question,options,points,sequence',
            'modules.lessons.documentLinks.document.currentVersion',
        ])->findOrFail($course->id);
        abort_unless($course->status === 'published', 409, 'Only a published course can be packaged for offline use.');

        $snapshot = $this->contentSnapshot($course);
        $snapshotJson = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $courseContentChecksum = hash('sha256', $snapshotJson);
        $package = DB::transaction(function () use ($course, $actor, $courseContentChecksum): LearningOfflinePackage {
            LearningCourse::query()->whereKey($course->id)->lockForUpdate()->sole();
            $nextVersion = ((int) LearningOfflinePackage::query()->where('learning_course_id', $course->id)->max('package_version')) + 1;

            return LearningOfflinePackage::create([
                'learning_course_id' => $course->id,
                'generated_by' => $actor->id,
                'package_version' => $nextVersion,
                'locale' => $course->language,
                'course_content_checksum' => $courseContentChecksum,
            ]);
        });

        $temporaryZip = null;
        $temporaryAssets = [];
        $storedDisk = null;
        $storedPath = null;

        try {
            $temporaryZip = tempnam(sys_get_temp_dir(), 'idmis-offline-');
            if ($temporaryZip === false) {
                throw new RuntimeException('Unable to allocate temporary package storage.');
            }
            $manifest = [
                'schema' => 'idmis.learning-offline-package.v1',
                'course' => $snapshot,
                'course_content_checksum' => $courseContentChecksum,
                'package_version' => $package->package_version,
                'generated_at' => now()->toIso8601String(),
                'assets' => $this->assetManifest($course),
                'sync_contract' => [
                    'schema' => 'idmis.learning-offline-progress.v1',
                    'review_required' => true,
                    'maximum_events' => 100,
                    'quiz_progress_excluded' => true,
                ],
            ];
            $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $manifestChecksum = hash('sha256', $manifestJson);
            $zip = new ZipArchive;
            if ($zip->open($temporaryZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to create the offline package archive.');
            }
            $zip->addFromString('manifest.json', $manifestJson);
            $zip->addFromString('index.html', $this->offlineHtml($course, $package, $manifestChecksum));
            $zip->addFromString('offline-progress-template.json', json_encode([
                'schema' => 'idmis.learning-offline-progress.v1',
                'client_sync_id' => null,
                'device_id' => null,
                'package_id' => $package->id,
                'package_manifest_checksum' => $manifestChecksum,
                'exported_at' => null,
                'events' => [],
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            foreach ($course->modules->flatMap->lessons as $lesson) {
                foreach ($lesson->documentLinks as $link) {
                    if (! $lesson->is_downloadable || $link->document->record_status !== 'active') {
                        continue;
                    }
                    $version = $link->document->currentVersion;
                    if ($version === null || $version->scan_status !== 'clean' || ! $this->integrityVerifier->matches($version->storage_disk, $version->path, $version->content_checksum)) {
                        throw ValidationException::withMessages(['package' => "Downloadable asset {$link->document->title} is unavailable, quarantined, or failed integrity verification."]);
                    }
                    $stream = Storage::disk($version->storage_disk)->readStream($version->path);
                    if (! is_resource($stream)) {
                        throw new RuntimeException("Unable to read downloadable asset {$link->document->title}.");
                    }
                    $temporaryAsset = tempnam(sys_get_temp_dir(), 'idmis-asset-');
                    if ($temporaryAsset === false) {
                        fclose($stream);
                        throw new RuntimeException('Unable to allocate temporary asset storage.');
                    }
                    $temporaryAssets[] = $temporaryAsset;
                    $target = fopen($temporaryAsset, 'wb');
                    if (! is_resource($target)) {
                        fclose($stream);
                        throw new RuntimeException('Unable to stage an offline asset.');
                    }
                    stream_copy_to_stream($stream, $target);
                    fclose($stream);
                    fclose($target);
                    $zip->addFile($temporaryAsset, $this->assetArchivePath($lesson, $link));
                }
            }
            if (! $zip->close()) {
                throw new RuntimeException('Unable to finalize the offline package archive.');
            }

            $path = "learning/offline/{$course->id}/v{$package->package_version}.zip";
            $disk = (string) config('filesystems.default');
            $archive = fopen($temporaryZip, 'rb');
            if (! is_resource($archive) || ! Storage::disk($disk)->put($path, $archive)) {
                if (is_resource($archive)) {
                    fclose($archive);
                }
                throw new RuntimeException('Unable to store the offline package archive.');
            }
            fclose($archive);
            $storedDisk = $disk;
            $storedPath = $path;
            $checksum = hash_file('sha256', $temporaryZip);
            $size = filesize($temporaryZip);
            if (! is_string($checksum) || ! is_int($size)) {
                throw new RuntimeException('Unable to verify the generated offline package.');
            }
            $readyAttributes = [
                'status' => 'ready',
                'storage_disk' => $disk,
                'path' => $path,
                'original_name' => Str::slug($course->code.'-'.$course->title)."-offline-v{$package->package_version}.zip",
                'mime_type' => 'application/zip',
                'size_bytes' => $size,
                'content_checksum' => $checksum,
                'manifest_checksum' => $manifestChecksum,
                'manifest_summary' => ['modules' => $course->modules->count(), 'lessons' => $course->modules->flatMap->lessons->count(), 'assets' => count($manifest['assets']), 'sync_schema' => $manifest['sync_contract']['schema']],
                'generated_at' => now(),
            ];
            $this->auditLogger->record($actor, $package, 'learning.offline-package.generated', "Offline package v{$package->package_version} generated for {$course->code}.", $course->county_id, ['course_content_checksum' => $courseContentChecksum, 'content_checksum' => $checksum, 'manifest_checksum' => $manifestChecksum]);
            $package->update($readyAttributes);

            return $package->refresh();
        } catch (Throwable $exception) {
            if ($package->status !== 'ready') {
                if (is_string($storedDisk) && is_string($storedPath)) {
                    Storage::disk($storedDisk)->delete($storedPath);
                }
                $package->update(['status' => 'failed', 'failed_at' => now(), 'failure_message' => Str::limit($exception->getMessage(), 1000)]);
            }
            throw $exception;
        } finally {
            if (is_string($temporaryZip) && file_exists($temporaryZip)) {
                unlink($temporaryZip);
            }
            foreach ($temporaryAssets as $temporaryAsset) {
                if (file_exists($temporaryAsset)) {
                    unlink($temporaryAsset);
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function contentSnapshot(LearningCourse $course): array
    {
        return [
            'id' => $course->id,
            'code' => $course->code,
            'title' => $course->title,
            'summary' => $course->summary,
            'description' => $course->description,
            'language' => $course->language,
            'estimated_minutes' => $course->estimated_minutes,
            'modules' => $course->modules->map(fn ($module): array => [
                'title' => $module->title,
                'description' => $module->description,
                'lessons' => $module->lessons->map(fn (LearningLesson $lesson): array => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'summary' => $lesson->summary,
                    'content_type' => $lesson->content_type,
                    'content_body' => $lesson->content_body,
                    'estimated_minutes' => $lesson->estimated_minutes,
                    'accessible_alternative' => $lesson->assetMetadata()['accessible_alternative'] ?? null,
                    'questions' => $lesson->questions->map(fn ($question): array => ['question' => $question->question, 'options' => $question->options, 'points' => $question->points])->values()->all(),
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function assetManifest(LearningCourse $course): array
    {
        $assets = [];
        foreach ($course->modules as $module) {
            foreach ($module->lessons as $lesson) {
                foreach ($lesson->documentLinks as $link) {
                    $version = $link->document->currentVersion;
                    $assets[] = ['lesson_id' => $lesson->id, 'document_id' => $link->document->id, 'title' => $link->document->title, 'included' => $lesson->is_downloadable && $link->document->record_status === 'active' && $version !== null && $version->scan_status === 'clean', 'archive_path' => $lesson->is_downloadable ? $this->assetArchivePath($lesson, $link) : null, 'mime_type' => $version === null ? null : $version->mime_type, 'size_bytes' => $version === null ? null : $version->size_bytes, 'content_checksum' => $version === null ? null : $version->content_checksum, 'scan_status' => $version === null ? null : $version->scan_status, 'source_type' => $link->document->source_type, 'licence' => $lesson->assetMetadata()['licence'] ?? null];
                }
            }
        }

        return $assets;
    }

    private function assetArchivePath(LearningLesson $lesson, DocumentLink $link): string
    {
        $name = $link->document->original_name ?? 'asset';

        return 'assets/'.Str::slug($lesson->title).'/'.Str::slug(pathinfo($name, PATHINFO_FILENAME)).'.'.Str::lower(pathinfo($name, PATHINFO_EXTENSION) ?: 'bin');
    }

    private function offlineHtml(LearningCourse $course, LearningOfflinePackage $package, string $manifestChecksum): string
    {
        $modules = $course->modules->map(function ($module): string {
            $lessons = $module->lessons->map(function (LearningLesson $lesson): string {
                $body = e($lesson->content_body ?? $lesson->summary ?? 'Use the accessible alternative supplied in the package manifest.');
                $alternative = e((string) ($lesson->assetMetadata()['accessible_alternative'] ?? ''));
                $progressControl = $lesson->content_type === 'quiz' ? '<p><em>Complete this assessment in the authenticated IDMIS learning hub.</em></p>' : '<button type="button" data-complete-lesson="'.e($lesson->id).'">Mark complete on this device</button><span role="status" data-status-for="'.e($lesson->id).'"></span>';

                return '<article><h3>'.e($lesson->title).'</h3><p>'.$body.'</p>'.($alternative !== '' ? '<p><strong>Accessible alternative:</strong> '.$alternative.'</p>' : '').$progressControl.'</article>';
            })->implode('');

            return '<section><h2>'.e($module->title).'</h2>'.$lessons.'</section>';
        })->implode('');

        $clientConfiguration = json_encode(['packageId' => $package->id, 'manifestChecksum' => $manifestChecksum, 'storageKey' => 'idmis-offline-progress-'.$package->id], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return '<!doctype html><html lang="'.e($course->language).'"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($course->title).'</title><style>body{font-family:system-ui,sans-serif;max-width:72ch;margin:auto;padding:2rem;line-height:1.6;color:#17212b}article{border-top:1px solid #ccd5dc;padding-block:1rem}code{overflow-wrap:anywhere}button{padding:.65rem 1rem;border:1px solid #147a55;border-radius:.4rem;background:#fff;color:#0b5d40;font-weight:650;cursor:pointer}button:focus-visible{outline:3px solid #111;outline-offset:3px}[role=status]{display:inline-block;margin-inline-start:.75rem}</style></head><body><header><p>Republic of Kenya · State Department for Devolution</p><h1>'.e($course->title).'</h1><p>'.e($course->summary).'</p></header><main>'.$modules.'</main><section aria-labelledby="sync-heading"><h2 id="sync-heading">Return your offline progress</h2><p>Export the device record, sign in to the IDMIS learning hub, and upload it for independent reconciliation.</p><button type="button" id="export-progress">Export progress record</button><p role="status" id="export-status"></p></section><footer><p>Package manifest SHA-256: <code>'.$manifestChecksum.'</code></p><p>Progress completed offline must be synchronized through an authorized IDMIS session; this package contains no answer key and does not alter the official learning record.</p></footer><script>const config='.$clientConfiguration.';const startedAt=Date.now();const deviceKey="idmis-offline-device-id";const deviceId=localStorage.getItem(deviceKey)||crypto.randomUUID();localStorage.setItem(deviceKey,deviceId);const readEvents=()=>JSON.parse(localStorage.getItem(config.storageKey)||"[]");document.querySelectorAll("[data-complete-lesson]").forEach((button)=>{const lessonId=button.dataset.completeLesson;const status=document.querySelector(`[data-status-for="${lessonId}"]`);if(readEvents().some((event)=>event.lesson_id===lessonId)){status.textContent="Completed on this device";}button.addEventListener("click",()=>{const events=readEvents().filter((event)=>event.lesson_id!==lessonId);events.push({client_event_id:crypto.randomUUID(),lesson_id:lessonId,status:"completed",progress_percentage:100,time_spent_seconds:Math.max(1,Math.min(86400,Math.round((Date.now()-startedAt)/1000))),occurred_at:new Date().toISOString(),state:{source:"offline-package"}});localStorage.setItem(config.storageKey,JSON.stringify(events));status.textContent="Completed on this device";});});document.getElementById("export-progress").addEventListener("click",()=>{const events=readEvents();const status=document.getElementById("export-status");if(events.length===0){status.textContent="Mark at least one lesson before exporting.";return;}const payload={schema:"idmis.learning-offline-progress.v1",client_sync_id:crypto.randomUUID(),device_id:deviceId,package_id:config.packageId,package_manifest_checksum:config.manifestChecksum,exported_at:new Date().toISOString(),events};const blob=new Blob([JSON.stringify(payload,null,2)],{type:"application/json"});const link=document.createElement("a");link.href=URL.createObjectURL(blob);link.download="idmis-offline-progress.json";link.click();URL.revokeObjectURL(link.href);status.textContent="Progress record exported. Upload it through your authenticated IDMIS learning hub.";});</script></body></html>';
    }
}
