<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\CountyGrant;
use App\Support\WorkspaceFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class CountyDetailData
{
    /** @return array<string, mixed> */
    public function for(County $county, WorkspaceFilters $filters): array
    {
        $assessmentsQuery = $this->filter(Assessment::query()->whereBelongsTo($county)->when($filters->cycleId, fn (Builder $query, string $cycleId) => $query->where('assessment_cycle_id', $cycleId)), $filters, ['cycle', 'status']);
        $documentsQuery = $this->filter(AssessmentDocument::query()->whereBelongsTo($county)->when($filters->cycleId, fn (Builder $query, string $cycleId) => $query->whereHas('assessment', fn (Builder $assessmentQuery) => $assessmentQuery->where('assessment_cycle_id', $cycleId))), $filters, ['title', 'category', 'verification_status']);
        $grantsQuery = $this->filter(CountyGrant::query()->whereBelongsTo($county), $filters, ['programme', 'financial_year', 'status']);

        $assessments = (clone $assessmentsQuery)->with(['county:id,name', 'assessor:id,name'])->withCount('documents')->latest()->paginate($filters->perPage, pageName: 'assessments_page')->withQueryString()->through(fn (Assessment $assessment): array => [
            'id' => $assessment->id,
            'status' => $assessment->status->value,
            'cells' => [$assessment->cycle, $assessment->status->value, $assessment->score ?? '—', $assessment->documents_count, $assessment->assessor_id ? $assessment->assessor->name : 'Unassigned'],
        ]);
        $documents = (clone $documentsQuery)->with(['county:id,name', 'assessment:id,cycle', 'uploader:id,name'])->latest()->paginate($filters->perPage, pageName: 'evidence_page')->withQueryString()->through(fn (AssessmentDocument $document): array => [
            'id' => $document->id,
            'status' => $document->verification_status,
            'meta' => [
                'title' => $document->title,
                'category' => $document->category,
                'sourceType' => $document->source_type,
                'description' => $document->description,
                'documentDate' => $document->document_date?->toDateString(),
                'retentionUntil' => $document->retention_until?->toDateString(),
                'tags' => collect($document->tags ?? [])->implode(', '),
                'mimeType' => $document->mime_type,
                'originalName' => $document->original_name,
            ],
            'cells' => [$document->title, $document->assessment->cycle, "{$document->category} · ".($document->source_type === 'scanned' ? 'Scanned copy' : 'Digital file'), $document->verification_status, $document->uploaded_by ? $document->uploader->name : 'System migration'],
        ]);
        $grants = (clone $grantsQuery)->with('county:id,name')->latest()->paginate($filters->perPage, pageName: 'grants_page')->withQueryString()->through(fn (CountyGrant $grant): array => [
            'id' => $grant->id,
            'status' => $grant->status,
            'meta' => ['allocatedAmount' => $grant->allocated_amount, 'disbursedAmount' => $grant->disbursed_amount],
            'cells' => [$grant->programme, $grant->financial_year, number_format((float) $grant->allocated_amount), number_format((float) $grant->disbursed_amount), $grant->status],
        ]);

        return [
            'county' => [...$county->identityCell(), 'region' => $county->region, 'officialWebsiteUrl' => $county->official_website_url, 'logoSourceAuthority' => $county->logo_source_authority, 'logoVerifiedAt' => $county->logo_verified_at?->toDateString()],
            'summary' => [
                'assessments' => (clone $assessmentsQuery)->count(),
                'documents' => (clone $documentsQuery)->count(),
                'verifiedDocuments' => (clone $documentsQuery)->where('verification_status', 'verified')->count(),
                'allocatedGrants' => (float) (clone $grantsQuery)->sum('allocated_amount'),
                'disbursedGrants' => (float) (clone $grantsQuery)->sum('disbursed_amount'),
            ],
            'assessments' => $this->table(['Cycle', 'Status', 'Score', 'Evidence', 'Assessor'], $assessments),
            'documents' => $this->table(['Document', 'Cycle', 'Category', 'Verification', 'Uploaded by'], $documents),
            'grants' => $this->table(['Programme', 'Financial year', 'Allocated (KES)', 'Disbursed (KES)', 'Status'], $grants),
        ];
    }

    /**
     * @param  list<string>  $columns
     * @param  LengthAwarePaginator<int, covariant array<string, mixed>>  $paginator
     * @return array<string, mixed>
     */
    private function table(array $columns, LengthAwarePaginator $paginator): array
    {
        return [
            'columns' => $columns,
            'rows' => $paginator->items(),
            'pagination' => ['currentPage' => $paginator->currentPage(), 'lastPage' => $paginator->lastPage(), 'perPage' => $paginator->perPage(), 'total' => $paginator->total(), 'pageName' => $paginator->getPageName()],
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     * @return Builder<TModel>
     */
    private function filter(Builder $query, WorkspaceFilters $filters, array $columns): Builder
    {
        return $query
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate($query->qualifyColumn('created_at'), '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate($query->qualifyColumn('created_at'), '<=', $to))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters, $columns): void {
                foreach ($columns as $index => $column) {
                    $index === 0
                        ? $query->where($query->qualifyColumn($column), 'ilike', '%'.$filters->search.'%')
                        : $query->orWhere($query->qualifyColumn($column), 'ilike', '%'.$filters->search.'%');
                }
            }));
    }
}
