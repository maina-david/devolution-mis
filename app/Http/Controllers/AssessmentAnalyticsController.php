<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssessmentAnalyticsRequest;
use App\Models\User;
use App\Services\AssessmentAnalyticsService;
use App\Services\AuditLogger;
use Dompdf\Dompdf;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssessmentAnalyticsController extends Controller
{
    public function __construct(private AssessmentAnalyticsService $analytics, private AuditLogger $auditLogger) {}

    public function index(AssessmentAnalyticsRequest $request): InertiaResponse
    {
        $filters = $this->filters($request);

        return Inertia::render('assessments/analytics', ['report' => $this->analytics->report($this->user($request), $filters), 'filters' => $filters]);
    }

    public function export(AssessmentAnalyticsRequest $request, string $currentTeam, string $format): Response
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'json', 'pdf'], true), 404);
        $user = $this->user($request);
        $filters = $this->filters($request);
        $rows = $this->analytics->exportRows($user, $filters);
        $this->auditLogger->record($user, $user, 'assessment.analytics_exported', 'Assessment comparative analytics exported as '.mb_strtoupper($format).'.', $user->county_id, ['format' => $format, 'records' => count($rows), 'filters' => $filters]);
        $filename = 'assessment-comparison-'.now()->format('Ymd-His');

        return match ($format) {
            'csv' => $this->csv($rows, "{$filename}.csv"),
            'xlsx' => $this->xlsx($rows, "{$filename}.xlsx"),
            'json' => response()->streamDownload(fn () => print json_encode(['generated_at' => now()->toIso8601String(), 'filters' => $filters, 'report' => $this->analytics->report($user, [...$filters, 'per_page' => 5000])], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "{$filename}.json", ['Content-Type' => 'application/json']),
            'pdf' => $this->pdf($rows, "{$filename}.pdf"),
        };
    }

    /** @param list<array<string, mixed>> $rows */
    private function csv(array $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }
            fputcsv($stream, ['County', 'Cycle', 'Score', 'Performance band', 'Publication checksum', 'Assessment ID']);
            foreach ($rows as $row) {
                fputcsv($stream, $this->rowValues($row));
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @param list<array<string, mixed>> $rows */
    private function xlsx(array $rows, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'idmis-assessment-analytics-');
        abort_if($path === false, 500, 'Export file could not be created.');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['County', 'Cycle', 'Score', 'Performance band', 'Publication checksum', 'Assessment ID']));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($this->rowValues($row)));
        }
        $writer->close();

        return response()->download($path, $filename)->deleteFileAfterSend();
    }

    /** @param list<array<string, mixed>> $rows */
    private function pdf(array $rows, string $filename): Response
    {
        $body = collect($rows)->map(function (array $row): string {
            $county = $row['county'];
            $logo = '';
            if (is_array($county) && is_string($county['logoUrl'] ?? null)) {
                $path = public_path(ltrim($county['logoUrl'], '/'));
                $contents = is_file($path) ? file_get_contents($path) : false;
                if ($contents !== false) {
                    $logo = '<img alt="" src="data:image/webp;base64,'.base64_encode($contents).'">';
                }
            }

            return '<tr><td>'.$logo.e(is_array($county) ? $county['name'] : $county).'</td><td>'.e($row['cycle']).'</td><td>'.e($row['score']).'</td><td>'.e($row['performance_band']).'</td><td class="checksum">'.e($row['publication_checksum']).'</td></tr>';
        })->implode('');
        $dompdf = new Dompdf;
        $dompdf->loadHtml('<style>body{font-family:sans-serif;font-size:10px;color:#172b3a}h1{color:#12304a}table{width:100%;border-collapse:collapse}th,td{padding:7px;border:1px solid #ccd6d0;text-align:left;vertical-align:middle}th{background:#eef4f0}img{width:24px;height:24px;object-fit:contain;vertical-align:middle;margin-right:6px}.checksum{font-family:monospace;font-size:8px}</style><h1>Assessment comparative analytics</h1><p>Generated '.e(now()->toDayDateTimeString()).'</p><table><thead><tr><th>County</th><th>Cycle</th><th>Score</th><th>Performance band</th><th>Publication checksum</th></tr></thead><tbody>'.$body.'</tbody></table>');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename=\"{$filename}\""]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string|float>
     */
    private function rowValues(array $row): array
    {
        $county = $row['county'];

        return [is_array($county) ? (string) $county['name'] : (string) $county, (string) $row['cycle'], (float) $row['score'], (string) $row['performance_band'], (string) $row['publication_checksum'], (string) $row['assessment_id']];
    }

    /** @return array{from: string|null, to: string|null, cycle_id: string|null, county_id: string|null, function_page: int, ranking_page: int, per_page: int} */
    private function filters(AssessmentAnalyticsRequest $request): array
    {
        return ['from' => $request->validated('from'), 'to' => $request->validated('to'), 'cycle_id' => $request->validated('cycle_id'), 'county_id' => $request->validated('county_id'), 'function_page' => $request->integer('function_page', 1), 'ranking_page' => $request->integer('ranking_page', 1), 'per_page' => $request->integer('per_page', 10)];
    }

    private function user(AssessmentAnalyticsRequest $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
