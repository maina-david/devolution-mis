<?php

namespace App\Services;

use App\Enums\ProgrammePermission;
use App\Models\County;
use App\Models\InnovationReplication;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use App\Support\WorkspaceFilters;

class GlobalSearch
{
    public function __construct(
        private ProgrammeWorkspaceData $workspaceData,
        private ProgrammeCountyScope $countyScope,
    ) {}

    /** @return list<array{category: string, id: string, title: string, description: string, url: string}> */
    public function for(User $user, string $teamSlug, string $term): array
    {
        $results = [];

        if ($user->can(ProgrammePermission::ViewCountyData->value)) {
            $counties = County::search($term)
                ->query(fn ($query) => $query->whereIn('id', $this->countyScope->query($user)->select('id')))
                ->take(6)
                ->get();

            foreach ($counties as $county) {
                $results[] = [
                    'category' => 'Counties',
                    'id' => $county->id,
                    'title' => $county->name.' County',
                    'description' => sprintf('County %03d · %s', $county->code, $county->region ?? ReferenceCatalogue::defaultCountryName()),
                    'url' => route('counties.show', ['current_team' => $teamSlug, 'county' => $county]),
                ];
            }
        }

        if ($user->can(ProgrammePermission::ViewKnowledge->value)) {
            $replications = InnovationReplication::query()
                ->whereIn('target_county_id', $this->countyScope->query($user)->select('id'))
                ->where(fn ($query) => $query->where('reference', 'ilike', "%{$term}%")->orWhere('adaptation_plan', 'ilike', "%{$term}%")->orWhere('success_measure', 'ilike', "%{$term}%")->orWhereHas('innovation', fn ($innovation) => $innovation->where('title', 'ilike', "%{$term}%")))
                ->with(['innovation:id,reference,title', 'targetCounty:id,name'])
                ->take(6)
                ->get();

            foreach ($replications as $replication) {
                $results[] = [
                    'category' => 'Innovation replication',
                    'id' => $replication->id,
                    'title' => $replication->reference.' · '.$replication->innovation->title,
                    'description' => $replication->targetCounty->name.' · '.str($replication->status)->headline(),
                    'url' => route('knowledge.innovation-replications.index', ['current_team' => $teamSlug, 'search' => $replication->reference]),
                ];
            }
        }

        $filters = new WorkspaceFilters(null, null, $term, 5);
        foreach ($this->providers() as $provider) {
            if (! $user->can($provider['permission'])) {
                continue;
            }

            $workspace = $this->workspaceData->{$provider['method']}($user, $filters);
            foreach ($workspace['rows'] as $row) {
                $results[] = $this->result($provider, $row, $workspace, $teamSlug, $term);

                if (count($results) >= 40) {
                    break 2;
                }
            }
        }

        return $results;
    }

