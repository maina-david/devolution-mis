<?php

namespace App\Http\Controllers;

use App\Enums\ProgrammePermission;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\MonitoringEvaluationResults;
use App\Services\ProgrammeCountyScope;
use App\Services\ProgrammeWorkspaceData;
use App\Support\WorkspaceFilters;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Gate;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceExportController extends Controller
{
    public function __invoke(WorkspaceIndexRequest $request, string $currentTeam, string $workspace, string $format, ProgrammeWorkspaceData $workspaceData, MonitoringEvaluationResults $monitoringResults, AuditLogger $auditLogger, ProgrammeCountyScope $countyScope): Response
    {
        if ($workspace === 'users') {
            abort_unless($request->user()?->can(ProgrammePermission::ManageCountyUsers->value) || $request->user()?->can(ProgrammePermission::ManageUserAccess->value), 403);
        } else {
            Gate::authorize($this->permission($workspace)->value);
        }
        $requestedFilters = WorkspaceFilters::fromRequest($request);
        $filters = new WorkspaceFilters($requestedFilters->from, $requestedFilters->to, $requestedFilters->search, 5000, $requestedFilters->cycleId, $requestedFilters->countyId, $requestedFilters->sectorId, $requestedFilters->status, $requestedFilters->classroomId, $requestedFilters->severity, $requestedFilters->gapCategoryId);
        $user = $this->user($request);
        if ($filters->countyId !== null) {
            abort_unless($countyScope->query($user)->whereKey($filters->countyId)->exists(), 403);
        }
        $data = match ($workspace) {
            'counties' => $workspaceData->counties($user, $filters),
            'assessments' => $workspaceData->assessments($user, $filters),
            'evidence' => $workspaceData->evidence($user, $filters),
            'grants' => $workspaceData->grants($user, $filters),
            'exchequer' => $workspaceData->exchequer($user, $filters),
            'reports' => $workspaceData->reports($user, $filters),
            'users' => $workspaceData->users($user, $filters),
            'audit' => $workspaceData->audit($user, $filters),
            'audit-assurance' => $workspaceData->auditAssurance($user, $filters),
            'platform' => $workspaceData->platform($filters),
            'monitoring-evaluation' => $workspaceData->monitoringEvaluation($user, $filters),
            'programme-evaluations' => $workspaceData->programmeEvaluations($user, $filters),
            'monitoring-performance' => $this->monitoringPerformance($monitoringResults->forUser($user, $filters)),
            'projects' => $workspaceData->projects($user, $filters),
            'partners' => $workspaceData->partners($user, $filters),
            'partner-actions' => $workspaceData->partnerActions($user, $filters),
            'dswg' => $workspaceData->dswg($user, $filters),
            'igr-resolutions' => $workspaceData->igrResolutions($user, $filters),
            'igr-gaps' => $workspaceData->igrResolutionGaps($user, $filters),
            'citizen-cases' => $workspaceData->citizenCases($user, $filters),
            'travel-clearance' => $workspaceData->travelClearance($user, $filters),
            'departmental-performance' => $workspaceData->departmentalPerformance($user, $filters),
            'learning' => $workspaceData->learning($user, $filters),
            'learning-cohorts' => $workspaceData->learningCohorts($user, $filters),
            'learning-attendance' => $workspaceData->learningAttendance($user, $filters),
            'learning-offline-syncs' => $workspaceData->learningOfflineSyncs($user, $filters),
            'knowledge' => $workspaceData->knowledge($user, $filters),
            'knowledge-innovations' => $workspaceData->knowledgeInnovations($user, $filters),
            'knowledge-moderation' => $workspaceData->knowledgeModeration($user, $filters),
            'integrations' => $workspaceData->integrations($user, $filters),
            'integration-systems' => $workspaceData->integrationSystems($user, $filters),
            'operations' => $workspaceData->operations($user, $filters),
            'operational-alerts' => $workspaceData->operationalAlerts($user, $filters),
            'data-governance' => $workspaceData->dataGovernance($user, $filters),
            'privacy-incidents' => $workspaceData->privacyIncidents($user, $filters),
            'security-governance' => $workspaceData->securityGovernance($user, $filters),
            'identity-lifecycle' => $workspaceData->identityLifecycle($user, $filters),
            'security-incidents' => $workspaceData->securityIncidents($user, $filters),
            'support-desk' => $workspaceData->supportTickets($user, $filters),
            'service-desk-policies' => $workspaceData->serviceDeskPolicies($user, $filters),
            'access-delegations' => $workspaceData->accessDelegations($user, $filters),
            'business-calendars' => $workspaceData->businessCalendars($user, $filters),
            'change-readiness' => $workspaceData->changeReadiness($user, $filters),
            'programme-coverage' => $workspaceData->programmeCountyCoverages($user, $filters),
            default => abort(404),
        };
        /** @var list<string> $columns */
        $columns = $data['columns'];
        /** @var list<array{id: string, cells: list<mixed>}> $rows */
        $rows = $data['rows'];
        $selectedIds = $request->selectedIds();
        if ($selectedIds !== []) {
            $selectedIdLookup = array_fill_keys($selectedIds, true);
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => isset($selectedIdLookup[$row['id']]),
            ));
            abort_unless(count($rows) === count($selectedIds), 422, 'One or more selected records are unavailable in your authorized workspace.');
        }
        $filename = str($workspace)->append('-', now()->format('Ymd-His'));
        $auditLogger->record($user, $user, 'workspace.exported', "{$data['title']} exported as ".mb_strtoupper($format).'.', $user->county_id, ['workspace' => $workspace, 'format' => $format, 'records' => count($rows), 'selection' => $selectedIds !== [] ? 'selected' : 'filtered']);

        return match ($format) {
            'csv' => $this->csv($columns, $rows, "{$filename}.csv"),
            'json' => response()->streamDownload(fn () => print json_encode(['columns' => $columns, 'rows' => array_column($rows, 'cells')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "{$filename}.json", ['Content-Type' => 'application/json']),
            'xlsx' => $this->xlsx($columns, $rows, "{$filename}.xlsx"),
            'pdf' => $this->pdf((string) $data['title'], $columns, $rows, "{$filename}.pdf"),
            default => abort(404),
        };
    }

    /** @param array<string, mixed> $results
     * @return array{title: string, columns: list<string>, rows: list<array{id: string, cells: list<mixed>}>}
     */
    private function monitoringPerformance(array $results): array
    {
        /** @var list<array<string, mixed>> $performanceRows */
        $performanceRows = $results['performance']['rows'];

        return [
            'title' => 'Monitoring and evaluation target performance',
            'columns' => ['Indicator', 'County', 'Programme', 'Dimension', 'Period end', 'Direction', 'Actual', 'Target', 'Variance', 'Variance (%)', 'Attainment (%)', 'Status'],
            'rows' => array_map(fn (array $row): array => ['id' => $row['id'], 'cells' => [
                $row['indicator']['code'].' · '.$row['indicator']['name'], $row['county'], $row['programme'] ?? '—', $row['dimension'], $row['periodEnd'], $row['indicator']['direction'], $row['actual'], $row['target'], $row['variance'], $row['variancePercentage'], $row['attainment'], $row['status'],
            ]], $performanceRows),
        ];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array{cells: list<mixed>}>  $rows
     */
    private function csv(array $columns, array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }
            fputcsv($stream, $columns);
            foreach ($rows as $row) {
                fputcsv($stream, array_map($this->plainCell(...), $row['cells']));
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array{cells: list<mixed>}>  $rows
     */
    private function xlsx(array $columns, array $rows, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'idmis-export-');
        abort_if($path === false, 500, 'Export file could not be created.');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($columns));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map($this->plainCell(...), $row['cells'])));
        }
        $writer->close();

        return response()->download($path, $filename)->deleteFileAfterSend();
    }

    /**
     * @param  list<string>  $columns
     * @param  list<array{cells: list<mixed>}>  $rows
     */
    private function pdf(string $title, array $columns, array $rows, string $filename): Response
    {
        $escape = fn (mixed $value): string => e((string) ($value ?? '—'));
        $header = collect($columns)->map(fn (string $column): string => '<th>'.$escape($column).'</th>')->implode('');
        $body = collect($rows)->map(fn (array $row): string => '<tr>'.implode('', array_map(fn (mixed $cell): string => '<td>'.$this->pdfCell($cell, $escape).'</td>', $row['cells'])).'</tr>')->implode('');
        $dompdf = new Dompdf;
        $dompdf->loadHtml("<style>body{font-family:sans-serif;font-size:10px}h1{color:#12304a}table{width:100%;border-collapse:collapse}th,td{padding:6px;border:1px solid #ccd6d0;text-align:left;vertical-align:middle}th{background:#eef4f0}.county{white-space:nowrap}.county img{width:24px;height:24px;object-fit:contain;vertical-align:middle;margin-right:6px}</style><h1>{$escape($title)}</h1><p>Generated ".now()->toDayDateTimeString()."</p><table><thead><tr>{$header}</tr></thead><tbody>{$body}</tbody></table>");
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename=\"{$filename}\""]);
    }

    private function plainCell(mixed $cell): string|int|float|null
    {
        if (is_array($cell) && ($cell['kind'] ?? null) === 'county') {
            return (string) ($cell['name'] ?? 'County');
        }

        if (is_array($cell) && ($cell['kind'] ?? null) === 'county-list') {
            $items = is_array($cell['items'] ?? null) ? $cell['items'] : [];

            return implode(', ', array_filter(array_map(
                fn (mixed $county): ?string => is_array($county) && is_string($county['name'] ?? null) ? $county['name'] : null,
                $items,
            )));
        }

        if (is_bool($cell)) {
            return $cell ? 'Yes' : 'No';
        }

        return is_string($cell) || is_int($cell) || is_float($cell) || $cell === null
            ? $cell
            : json_encode($cell, JSON_THROW_ON_ERROR);
    }

    /** @param callable(mixed): string $escape */
    private function pdfCell(mixed $cell, callable $escape): string
    {
        if (is_array($cell) && ($cell['kind'] ?? null) === 'county-list') {
            $items = is_array($cell['items'] ?? null) ? $cell['items'] : [];

            return implode('<br>', array_map(fn (mixed $county): string => $this->pdfCell($county, $escape), $items));
        }

        if (! is_array($cell) || ($cell['kind'] ?? null) !== 'county') {
            return $escape($this->plainCell($cell));
        }

        $logoPath = is_string($cell['logoUrl'] ?? null) ? public_path(ltrim($cell['logoUrl'], '/')) : null;
        $logo = '';
        if ($logoPath && is_file($logoPath)) {
            $contents = file_get_contents($logoPath);
            if ($contents !== false) {
                $logo = '<img alt="" src="data:image/webp;base64,'.base64_encode($contents).'">';
            }
        }

        return '<span class="county">'.$logo.$escape($cell['name'] ?? 'County').'</span>';
    }

    private function permission(string $workspace): ProgrammePermission
    {
        return match ($workspace) {
            'counties', 'assessments', 'evidence' => ProgrammePermission::ViewCountyData,
            'grants', 'exchequer' => ProgrammePermission::ViewGrants,
            'reports' => ProgrammePermission::ViewNationalReports,
            'users' => ProgrammePermission::ManageUserAccess,
            'audit', 'audit-assurance' => ProgrammePermission::ViewAuditTrail,
            'platform' => ProgrammePermission::ConfigurePlatform,
            'monitoring-evaluation', 'monitoring-performance', 'programme-evaluations' => ProgrammePermission::ViewMonitoringEvaluation,
            'projects' => ProgrammePermission::ViewProjects,
            'partners', 'partner-actions' => ProgrammePermission::ViewPartnerCoordination,
            'dswg' => ProgrammePermission::ViewDswg,
            'igr-resolutions', 'igr-gaps' => ProgrammePermission::ViewIgrResolutions,
            'citizen-cases' => ProgrammePermission::ViewCitizenCases,
            'travel-clearance' => ProgrammePermission::ViewTravelClearance,
            'departmental-performance' => ProgrammePermission::ViewDepartmentalPerformance,
            'learning', 'learning-cohorts', 'learning-attendance', 'learning-offline-syncs' => ProgrammePermission::ViewLearning,
            'knowledge', 'knowledge-innovations', 'knowledge-moderation' => ProgrammePermission::ViewKnowledge,
            'integrations', 'integration-systems' => ProgrammePermission::ViewIntegrations,
            'operations', 'operational-alerts' => ProgrammePermission::ViewOperations,
            'data-governance', 'privacy-incidents' => ProgrammePermission::ViewDataGovernance,
            'security-governance', 'security-incidents', 'access-delegations', 'identity-lifecycle' => ProgrammePermission::ViewSecurityGovernance,
            'support-desk' => ProgrammePermission::ViewSupportDesk,
            'service-desk-policies' => ProgrammePermission::ConfigureSupportDesk,
            'business-calendars' => ProgrammePermission::ManageWorkflows,
            'change-readiness' => ProgrammePermission::ViewChangeReadiness,
            'programme-coverage' => ProgrammePermission::ManageReferenceData,
            default => abort(404),
        };
    }

    private function user(WorkspaceIndexRequest $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
