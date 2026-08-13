<?php

namespace App\Services;

use App\Models\AnalyticsDashboard;
use App\Models\AnalyticsWidget;
use App\Models\County;
use App\Models\ReportRun;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

class ScheduledReportGenerator
{
    public function __construct(
        private AnalyticsMetricCatalogue $metricCatalogue,
        private AuditLogger $auditLogger,
    ) {}

    public function generate(ReportRun $run): ReportRun
    {
        $run->load(['schedule.creator', 'schedule.county', 'schedule.referenceDataRelease']);
        $schedule = $run->schedule;
        $creator = $schedule->creator;
        $dashboardId = $schedule->filters['dashboard_id'] ?? null;
        $dashboard = is_string($dashboardId)
            ? AnalyticsDashboard::query()->with(['widgets' => fn ($query) => $query->orderBy('position'), 'referenceDataRelease'])->find($dashboardId)
            : null;

        if (! $dashboard instanceof AnalyticsDashboard || $dashboard->status !== 'published' || $dashboard->referenceDataRelease === null || $schedule->referenceDataRelease === null) {
            throw new RuntimeException('The approved scheduled-report configuration is no longer executable.');
        }

        $run->update(['status' => 'processing', 'started_at' => now(), 'error_detail' => null]);
        $filters = array_filter([
            'from' => $schedule->filters['from'] ?? null,
            'to' => $schedule->filters['to'] ?? null,
            'county_id' => $schedule->county_id ?? $dashboard->county_id,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
        $rows = array_values($dashboard->widgets->map(function (AnalyticsWidget $widget) use ($creator, $filters): array {
            $measurement = $this->metricCatalogue->evaluate(
                $creator,
                $widget->metric_key,
                [...($widget->filters ?? []), ...$filters],
                $widget->disaggregation,
            );

            return [
                'title' => $widget->title,
                'metric_key' => $widget->metric_key,
                'value' => $measurement['value'],
                'unit' => $measurement['unit'],
                'provenance' => $measurement['provenance'],
                'measured_at' => $measurement['measured_at'],
                'series' => $measurement['series'],
                'trend' => $measurement['trend'],
                'visualization' => $widget->visualization,
                'time_grain' => $widget->filters['time_grain'] ?? null,
            ];
        })->all());
        $format = $schedule->format;
        $lineage = [
            'dashboard_reference_release' => $dashboard->referenceDataRelease->version,
            'dashboard_reference_checksum' => $dashboard->referenceDataRelease->checksum,
            'schedule_reference_release' => $schedule->referenceDataRelease->version,
            'schedule_reference_checksum' => $schedule->referenceDataRelease->checksum,
        ];
        $contents = $this->render($format, $dashboard, $rows, $filters, $schedule->county, $lineage);
        $disk = (string) config('analytics.report_disk', 'local');
        $path = "scheduled-reports/{$schedule->id}/{$run->id}.{$format}";
        if (! Storage::disk($disk)->put($path, $contents)) {
            throw new RuntimeException('The private scheduled-report artifact could not be stored.');
        }
        $run->update([
            'status' => 'completed',
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $this->mimeType($format),
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'record_count' => count($rows),
            'completed_at' => now(),
        ]);
        $this->auditLogger->record(null, $run, 'analytics.report.generated', "Scheduled report {$schedule->code} generated as ".mb_strtoupper($format).'.', $schedule->county_id, ['sha256' => $run->sha256, 'records' => count($rows)]);

        User::query()->whereKey($schedule->recipient_user_ids)->get()->each(
            fn (User $recipient) => $recipient->notify(new ProgrammeAlert('Scheduled report ready', "{$schedule->name} is ready for authorized download.", 'analytics')),
        );

        return $run->refresh();
    }

    /** @param list<array<string, mixed>> $rows
     * @param  array<string, mixed>  $filters
     * @param  array<string, int|string>  $lineage
     */
    private function render(string $format, AnalyticsDashboard $dashboard, array $rows, array $filters, ?County $county, array $lineage): string
    {
        return match ($format) {
            'csv' => $this->csv($rows, $lineage),
            'json' => json_encode(['dashboard' => ['code' => $dashboard->code, 'name' => $dashboard->name, 'checksum' => $dashboard->checksum], 'reference_data' => $lineage, 'county' => $county?->identityCell(), 'filters' => $filters, 'rows' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            'xlsx' => $this->xlsx($rows, $lineage),
            'pdf' => $this->pdf($dashboard, $rows, $filters, $county, $lineage),
            default => throw new RuntimeException('Unsupported scheduled-report format.'),
        };
    }

    /** @param list<array<string, mixed>> $rows
     * @param  array<string, int|string>  $lineage
     */
    private function csv(array $rows, array $lineage): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('The CSV report stream could not be opened.');
        }
        fputcsv($stream, ['Reference lineage', json_encode($lineage, JSON_THROW_ON_ERROR)]);
        fputcsv($stream, ['Metric', 'Metric key', 'Value', 'Unit', 'Visualization', 'Time grain', 'Trend', 'Provenance', 'Measured at']);
        foreach ($rows as $row) {
            fputcsv($stream, [$row['title'], $row['metric_key'], $row['value'], $row['unit'], $row['visualization'], $row['time_grain'], json_encode($row['trend'], JSON_THROW_ON_ERROR), $row['provenance'], $row['measured_at']]);
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false) {
            throw new RuntimeException('The CSV report could not be rendered.');
        }

        return $contents;
    }

    /** @param list<array<string, mixed>> $rows
     * @param  array<string, int|string>  $lineage
     */
    private function xlsx(array $rows, array $lineage): string
    {
        $path = tempnam(sys_get_temp_dir(), 'idmis-scheduled-report-');
        if ($path === false) {
            throw new RuntimeException('The spreadsheet report file could not be created.');
        }
        try {
            $writer = new Writer;
            $writer->openToFile($path);
            $writer->addRow(Row::fromValues(['Reference lineage', json_encode($lineage, JSON_THROW_ON_ERROR)]));
            $writer->addRow(Row::fromValues(['Metric', 'Metric key', 'Value', 'Unit', 'Visualization', 'Time grain', 'Trend', 'Provenance', 'Measured at']));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues([$row['title'], $row['metric_key'], $row['value'], $row['unit'], $row['visualization'], $row['time_grain'], json_encode($row['trend'], JSON_THROW_ON_ERROR), $row['provenance'], $row['measured_at']]));
            }
            $writer->close();
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException('The spreadsheet report could not be read.');
            }

            return $contents;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /** @param list<array<string, mixed>> $rows
     * @param  array<string, mixed>  $filters
     * @param  array<string, int|string>  $lineage
     */
    private function pdf(AnalyticsDashboard $dashboard, array $rows, array $filters, ?County $county, array $lineage): string
    {
        $body = collect($rows)->map(fn (array $row): string => '<tr><td>'.e($row['title']).'</td><td>'.e($row['metric_key']).'</td><td>'.e($row['value']).'</td><td>'.e($row['unit']).'</td><td>'.e($row['visualization']).'</td><td>'.e($row['time_grain'] ?? '—').'</td><td>'.e(json_encode($row['trend'], JSON_THROW_ON_ERROR)).'</td><td>'.e($row['provenance']).'</td><td>'.e($row['measured_at']).'</td></tr>')->implode('');
        $period = e(($filters['from'] ?? 'All history').' to '.($filters['to'] ?? 'current'));
        $countyIdentity = $this->countyPdfIdentity($county);
        $dompdf = new Dompdf;
        $dompdf->loadHtml('<style>body{font-family:sans-serif;color:#172b22;font-size:9px}h1{color:#123b2a}.identity{display:flex;align-items:center;margin-bottom:12px}.identity img{width:48px;height:48px;object-fit:contain;margin-right:10px}.identity strong{font-size:14px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #cbd8d0;padding:5px;text-align:left;vertical-align:top;overflow-wrap:anywhere}th{background:#edf5f0}.meta{color:#52665b}</style>'.$countyIdentity.'<h1>'.e($dashboard->name).'</h1><p class="meta">Dashboard '.e($dashboard->code).' · configuration '.e($dashboard->checksum).' · period '.$period.' · generated '.e(now()->toIso8601String()).'</p><p class="meta">Reference lineage '.e(json_encode($lineage, JSON_THROW_ON_ERROR)).'</p><table><thead><tr><th>Metric</th><th>Metric key</th><th>Value</th><th>Unit</th><th>Visualization</th><th>Time grain</th><th>Trend</th><th>Provenance</th><th>Measured at</th></tr></thead><tbody>'.$body.'</tbody></table>');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    private function countyPdfIdentity(?County $county): string
    {
        if (! $county instanceof County) {
            return '<div class="identity"><strong>National portfolio</strong></div>';
        }

        $logo = '';
        $logoPath = is_string($county->logo_path) ? public_path(ltrim($county->logo_path, '/')) : null;
        if ($logoPath !== null && is_file($logoPath)) {
            $contents = file_get_contents($logoPath);
            if ($contents !== false) {
                $logo = '<img alt="" src="data:image/webp;base64,'.base64_encode($contents).'">';
            }
        }

        return '<div class="identity">'.$logo.'<div><strong>'.e($county->name).' County</strong><br><span>County '.str_pad((string) $county->code, 3, '0', STR_PAD_LEFT).' · identity verified '.e($county->logo_verified_at?->toDateString() ?? 'pending').'</span></div></div>';
    }

    private function mimeType(string $format): string
    {
        return match ($format) {
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
