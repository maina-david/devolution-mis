<?php

namespace App\Http\Controllers;

use App\Http\Requests\LearningAnalyticsRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LearningAnalyticsService;
use Dompdf\Dompdf;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use LogicException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LearningAnalyticsController extends Controller
{
    public function __construct(private LearningAnalyticsService $analytics, private AuditLogger $auditLogger) {}

    public function index(LearningAnalyticsRequest $request): InertiaResponse
    {
        $filters = $this->filters($request);

        return Inertia::render('learning/analytics', ['report' => $this->analytics->report($this->user($request), $filters), 'filters' => $filters]);
    }

    public function export(LearningAnalyticsRequest $request, string $format): Response
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'json', 'pdf'], true), 404);
        $user = $this->user($request);
        $filters = $this->filters($request);
        $rows = $this->analytics->exportRows($user, $filters);
        $this->auditLogger->record($user, $user, 'learning.analytics.exported', __('learning-analytics.audit_exported', ['format' => mb_strtoupper($format)]), $user->county_id, ['format' => $format, 'records' => count($rows), 'filters' => $filters]);
        $filename = 'learning-analytics-'.now()->format('Ymd-His');

        return match ($format) {
            'csv' => $this->csv($rows, "{$filename}.csv"),
            'xlsx' => $this->xlsx($rows, "{$filename}.xlsx"),
            'json' => response()->streamDownload(fn () => print json_encode(['generated_at' => now()->toIso8601String(), 'filters' => $filters, 'rows' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "{$filename}.json", ['Content-Type' => 'application/json']),
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
            fputcsv($stream, $this->headings());
            foreach ($rows as $row) {
                fputcsv($stream, $this->values($row));
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** @param list<array<string, mixed>> $rows */
    private function xlsx(array $rows, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'idmis-learning-analytics-');
        abort_if($path === false, 500, __('learning-analytics.export_failed'));
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->headings()));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($this->values($row)));
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

            $values = $this->metricValues($row);

            return '<tr><td>'.$logo.e(is_array($county) ? $county['name'] : '').'</td><td>'.e($row['course_code']).' · '.e($row['course_title']).'</td><td>'.e($values[0]).'</td><td>'.e($values[1]).'</td><td>'.e($values[2]).'</td><td>'.e($values[3]).'</td><td>'.e($values[4]).'</td></tr>';
        })->implode('');
        $dompdf = new Dompdf;
        $headings = $this->headings();
        $dompdf->loadHtml('<style>body{font-family:sans-serif;font-size:10px;color:#172b3a}h1{color:#12304a}table{width:100%;border-collapse:collapse}th,td{padding:7px;border:1px solid #ccd6d0;text-align:left}th{background:#eef4f0}img{width:24px;height:24px;object-fit:contain;vertical-align:middle;margin-right:6px}</style><h1>'.e(__('learning-analytics.pdf_title')).'</h1><p>'.e(__('learning-analytics.generated', ['date' => now()->translatedFormat('j F Y H:i')])).'</p><table><thead><tr><th>'.e($headings[0]).'</th><th>'.e($headings[2]).'</th><th>'.e($headings[4]).'</th><th>'.e($headings[5]).'</th><th>'.e($headings[6]).'</th><th>'.e($headings[7]).'</th><th>'.e($headings[8]).'</th></tr></thead><tbody>'.$body.'</tbody></table>');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename=\"{$filename}\""]);
    }

    /** @return list<string> */
    private function headings(): array
    {
        $headings = __('learning-analytics.export_headings');
        $validated = [];

        if (! is_array($headings) || count($headings) !== 9) {
            $message = 'The learning analytics export heading catalogue must contain nine entries.';

            throw new LogicException($message);
        }

        foreach ($headings as $heading) {
            if (! is_string($heading)) {
                $message = 'Every learning analytics export heading must be a string.';

                throw new LogicException($message);
            }

            $validated[] = $heading;
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string|int|float|null>
     */
    private function values(array $row): array
    {
        $county = is_array($row['county']) ? $row['county'] : [];

        return [(string) ($county['name'] ?? ''), (string) ($county['code'] ?? ''), (string) $row['course_code'], (string) $row['course_title'], ...$this->metricValues($row)];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string|int|float|null>
     */
    private function metricValues(array $row): array
    {
        if ($row['suppressed'] === true) {
            $minimumCellSize = max(2, min(100, (int) config('analytics.minimum_aggregate_cell_size', 5)));
            $label = __('learning-analytics.suppressed', ['count' => $minimumCellSize]);

            return [$label, $label, $label, $label, $label];
        }

        return [(int) $row['enrollments'], (int) $row['completed'], (float) $row['completion_rate'], (float) $row['average_progress'], is_numeric($row['average_score']) ? (float) $row['average_score'] : null];
    }

    /** @return array<string, mixed> */
    private function filters(LearningAnalyticsRequest $request): array
    {
        return [...$request->safe()->only(['from', 'to', 'county_id', 'course_id', 'status', 'search']), 'course_page' => $request->integer('course_page', 1), 'county_page' => $request->integer('county_page', 1), 'question_page' => $request->integer('question_page', 1), 'per_page' => $request->integer('per_page', 10)];
    }

    private function user(LearningAnalyticsRequest $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