    /**
     * @return list<array{category: string, method: string, permission: string, route: string, detailRoute?: string}>
     */
    private function providers(): array
    {
        return [
            ['category' => 'Assessments', 'method' => 'assessments', 'permission' => ProgrammePermission::ViewCountyData->value, 'route' => 'assessments.index', 'detailRoute' => 'assessments.show'],
            ['category' => 'Documents & evidence', 'method' => 'evidence', 'permission' => ProgrammePermission::ViewCountyData->value, 'route' => 'evidence.index'],
            ['category' => 'Grants', 'method' => 'grants', 'permission' => ProgrammePermission::ViewGrants->value, 'route' => 'grants.index'],
            ['category' => 'Exchequer requests', 'method' => 'exchequer', 'permission' => ProgrammePermission::ViewGrants->value, 'route' => 'exchequer.index'],
            ['category' => 'Projects', 'method' => 'projects', 'permission' => ProgrammePermission::ViewProjects->value, 'route' => 'projects.index', 'detailRoute' => 'projects.show'],
            ['category' => 'Partners', 'method' => 'partners', 'permission' => ProgrammePermission::ViewPartnerCoordination->value, 'route' => 'partners.index'],
            ['category' => 'Sector working groups', 'method' => 'dswg', 'permission' => ProgrammePermission::ViewDswg->value, 'route' => 'dswg.index'],
            ['category' => 'IGR resolutions', 'method' => 'igrResolutions', 'permission' => ProgrammePermission::ViewIgrResolutions->value, 'route' => 'igr-resolutions.index'],
            ['category' => 'M&E records', 'method' => 'monitoringEvaluation', 'permission' => ProgrammePermission::ViewMonitoringEvaluation->value, 'route' => 'monitoring-evaluation.index'],
            ['category' => 'Citizen cases', 'method' => 'citizenCases', 'permission' => ProgrammePermission::ViewCitizenCases->value, 'route' => 'citizen-cases.index'],
            ['category' => 'Travel requests', 'method' => 'travelClearance', 'permission' => ProgrammePermission::ViewTravelClearance->value, 'route' => 'travel-clearance.index'],
            ['category' => 'Performance plans', 'method' => 'departmentalPerformance', 'permission' => ProgrammePermission::ViewDepartmentalPerformance->value, 'route' => 'departmental-performance.index'],
            ['category' => 'Learning', 'method' => 'learning', 'permission' => ProgrammePermission::ViewLearning->value, 'route' => 'learning.index'],
            ['category' => 'Knowledge', 'method' => 'knowledge', 'permission' => ProgrammePermission::ViewKnowledge->value, 'route' => 'knowledge.index'],
            ['category' => 'Integrations', 'method' => 'integrations', 'permission' => ProgrammePermission::ViewIntegrations->value, 'route' => 'integrations.index'],
            ['category' => 'Operations', 'method' => 'operations', 'permission' => ProgrammePermission::ViewOperations->value, 'route' => 'operations.index'],
            ['category' => 'Data governance', 'method' => 'dataGovernance', 'permission' => ProgrammePermission::ViewDataGovernance->value, 'route' => 'data-governance.index'],
            ['category' => 'Security governance', 'method' => 'securityIncidents', 'permission' => ProgrammePermission::ViewSecurityGovernance->value, 'route' => 'security-governance.index'],
            ['category' => 'Rollout & training', 'method' => 'changeReadiness', 'permission' => ProgrammePermission::ViewChangeReadiness->value, 'route' => 'change-readiness.index'],
            ['category' => 'Service desk', 'method' => 'supportTickets', 'permission' => ProgrammePermission::ViewSupportDesk->value, 'route' => 'support-desk.index'],
            ['category' => 'Service policies', 'method' => 'serviceDeskPolicies', 'permission' => ProgrammePermission::ConfigureSupportDesk->value, 'route' => 'support-desk.index'],
            ['category' => 'Users', 'method' => 'users', 'permission' => ProgrammePermission::ManageCountyUsers->value, 'route' => 'programme-users.index'],
        ];
    }

    /**
     * @param  array{category: string, method: string, permission: string, route: string, detailRoute?: string}  $provider
     * @param  array{id: string, cells: list<mixed>}  $row
     * @param  array<string, mixed>  $workspace
     * @return array{category: string, id: string, title: string, description: string, url: string}
     */
    private function result(array $provider, array $row, array $workspace, string $teamSlug, string $term): array
    {
        $labels = collect($row['cells'])->map(fn (mixed $cell): string => $this->cellText($cell))->filter()->values();
        $parameters = ['current_team' => $teamSlug];
        if (isset($provider['detailRoute'])) {
            $parameters[$provider['method'] === 'projects' ? 'project' : 'assessment'] = $row['id'];
        } else {
            $parameters['search'] = $term;
        }

        return [
            'category' => $provider['category'],
            'id' => $row['id'],
            'title' => $labels->first() ?: (string) $workspace['title'],
            'description' => $labels->slice(1, 2)->implode(' · ') ?: (string) $workspace['description'],
            'url' => route($provider['detailRoute'] ?? $provider['route'], $parameters),
        ];
    }

    private function cellText(mixed $cell): string
    {
        if (is_scalar($cell)) {
            return trim((string) $cell);
        }

        if (! is_array($cell)) {
            return '';
        }

        if (isset($cell['name']) && is_string($cell['name'])) {
            return $cell['name'];
        }

        if (isset($cell['items']) && is_array($cell['items'])) {
            return collect($cell['items'])->pluck('name')->filter()->implode(', ');
        }

        return '';
    }
}
