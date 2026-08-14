<?php

namespace App\Http\Controllers;

use App\Actions\CreateInnovationReplication;
use App\Actions\UpdateInnovationReplication;
use App\Actions\VerifyInnovationReplication;
use App\Enums\ProgrammePermission;
use App\Http\Requests\StoreInnovationReplicationRequest;
use App\Http\Requests\UpdateInnovationReplicationRequest;
use App\Http\Requests\VerifyInnovationReplicationRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\DevolutionInnovation;
use App\Models\InnovationReplication;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Services\ProgrammeCountyScope;
use Dompdf\Dompdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InnovationReplicationController extends Controller
{
    public function __construct(private ProgrammeCountyScope $countyScope, private AuditLogger $auditLogger, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver) {}

    public function index(WorkspaceIndexRequest $request): InertiaResponse
    {
        Gate::authorize(ProgrammePermission::ViewKnowledge->value);
        $user = $this->user($request);
        $release = $this->referenceDataReleaseResolver->availableForSelection(now());
        $governedCountyIds = collect($release?->snapshot['counties'] ?? [])->pluck('id')->filter()->all();
        $counties = $this->countyScope->query($user)->whereIn('id', $governedCountyIds)->orderBy('code')->get();
        $countyIds = $counties->pluck('id');
        $query = $this->query($countyIds, $request->safe()->only(['from', 'to', 'county_id', 'status', 'search']));
        $summaryQuery = clone $query;
        $rows = $query->latest()->paginate($request->integer('per_page', 10))->withQueryString();
        $summary = (array) $summaryQuery
            ->toBase()
            ->selectRaw("count(*) as total, count(*) filter (where status = 'piloting') as piloting, count(*) filter (where status = 'verification') as awaiting_verification, count(*) filter (where status = 'adopted') as adopted")
            ->first();

        return Inertia::render('knowledge/innovation-replications', [
            'replications' => $rows->through(fn (InnovationReplication $replication): array => $this->payload($replication)),
            'summary' => ['total' => (int) ($summary['total'] ?? 0), 'piloting' => (int) ($summary['piloting'] ?? 0), 'awaitingVerification' => (int) ($summary['awaiting_verification'] ?? 0), 'adopted' => (int) ($summary['adopted'] ?? 0)],
            'filters' => $request->safe()->only(['from', 'to', 'county_id', 'status', 'search']),
            'options' => [
                'counties' => $counties->map->identityCell()->values(),
                'innovations' => $user->can(ProgrammePermission::ManageKnowledge->value) ? DevolutionInnovation::query()->where('status', 'scaling')->whereNotNull('county_id')->whereNotNull('reference_data_release_id')->whereIn('county_id', $governedCountyIds)->with('county:id,name,code,logo_path')->orderBy('title')->get()->map(fn (DevolutionInnovation $innovation): array => ['id' => $innovation->id, 'label' => "{$innovation->reference} · {$innovation->title}", 'county' => $innovation->county?->identityCell()])->values() : [],
                'adopters' => $user->can(ProgrammePermission::ManageKnowledge->value) ? User::query()->whereNull('access_revoked_at')->where(fn (Builder $query) => $query->whereIn('county_id', $countyIds)->orWhereHas('assignedCounties', fn (Builder $assigned) => $assigned->whereIn('counties.id', $countyIds)))->orderBy('name')->get(['id', 'name', 'county_id']) : [],
            ],
            'catalogue' => $release === null ? ['available' => false] : ['available' => true, 'version' => $release->version, 'effectiveFrom' => $release->effective_from?->toDateString(), 'checksum' => $release->checksum],
            'capabilities' => ['manage' => $user->can(ProgrammePermission::ManageKnowledge->value), 'contribute' => $user->can(ProgrammePermission::ContributeKnowledge->value), 'verify' => $user->can(ProgrammePermission::CurateKnowledge->value)],
        ]);
    }

    public function store(StoreInnovationReplicationRequest $request, CreateInnovationReplication $action): RedirectResponse
    {
        $replication = $action->handle($this->user($request), $request->validated());

        return back()->with('success', __('innovation-replications.outcomes.created', ['reference' => $replication->reference]));
    }

    public function update(UpdateInnovationReplicationRequest $request, InnovationReplication $replication, UpdateInnovationReplication $action): RedirectResponse
    {
        $action->handle($replication, $this->user($request), $request->validated());

        return back()->with('success', __('innovation-replications.outcomes.updated'));
    }

    public function verify(VerifyInnovationReplicationRequest $request, InnovationReplication $replication, VerifyInnovationReplication $action): RedirectResponse
    {
        $action->handle($replication, $this->user($request), $request->validated());

        return back()->with('success', __('innovation-replications.outcomes.verified'));
    }

    public function export(WorkspaceIndexRequest $request, string $format): Response
    {
        Gate::authorize(ProgrammePermission::ViewKnowledge->value);
        abort_unless(in_array($format, ['csv', 'xlsx', 'json', 'pdf'], true), 404);
        $user = $this->user($request);
        $filters = $request->safe()->only(['from', 'to', 'county_id', 'status', 'search']);
        $countyIds = $this->countyScope->query($user)->pluck('id');
        $rows = array_values($this->query($countyIds, $filters)->orderBy('reference')->get()->map(fn (InnovationReplication $replication): array => $this->payload($replication))->values()->all());
        $this->auditLogger->record($user, $user, 'knowledge.innovation_replication.exported', __('innovation-replications.audit.exported', ['format' => mb_strtoupper($format)]), $user->county_id, ['format' => $format, 'records' => count($rows), 'filters' => $filters]);
        $filename = 'innovation-replication-portfolio-'.now()->format('Ymd-His');

        return match ($format) {
            'csv' => $this->csv($rows, "{$filename}.csv"),
            'xlsx' => $this->xlsx($rows, "{$filename}.xlsx"),
            'json' => response()->streamDownload(fn () => print json_encode(['generated_at' => now()->toIso8601String(), 'filters' => $filters, 'rows' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "{$filename}.json", ['Content-Type' => 'application/json']),
            'pdf' => $this->pdf($rows, "{$filename}.pdf"),
        };
    }

    /**
     * @param  Collection<int, string>  $countyIds
     * @param  array<string, mixed>  $filters
     * @return Builder<InnovationReplication>
     */
    private function query(Collection $countyIds, array $filters): Builder
    {
        if (isset($filters['county_id']) && ! $countyIds->contains($filters['county_id'])) {
            abort(403);
        }

        return InnovationReplication::query()
            ->whereIn('target_county_id', $countyIds)
            ->with(['innovation:id,reference,title', 'sourceCounty:id,name,code,logo_path', 'targetCounty:id,name,code,logo_path', 'referenceDataRelease:id,version,effective_from,checksum', 'accountableUser:id,name', 'creator:id,name', 'submitter:id,name', 'verifier:id,name', 'documentLinks.document:id,title,original_name,mime_type,scan_status,record_status'])
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->when($filters['county_id'] ?? null, fn (Builder $query, string $countyId) => $query->where('target_county_id', $countyId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $term) => $term->where('reference', 'ilike', "%{$search}%")->orWhere('adaptation_plan', 'ilike', "%{$search}%")->orWhere('success_measure', 'ilike', "%{$search}%")->orWhereHas('innovation', fn (Builder $innovation) => $innovation->where('title', 'ilike', "%{$search}%"))));
    }

    /** @return array<string, mixed> */
    private function payload(InnovationReplication $replication): array
    {
        return ['id' => $replication->id, 'reference' => $replication->reference, 'referenceData' => $replication->referenceDataRelease === null ? null : ['version' => $replication->referenceDataRelease->version, 'effectiveFrom' => $replication->referenceDataRelease->effective_from?->toDateString(), 'checksum' => $replication->referenceDataRelease->checksum], 'innovation' => ['id' => $replication->innovation->id, 'reference' => $replication->innovation->reference, 'title' => $replication->innovation->title], 'sourceCounty' => $replication->sourceCounty->identityCell(), 'targetCounty' => $replication->targetCounty->identityCell(), 'accountableAdopter' => $replication->accountableUser->name, 'creator' => $replication->creator->name, 'submitter' => $replication->submitter?->name, 'verifier' => $replication->verifier?->name, 'adaptationPlan' => $replication->adaptation_plan, 'successMeasure' => $replication->success_measure, 'baselineValue' => (float) $replication->baseline_value, 'targetValue' => (float) $replication->target_value, 'actualValue' => $replication->actual_value === null ? null : (float) $replication->actual_value, 'startsOn' => $replication->starts_on->toDateString(), 'targetCompletionOn' => $replication->target_completion_on->toDateString(), 'outcomeSummary' => $replication->outcome_summary, 'status' => $replication->status, 'verificationDecision' => $replication->verification_decision, 'verificationRationale' => $replication->verification_rationale, 'decisionChecksum' => $replication->decision_checksum, 'submittedAt' => $replication->submitted_at?->toIso8601String(), 'verifiedAt' => $replication->verified_at?->toIso8601String(), 'documents' => $replication->documentLinks->map(fn ($link): array => ['id' => $link->document->id, 'title' => $link->document->title, 'originalName' => $link->document->original_name, 'mimeType' => $link->document->mime_type, 'scanStatus' => $link->document->scan_status])->values()];
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
        $path = tempnam(sys_get_temp_dir(), 'idmis-replication-');
        abort_if($path === false, 500, __('innovation-replications.errors.export_file_create_failed'));
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
            $county = $row['targetCounty'];
            $logo = '';
            if (is_array($county) && is_string($county['logoUrl'] ?? null)) {
                $contents = @file_get_contents(public_path(ltrim($county['logoUrl'], '/')));
                if ($contents !== false) {
                    $logo = '<img alt="" src="data:image/webp;base64,'.base64_encode($contents).'">';
                }
            }

            return '<tr><td>'.e($row['reference']).'</td><td>'.e($row['innovation']['title']).'</td><td>'.$logo.e($county['name']).'</td><td>'.e($row['referenceData']['version'] ?? __('innovation-replications.legacy_unpinned')).'</td><td>'.e($row['referenceData']['checksum'] ?? __('innovation-replications.legacy_unpinned')).'</td><td>'.e($row['accountableAdopter']).'</td><td>'.e($row['successMeasure']).'</td><td>'.e($row['actualValue'] ?? __('innovation-replications.not_available')).'</td><td>'.e(__('innovation-replications.'.$row['status'])).'</td></tr>';
        })->implode('');
        $dompdf = new Dompdf;
        $headings = $this->headings();
        $header = collect([$headings[0], $headings[1], $headings[3], $headings[4], $headings[5], $headings[6], $headings[7], $headings[10], $headings[11]])->map(fn (string $heading): string => '<th>'.e($heading).'</th>')->implode('');
        $dompdf->loadHtml('<style>body{font-family:sans-serif;font-size:10px;color:#172b3a}h1{color:#12304a}table{width:100%;border-collapse:collapse}th,td{padding:7px;border:1px solid #ccd6d0;text-align:left}th{background:#eef4f0}img{width:24px;height:24px;object-fit:contain;vertical-align:middle;margin-right:6px}</style><h1>'.e(__('innovation-replications.page_title')).'</h1><p>'.e(__('innovation-replications.generated_at', ['date' => now()->toDayDateTimeString()])).'</p><table><thead><tr>'.$header.'</tr></thead><tbody>'.$body.'</tbody></table>');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename=\"{$filename}\""]);
    }

    /** @return list<string> */
    private function headings(): array
    {
        return [__('innovation-replications.reference'), __('innovation-replications.source_innovation'), __('innovation-replications.source_county'), __('innovation-replications.target_county'), __('innovation-replications.catalogue'), __('innovation-replications.catalogue_checksum'), __('innovation-replications.accountable_adopter'), __('innovation-replications.success_measure'), __('innovation-replications.baseline_value'), __('innovation-replications.target_value'), __('innovation-replications.actual'), __('innovation-replications.status'), __('innovation-replications.independent_decision'), __('innovation-replications.target_completion')];
    }

    /** @param array<string, mixed> $row
     * @return list<string|float|null>
     */
    private function values(array $row): array
    {
        return [$row['reference'], $row['innovation']['title'], $row['sourceCounty']['name'], $row['targetCounty']['name'], $row['referenceData']['version'] ?? __('innovation-replications.legacy_unpinned'), $row['referenceData']['checksum'] ?? __('innovation-replications.legacy_unpinned'), $row['accountableAdopter'], $row['successMeasure'], $row['baselineValue'], $row['targetValue'], $row['actualValue'], __('innovation-replications.'.$row['status']), __('innovation-replications.'.$row['verificationDecision']), $row['targetCompletionOn']];
    }

    private function user(WorkspaceIndexRequest|StoreInnovationReplicationRequest|UpdateInnovationReplicationRequest|VerifyInnovationReplicationRequest $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
