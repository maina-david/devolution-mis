<?php

namespace App\Services;

use App\Models\AccessDelegation;
use App\Models\AnalyticsDashboard;
use App\Models\Assessment;
use App\Models\CitizenCase;
use App\Models\DevolutionInnovation;
use App\Models\DevolutionProject;
use App\Models\DswgAction;
use App\Models\DswgWorkingGroup;
use App\Models\ExchequerRequest;
use App\Models\IgrResolution;
use App\Models\IndicatorDefinition;
use App\Models\InnovationReplication;
use App\Models\IntegrationSystem;
use App\Models\KnowledgeItem;
use App\Models\LearningCourse;
use App\Models\PartnerCollaborationAction;
use App\Models\PartnerProfile;
use App\Models\PerformancePlan;
use App\Models\ProgrammeEvaluation;
use App\Models\ReferenceLineageDisposition;
use App\Models\ReportSchedule;
use App\Models\SupportTicket;
use App\Models\TravelRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class LegacyReferenceInventory
{
    public const TYPE_KEYS = ['access_delegation', 'analytics_dashboard', 'assessment', 'citizen_case', 'innovation', 'project', 'dswg_action', 'dswg_working_group', 'exchequer_request', 'igr_resolution', 'indicator_definition', 'innovation_replication', 'integration_system', 'knowledge_item', 'learning_course', 'partner_action', 'partner_profile', 'performance_plan', 'programme_evaluation', 'report_schedule', 'support_ticket', 'travel_request'];

    /** @return array<string, array{label: string, model: class-string<Model>, releaseColumn: string}> */
    public function types(): array
    {
        return [
            'access_delegation' => $this->definition('access_delegation', AccessDelegation::class),
            'analytics_dashboard' => $this->definition('analytics_dashboard', AnalyticsDashboard::class),
            'assessment' => $this->definition('assessment', Assessment::class),
            'citizen_case' => $this->definition('citizen_case', CitizenCase::class, 'intake_reference_data_release_id'),
            'innovation' => $this->definition('innovation', DevolutionInnovation::class),
            'project' => $this->definition('project', DevolutionProject::class),
            'dswg_action' => $this->definition('dswg_action', DswgAction::class),
            'dswg_working_group' => $this->definition('dswg_working_group', DswgWorkingGroup::class),
            'exchequer_request' => $this->definition('exchequer_request', ExchequerRequest::class),
            'igr_resolution' => $this->definition('igr_resolution', IgrResolution::class),
            'indicator_definition' => $this->definition('indicator_definition', IndicatorDefinition::class),
            'innovation_replication' => $this->definition('innovation_replication', InnovationReplication::class),
            'integration_system' => $this->definition('integration_system', IntegrationSystem::class),
            'knowledge_item' => $this->definition('knowledge_item', KnowledgeItem::class),
            'learning_course' => $this->definition('learning_course', LearningCourse::class),
            'partner_action' => $this->definition('partner_action', PartnerCollaborationAction::class),
            'partner_profile' => $this->definition('partner_profile', PartnerProfile::class),
            'performance_plan' => $this->definition('performance_plan', PerformancePlan::class),
            'programme_evaluation' => $this->definition('programme_evaluation', ProgrammeEvaluation::class),
            'report_schedule' => $this->definition('report_schedule', ReportSchedule::class),
            'support_ticket' => $this->definition('support_ticket', SupportTicket::class),
            'travel_request' => $this->definition('travel_request', TravelRequest::class),
        ];
    }

    /** @param class-string<Model> $model
     * @return array{label: string, model: class-string<Model>, releaseColumn: string}
     */
    private function definition(string $key, string $model, string $releaseColumn = 'reference_data_release_id'): array
    {
        return ['label' => __('migration.lineage_types.'.$key), 'model' => $model, 'releaseColumn' => $releaseColumn];
    }

    public function record(string $type, string $id, bool $requireUnpinned = false): Model
    {
        $definition = $this->types()[$type] ?? null;
        abort_unless($definition !== null, 422, __('migration.lineage_errors.unsupported_record_type'));
        $record = $definition['model']::query()->findOrFail($id);
        if ($requireUnpinned) {
            abort_unless($record->getAttribute($definition['releaseColumn']) === null, 409, __('migration.lineage_errors.already_pinned'));
        }

        return $record;
    }

    public function releaseColumn(string $type): string
    {
        $definition = $this->types()[$type] ?? null;
        abort_unless($definition !== null, 422, __('migration.lineage_errors.unsupported_record_type'));

        return $definition['releaseColumn'];
    }

    /** @return array<string, mixed> */
    public function safeSnapshot(Model $record): array
    {
        $attributes = [
            'id', 'reference', 'code', 'title', 'name', 'county_id', 'target_county_id', 'source_county_id',
            'organization_id', 'partner_organization_id', 'owner_organization_id', 'accountable_organization_id',
            'lead_organization_id', 'sector_id', 'programme_id', 'created_at',
            'access_type', 'scope_type', 'permission_scope', 'county_scope_snapshot',
        ];

        return collect($attributes)
            ->filter(fn (string $attribute): bool => array_key_exists($attribute, $record->getAttributes()))
            ->mapWithKeys(fn (string $attribute): array => [$attribute => $record->getAttribute($attribute)])
            ->all();
    }

    /** @return list<array{id: string, label: string, snapshot: array<string, mixed>}> */
    public function candidates(string $type, int $limit = 50): array
    {
        $definition = $this->types()[$type] ?? null;
        abort_unless($definition !== null, 422, __('migration.lineage_errors.unsupported_record_type'));

        $candidates = $definition['model']::query()
            ->whereNull($definition['releaseColumn'])
            ->whereNotIn('id', ReferenceLineageDisposition::query()->select('record_id')->where('record_type', $type)->whereIn('status', ['proposed', 'approved', 'applied']))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (Model $record): array {
                $snapshot = $this->safeSnapshot($record);
                $label = collect(['reference', 'code', 'title', 'name'])->map(fn (string $key): mixed => $snapshot[$key] ?? null)->first(fn (mixed $value): bool => is_string($value) && $value !== '');

                return ['id' => (string) $record->getKey(), 'label' => is_string($label) ? $label : (string) $record->getKey(), 'snapshot' => $snapshot];
            })->all();

        return array_values($candidates);
    }

    /** @return array{total: int, recordTypes: int, records: list<array{key: string, type: string, model: class-string<Model>, count: int, available: int, pending: int, applied: int, oldestAt: string|null, latestAt: string|null}>} */
    public function report(): array
    {
        $records = [];
        foreach ($this->types() as $key => $definition) {
            $unpinnedQuery = $definition['model']::query()->whereNull($definition['releaseColumn']);
            $appliedRecordIds = ReferenceLineageDisposition::query()
                ->select('record_id')
                ->where('record_type', $key)
                ->where('status', 'applied');
            $unresolvedQuery = (clone $unpinnedQuery)->whereNotIn('id', $appliedRecordIds);
            $count = $unresolvedQuery->count();
            $pending = ReferenceLineageDisposition::query()
                ->where('record_type', $key)
                ->whereIn('status', ['proposed', 'approved'])
                ->count();
            $applied = ReferenceLineageDisposition::query()
                ->where('record_type', $key)
                ->where('status', 'applied')
                ->count();
            if ($count === 0 && $applied === 0) {
                continue;
            }
            $oldestAt = (clone $unresolvedQuery)->min('created_at');
            $latestAt = (clone $unresolvedQuery)->max('created_at');
            $records[] = [
                'key' => $key,
                'type' => $definition['label'],
                'model' => $definition['model'],
                'count' => $count,
                'available' => max(0, $count - $pending),
                'pending' => $pending,
                'applied' => $applied,
                'oldestAt' => is_string($oldestAt) ? Carbon::parse($oldestAt)->toIso8601String() : null,
                'latestAt' => is_string($latestAt) ? Carbon::parse($latestAt)->toIso8601String() : null,
            ];
        }
        usort($records, fn (array $left, array $right): int => $right['count'] <=> $left['count']);

        return ['total' => array_sum(array_column($records, 'count')), 'recordTypes' => count($records), 'records' => $records];
    }
}
