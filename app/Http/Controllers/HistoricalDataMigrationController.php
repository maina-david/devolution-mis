<?php

namespace App\Http\Controllers;

use App\Actions\ApplyHistoricalDataMigration;
use App\Actions\ReviewHistoricalDataMigration;
use App\Actions\StageHistoricalDataMigration;
use App\Actions\StageReferenceDataImport;
use App\Enums\ProgrammePermission;
use App\Http\Requests\ApplyHistoricalDataMigrationRequest;
use App\Http\Requests\HistoricalDataMigrationIndexRequest;
use App\Http\Requests\ReviewHistoricalDataMigrationRequest;
use App\Http\Requests\StoreHistoricalDataMigrationRequest;
use App\Http\Requests\StoreReferenceDataImportRequest;
use App\Models\DataMigrationBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HistoricalDataMigrationController extends Controller
{
    public function index(HistoricalDataMigrationIndexRequest $request): Response
    {
        $this->authorizeView();
        $filters = $request->validated();
        $search = trim((string) ($filters['search'] ?? ''));

        $batches = DataMigrationBatch::query()
            ->with(['submitter:id,name', 'reviewer:id,name', 'applier:id,name'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('reference', 'ilike', "%{$search}%")
                ->orWhere('source_name', 'ilike', "%{$search}%")
                ->orWhere('source_reference', 'ilike', "%{$search}%")))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('dataset_type', $type))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString()
            ->through(fn ($batch): array => [
                'id' => $batch->id,
                'reference' => $batch->reference,
                'datasetType' => $batch->dataset_type,
                'sourceName' => $batch->source_name,
                'sourceReference' => $batch->source_reference,
                'periodFrom' => $batch->period_from->toDateString(),
                'periodTo' => $batch->period_to->toDateString(),
                'originalName' => $batch->original_name,
                'fileChecksum' => $batch->file_checksum,
                'status' => $batch->status,
                'totalRows' => $batch->total_rows,
                'validRows' => $batch->valid_rows,
                'invalidRows' => $batch->invalid_rows,
                'errorCounts' => $batch->validation_report['error_counts'] ?? [],
                'referenceDataRelease' => $batch->validation_report['reference_data_release'] ?? null,
                'submittedBy' => $batch->submitter->name,
                'reviewedBy' => $batch->reviewer?->name,
                'reviewNotes' => $batch->review_notes,
                'appliedBy' => $batch->applier?->name,
                'createdAt' => $batch->created_at->toIso8601String(),
                'reviewedAt' => $batch->reviewed_at?->toIso8601String(),
                'appliedAt' => $batch->applied_at?->toIso8601String(),
            ]);

        return Inertia::render('data-migrations/index', [
            'batches' => $batches,
            'filters' => $filters,
            'capabilities' => [
                'stage' => $request->user()?->can(ProgrammePermission::ManageReferenceData->value) === true,
                'review' => $request->user()?->can(ProgrammePermission::ApproveReferenceData->value) === true,
                'apply' => $request->user()?->can(ProgrammePermission::ManageOperations->value) === true,
            ],
        ]);
    }

    public function store(StoreHistoricalDataMigrationRequest $request, StageHistoricalDataMigration $stage): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $attributes = $request->validated();
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422, 'A CSV or XLSX source file is required.');
        $stage->handle($user, $file, $attributes['dataset_type'], $attributes['source_name'], $attributes['source_reference'], $attributes['period_from'], $attributes['period_to']);

        return $this->success('Historical source staged and reconciled. Review every reported exception before approval.');
    }

    public function storeReferenceData(StoreReferenceDataImportRequest $request, StageReferenceDataImport $stage): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $attributes = $request->validated();
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422, 'A CSV or XLSX source file is required.');
        $stage->handle($user, $file, $attributes['dataset_type'], $attributes['source_name'], $attributes['source_reference']);

        return $this->success('Bulk import staged and validated. Review every reported exception before approval.');
    }

    public function template(Request $request, string $datasetType): BinaryFileResponse|StreamedResponse
    {
        $this->authorizeView();
        $headers = StageReferenceDataImport::HEADERS[$datasetType]
            ?? (in_array($datasetType, ['acpa_scores', 'performance_metrics', 'evaluation_baselines'], true)
                ? StageHistoricalDataMigration::REQUIRED_HEADERS
                : null);
        abort_unless(is_array($headers), 404);
        $format = strtolower((string) $request->query('format', 'csv'));
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);

        if ($format === 'xlsx') {
            $path = tempnam(sys_get_temp_dir(), 'idmis-import-template-');
            abort_if($path === false, 500, 'The XLSX template could not be created.');
            $writer = new Writer;
            $writer->openToFile($path);
            $writer->addRow(Row::fromValues($headers));
            $writer->close();

            return response()->download(
                $path,
                "{$datasetType}-bulk-import-template.xlsx",
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            )->deleteFileAfterSend(true);
        }

        return response()->streamDownload(function () use ($headers): void {
            $stream = fopen('php://output', 'w');
            if ($stream !== false) {
                fputcsv($stream, $headers);
                fclose($stream);
            }
        }, "{$datasetType}-bulk-import-template.csv", ['Content-Type' => 'text/csv']);
    }

    public function review(ReviewHistoricalDataMigrationRequest $request, DataMigrationBatch $dataMigrationBatch, ReviewHistoricalDataMigration $review): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $attributes = $request->validated();
        $review->handle($dataMigrationBatch, $user, $attributes['decision'], $attributes['notes']);

        return $this->success('Historical migration review decision recorded.');
    }

    public function apply(ApplyHistoricalDataMigrationRequest $request, DataMigrationBatch $dataMigrationBatch, ApplyHistoricalDataMigration $apply): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $apply->handle($dataMigrationBatch, $user);

        return $this->success('Historical records applied to the immutable provenance register.');
    }

    public function download(DataMigrationBatch $dataMigrationBatch): StreamedResponse
    {
        $this->authorizeView();
        abort_unless(Storage::disk('local')->exists($dataMigrationBatch->path), 404, 'The retained source file is unavailable.');
        abort_unless(hash('sha256', Storage::disk('local')->get($dataMigrationBatch->path)) === $dataMigrationBatch->file_checksum, 409, 'The retained source file failed its integrity check.');

        return Storage::disk('local')->download($dataMigrationBatch->path, $dataMigrationBatch->original_name, ['Content-Type' => $dataMigrationBatch->mime_type]);
    }

    public function downloadExceptions(DataMigrationBatch $dataMigrationBatch): StreamedResponse
    {
        $this->authorizeView();
        abort_if($dataMigrationBatch->invalid_rows === 0, 404, 'This migration batch has no validation exceptions.');

        $firstException = $dataMigrationBatch->rows()
            ->where('validation_status', 'invalid')
            ->orderBy('row_number')
            ->firstOrFail();
        $payloadHeaders = array_keys($firstException->source_payload ?? []);
        $filename = "{$dataMigrationBatch->reference}-row-exceptions.csv";

        return response()->streamDownload(function () use ($dataMigrationBatch, $payloadHeaders): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'batch_reference',
                'dataset_type',
                'source_file_checksum',
                'row_number',
                ...$payloadHeaders,
                'validation_errors',
                'source_row_checksum',
            ]);

            foreach ($dataMigrationBatch->rows()->where('validation_status', 'invalid')->orderBy('row_number')->cursor() as $row) {
                $payload = $row->source_payload ?? [];
                fputcsv($stream, [
                    $dataMigrationBatch->reference,
                    $dataMigrationBatch->dataset_type,
                    $dataMigrationBatch->file_checksum,
                    $row->row_number,
                    ...array_map(fn (string $header): string => $this->csvCell($payload[$header] ?? ''), $payloadHeaders),
                    implode('|', $row->validation_errors ?? []),
                    $row->source_checksum,
                ]);
            }

            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function authorizeView(): void
    {
        abort_unless(Gate::any([
            ProgrammePermission::ManageReferenceData->value,
            ProgrammePermission::ApproveReferenceData->value,
            ProgrammePermission::ManageOperations->value,
        ]), 403);
    }

    private function success(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function csvCell(mixed $value): string
    {
        $cell = (string) $value;

        return preg_match('/^[=+\-@]/', ltrim($cell)) === 1 ? "'{$cell}" : $cell;
    }
}
