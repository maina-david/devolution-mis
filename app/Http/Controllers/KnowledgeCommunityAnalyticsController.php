<?php

namespace App\Http\Controllers;

use App\Http\Requests\KnowledgeCommunityAnalyticsRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\KnowledgeCommunityAnalyticsService;
use Dompdf\Dompdf;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KnowledgeCommunityAnalyticsController extends Controller
{
    public function __construct(private KnowledgeCommunityAnalyticsService $analytics, private AuditLogger $auditLogger) {}

    public function index(KnowledgeCommunityAnalyticsRequest $request): InertiaResponse
    {
        $filters = $this->filters($request);

        return Inertia::render('knowledge/community-analytics', ['report' => $this->analytics->report($this->user($request), $filters), 'filters' => $filters]);
    }

    public function export(KnowledgeCommunityAnalyticsRequest $request, string $format): Response
    {
        abort_unless(in_array($format, ['csv', 'xlsx', 'json', 'pdf'], true), 404);
        $user = $this->user($request);
        $filters = $this->filters($request);
        $rows = $this->analytics->exportRows($user, $filters);
        $this->auditLogger->record($user, $user, 'knowledge.community_analytics.exported', __('knowledge.ui.export_audit', ['format' => mb_strtoupper($format)]), $user->county_id, ['format' => $format, 'records' => count($rows), 'filters' => $filters]);
        $filename = 'knowledge-community-analytics-'.now()->format('Ymd-His');

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
        $path = tempnam(sys_get_temp_dir(), 'idmis-community-analytics-');
        abort_if($path === false, 500, __('knowledge.ui.export_failed'));
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
            $county = is_array($row['county']) ? $row['county'] : null;
            $logo = '';
            if ($county !== null && is_string($county['logoUrl'] ?? null)) {
                $path = public_path(ltrim($county['logoUrl'], '/'));
                $contents = is_file($path) ? file_get_contents($path) : false;
                if ($contents !== false) {
                    $logo = '<img alt="" src="data:image/webp;base64,'.base64_encode($contents).'">';
                }
            }

            return '<tr><td>'.$logo.e($county['name'] ?? __('knowledge.ui.national')).'</td><td>'.e($row['title']).'</td><td>'.e(__('knowledge.ui.'.$row['status'])).'</td><td>'.e($row['contributions']).'</td><td>'.e($row['contributors']).'</td><td>'.e($row['subscriptions']).'</td><td>'.e($row['reports']).'</td><td>'.e($row['openReports']).'</td><td>'.e($row['resolutionRate']).'%</td></tr>';
        })->implode('');
        $headings = collect([__('knowledge.ui.county'), __('knowledge.ui.discussion'), __('knowledge.ui.status'), __('knowledge.ui.contributions'), __('knowledge.ui.contributors'), __('knowledge.ui.subscriptions'), __('knowledge.ui.reports'), __('knowledge.ui.open_reports'), __('knowledge.ui.resolution_rate')])
            ->map(fn (string $heading): string => '<th>'.e($heading).'</th>')
            ->implode('');
        $dompdf = new Dompdf;
        $dompdf->loadHtml('<style>body{font-family:sans-serif;font-size:10px;color:#172b3a}h1{color:#12304a}table{width:100%;border-collapse:collapse}th,td{padding:7px;border:1px solid #ccd6d0;text-align:left}th{background:#eef4f0}img{width:24px;height:24px;object-fit:contain;vertical-align:middle;margin-right:6px}</style><h1>'.e(__('knowledge.ui.community_analytics_title')).'</h1><p>'.e(__('knowledge.ui.generated', ['date' => now()->translatedFormat('j F Y H:i')])).'</p><table><thead><tr>'.$headings.'</tr></thead><tbody>'.$body.'</tbody></table>');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename=\"{$filename}\""]);
    }

    /** @return list<string> */
    private function headings(): array
    {
        return [__('knowledge.ui.county'), __('knowledge.ui.county_code'), __('knowledge.ui.discussion'), __('knowledge.ui.visibility'), __('knowledge.ui.status'), __('knowledge.ui.contributions'), __('knowledge.ui.contributors'), __('knowledge.ui.subscriptions'), __('knowledge.ui.reports'), __('knowledge.ui.open_reports'), __('knowledge.ui.resolution_rate'), __('knowledge.ui.last_activity')];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string|int|float|null>
     */
    private function values(array $row): array
    {
        $county = is_array($row['county']) ? $row['county'] : [];

        return [(string) ($county['name'] ?? __('knowledge.ui.national')), (string) ($county['code'] ?? ''), (string) $row['title'], (string) $row['visibility'], (string) $row['status'], (int) $row['contributions'], (int) $row['contributors'], (int) $row['subscriptions'], (int) $row['reports'], (int) $row['openReports'], (float) $row['resolutionRate'], is_string($row['lastActivityAt']) ? $row['lastActivityAt'] : null];
    }

    /** @return array<string, mixed> */
    private function filters(KnowledgeCommunityAnalyticsRequest $request): array
    {
        return [...$request->safe()->only(['from', 'to', 'county_id', 'status', 'search']), 'page' => $request->integer('page', 1), 'county_page' => $request->integer('county_page', 1), 'per_page' => $request->integer('per_page', 10)];
    }

    private function user(KnowledgeCommunityAnalyticsRequest $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
