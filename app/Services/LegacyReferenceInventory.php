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
use App\Models\ReportSchedule;
use App\Models\SupportTicket;
use App\Models\TravelRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class LegacyReferenceInventory
{
    /** @return array{total: int, recordTypes: int, records: list<array{type: string, model: class-string<Model>, count: int, oldestAt: string|null, latestAt: string|null}>} */
    public function report(): array
    {
        $types = [
            'Analytics dashboards' => AnalyticsDashboard::class, 'Assessments' => Assessment::class,
            'Citizen cases' => CitizenCase::class, 'Innovations' => DevolutionInnovation::class,
            'Projects' => DevolutionProject::class, 'DSWG actions' => DswgAction::class,
            'DSWG working groups' => DswgWorkingGroup::class, 'Exchequer requests' => ExchequerRequest::class,
            'IGR resolutions' => IgrResolution::class, 'Indicator definitions' => IndicatorDefinition::class,
            'Innovation replications' => InnovationReplication::class, 'Integration systems' => IntegrationSystem::class,
            'Knowledge items' => KnowledgeItem::class, 'Learning courses' => LearningCourse::class,
            'Partner actions' => PartnerCollaborationAction::class, 'Partner profiles' => PartnerProfile::class,
            'Performance plans' => PerformancePlan::class, 'Programme evaluations' => ProgrammeEvaluation::class,
            'Report schedules' => ReportSchedule::class, 'Support tickets' => SupportTicket::class,
            'Travel requests' => TravelRequest::class,
        ];
        $records = [];
        foreach ($types as $type => $modelClass) {
            $releaseColumn = $modelClass === CitizenCase::class ? 'intake_reference_data_release_id' : 'reference_data_release_id';
            $query = $modelClass::query()->whereNull($releaseColumn);
            $count = $query->count();
            if ($count === 0) {
                continue;
            }
            $oldestAt = (clone $query)->min('created_at');
            $latestAt = (clone $query)->max('created_at');
            $records[] = [
                'type' => $type,
                'model' => $modelClass,
                'count' => $count,
                'oldestAt' => is_string($oldestAt) ? Carbon::parse($oldestAt)->toIso8601String() : null,
                'latestAt' => is_string($latestAt) ? Carbon::parse($latestAt)->toIso8601String() : null,
            ];
        }
        usort($records, fn (array $left, array $right): int => $right['count'] <=> $left['count']);

        return ['total' => array_sum(array_column($records, 'count')), 'recordTypes' => count($records), 'records' => $records];
    }
}
