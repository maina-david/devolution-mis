<?php

namespace App\Actions;

use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\KnowledgeItem;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CreateKnowledgeItem
{
    public function __construct(private StartWorkflow $startWorkflow, private AuditLogger $auditLogger, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): KnowledgeItem
    {
        $countyId = is_string($attributes['county_id'] ?? null) ? $attributes['county_id'] : null;
        if ($countyId !== null) {
            abort_unless($actor->canAccessCounty(County::query()->findOrFail($countyId)), 403);
        }

        return DB::transaction(function () use ($actor, $attributes): KnowledgeItem {
            $countyId = is_string($attributes['county_id'] ?? null) ? $attributes['county_id'] : null;
            $sectorId = is_string($attributes['sector_id'] ?? null) ? $attributes['sector_id'] : null;
            $referenceDataRelease = $this->referenceDataReleaseResolver->forKnowledgeItem($countyId, $sectorId, now());
            $documentId = $attributes['assessment_document_id'] ?? null;
            $document = is_string($documentId)
                ? AssessmentDocument::query()->findOrFail($documentId)
                : null;

            if ($document && ($document->scan_status !== 'clean' || $document->record_status !== 'active')) {
                throw new AuthorizationException('Only active, malware-cleared repository documents may be published as knowledge resources.');
            }

            if ($document && ! $actor->programmeRole()->hasNationalScope() && ! $actor->assignedCounties()->whereKey($document->county_id)->exists() && $actor->county_id !== $document->county_id) {
                throw new AuthorizationException('The selected repository document is outside your county portfolio.');
            }

            $courseIds = is_array($attributes['course_ids'] ?? null)
                ? array_values(array_filter($attributes['course_ids'], is_string(...)))
                : [];
            unset($attributes['course_ids']);
            $tags = collect(is_array($attributes['tags'] ?? null) ? $attributes['tags'] : explode(',', (string) ($attributes['tags'] ?? '')))
                ->map(fn (mixed $tag): string => mb_strtolower(trim((string) $tag)))
                ->filter()->unique()->take(20)->values()->all();
            $item = KnowledgeItem::create([
                ...$attributes,
                'reference_data_release_id' => $referenceDataRelease->id,
                'author_id' => $actor->id,
                'reference' => 'KM-'.now()->format('Y').'-'.str_pad((string) (KnowledgeItem::withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT),
                'tags' => $tags,
                'status' => 'draft',
            ]);
            $item->courses()->sync($courseIds);

            $definition = WorkflowDefinition::query()->where('code', 'KNOWLEDGE-PUBLICATION')->firstOrFail();
            $instance = $this->startWorkflow->handle($definition, $item, $actor, [
                'has_content' => filled($item->content_body) || filled($item->external_url) || $document !== null,
                'repository_ready' => true,
            ], $item->county_id);
            $item->update(['workflow_instance_id' => $instance->id]);
            $this->auditLogger->record($actor, $item, 'knowledge.item.created', "Knowledge item {$item->reference} created.", $item->county_id, ['tags' => $tags, 'course_ids' => $courseIds, 'reference_data_release_id' => $referenceDataRelease->id, 'reference_data_release_version' => $referenceDataRelease->version, 'reference_data_release_checksum' => $referenceDataRelease->checksum]);

            return $item->refresh();
        });
    }
}
