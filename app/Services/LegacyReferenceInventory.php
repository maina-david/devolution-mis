<?php

namespace App\Services;

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
    public const TYPE_KEYS = ['analytics_dashboard', 'assessment', 'citizen_case', 'innovation', 'project', 'dswg_action', 'dswg_working_group', 'exchequer_request', 'igr_resolution', 'indicator_definition', 'innovation_replication', 'integration_system', 'knowledge_item', 'learning_course', 'partner_action', 'partner_profile', 'performance_plan', 'programme_evaluation', 'report_schedule', 'support_ticket', 'travel_request'];

    /** @return array<string, array{label: string, model: class-string<Model>, releaseColumn: string}> */
    public function types(): array
    {
        return [
            'analytics_dashboard' => ['label' => 'Analytics dashboards', 'model' => AnalyticsDashboard::class, 'releaseColumn' => 'reference_data_release_id'],
            'assessment' => ['label' => 'Assessments', 'model' => Assessment::class, 'releaseColumn' => 'reference_data_release_id'],
            'citizen_case' => ['label' => 'Citizen cases', 'model' => CitizenCase::class, 'releaseColumn' => 'intake_reference_data_release_id'],
            'innovation' => ['label' => 'Innovations', 'model' => DevolutionInnovation::class, 'releaseColumn' => 'reference_data_release_id'],
            'project' => ['label' => 'Projects', 'model' => DevolutionProject::class, 'releaseColumn' => 'reference_data_release_id'],
            'dswg_action' => ['label' => 'DSWG actions', 'model' => DswgAction::class, 'releaseColumn' => 'reference_data_release_id'],
            'dswg_working_group' => ['label' => 'DSWG working groups', 'model' => DswgWorkingGroup::class, 'releaseColumn' => 'reference_data_release_id'],
            'exchequer_request' => ['label' => 'Exchequer requests', 'model' => ExchequerRequest::class, 'releaseColumn' => 'reference_data_release_id'],
            'igr_resolution' => ['label' => 'IGR resolutions', 'model' => IgrResolution::class, 'releaseColumn' => 'reference_data_release_id'],
            'indicator_definition' => ['label' => 'Indicator definitions', 'model' => IndicatorDefinition::class, 'releaseColumn' => 'reference_data_release_id'],
            'innovation_replication' => ['label' => 'Innovation replications', 'model' => InnovationReplication::class, 'releaseColumn' => 'reference_data_release_id'],
            'integration_system' => ['label' => 'Integration systems', 'model' => IntegrationSystem::class, 'releaseColumn' => 'reference_data_release_id'],
            'knowledge_item' => ['label' => 'Knowledge items', 'model' => KnowledgeItem::class, 'releaseColumn' => 'reference_data_release_id'],
            'learning_course' => ['label' => 'Learning courses', 'model' => LearningCourse::class, 'releaseColumn' => 'reference_data_release_id'],
            'partner_action' => ['label' => 'Partner actions', 'model' => PartnerCollaborationAction::class, 'releaseColumn' => 'reference_data_release_id'],
            'partner_profile' => ['label' => 'Partner profiles', 'model' => PartnerProfile::class, 'releaseColumn' => 'reference_data_release_id'],
            'performance_plan' => ['label' => 'Performance plans', 'model' => PerformancePlan::class, 'releaseColumn' => 'reference_data_release_id'],
            'programme_evaluation' => ['label' => 'Programme evaluations', 'model' => ProgrammeEvaluation::class, 'releaseColumn' => 'reference_data_release_id'],
            'report_schedule' => ['label' => 'Report schedules', 'model' => ReportSchedule::class, 'releaseColumn' => 'reference_data_release_id'],
            'support_ticket' => ['label' => 'Support tickets', 'model' => SupportTicket::class, 'releaseColumn' => 'reference_data_release_id'],
            'travel_request' => ['label' => 'Travel requests', 'model' => TravelRequest::class, 'releaseColumn' => 'reference_data_release_id'],
        ];
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
        return collect($record->getAttributes())->only([
            'id', 'reference', 'code', 'title', 'name', 'county_id', 'target_county_id', 'source_county_id',
            'organization_id', 'partner_organization_id', 'owner_organization_id', 'accountable_organization_id',
            'lead_organization_id', 'sector_id', 'programme_id', 'created_at',
        ])->all();
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
