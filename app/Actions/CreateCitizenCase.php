<?php

namespace App\Actions;

use App\Models\CitizenCase;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateCitizenCase
{
    public function __construct(private StoreCitizenCaseAttachment $storeAttachment, private AuditLogger $auditLogger, private EffectiveReferenceDataReleaseResolver $referenceDataResolver) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{case: CitizenCase, tracking_token: string}
     */
    public function handle(array $attributes, ?UploadedFile $attachment = null): array
    {
        $trackingToken = Str::random(48);
        $case = DB::transaction(function () use ($attributes, $trackingToken, $attachment): CitizenCase {
            $referenceDataRelease = $this->referenceDataResolver->forCitizenCase((string) $attributes['county_id'], is_string($attributes['sector_id'] ?? null) ? $attributes['sector_id'] : null, now());
            $case = CitizenCase::create([...collect($attributes)->except(['attachment', 'source_type', 'website'])->all(), 'reference' => mb_strtoupper(substr((string) $attributes['case_type'], 0, 3)).'-'.now()->format('Y').'-'.mb_strtoupper(Str::random(10)), 'tracking_token_hash' => hash('sha256', $trackingToken), 'intake_reference_data_release_id' => $referenceDataRelease->id, 'consent_recorded_at' => now(), 'first_response_due_at' => now()->addHours($attributes['case_type'] === 'grievance' ? 24 : 72), 'resolution_due_at' => now()->addDays($attributes['case_type'] === 'grievance' ? 14 : 10), 'source_metadata' => ['received_at' => now()->toIso8601String(), 'channel' => $attributes['channel']]]);
            $case->messages()->create(['direction' => 'inbound', 'visibility' => 'public', 'channel' => $case->channel, 'body' => $case->description, 'delivery_status' => 'received', 'posted_at' => now()]);
            if ($attachment) {
                $this->storeAttachment->handle($case, $attachment, (string) ($attributes['source_type'] ?? 'born_digital'));
            }
            $this->auditLogger->record(null, $case, 'citizen_case.received', "Public {$case->case_type} {$case->reference} received.", $case->county_id, ['channel' => $case->channel, 'category' => $case->category, 'anonymous' => $case->is_anonymous, 'reference_data_release_id' => $referenceDataRelease->id, 'reference_data_version' => $referenceDataRelease->version, 'reference_data_checksum' => $referenceDataRelease->checksum]);

            return $case;
        });

        return ['case' => $case, 'tracking_token' => $trackingToken];
    }
}
