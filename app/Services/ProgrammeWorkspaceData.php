<?php

namespace App\Services;

use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\AccessDelegation;
use App\Models\AccessReviewItem;
use App\Models\Assessment;
use App\Models\AssessmentCycle;
use App\Models\AssessmentDocument;
use App\Models\AuditAssuranceRun;
use App\Models\AuditEvent;
use App\Models\BusinessCalendar;
use App\Models\CitizenCase;
use App\Models\CountyGrant;
use App\Models\DevolutionInnovation;
use App\Models\DevolutionProject;
use App\Models\DocumentDisposition;
use App\Models\DocumentLink;
use App\Models\DswgAction;
use App\Models\DswgMeeting;
use App\Models\ExchequerRequest;
use App\Models\IdentityLifecycleRequest;
use App\Models\IgrResolution;
use App\Models\IgrResolutionDependency;
use App\Models\IgrResolutionGap;
use App\Models\IndicatorObservation;
use App\Models\IntegrationExchange;
use App\Models\IntegrationSystem;
use App\Models\KnowledgeCommunityReport;
use App\Models\KnowledgeItem;
use App\Models\LearningCohort;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningOfflineSync;
use App\Models\OperationalAlert;
use App\Models\OperationalBackup;
use App\Models\PartnerAgreement;
use App\Models\PartnerCollaborationAction;
use App\Models\PartnerContributionSourceMatch;
use App\Models\PartnerProfile;
use App\Models\PerformancePlan;
use App\Models\PlatformSetting;
use App\Models\PrivacyIncident;
use App\Models\ProcessingActivity;
use App\Models\ProgrammeCountyCoverage;
use App\Models\ProgrammeEvaluation;
use App\Models\SecurityIncident;
use App\Models\ServiceDeskPolicy;
use App\Models\SupportTicket;
use App\Models\TrainingParticipant;
use App\Models\TravelRequest;
use App\Models\UatCampaign;
use App\Models\User;
use App\Models\VirtualClassroom;
use App\Models\VirtualClassroomAttendance;
use App\Support\WorkspaceFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProgrammeWorkspaceData
{
    public function __construct(private ProgrammeCountyScope $countyScope, private VirtualClassroomAccess $classroomAccess, private ProjectScheduleAnalyzer $projectScheduleAnalyzer, private ProjectEarnedValueAnalyzer $projectEarnedValueAnalyzer, private SupportTicketAccess $supportTicketAccess, private IgrGapScope $igrGapScope) {}

    /** @return array<string, mixed> */
    public function counties(User $user, WorkspaceFilters $filters): array
    {
        $counties = $this->applyFilters($this->countyScope->query($user)->withCount(['assessments', 'documents', 'grants']), $filters, ['name', 'region'])->orderBy('code')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('County performance', 'Authorized county records, assessment coverage, evidence readiness, and grant activity.', ['County', 'Region', 'Assessments', 'Evidence', 'Grants'], $counties->through(fn ($county) => [
            'id' => $county->id,
            'meta' => ['countyId' => $county->id],
            'cells' => [$county->identityCell(), $county->region ?? '—', $county->assessments_count, $county->documents_count, $county->grants_count],
        ]));
    }

    /** @return array<string, mixed> */
    public function programmeCountyCoverages(User $user, WorkspaceFilters $filters): array
    {
        $coverages = ProgrammeCountyCoverage::query()
            ->with(['programme:id,code,name,sector_id', 'programme.sector:id,name', 'county', 'implementationLead:id,name', 'creator:id,name'])
            ->when($filters->from, fn (Builder $query, string $from) => $query->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from)))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('starts_on', '<=', $to))
            ->when($filters->countyId, fn (Builder $query, string $countyId) => $query->where('county_id', $countyId))
            ->when($filters->sectorId, fn (Builder $query, string $sectorId) => $query->whereHas('programme', fn (Builder $programme) => $programme->where('sector_id', $sectorId)))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('source_reference', 'ilike', '%'.$filters->search.'%')
                ->orWhereHas('programme', fn (Builder $programme) => $programme->where('code', 'ilike', '%'.$filters->search.'%')->orWhere('name', 'ilike', '%'.$filters->search.'%'))
                ->orWhereHas('county', fn (Builder $county) => $county->where('name', 'ilike', '%'.$filters->search.'%'))
                ->orWhereHas('implementationLead', fn (Builder $organization) => $organization->where('name', 'ilike', '%'.$filters->search.'%'))))
            ->orderByDesc('starts_on')
            ->paginate($filters->perPage, pageName: 'programme_coverages_page')
            ->withQueryString();

        return $this->workspace('Programme county coverage', 'Effective-dated programme reach, county implementation authority and attributed funding coverage.', ['Programme', 'County', 'Sector', 'Implementation lead', 'Period', 'Allocation', 'Source', 'Created by', 'Status'], $coverages->through(fn (ProgrammeCountyCoverage $coverage): array => [
            'id' => $coverage->id,
            'status' => $coverage->status,
            'meta' => ['countyId' => $coverage->county_id],
            'cells' => [
                $coverage->programme->code.' · '.$coverage->programme->name,
                $coverage->county->identityCell(),
                $coverage->programme->sector_id !== null ? $coverage->programme->sector->name : 'Unassigned',
                $coverage->implementation_lead_id !== null ? $coverage->implementationLead->name : 'Unassigned',
                $coverage->starts_on->toDateString().' – '.($coverage->ends_on?->toDateString() ?? 'Open ended'),
                $coverage->funding_allocation !== null ? $coverage->currency.' '.number_format((float) $coverage->funding_allocation, 2) : 'Not allocated',
                $coverage->source_reference,
                $coverage->creator->name,
                $coverage->status,
            ],
        ]));
    }

    /** @return array<string, mixed> */
    public function assessments(User $user, WorkspaceFilters $filters): array
    {
        $assessments = $this->applyFilters(Assessment::query()->whereIn('county_id', $this->countyScope->query($user)->select('id'))->when($filters->cycleId, fn (Builder $query, string $cycleId) => $query->where('assessment_cycle_id', $cycleId))->with(['county:id,name,code,logo_path', 'assessor:id,name', 'creator:id,name', 'referenceDataRelease:id,version,effective_from,checksum'])->withCount('documents'), $filters, ['cycle', 'status'])->latest()->paginate($filters->perPage)->withQueryString();

        $workspace = $this->workspace('ACPA assessments', 'Track preparation, submission, independent verification, scoring, and approval by assessment cycle.', ['County', 'Cycle', 'Reference release', 'Reference checksum', 'Created by', 'Status', 'Score', 'Evidence', 'Assessor'], $assessments->through(fn (Assessment $assessment) => [
            'id' => $assessment->id,
            'status' => $assessment->status->value,
            'meta' => ['countyId' => $assessment->county_id],
            'cells' => [
                $assessment->county->identityCell(),
                $assessment->cycle,
                $assessment->referenceDataRelease ? "v{$assessment->referenceDataRelease->version} · {$assessment->referenceDataRelease->effective_from?->toDateString()}" : 'Legacy unpinned',
                $assessment->referenceDataRelease ? $assessment->referenceDataRelease->checksum : 'Not available',
                $assessment->created_by ? $assessment->creator->name : 'Legacy unrecorded',
                $assessment->status->value,
                $assessment->score ?? '—',
                $assessment->documents_count,
                $assessment->assessor_id ? $assessment->assessor->name : 'Unassigned',
            ],
        ]));

        $workspace['assessmentCreationOptions'] = $this->assessmentCreationOptions($user);

        return $workspace;
    }

    /** @return array{counties: list<array<string, mixed>>, cycles: list<array{id: string, name: string}>, pairs: list<array{countyId: string, cycleId: string}>} */
    private function assessmentCreationOptions(User $user): array
    {
        if (! $user->can(ProgrammePermission::ManageAssessmentConfiguration->value)) {
            return ['counties' => [], 'cycles' => [], 'pairs' => []];
        }

        $counties = $this->countyScope->query($user)->orderBy('name')->get(['id', 'name', 'code', 'logo_path']);
        $cycles = AssessmentCycle::query()
            ->whereIn('status', ['planned', 'open'])
            ->whereHas('scorecardVersion', fn (Builder $query) => $query->whereIn('status', ['published', 'retired'])->whereNotNull('checksum'))
            ->orderByDesc('period_start')
            ->get(['id', 'code', 'name', 'status']);
        $existingPairs = Assessment::query()
            ->withTrashed()
            ->whereIn('county_id', $counties->pluck('id'))
            ->whereIn('assessment_cycle_id', $cycles->pluck('id'))
            ->get(['county_id', 'assessment_cycle_id'])
            ->mapWithKeys(fn (Assessment $assessment): array => ["{$assessment->county_id}:{$assessment->assessment_cycle_id}" => true]);

        $pairs = [];
        $eligibleCountyIds = [];
        $eligibleCycleIds = [];
        foreach ($counties as $county) {
            foreach ($cycles as $cycle) {
                if ($existingPairs->has("{$county->id}:{$cycle->id}")) {
                    continue;
                }

                $pairs[] = ['countyId' => $county->id, 'cycleId' => $cycle->id];
                $eligibleCountyIds[$county->id] = true;
                $eligibleCycleIds[$cycle->id] = true;
            }
        }

        $countyOptions = [];
        foreach ($counties as $county) {
            if (isset($eligibleCountyIds[$county->id])) {
                $countyOptions[] = $county->identityCell();
            }
        }
        $cycleOptions = [];
        foreach ($cycles as $cycle) {
            if (isset($eligibleCycleIds[$cycle->id])) {
                $cycleOptions[] = ['id' => $cycle->id, 'name' => "{$cycle->name} ({$cycle->code}) · {$cycle->status}"];
            }
        }

        return [
            'counties' => $countyOptions,
            'cycles' => $cycleOptions,
            'pairs' => $pairs,
        ];
    }

    /** @return array<string, mixed> */
    public function evidence(User $user, WorkspaceFilters $filters): array
    {
        $query = AssessmentDocument::query()
            ->when($user->can('records:manage'), fn (Builder $query) => $query->withTrashed())
            ->where(function (Builder $query) use ($user): void {
                $projectMorphClass = (new DevolutionProject)->getMorphClass();
                $visibleProjectIds = DevolutionProject::query()
                    ->whereHas('counties', fn (Builder $query) => $query->whereIn('counties.id', $this->countyScope->query($user)->select('id')))
                    ->select('id');
                $travelMorphClass = (new TravelRequest)->getMorphClass();
                $visibleTravelIds = TravelRequest::query()
                    ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('requester_id', $user->id)->orWhereIn('county_id', $this->countyScope->query($user)->select('id'))))
                    ->select('id');
                $partnerAgreementMorphClass = (new PartnerAgreement)->getMorphClass();
                $visiblePartnerAgreementIds = PartnerAgreement::query()
                    ->whereHas('partner.counties', fn (Builder $query) => $query->whereIn('counties.id', $this->countyScope->query($user)->select('id')))
                    ->select('id');
                $dswgMeetingMorphClass = (new DswgMeeting)->getMorphClass();
                $visibleDswgMeetingIds = DswgMeeting::query()
                    ->whereHas('workingGroup.counties', fn (Builder $query) => $query->whereIn('counties.id', $this->countyScope->query($user)->select('id')))
                    ->select('id');
                $dswgActionMorphClass = (new DswgAction)->getMorphClass();
                $visibleDswgActionIds = DswgAction::query()
                    ->whereHas('meeting.workingGroup.counties', fn (Builder $query) => $query->whereIn('counties.id', $this->countyScope->query($user)->select('id')))
                    ->select('id');
                $igrResolutionMorphClass = (new IgrResolution)->getMorphClass();
                $visibleIgrResolutionIds = IgrResolution::query()
                    ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereHas('assignments', fn (Builder $assignments) => $assignments->where('user_id', $user->id)->orWhereIn('county_id', $this->countyScope->query($user)->select('id'))))
                    ->select('id');
                $programmeEvaluationMorphClass = (new ProgrammeEvaluation)->getMorphClass();
                $visibleProgrammeEvaluationIds = ProgrammeEvaluation::query()
                    ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('lead_evaluator_id', $user->id)->orWhereIn('county_id', $this->countyScope->query($user)->select('id'))))
                    ->select('id');
                $performancePlanMorphClass = (new PerformancePlan)->getMorphClass();
                $visiblePerformancePlanIds = PerformancePlan::query()
                    ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('employee_id', $user->id)->orWhere('supervisor_id', $user->id)))
                    ->select('id');
                $securityIncidentMorphClass = (new SecurityIncident)->getMorphClass();
                $visibleSecurityIncidentIds = SecurityIncident::query()
                    ->when(! $user->can(ProgrammePermission::ViewSecurityGovernance->value), fn (Builder $query) => $query->whereRaw('1 = 0'))
                    ->select('id');
                $query->whereIn('county_id', $this->countyScope->query($user)->select('id'))
                    ->orWhereHas('links', fn (Builder $links) => $links
                        ->where(fn (Builder $links) => $links->where('subject_type', $projectMorphClass)->whereIn('subject_id', $visibleProjectIds))
                        ->orWhere(fn (Builder $links) => $links->where('subject_type', $travelMorphClass)->whereIn('subject_id', $visibleTravelIds))
                        ->orWhere(fn (Builder $links) => $links->where('subject_type', $partnerAgreementMorphClass)->whereIn('subject_id', $visiblePartnerAgreementIds))
                        ->orWhere(fn (Builder $links) => $links->where('subject_type', $dswgMeetingMorphClass)->whereIn('subject_id', $visibleDswgMeetingIds))
                        ->orWhere(fn (Builder $links) => $links->where('subject_type', $dswgActionMorphClass)->whereIn('subject_id', $visibleDswgActionIds))
                        ->orWhere(fn (Builder $links) => $links->where('subject_type', $igrResolutionMorphClass)->whereIn('subject_id', $visibleIgrResolutionIds))
                        ->orWhere(fn (Builder $links) => $links->where('subject_type', $programmeEvaluationMorphClass)->whereIn('subject_id', $visibleProgrammeEvaluationIds))
                        ->orWhere(fn (Builder $links) => $links->where('subject_type', $performancePlanMorphClass)->whereIn('subject_id', $visiblePerformancePlanIds))
                        ->orWhere(fn (Builder $links) => $links->where('subject_type', $securityIncidentMorphClass)->whereIn('subject_id', $visibleSecurityIncidentIds)));
            })
            ->when($filters->cycleId, fn (Builder $query, string $cycleId) => $query->whereHas('assessment', fn (Builder $assessmentQuery) => $assessmentQuery->where('assessment_cycle_id', $cycleId)))
            ->with([
                'county:id,name,code,logo_path',
                'assessment:id,cycle',
                'links:id,assessment_document_id,subject_type,subject_id,purpose',
                'links.subject',
                'uploader:id,name',
                'currentVersion.extraction',
                'currentVersion.extraction.attempts',
                'versions:id,assessment_document_id,version_number,storage_disk,path,original_name,mime_type,size_bytes,content_checksum,scan_status,ocr_status,change_summary,uploaded_by,created_at',
                'versions.uploader:id,name',
                'versions.extraction',
                'versions.extraction.attempts',
                'legalHolds:id,assessment_document_id,reference,reason,authority,placed_by,placed_at,released_by,released_at,release_reason',
                'legalHolds.placer:id,name',
                'legalHolds.releaser:id,name',
                'dispositions:id,assessment_document_id,requested_by,reviewed_by,executed_by,action,reason,authority_reference,retention_due_at,scheduled_for,status,decision_reason,reviewed_at,execution_started_at,executed_at,manifest_checksum,object_count,total_bytes,execution_error,created_at',
                'dispositions.requester:id,name',
                'dispositions.reviewer:id,name',
                'dispositions.executor:id,name',
            ])
            ->withExists(['legalHolds as active_legal_hold' => fn (Builder $query) => $query->whereNull('released_at')]);
        $dateFilters = new WorkspaceFilters($filters->from, $filters->to, '', $filters->perPage, $filters->cycleId);
        $documents = $this->applyFilters($query, $dateFilters, [])
            ->when($filters->search !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $search = $filters->search;
                    $query->where('title', 'ilike', "%{$search}%")
                        ->orWhere('category', 'ilike', "%{$search}%")
                        ->orWhere('description', 'ilike', "%{$search}%")
                        ->orWhereHas('currentVersion.extraction', function (Builder $query) use ($search): void {
                            if (DB::getDriverName() === 'pgsql') {
                                $query->whereRaw("to_tsvector('simple', coalesce(extracted_text, '')) @@ websearch_to_tsquery('simple', ?)", [$search]);
                            } else {
                                $query->where('extracted_text', 'like', "%{$search}%");
                            }
                        });
                });
            })
            ->latest()
            ->paginate($filters->perPage)
            ->withQueryString();

        $workspace = $this->workspace('Evidence library', 'Secure evidence register for plans, audit opinions, public participation reports, legislation, and supporting records.', ['Document', 'County', 'Cycle', 'Category', 'Integrity', 'Verification'], $documents->through(fn (AssessmentDocument $document) => [
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
                'sizeBytes' => $document->size_bytes !== null ? (string) $document->size_bytes : null,
                'checksum' => $document->content_checksum,
                'scanStatus' => $document->scan_status,
                'ocrStatus' => $document->ocr_status,
                'extractionEngine' => $document->currentVersion?->extraction?->engine,
                'extractionCompletedAt' => $document->currentVersion?->extraction?->completed_at?->toIso8601String(),
                'extractionError' => $document->currentVersion?->extraction?->error_detail,
                'extractedTextPreview' => $document->currentVersion?->extraction?->extracted_text !== null
                    ? Str::limit($document->currentVersion->extraction->extracted_text, 1200)
                    : null,
                'extractionAttempts' => $document->currentVersion?->extraction?->attempts->map(fn ($attempt): array => [
                    'id' => $attempt->id,
                    'number' => $attempt->attempt_number,
                    'status' => $attempt->status,
                    'engine' => $attempt->engine,
                    'language' => $attempt->language,
                    'triggerSource' => $attempt->trigger_source,
                    'initiatedBy' => $attempt->initiated_by_name ?? 'Automated worker',
                    'characterCount' => $attempt->character_count,
                    'pageCount' => $attempt->page_count,
                    'errorCode' => $attempt->error_code,
                    'errorDetail' => $attempt->error_detail,
                    'startedAt' => $attempt->started_at->toIso8601String(),
                    'completedAt' => $attempt->completed_at?->toIso8601String(),
                    'durationMs' => $attempt->duration_ms,
                    'checksum' => $attempt->text_checksum_sha256,
                ])->values()->all() ?? [],
                'recordStatus' => $document->record_status,
                'version' => (string) $document->version,
                'activeLegalHold' => $document->active_legal_hold ? 'true' : 'false',
                'versions' => $document->versions->map(fn ($version): array => [
                    'id' => $version->id,
                    'number' => $version->version_number,
                    'originalName' => $version->original_name,
                    'mimeType' => $version->mime_type,
                    'sizeBytes' => $version->size_bytes,
                    'checksum' => $version->content_checksum,
                    'scanStatus' => $version->scan_status,
                    'ocrStatus' => $version->extractionStatus(),
                    'changeSummary' => $version->change_summary,
                    'uploadedBy' => $version->uploaded_by ? $version->uploader->name : 'System migration',
                    'createdAt' => $version->created_at->toIso8601String(),
                    'isCurrent' => $document->current_version_id === $version->id,
                    'extractionAttempts' => $version->extraction?->attempts->map(fn ($attempt): array => [
                        'id' => $attempt->id,
                        'number' => $attempt->attempt_number,
                        'status' => $attempt->status,
                        'engine' => $attempt->engine,
                        'triggerSource' => $attempt->trigger_source,
                        'initiatedBy' => $attempt->initiated_by_name ?? 'Automated worker',
                        'startedAt' => $attempt->started_at->toIso8601String(),
                        'completedAt' => $attempt->completed_at?->toIso8601String(),
                        'durationMs' => $attempt->duration_ms,
                        'errorCode' => $attempt->error_code,
                    ])->values()->all() ?? [],
                ])->values()->all(),
                'legalHolds' => $document->legalHolds->map(fn ($hold): array => [
                    'id' => $hold->id,
                    'reference' => $hold->reference,
                    'reason' => $hold->reason,
                    'authority' => $hold->authority,
                    'placedBy' => $hold->placed_by ? $hold->placer->name : 'System migration',
                    'placedAt' => $hold->placed_at->toIso8601String(),
                    'releasedBy' => $hold->released_by ? $hold->releaser->name : null,
                    'releasedAt' => $hold->released_at?->toIso8601String(),
                    'releaseReason' => $hold->release_reason,
                    'canRelease' => $hold->released_at === null && $hold->placed_by !== $user->id,
                ])->values()->all(),
                'dispositions' => $document->dispositions->map(fn (DocumentDisposition $disposition): array => [
                    'id' => $disposition->id,
                    'action' => $disposition->action,
                    'reason' => $disposition->reason,
                    'authorityReference' => $disposition->authority_reference,
                    'retentionDueAt' => $disposition->retention_due_at->toDateString(),
                    'scheduledFor' => $disposition->scheduled_for->toDateString(),
                    'status' => $disposition->status,
                    'requestedBy' => $disposition->requester->name,
                    'requestedAt' => $disposition->created_at->toIso8601String(),
                    'reviewedBy' => $disposition->reviewed_by ? $disposition->reviewer->name : null,
                    'reviewedAt' => $disposition->reviewed_at?->toIso8601String(),
                    'decisionReason' => $disposition->decision_reason,
                    'executedBy' => $disposition->executed_by ? $disposition->executor->name : null,
                    'executedAt' => $disposition->executed_at?->toIso8601String(),
                    'manifestChecksum' => $disposition->manifest_checksum,
                    'objectCount' => $disposition->object_count,
                    'totalBytes' => $disposition->total_bytes,
                    'executionError' => $disposition->execution_error,
                    'canDecide' => $disposition->status === 'pending' && $disposition->requested_by !== $user->id,
                    'canExecute' => in_array($disposition->status, ['approved', 'execution_failed'], true) && ! in_array($user->id, [$disposition->requested_by, $disposition->reviewed_by], true),
                ])->values()->all(),
                'countyId' => $document->county_id,
                'countyName' => $document->county?->name,
                'countyCode' => $document->county !== null ? (string) $document->county->code : null,
                'countyLogoUrl' => $document->county?->logo_path,
            ],
            'cells' => [$document->title, $document->county?->identityCell() ?? 'National', $document->assessment_id !== null ? $document->assessment->cycle : str($document->links->isEmpty() ? 'Linked record' : $document->links->first()->purpose)->headline()->toString(), "{$document->category} · ".($document->source_type === 'scanned' ? 'Scanned copy' : 'Digital file'), "v{$document->version} · scan {$document->scan_status} · extraction {$document->ocr_status} · ".($document->currentVersion?->extraction?->attempts->count() ?? 0).' attempt(s)'.($document->active_legal_hold ? ' · legal hold' : ''), $document->verification_status],
        ]));

        $workspace['assessmentOptions'] = Assessment::query()
            ->whereIn('county_id', $this->countyScope->query($user)->select('id'))
            ->whereNotIn('status', ['assessed', 'approved'])
            ->with('county:id,name')
            ->latest()
            ->get()
            ->map(fn (Assessment $assessment) => ['id' => $assessment->id, 'label' => "{$assessment->county->name} · {$assessment->cycle}"])
            ->values();

        return $workspace;
    }

    /** @return array<string, mixed> */
    public function grants(User $user, WorkspaceFilters $filters): array
    {
        $grants = $this->applyFilters(CountyGrant::query()->whereIn('county_id', $this->countyScope->query($user)->select('id'))->with('county:id,name,code,logo_path'), $filters, ['programme', 'financial_year', 'status'])->latest()->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Exchequer and grants', 'Monitor allocation, processing, disbursement, and county receipt across programme funding streams.', ['County', 'Programme', 'Financial year', 'Allocated (KES)', 'Disbursed (KES)', 'Status'], $grants->through(fn (CountyGrant $grant) => [
            'id' => $grant->id,
            'status' => $grant->status,
            'meta' => ['allocatedAmount' => $grant->allocated_amount, 'disbursedAmount' => $grant->disbursed_amount, 'countyId' => $grant->county_id],
            'cells' => [$grant->county->identityCell(), $grant->programme, $grant->financial_year, number_format((float) $grant->allocated_amount), number_format((float) $grant->disbursed_amount), $grant->status],
        ]));
    }

    /** @return array<string, mixed> */
    public function users(User $user, WorkspaceFilters $filters): array
    {
        $users = User::query()
            ->with(['county:id,name,code,logo_path', 'roles:id,name'])
            ->when(! $user->can('user-access:manage'), function ($query) use ($user): void {
                $query->when(
                    $user->programmeRole() === UserRole::CountyAdmin,
                    fn ($accounts) => $accounts->where('county_id', $user->county_id),
                    fn ($accounts) => $accounts->whereHas('roles', fn ($roles) => $roles->whereIn('name', [UserRole::CountyOfficial->value, UserRole::CountyAdmin->value])),
                );
            })
            ->tap(fn (Builder $query) => $this->applyFilters($query, $filters, ['name', 'email']))
            ->orderBy('name')
            ->paginate($filters->perPage)->withQueryString();

        $workspace = $this->workspace('User access', 'Administrator-granted identities, programme roles, county assignments, and access status.', ['Name', 'Email', 'Role', 'Home county', 'Status'], $users->through(fn (User $account) => [
            'id' => $account->id,
            'cells' => [$account->name, $account->email, $account->getRoleNames()->first() ?? 'Unassigned', $account->county_id ? $account->county->identityCell() : 'National / portfolio', $account->email_verified_at ? 'Active' : 'Pending verification'],
        ]));

        $roles = $user->can('user-access:manage')
            ? UserRole::cases()
            : ($user->programmeRole() === UserRole::CountyAdmin ? [UserRole::CountyOfficial] : [UserRole::CountyOfficial, UserRole::CountyAdmin]);
        $workspace['accessOptions'] = [
            'roles' => collect($roles)->map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()])->values(),
            'counties' => $this->countyScope->query($user)->orderBy('code')->get()->map->identityCell()->values(),
        ];

        return $workspace;
    }

    /** @return array<string, mixed> */
    public function reports(User $user, WorkspaceFilters $filters): array
    {
        $counties = $this->applyFilters($this->countyScope->query($user)->withCount(['assessments', 'documents']), $filters, ['name', 'region'])->orderBy('code')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('National reports', 'Portfolio-ready county coverage and evidence indicators for programme oversight and decision support.', ['County', 'Region', 'Assessment cycles', 'Evidence records', 'Coverage status'], $counties->through(fn ($county) => [
            'id' => $county->id,
            'meta' => ['countyId' => $county->id],
            'cells' => [$county->identityCell(), $county->region ?? '—', $county->assessments_count, $county->documents_count, $county->assessments_count > 0 ? 'Reporting' : 'Not started'],
        ]));
    }

    /** @return array<string, mixed> */
    public function audit(User $user, WorkspaceFilters $filters): array
    {
        $events = $this->applyFilters(AuditEvent::query()
            ->where(fn ($query) => $query->whereNull('county_id')->orWhereIn('county_id', $this->countyScope->query($user)->select('id')))
            ->with(['actor:id,name', 'county:id,name,code,logo_path']), $filters, ['action', 'description'])
            ->latest()
            ->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Audit trail', 'Immutable workflow and access events within your authorized county scope.', ['Action', 'Description', 'County', 'Actor', 'Recorded'], $events->through(fn (AuditEvent $event) => [
            'id' => $event->id,
            'cells' => [$event->action, $event->description, $event->county_id ? $event->county->identityCell() : 'Platform-wide', $event->actor_id ? $event->actor->name : 'System', $event->created_at?->toDayDateTimeString()],
        ]));
    }

    /** @return array<string, mixed> */
    public function auditAssurance(User $user, WorkspaceFilters $filters): array
    {
        abort_unless($user->programmeRole()->hasNationalScope(), 403);
        $runs = $this->applyFilters(AuditAssuranceRun::query()->when($filters->status, fn (Builder $query, string $status) => $query->where('outcome', $status))->with('initiator:id,name'), $filters, ['environment', 'outcome', 'initiated_by_name', 'evidence_checksum'])->latest('started_at')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Audit integrity assurance', 'Retained verification of predecessor continuity, reproducible event hashes, legacy coverage, private anchor artifacts and detached signatures.', ['Environment', 'Outcome', 'Events', 'Verified', 'Legacy', 'Mismatches', 'Covered through', 'Chain root', 'Findings', 'Artifact checksum', 'Signature', 'Evidence checksum', 'Initiated by', 'Completed'], $runs->through(fn (AuditAssuranceRun $run): array => ['id' => $run->id, 'status' => $run->outcome, 'meta' => ['eventCount' => (string) $run->event_count, 'verifiedEventCount' => (string) $run->verified_event_count, 'legacyEventCount' => (string) $run->legacy_event_count, 'mismatchCount' => (string) $run->mismatch_count, 'firstEventId' => $run->first_event_id, 'lastEventId' => $run->last_event_id, 'firstEventHash' => $run->first_event_hash, 'lastEventHash' => $run->last_event_hash, 'chainRootChecksum' => $run->chain_root_checksum, 'findingCodes' => implode(', ', $run->finding_codes), 'artifactChecksum' => $run->artifact_checksum, 'signatureAlgorithm' => $run->signature_algorithm, 'signingKeyReference' => $run->signing_key_reference, 'signature' => $run->signature, 'evidenceChecksum' => $run->evidence_checksum, 'artifactAvailable' => $run->path !== null ? 'true' : 'false'], 'cells' => [$run->environment, $run->outcome, $run->event_count, $run->verified_event_count, $run->legacy_event_count, $run->mismatch_count, $run->last_event_id ?? 'No events', $run->chain_root_checksum, implode(', ', $run->finding_codes), $run->artifact_checksum ?? 'Unavailable', $run->signature_algorithm ? $run->signature_algorithm.' · '.$run->signing_key_reference : 'Unsigned', $run->evidence_checksum, $run->initiated_by_name, $run->completed_at->toIso8601String()]]));
    }

    /** @return array<string, mixed> */
    public function platform(WorkspaceFilters $filters): array
    {
        $settings = $this->applyFilters(PlatformSetting::query()->with('updater:id,name'), $filters, ['label', 'group', 'value'])->orderBy('group')->orderBy('label')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Platform controls', 'Governed configuration surface for integrations, document retention, access controls, and operational readiness.', ['Control', 'Group', 'Value', 'Updated by'], $settings->through(fn (PlatformSetting $setting) => [
            'id' => $setting->id,
            'meta' => ['value' => $setting->value, 'type' => $setting->type, 'description' => $setting->description],
            'cells' => [$setting->label, $setting->group, $setting->value ?? '—', $setting->updated_by ? $setting->updater->name : 'System baseline'],
        ]));
    }

    /** @return array<string, mixed> */
    public function monitoringEvaluation(User $user, WorkspaceFilters $filters): array
    {
        $observations = IndicatorObservation::query()
            ->whereIn('county_id', $this->countyScope->query($user)->select('id'))
            ->with(['indicator:id,reference_data_release_id,code,name,unit_of_measure', 'indicator.referenceDataRelease:id,version,checksum', 'county:id,name,code,logo_path', 'programme:id,name'])
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('period_end', '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('period_start', '<=', $to))
            ->when($filters->countyId, fn (Builder $query, string $countyId) => $query->where('county_id', $countyId))
            ->when($filters->sectorId, fn (Builder $query, string $sectorId) => $query->whereHas('indicator', fn (Builder $indicator) => $indicator->where('sector_id', $sectorId)))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('verification_status', $status))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('source_reference', 'ilike', '%'.$filters->search.'%')
                    ->orWhereHas('indicator', fn (Builder $indicator) => $indicator->where('name', 'ilike', '%'.$filters->search.'%')->orWhere('code', 'ilike', '%'.$filters->search.'%'))
                    ->orWhereHas('county', fn (Builder $county) => $county->where('name', 'ilike', '%'.$filters->search.'%'));
            }))
            ->latest('period_end')
            ->paginate($filters->perPage)
            ->withQueryString();

        return $this->workspace('Monitoring and evaluation', 'Results-chain targets, actuals, provenance, data-quality review, and independent verification.', ['Indicator', 'Indicator catalogue', 'Indicator catalogue checksum', 'County', 'Programme', 'Period', 'Measure', 'Dimension', 'Value', 'Source', 'Verification'], $observations->through(fn (IndicatorObservation $observation) => [
            'id' => $observation->id,
            'status' => $observation->verification_status,
            'meta' => ['countyId' => $observation->county_id],
            'cells' => [
                "{$observation->indicator->code} · {$observation->indicator->name}",
                $observation->indicator->reference_data_release_id ? $observation->indicator->referenceDataRelease->version : 'Legacy unpinned',
                $observation->indicator->reference_data_release_id ? $observation->indicator->referenceDataRelease->checksum : 'Legacy unpinned',
                $observation->county->identityCell(),
                $observation->programme->name,
                $observation->period_start->toDateString().' – '.$observation->period_end->toDateString(),
                $observation->measure_type,
                $observation->disaggregation ? collect($observation->disaggregation)->map(fn (mixed $value, int|string $key): string => "{$key}: ".(is_scalar($value) ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR)))->implode(', ') : 'Total',
                $observation->numeric_value ?? $observation->narrative_value ?? '—',
                $observation->source_reference,
                $observation->verification_status,
            ],
        ]));
    }

    /** @return array<string, mixed> */
    public function programmeEvaluations(User $user, WorkspaceFilters $filters): array
    {
        $evaluations = ProgrammeEvaluation::query()
            ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('lead_evaluator_id', $user->id)
                ->orWhereIn('county_id', $this->countyScope->query($user)->select('id'))))
            ->with([
                'programme:id,code,name,sector_id',
                'county:id,name,code,logo_path,official_website_url,logo_source_url,logo_source_authority,logo_source_checksum_sha256,logo_checksum_sha256,logo_verified_at',
                'referenceDataRelease:id,version,effective_from,checksum',
            ])
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('period_end', '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('period_start', '<=', $to))
            ->when($filters->countyId, fn (Builder $query, string $countyId) => $query->where('county_id', $countyId))
            ->when($filters->sectorId, fn (Builder $query, string $sectorId) => $query->whereHas('programme', fn (Builder $programme) => $programme->where('sector_id', $sectorId)))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('code', 'ilike', '%'.$filters->search.'%')
                    ->orWhere('title', 'ilike', '%'.$filters->search.'%')
                    ->orWhere('evaluation_type', 'ilike', '%'.$filters->search.'%')
                    ->orWhereHas('programme', fn (Builder $programme) => $programme->where('name', 'ilike', '%'.$filters->search.'%'))
                    ->orWhereHas('county', fn (Builder $county) => $county->where('name', 'ilike', '%'.$filters->search.'%'));
            }))
            ->latest()
            ->paginate($filters->perPage)
            ->withQueryString();

        return $this->workspace('Programme evaluation register', 'Governed evaluation scope, lifecycle and reproducible reference-data lineage.', ['Code', 'Evaluation', 'Type', 'County', 'Programme', 'Period', 'Reference release', 'Reference checksum', 'Status'], $evaluations->through(function (ProgrammeEvaluation $evaluation): array {
            $referenceDataRelease = $evaluation->referenceDataRelease;
            $referenceReleaseLabel = 'Legacy unpinned';
            $referenceChecksum = '—';
            if ($referenceDataRelease !== null) {
                $referenceReleaseLabel = "v{$referenceDataRelease->version} · {$referenceDataRelease->effective_from?->toDateString()}";
                $referenceChecksum = $referenceDataRelease->checksum;
            }

            return [
                'id' => $evaluation->id,
                'status' => $evaluation->status,
                'meta' => ['countyId' => $evaluation->county_id, 'programmeId' => $evaluation->programme_id],
                'cells' => [
                    $evaluation->code,
                    $evaluation->title,
                    $evaluation->evaluation_type,
                    $evaluation->county?->identityCell() ?? 'National',
                    $evaluation->programme ? "{$evaluation->programme->code} · {$evaluation->programme->name}" : 'Cross-programme',
                    $evaluation->period_start->toDateString().' – '.$evaluation->period_end->toDateString(),
                    $referenceReleaseLabel,
                    $referenceChecksum,
                    $evaluation->status,
                ],
            ];
        }));
    }

    /** @return array<string, mixed> */
    public function projects(User $user, WorkspaceFilters $filters): array
    {
        $projects = DevolutionProject::query()
            ->whereHas('counties', fn (Builder $query) => $query->whereIn('counties.id', $this->countyScope->query($user)->select('id')))
            ->with(['leadCounty:id,name,code,logo_path', 'sector:id,name', 'programme:id,name', 'referenceDataRelease:id,version,checksum,effective_from,status', 'milestones', 'resources.allocations', 'approvedScheduleBaselines' => fn ($query) => $query->latest('version')])
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('planned_end_date', '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('planned_start_date', '<=', $to))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('title', 'ilike', '%'.$filters->search.'%')->orWhere('code', 'ilike', '%'.$filters->search.'%')->orWhere('status', mb_strtolower($filters->search))))
            ->latest()->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Project delivery portfolio', 'County, sector and national investment delivery with lifecycle, physical, financial, schedule, resource-control and reference-data lineage.', ['Code', 'Project', 'Lead county', 'Sector', 'Programme', 'Reference release', 'Reference checksum', 'Stage', 'Progress (%)', 'Budget', 'Expenditure', 'Baseline', 'Critical path', 'Forecast finish', 'Forecast variance (days)', 'Resources', 'Resource plan cost', 'PV', 'EV', 'AC', 'CPI', 'SPI', 'EAC', 'VAC', 'Status'], $projects->through(function (DevolutionProject $project): array {
            $baseline = $project->approvedScheduleBaselines->first();
            $schedule = $project->milestones->isEmpty() ? null : $this->projectScheduleAnalyzer->variance($project->milestones, $baseline, now()->toImmutable());
            $earnedValue = $this->projectEarnedValueAnalyzer->analyze($project, $baseline, now()->toImmutable());
            $referenceRelease = $project->referenceDataRelease;

            return [
                'id' => $project->id,
                'status' => $project->status,
                'meta' => ['countyId' => $project->lead_county_id],
                'cells' => [$project->code, $project->title, $project->leadCounty->identityCell(), $project->sector->name, $project->programme_id ? $project->programme->name : '—', $referenceRelease ? "v{$referenceRelease->version} · {$referenceRelease->effective_from?->toDateString()}" : 'Legacy unpinned', $referenceRelease ? $referenceRelease->checksum : '—', $project->lifecycle_stage, $project->physical_progress, $project->approved_budget, $project->actual_expenditure, $baseline ? "v{$baseline->version}" : 'Not approved', $schedule ? implode(' → ', $schedule['critical_path_codes']) : '—', $schedule['forecast_finish'] ?? '—', $schedule['forecast_variance_days'] ?? '—', $project->resources->count(), $project->resources->sum(fn ($resource) => $resource->allocations->sum('planned_cost')), $earnedValue['planned_value'] ?? '—', $earnedValue['earned_value'], $earnedValue['actual_cost'], $earnedValue['cost_performance_index'] ?? '—', $earnedValue['schedule_performance_index'] ?? '—', $earnedValue['estimate_at_completion'] ?? '—', $earnedValue['variance_at_completion'] ?? '—', $project->status],
            ];
        }));
    }

    /** @return array<string, mixed> */
    public function partners(User $user, WorkspaceFilters $filters): array
    {
        $partners = PartnerProfile::query()
            ->whereHas('counties', fn (Builder $query) => $query->whereIn('counties.id', $this->countyScope->query($user)->select('id')))
            ->with(['organization:id,name', 'counties:id,name,code,logo_path,official_website_url,logo_source_url,logo_source_authority,logo_source_checksum_sha256,logo_checksum_sha256,logo_verified_at', 'sectors:id,name', 'referenceDataRelease:id,version,checksum,effective_from,status'])
            ->withCount(['agreements', 'contributions', 'collaborationPlans', 'contributions as reconciled_contributions_count' => fn (Builder $query) => $query->whereHas('reconciliations'), 'operationalAlerts as open_operational_alerts_count' => fn (Builder $query) => $query->where('status', 'open')])
            ->withSum('contributions as committed_total', 'committed_amount')
            ->withSum('contributions as disbursed_total', 'disbursed_amount')
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereHas('agreements', fn (Builder $agreements) => $agreements->whereDate('ends_on', '>=', $from)))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereHas('agreements', fn (Builder $agreements) => $agreements->whereDate('starts_on', '<=', $to)))
            ->when($filters->countyId, fn (Builder $query, string $countyId) => $query->whereHas('counties', fn (Builder $counties) => $counties->whereKey($countyId)))
            ->when($filters->sectorId, fn (Builder $query, string $sectorId) => $query->whereHas('sectors', fn (Builder $sectors) => $sectors->whereKey($sectorId)))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('partner_type', 'ilike', '%'.$filters->search.'%')
                    ->orWhere('strategic_priorities', 'ilike', '%'.$filters->search.'%')
                    ->orWhereHas('organization', fn (Builder $organization) => $organization->where('name', 'ilike', '%'.$filters->search.'%'))
                    ->orWhereHas('counties', fn (Builder $counties) => $counties->where('name', 'ilike', '%'.$filters->search.'%'))
                    ->orWhereHas('sectors', fn (Builder $sectors) => $sectors->where('name', 'ilike', '%'.$filters->search.'%'));
            }))
            ->latest()
            ->paginate($filters->perPage)
            ->withQueryString();

        return $this->workspace('Partner coordination', 'Who funds what, where and how, with agreement coverage, reconciliation, governed catalogue lineage and operational intelligence.', ['Partner', 'Type', 'Counties', 'Sectors', 'Reference release', 'Reference checksum', 'Agreements', 'Plans', 'Contributions', 'Reconciled', 'Open alerts', 'Committed', 'Disbursed', 'Status'], $partners->through(function (PartnerProfile $partner): array {
            $referenceRelease = $partner->referenceDataRelease;

            return [
                'id' => $partner->id,
                'status' => $partner->status,
                'meta' => ['countyId' => $partner->counties->first()?->id],
                'cells' => [
                    $partner->organization->name,
                    $partner->partner_type,
                    ['kind' => 'county-list', 'items' => $partner->counties->map->identityCell()->values()->all()],
                    $partner->sectors->pluck('name')->implode(', '),
                    $referenceRelease ? "v{$referenceRelease->version} · {$referenceRelease->effective_from?->toDateString()}" : 'Legacy unpinned',
                    $referenceRelease ? $referenceRelease->checksum : '—',
                    $partner->agreements_count,
                    $partner->collaboration_plans_count,
                    $partner->contributions_count,
                    $partner->reconciled_contributions_count,
                    $partner->open_operational_alerts_count,
                    $partner->committed_total ?? 0,
                    $partner->disbursed_total ?? 0,
                    $partner->status,
                ],
            ];
        }));
    }

    /** @return array<string, mixed> */
    public function partnerActions(User $user, WorkspaceFilters $filters): array
    {
        $actions = PartnerCollaborationAction::query()
            ->whereIn('county_id', $this->countyScope->query($user)->select('id'))
            ->with(['plan.partner.organization:id,name', 'county', 'accountableUser:id,name', 'accountableOrganization:id,name', 'referenceDataRelease:id,version,checksum,effective_from', 'updates.decision.verifier:id,name', 'updates.submitter:id,name'])
            ->withCount(['documentLinks as evidence_count' => fn (Builder $query) => $query->whereHas('document', fn (Builder $document) => $document->where('scan_status', 'clean')->where('record_status', 'active'))])
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('due_on', '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('due_on', '<=', $to))
            ->when($filters->countyId, fn (Builder $query, string $countyId) => $query->where('county_id', $countyId))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('code', 'ilike', '%'.$filters->search.'%')->orWhere('title', 'ilike', '%'.$filters->search.'%')->orWhereHas('plan', fn (Builder $plan) => $plan->where('reference', 'ilike', '%'.$filters->search.'%')->orWhere('title', 'ilike', '%'.$filters->search.'%'))->orWhereHas('accountableUser', fn (Builder $owner) => $owner->where('name', 'ilike', '%'.$filters->search.'%'))))
            ->orderBy('due_on')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Partner collaboration actions', 'County-scoped accountable actions, evidence, deadline controls, governed catalogue lineage and independent progress decisions.', ['Plan', 'Partner', 'Action', 'County', 'Owner', 'Owner organization', 'Reference release', 'Reference checksum', 'Due', 'Progress (%)', 'Status', 'Reminder sent', 'Escalated', 'Clean evidence', 'Latest submission', 'Latest decision'], $actions->through(function (PartnerCollaborationAction $action): array {
            $latest = $action->updates->first();

            return ['id' => $action->id, 'status' => $action->status, 'meta' => ['countyId' => $action->county_id], 'cells' => [$action->plan->reference.' · '.$action->plan->title, $action->plan->partner->organization->name, $action->code.' · '.$action->title, $action->county->identityCell(), $action->accountableUser->name, $action->accountable_organization_id ? $action->accountableOrganization->name : '—', $action->referenceDataRelease ? 'v'.$action->referenceDataRelease->version.' · '.$action->referenceDataRelease->effective_from?->toDateString() : 'Legacy · unpinned', $action->referenceDataRelease ? $action->referenceDataRelease->checksum : 'Legacy · unpinned', $action->due_on->toDateString(), (float) $action->progress_percentage, $action->status, $action->reminder_sent_at?->toIso8601String() ?? '—', $action->escalated_at?->toIso8601String() ?? '—', $action->evidence_count, $latest ? $latest->progress_percentage.'% · '.$latest->submitter->name : '—', $latest?->decision ? $latest->decision->decision.' · '.$latest->decision->verifier->name : 'Pending']];
        }));
    }

    /** @return array<string, mixed> */
    public function dswg(User $user, WorkspaceFilters $filters): array
    {
        $actions = DswgAction::query()
            ->whereHas('meeting.workingGroup.counties', fn (Builder $query) => $query->whereIn('counties.id', $this->countyScope->query($user)->select('id')))
            ->with([
                'meeting.workingGroup:id,code,name,reference_data_release_id',
                'meeting.workingGroup.referenceDataRelease:id,version,checksum,effective_from,status',
                'accountableUser:id,name',
                'accountableOrganization:id,name',
                'county:id,name,code,logo_path',
                'referenceDataRelease:id,version,checksum,effective_from',
                'documentLinks' => fn ($query) => $query->whereHas('document', fn (Builder $document) => $document->whereNull('deleted_at'))->with(['document:id,title,category,source_type,original_name,mime_type,scan_status,ocr_status,record_status']),
            ])
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('due_on', '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('due_on', '<=', $to))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters): void {
                $query->where('code', 'ilike', '%'.$filters->search.'%')
                    ->orWhere('title', 'ilike', '%'.$filters->search.'%')
                    ->orWhere('status', 'ilike', '%'.$filters->search.'%')
                    ->orWhereHas('meeting.workingGroup', fn (Builder $groups) => $groups->where('name', 'ilike', '%'.$filters->search.'%'))
                    ->orWhereHas('accountableUser', fn (Builder $users) => $users->where('name', 'ilike', '%'.$filters->search.'%'));
            }))
            ->orderByRaw("CASE WHEN status NOT IN ('completed') AND due_on < CURRENT_DATE THEN 0 ELSE 1 END")
            ->orderBy('due_on')
            ->paginate($filters->perPage)
            ->withQueryString();

        return $this->workspace('DSWG accountable actions', 'Decisions translated into named, deadline-bound actions with governed completion, working-group and decision-time reference-data lineage, and independent verification.', ['Action', 'Working group', 'Group reference release', 'Group reference checksum', 'Action reference release', 'Action reference checksum', 'Meeting', 'Accountable person', 'Organization', 'County', 'Due date', 'Progress', 'Priority', 'Status'], $actions->through(function (DswgAction $action): array {
            $groupReferenceRelease = $action->meeting->workingGroup->referenceDataRelease;
            $actionReferenceRelease = $action->referenceDataRelease;

            return [
                'id' => $action->id,
                'status' => $action->status,
                'meta' => [
                    'countyId' => $action->county_id,
                    'meetingId' => $action->dswg_meeting_id,
                    'accountableUserId' => $action->accountable_user_id,
                ],
                'documents' => $action->documentLinks->map(fn (DocumentLink $link): array => [
                    'id' => $link->document->id,
                    'purpose' => $link->purpose,
                    'title' => $link->document->title,
                    'category' => $link->document->category,
                    'sourceType' => $link->document->source_type,
                    'originalName' => $link->document->original_name,
                    'mimeType' => $link->document->mime_type,
                    'scanStatus' => $link->document->scan_status,
                    'ocrStatus' => $link->document->ocr_status,
                ])->values()->all(),
                'cells' => [
                    "{$action->code} · {$action->title}",
                    $action->meeting->workingGroup->name,
                    $groupReferenceRelease ? "v{$groupReferenceRelease->version} · {$groupReferenceRelease->effective_from?->toDateString()}" : 'Legacy · unpinned',
                    $groupReferenceRelease ? $groupReferenceRelease->checksum : 'Legacy · unpinned',
                    $actionReferenceRelease ? "v{$actionReferenceRelease->version} · {$actionReferenceRelease->effective_from?->toDateString()}" : 'Legacy · unpinned',
                    $actionReferenceRelease ? $actionReferenceRelease->checksum : 'Legacy · unpinned',
                    $action->meeting->reference,
                    $action->accountableUser->name,
                    $action->accountable_organization_id ? $action->accountableOrganization->name : '—',
                    $action->county_id ? $action->county->identityCell() : 'National / multi-county',
                    $action->due_on->toDateString(),
                    "{$action->progress_percentage}%",
                    $action->priority,
                    $action->status,
                ],
            ];
        }));
    }

    /** @return array<string, mixed> */
    public function igrResolutions(User $user, WorkspaceFilters $filters): array
    {
        $visibleGapIds = $this->igrGapScope->visibleTo($user)->select('id');
        $resolutions = IgrResolution::query()
            ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereHas('assignments', fn (Builder $assignments) => $assignments->where('user_id', $user->id)->orWhereIn('county_id', $this->countyScope->query($user)->select('id'))))
            ->with(['forum:id,name', 'referenceDataRelease:id,version,effective_from,checksum', 'meeting:id,reference,held_on,minutes_reference', 'assignments.user:id,name', 'assignments.organization:id,name', 'assignments.county:id,name', 'dependencies.prerequisiteResolution:id,resolution_number,status', 'gaps' => function (Relation $relation) use ($visibleGapIds): void {
                $relation->getQuery()
                    ->whereIn('igr_resolution_gaps.id', clone $visibleGapIds)
                    ->select(['id', 'igr_resolution_id', 'title', 'severity', 'status']);
            }, 'documentLinks.document:id,title,category,source_type,original_name,mime_type,scan_status,ocr_status'])
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('due_on', '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('due_on', '<=', $to))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('resolution_number', 'ilike', '%'.$filters->search.'%')->orWhere('title', 'ilike', '%'.$filters->search.'%')->orWhere('status', 'ilike', '%'.$filters->search.'%')->orWhere('implementation_gap', 'ilike', '%'.$filters->search.'%')))
            ->orderByRaw("CASE WHEN status != 'closed' AND due_on < CURRENT_DATE THEN 0 ELSE 1 END")
            ->orderBy('due_on')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('IGR resolutions', 'Forum meeting decisions, accountable parties, dependency chains, deadlines, implementation gaps, reference-data lineage and independently governed closure.', ['Resolution', 'Forum', 'Reference release', 'Reference checksum', 'Formal meeting', 'Responsible parties', 'Counties', 'Blocking prerequisites', 'Due date', 'Progress', 'Implementation gap', 'Priority', 'Status'], $resolutions->through(fn (IgrResolution $resolution) => [
            'id' => $resolution->id, 'status' => $resolution->status, 'meta' => ['countyId' => $resolution->assignments->firstWhere('county_id', '!=', null)?->county_id],
            'documents' => $resolution->documentLinks->map(fn (DocumentLink $link): array => ['id' => $link->document->id, 'purpose' => $link->purpose, 'title' => $link->document->title, 'category' => $link->document->category, 'sourceType' => $link->document->source_type, 'originalName' => $link->document->original_name, 'mimeType' => $link->document->mime_type, 'scanStatus' => $link->document->scan_status, 'ocrStatus' => $link->document->ocr_status])->values()->all(),
            'cells' => ["{$resolution->resolution_number} · {$resolution->title}", $resolution->forum->name, $resolution->referenceDataRelease ? "v{$resolution->referenceDataRelease->version} · {$resolution->referenceDataRelease->effective_from?->toDateString()}" : 'Legacy unpinned', $resolution->referenceDataRelease ? $resolution->referenceDataRelease->checksum : '—', $resolution->meeting ? "{$resolution->meeting->reference} · {$resolution->meeting->held_on->toDateString()} · {$resolution->meeting->minutes_reference}" : 'Historical record — meeting not linked', $resolution->assignments->map(fn ($assignment) => $assignment->user_id ? $assignment->user?->name : $assignment->organization?->name)->filter()->implode(', '), $resolution->assignments->pluck('county.name')->filter()->unique()->implode(', ') ?: 'National / multi-county', $resolution->dependencies->where('dependency_type', 'blocks')->map(fn (IgrResolutionDependency $dependency): string => "{$dependency->prerequisiteResolution->resolution_number} ({$dependency->prerequisiteResolution->status})")->implode(', ') ?: 'None', $resolution->due_on->toDateString(), "{$resolution->progress_percentage}%", $this->igrGapScope->activeHeadline($resolution) ?? 'No gap reported', $resolution->priority, $resolution->status],
        ]));
    }

    /** @return array<string, mixed> */
    public function igrResolutionGaps(User $user, WorkspaceFilters $filters): array
    {
        $gaps = $this->igrGapScope->visibleTo($user)
            ->with(['resolution:id,resolution_number,title', 'category:id,code,name', 'county:id,name,code,logo_path,logo_source_authority,logo_verified_at', 'owner:id,name', 'reporter:id,name', 'resolver:id,name', 'accepter:id,name'])
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('due_on', '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('due_on', '<=', $to))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters->countyId, fn (Builder $query, string $countyId) => $query->where('county_id', $countyId))
            ->when($filters->severity, fn (Builder $query, string $severity) => $query->where('severity', $severity))
            ->when($filters->gapCategoryId, fn (Builder $query, string $categoryId) => $query->where('igr_gap_category_id', $categoryId))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('title', 'ilike', '%'.$filters->search.'%')->orWhere('description', 'ilike', '%'.$filters->search.'%')->orWhere('impact', 'ilike', '%'.$filters->search.'%')->orWhere('severity', 'ilike', '%'.$filters->search.'%')->orWhereHas('category', fn (Builder $category) => $category->where('name', 'ilike', '%'.$filters->search.'%'))->orWhereHas('resolution', fn (Builder $resolution) => $resolution->where('resolution_number', 'ilike', '%'.$filters->search.'%'))))
            ->orderByRaw("CASE WHEN status NOT IN ('accepted') AND due_on < CURRENT_DATE THEN 0 ELSE 1 END")
            ->orderBy('due_on')
            ->paginate($filters->perPage)
            ->withQueryString();

        return $this->workspace('IGR implementation gaps', 'Governed gap categories, affected counties, accountable owners, due dates, severity, mitigation and independent acceptance.', ['Gap', 'Resolution', 'Category', 'Affected county', 'Owner', 'Severity', 'Due date', 'Mitigation', 'Resolution', 'Status'], $gaps->through(fn (IgrResolutionGap $gap): array => [
            'id' => $gap->id,
            'status' => $gap->status,
            'meta' => ['countyId' => $gap->county_id, 'resolutionId' => $gap->igr_resolution_id, 'ownerUserId' => $gap->owner_user_id],
            'cells' => [$gap->title, "{$gap->resolution->resolution_number} · {$gap->resolution->title}", "{$gap->category->code} · {$gap->category->name}", $gap->county?->identityCell() ?? 'National / multi-county', $gap->owner->name, $gap->severity, $gap->due_on->toDateString(), $gap->mitigation_plan ?? 'Not started', $gap->resolution_note ?? 'Pending', $gap->status],
        ]));
    }

    /** @return array<string, mixed> */
    public function citizenCases(User $user, WorkspaceFilters $filters): array
    {
        $cases = CitizenCase::query()->whereIn('county_id', $this->countyScope->query($user)->select('id'))
            ->when(! $user->can('citizen-cases:manage') && ! $user->can('citizen-cases:resolve'), fn (Builder $query) => $query->where('is_sensitive', false))
            ->with(['county:id,name,code,logo_path', 'sector:id,name', 'assignee:id,name', 'intakeReferenceDataRelease:id,version,effective_from,checksum', 'triageReferenceDataRelease:id,version,effective_from,checksum'])->withCount('messages')
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('reference', 'ilike', '%'.$filters->search.'%')->orWhere('subject', 'ilike', '%'.$filters->search.'%')->orWhere('category', 'ilike', '%'.$filters->search.'%')->orWhere('status', 'ilike', '%'.$filters->search.'%')))
            ->orderByRaw("CASE WHEN status NOT IN ('resolved','closed') AND resolution_due_at < NOW() THEN 0 ELSE 1 END")->latest()->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Citizen feedback and grievances', 'Privacy-aware, SLA-governed citizen cases across authorized counties with reproducible intake and triage catalogue lineage.', ['Reference', 'Type', 'Category', 'County', 'Sector', 'Intake release', 'Intake checksum', 'Triage release', 'Triage checksum', 'Channel', 'Priority', 'Assignee', 'Resolution due', 'Messages', 'Status'], $cases->through(fn (CitizenCase $case) => ['id' => $case->id, 'status' => $case->status, 'meta' => ['countyId' => $case->county_id, 'assignedTo' => $case->assigned_to], 'cells' => [$case->reference.' · '.$case->subject, $case->case_type, $case->category, $case->county->identityCell(), $case->sector_id ? $case->sector?->name : '—', $case->intakeReferenceDataRelease ? "v{$case->intakeReferenceDataRelease->version} · {$case->intakeReferenceDataRelease->effective_from?->toDateString()}" : 'Legacy unpinned', $case->intakeReferenceDataRelease ? $case->intakeReferenceDataRelease->checksum : '—', $case->triageReferenceDataRelease ? "v{$case->triageReferenceDataRelease->version} · {$case->triageReferenceDataRelease->effective_from?->toDateString()}" : 'Not yet triaged / legacy', $case->triageReferenceDataRelease ? $case->triageReferenceDataRelease->checksum : '—', $case->channel, $case->priority, $case->assigned_to ? $case->assignee?->name : 'Unassigned', $case->resolution_due_at->toDateTimeString(), $case->messages_count, $case->status]]));
    }

    /** @return array<string, mixed> */
    public function travelClearance(User $user, WorkspaceFilters $filters): array
    {
        $requests = $this->applyFilters(TravelRequest::query()
            ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(function (Builder $query) use ($user): void {
                $query->where('requester_id', $user->id)->orWhereIn('county_id', $this->countyScope->query($user)->select('id'));
            }))
            ->with(['requester:id,name', 'county:id,name,code,logo_path', 'sector:id,name', 'referenceDataRelease:id,version,effective_from,checksum']), $filters, ['reference', 'purpose', 'destination_city', 'status'])
            ->latest()->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Travel clearance register', 'Official travel requests, itinerary estimates, management decisions and finance clearance.', ['Reference', 'Requester', 'County', 'Sector', 'Destination', 'Travel dates', 'Estimate', 'Finance reference', 'Reference release', 'Reference checksum', 'Status'], $requests->through(fn (TravelRequest $travelRequest): array => [
            'id' => $travelRequest->id,
            'status' => $travelRequest->status,
            'meta' => ['countyId' => $travelRequest->county_id],
            'cells' => [$travelRequest->reference, $travelRequest->requester->name, $travelRequest->county_id ? $travelRequest->county->identityCell() : 'National', $travelRequest->sector_id ? $travelRequest->sector->name : '—', "{$travelRequest->destination_city}, {$travelRequest->destination_country}", $travelRequest->departure_date->toDateString().' – '.$travelRequest->return_date->toDateString(), $travelRequest->currency.' '.$travelRequest->estimated_cost, $travelRequest->finance_commitment_reference ?? 'Pending', $travelRequest->referenceDataRelease ? "v{$travelRequest->referenceDataRelease->version} · {$travelRequest->referenceDataRelease->effective_from?->toDateString()}" : 'Legacy unpinned', $travelRequest->referenceDataRelease ? $travelRequest->referenceDataRelease->checksum : '—', $travelRequest->status],
        ]));
    }

    /** @return array<string, mixed> */
    public function departmentalPerformance(User $user, WorkspaceFilters $filters): array
    {
        $plans = $this->applyFilters(PerformancePlan::query()
            ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('employee_id', $user->id)->orWhere('supervisor_id', $user->id)))
            ->with(['cycle:id,code,name', 'employee:id,name', 'supervisor:id,name', 'organization:id,name', 'referenceDataRelease:id,version,effective_from,checksum'])->withCount(['goals', 'goalVersions', 'goalAmendments', 'goalAmendments as pending_goal_amendments_count' => fn (Builder $query) => $query->whereDoesntHave('decision')]), $filters, ['plan_type', 'job_title', 'hris_employee_reference', 'status'])
            ->latest()->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Departmental performance register', 'Weighted goals, governed amendment/version history, evidence-backed appraisal scores and capacity-development actions.', ['Employee', 'Cycle', 'Department', 'Reference release', 'Reference checksum', 'Job title', 'Supervisor', 'Goals', 'Goal versions', 'Amendments', 'Pending amendments', 'Self score', 'Final score', 'Capacity gaps', 'HRIS status', 'Status'], $plans->through(fn (PerformancePlan $plan): array => [
            'id' => $plan->id, 'status' => $plan->status,
            'cells' => [$plan->employee->name, $plan->cycle->name, $plan->organization_id ? $plan->organization->name : '—', $plan->referenceDataRelease ? "v{$plan->referenceDataRelease->version} · {$plan->referenceDataRelease->effective_from?->toDateString()}" : 'Legacy · unpinned', $plan->referenceDataRelease ? $plan->referenceDataRelease->checksum : 'Legacy · unpinned', $plan->job_title ?? '—', $plan->supervisor->name, $plan->goals_count, $plan->goal_versions_count, $plan->goal_amendments_count, $plan->pending_goal_amendments_count, $plan->self_score ?? '—', $plan->final_score ?? '—', $plan->capacity_gap_summary ?? 'Not yet assessed', $plan->integration_status, $plan->status],
        ]));
    }

    /** @return array<string, mixed> */
    public function learning(User $user, WorkspaceFilters $filters): array
    {
        $canManage = $user->can('learning:manage') || $user->can('learning:review');
        $countyIds = collect([$user->county_id])->merge($user->assignedCounties()->pluck('counties.id'))->filter()->unique();
        $courses = $this->applyFilters(LearningCourse::query()->when(! $canManage, fn (Builder $query) => $query->where('status', 'published'))->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('county_id')->orWhereIn('county_id', $countyIds)))->with(['county:id,name,code,logo_path', 'sector:id,name', 'owner:id,name', 'referenceDataRelease:id,version,checksum', 'latestOfflinePackage', 'latestReadyOfflinePackage'])->withCount(['modules', 'enrollments']), $filters, ['code', 'title', 'category', 'level', 'status'])->latest()->paginate($filters->perPage)->withQueryString();

        return $this->workspace('E-Learning catalogue', 'Governed multimedia courses, certification requirements, constrained-connectivity packages and institutional learning reach.', ['Code', 'Course', 'Category', 'Level', 'County scope', 'Sector', 'Reference release', 'Reference checksum', 'Delivery', 'Duration (minutes)', 'Modules', 'Enrolments', 'Latest package attempt', 'Latest ready package', 'Package checksum', 'Owner', 'Status'], $courses->through(fn (LearningCourse $course): array => ['id' => $course->id, 'status' => $course->status, 'meta' => ['countyId' => $course->county_id], 'cells' => [$course->code, $course->title, $course->category, $course->level, $course->county_id ? $course->county->identityCell() : 'National', $course->sector_id ? $course->sector->name : 'Cross-sector', $course->referenceDataRelease ? 'v'.$course->referenceDataRelease->version : 'Legacy · unpinned', $course->referenceDataRelease ? $course->referenceDataRelease->checksum : 'Legacy · unpinned', $course->delivery_mode, $course->estimated_minutes, $course->modules_count, $course->enrollments_count, $course->latestOfflinePackage ? 'v'.$course->latestOfflinePackage->package_version.' · '.$course->latestOfflinePackage->status : 'Not generated', $course->latestReadyOfflinePackage ? 'v'.$course->latestReadyOfflinePackage->package_version : 'None', $course->latestReadyOfflinePackage ? $course->latestReadyOfflinePackage->content_checksum : '—', $course->owner->name, $course->status]]));
    }

    /** @return array<string, mixed> */
    public function learningCohorts(User $user, WorkspaceFilters $filters): array
    {
        $cohorts = LearningCohort::query()
            ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereIn('county_id', $this->countyScope->query($user)->select('id')))
            ->with(['course:id,code,title', 'instructor:id,name', 'county:id,name,code,logo_path'])
            ->withCount('memberships')
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('starts_at', '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('starts_at', '<=', $to))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('code', 'ilike', '%'.$filters->search.'%')->orWhere('name', 'ilike', '%'.$filters->search.'%')->orWhereHas('course', fn (Builder $query) => $query->where('code', 'ilike', '%'.$filters->search.'%')->orWhere('title', 'ilike', '%'.$filters->search.'%'))))
            ->latest('starts_at')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Learning cohort register', 'Course-bound cohorts, accountable instructors, county scope, capacity, enrollment windows and delivery lifecycle.', ['Code', 'Cohort', 'Course', 'Instructor', 'County', 'Roster', 'Capacity', 'Enrollment opens', 'Enrollment closes', 'Starts', 'Ends', 'Status'], $cohorts->through(fn (LearningCohort $cohort): array => ['id' => $cohort->id, 'status' => $cohort->status, 'meta' => ['countyId' => $cohort->county_id], 'cells' => [$cohort->code, $cohort->name, $cohort->course->code.' · '.$cohort->course->title, $cohort->instructor->name, $cohort->county_id ? $cohort->county->identityCell() : 'National', $cohort->memberships_count, $cohort->capacity, $cohort->enrollment_opens_on->toDateString(), $cohort->enrollment_closes_on->toDateString(), $cohort->starts_at->toIso8601String(), $cohort->ends_at->toIso8601String(), $cohort->status]]));
    }

    /** @return array<string, mixed> */
    public function learningAttendance(User $user, WorkspaceFilters $filters): array
    {
        abort_unless($filters->classroomId !== null, 422, 'A classroom is required for the attendance register.');
        $classroom = VirtualClassroom::query()->with('course.county')->findOrFail($filters->classroomId);
        abort_unless($this->classroomAccess->canManageAttendance($user, $classroom), 403);

        $enrollments = LearningEnrollment::query()
            ->where('learning_course_id', $classroom->learning_course_id)
            ->with(['user:id,name,email', 'county:id,name,code,logo_path', 'classroomAttendances' => fn ($query) => $query->where('virtual_classroom_id', $classroom->id)->with('recorder:id,name')])
            ->when($filters->search !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $query) => $query->where('name', 'ilike', '%'.$filters->search.'%')->orWhere('email', 'ilike', '%'.$filters->search.'%')))
            ->when($filters->status === 'not_recorded', fn (Builder $query) => $query->whereDoesntHave('classroomAttendances', fn (Builder $query) => $query->where('virtual_classroom_id', $classroom->id)))
            ->when($filters->status !== null && $filters->status !== 'not_recorded', fn (Builder $query) => $query->whereHas('classroomAttendances', fn (Builder $query) => $query->where('virtual_classroom_id', $classroom->id)->where('attendance_status', $filters->status)))
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereHas('classroomAttendances', fn (Builder $query) => $query->where('virtual_classroom_id', $classroom->id)->whereDate('recorded_at', '>=', $from)))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereHas('classroomAttendances', fn (Builder $query) => $query->where('virtual_classroom_id', $classroom->id)->whereDate('recorded_at', '<=', $to)))
            ->latest('enrolled_at')
            ->paginate($filters->perPage)
            ->withQueryString();

        return $this->workspace('Virtual classroom attendance', 'Enrollment-bound attendance with duration classification, provider-event reconciliation and attributed amendments.', ['Learner', 'County', 'Enrolment', 'Joined', 'Left', 'Minutes', 'Source', 'Recorded by', 'Recorded at', 'Attendance'], $enrollments->through(fn (LearningEnrollment $enrollment): array => $this->learningAttendanceRow($enrollment)));
    }

    /** @return array<string, mixed> */
    public function learningOfflineSyncs(User $user, WorkspaceFilters $filters): array
    {
        $canGovern = $user->canAny([ProgrammePermission::ManageLearning->value, ProgrammePermission::ReviewLearning->value]);
        $syncs = LearningOfflineSync::query()
            ->when(! $canGovern, fn (Builder $query) => $query->where('submitted_by', $user->id))
            ->when($canGovern && ! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereIn('county_id', $this->countyScope->query($user)->select('id')))
            ->with(['offlinePackage:id,learning_course_id,package_version', 'offlinePackage.course:id,code,title', 'enrollment:id,user_id', 'enrollment.user:id,name', 'county:id,name,code,logo_path', 'reviewer:id,name'])
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('submitted_at', '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('submitted_at', '<=', $to))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters->search !== '', function (Builder $query) use ($filters): void {
                $query->where(fn (Builder $query) => $query->where('client_sync_id', 'ilike', '%'.$filters->search.'%')->orWhere('submitted_by_name', 'ilike', '%'.$filters->search.'%')->orWhereHas('offlinePackage.course', fn (Builder $query) => $query->where('code', 'ilike', '%'.$filters->search.'%')->orWhere('title', 'ilike', '%'.$filters->search.'%')));
            })
            ->latest('submitted_at')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Offline learning synchronization register', 'Checksum-bound learner submissions, independent reconciliation decisions, conflicts and applied progress evidence.', ['Client sync', 'Course', 'Package', 'Learner', 'County', 'Events', 'Submitted', 'Reviewer', 'Reviewed', 'Payload checksum', 'Decision checksum', 'Decision reason', 'Status'], $syncs->through(fn (LearningOfflineSync $sync): array => [
            'id' => $sync->id,
            'status' => $sync->status,
            'meta' => ['countyId' => $sync->county_id],
            'cells' => [$sync->client_sync_id, $sync->offlinePackage->course->code.' · '.$sync->offlinePackage->course->title, 'v'.$sync->offlinePackage->package_version, $sync->enrollment->user->name, $sync->county_id ? $sync->county->identityCell() : 'National', $sync->event_count, $sync->submitted_at->toIso8601String(), $sync->reviewed_by !== null ? $sync->reviewer->name : 'Pending', $sync->reviewed_at?->toIso8601String() ?? 'Pending', $sync->payload_checksum, $sync->decision_checksum ?? 'Pending', $sync->decision_reason ?? 'Pending', $sync->status],
        ]));
    }

    /** @return array{id: string, status: string, meta: array<string, string|null>, cells: list<mixed>} */
    private function learningAttendanceRow(LearningEnrollment $enrollment): array
    {
        $attendance = $enrollment->classroomAttendances->isEmpty() ? null : $enrollment->classroomAttendances->firstOrFail();
        $county = $enrollment->county_id ? $enrollment->county->identityCell() : 'National';
        if (! $attendance instanceof VirtualClassroomAttendance) {
            return ['id' => $enrollment->id, 'status' => 'not_recorded', 'meta' => ['userName' => $enrollment->user->name, 'joinedAt' => null, 'leftAt' => null, 'source' => 'manual', 'providerEventId' => null, 'notes' => null], 'cells' => [$enrollment->user->name, $county, $enrollment->status, '—', '—', 0, '—', '—', '—', 'not_recorded']];
        }

        return ['id' => $enrollment->id, 'status' => $attendance->attendance_status, 'meta' => ['userName' => $enrollment->user->name, 'joinedAt' => $attendance->joined_at?->format('Y-m-d\\TH:i'), 'leftAt' => $attendance->left_at?->format('Y-m-d\\TH:i'), 'source' => $attendance->source, 'providerEventId' => $attendance->provider_event_id, 'notes' => $attendance->notes], 'cells' => [$enrollment->user->name, $county, $enrollment->status, $attendance->joined_at?->toIso8601String() ?? '—', $attendance->left_at?->toIso8601String() ?? '—', $attendance->attended_minutes, $attendance->source, $attendance->recorder->name, $attendance->recorded_at->toIso8601String(), $attendance->attendance_status]];
    }

    /** @return array<string, mixed> */
    public function knowledge(User $user, WorkspaceFilters $filters): array
    {
        $canCurate = $user->can('knowledge:curate') || $user->can('knowledge:manage');
        $countyIds = $this->countyScope->query($user)->pluck('id');
        $items = $this->applyFilters(KnowledgeItem::query()
            ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $scope) => $scope->where(fn (Builder $published) => $published->where('status', 'published')->where(fn (Builder $county) => $county->whereNull('county_id')->orWhereIn('county_id', $countyIds)))->orWhere('author_id', $user->id)->when($canCurate, fn (Builder $assigned) => $assigned->orWhereIn('county_id', $countyIds))))
            ->when(! $canCurate, fn (Builder $query) => $query->where(fn (Builder $visible) => $visible->where('status', 'published')->orWhere('author_id', $user->id)))
            ->with(['county:id,name,code,logo_path', 'sector:id,name', 'author:id,name', 'referenceDataRelease:id,version,checksum', 'courses:id,code,title'])->withCount('discussions'), $filters, ['reference', 'title', 'summary', 'item_type', 'status'])
            ->latest()->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Knowledge repository', 'Curated devolution practices, research, learning links and community discussions.', ['Reference', 'Title', 'Type', 'County scope', 'Sector', 'Reference release', 'Reference checksum', 'Tags', 'E-learning links', 'Discussions', 'Author', 'Published', 'Status'], $items->through(fn (KnowledgeItem $item): array => [
            'id' => $item->id, 'status' => $item->status, 'meta' => ['countyId' => $item->county_id],
            'cells' => [$item->reference, $item->title, str_replace('_', ' ', $item->item_type), $item->county_id ? $item->county->identityCell() : 'National', $item->sector_id ? $item->sector->name : 'Cross-sector', $item->referenceDataRelease ? 'v'.$item->referenceDataRelease->version : 'Legacy · unpinned', $item->referenceDataRelease ? $item->referenceDataRelease->checksum : 'Legacy · unpinned', implode(', ', $item->tags ?? []), $item->courses->pluck('code')->implode(', ') ?: '—', $item->discussions_count, $item->author->name, $item->published_on?->toDateString() ?? 'Not published', $item->status],
        ]));
    }

    /** @return array<string, mixed> */
    public function knowledgeInnovations(User $user, WorkspaceFilters $filters): array
    {
        $innovations = $this->applyFilters(DevolutionInnovation::query()
            ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $scope) => $scope->where('submitted_by', $user->id)->orWhereIn('county_id', $this->countyScope->query($user)->select('id'))))
            ->when($filters->countyId, fn (Builder $query, string $countyId) => $query->where('county_id', $countyId))
            ->when($filters->sectorId, fn (Builder $query, string $sectorId) => $query->where('sector_id', $sectorId))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->with(['county:id,name,code,logo_path', 'sector:id,name', 'referenceDataRelease:id,version,checksum', 'submitter:id,name', 'fundingDecisions', 'panelReviews', 'experimentMilestones']), $filters, ['reference', 'title', 'problem_statement', 'proposed_solution', 'status', 'stage'])
            ->latest()->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Innovation governance register', 'Panel scoring, funding decisions, controlled experiments and independent scale-readiness evidence.', ['Reference', 'Innovation', 'County', 'Sector', 'Reference release', 'Reference checksum', 'Maturity', 'Stage', 'Status', 'Panel reviews', 'Panel average', 'Latest funding', 'Milestones', 'Verified milestones', 'Submitter', 'Created'], $innovations->through(function (DevolutionInnovation $innovation): array {
            $latestFunding = $innovation->fundingDecisions->sortByDesc('decision_version')->first();

            return ['id' => $innovation->id, 'status' => $innovation->status, 'meta' => ['countyId' => $innovation->county_id], 'cells' => [$innovation->reference, $innovation->title, $innovation->county_id ? $innovation->county->identityCell() : 'National', $innovation->sector_id ? $innovation->sector->name : 'Cross-sector', $innovation->referenceDataRelease ? 'v'.$innovation->referenceDataRelease->version : 'Legacy · unpinned', $innovation->referenceDataRelease ? $innovation->referenceDataRelease->checksum : 'Legacy · unpinned', $innovation->maturity_level, $innovation->stage, $innovation->status, $innovation->panelReviews->count(), $innovation->panelReviews->isEmpty() ? 'Not scored' : round((float) $innovation->panelReviews->avg('weighted_score'), 2), $latestFunding ? str_replace('_', ' ', $latestFunding->decision).' '.$latestFunding->currency.' '.number_format((float) $latestFunding->amount, 2) : 'Pending', $innovation->experimentMilestones->count(), $innovation->experimentMilestones->where('verification_decision', 'verified')->count(), $innovation->submitter->name, $innovation->created_at->toIso8601String()]];
        }));
    }

    /** @return array<string, mixed> */
    public function knowledgeModeration(User $user, WorkspaceFilters $filters): array
    {
        $canModerate = $user->can('knowledge:curate') || $user->can('knowledge:manage');
        $reports = $this->applyFilters(KnowledgeCommunityReport::query()
            ->when(! $canModerate, fn (Builder $query) => $query->where('reported_by', $user->id))
            ->when($canModerate && ! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereIn('county_id', $this->countyScope->query($user)->select('id')))
            ->with(['post:id,knowledge_discussion_id,author_id,moderation_status', 'post.author:id,name', 'post.discussion:id,title', 'county:id,name,code,logo_path', 'reporter:id,name', 'triager:id,name', 'decisionMaker:id,name', 'workflowInstance:id,due_at']), $filters, ['reference', 'category', 'severity', 'description', 'status'])
            ->latest()->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Knowledge community moderation queue', 'Scoped reports, SLA due dates, independent triage and final moderation decisions.', ['Reference', 'Discussion', 'Post author', 'County', 'Reporter', 'Category', 'Severity', 'Reported', 'SLA due', 'Triager', 'Decision maker', 'Resolution', 'Post action', 'Post status', 'Status'], $reports->through(fn (KnowledgeCommunityReport $report): array => [
            'id' => $report->id,
            'status' => $report->status,
            'meta' => ['countyId' => $report->county_id],
            'cells' => [$report->reference, $report->post->discussion->title, $report->post->author->name, $report->county_id ? $report->county->identityCell() : 'National', $report->reporter->name, str_replace('_', ' ', $report->category), $report->severity, $report->created_at->toIso8601String(), $report->workflowInstance->due_at?->toIso8601String() ?? 'Completed', $report->triaged_by ? $report->triager->name : 'Pending', $report->decided_by ? $report->decisionMaker->name : 'Pending', $report->resolution ?? '—', $report->post_action ?? 'Pending', $report->post->moderation_status, $report->status],
        ]));
    }

    /** @return array<string, mixed> */
    public function integrations(User $user, WorkspaceFilters $filters): array
    {
        $exchanges = $this->applyFilters(IntegrationExchange::query()->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereIn('county_id', $this->countyScope->query($user)->select('id')))->with(['contract:id,integration_system_id,name,version', 'contract.system:id,code,name', 'county:id,name,code,logo_path', 'partnerContributionSourceMatch:id,integration_exchange_id,partner_contribution_id,outcome,disbursement_variance,match_checksum,matched_at', 'attempts']), $filters, ['external_reference', 'idempotency_key', 'status', 'error_category'])->latest('accepted_at')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Integration exchange register', 'Payload-safe operational metadata, integrity checksums, delivery outcomes, replay identifiers and governed source-match evidence.', ['System', 'Contract', 'County', 'Correlation ID', 'External reference', 'Idempotency key', 'Payload checksum', 'Attempts', 'Latest attempt outcome', 'Next attempt', 'Latest attempt checksum', 'HTTP status', 'Accepted', 'Source match', 'Local contribution', 'Variance', 'Match checksum', 'Status'], $exchanges->through(function (IntegrationExchange $exchange): array {
            $match = $exchange->getRelation('partnerContributionSourceMatch');
            $latestAttempt = $exchange->attempts->last();
            $latestAttemptOutcome = $exchange->attempts->isEmpty() ? 'Pending' : $latestAttempt->outcome;
            $latestAttemptChecksum = $exchange->attempts->isEmpty() ? '—' : ($latestAttempt->response_checksum ?? '—');
            $sourceMatchCells = ['Pending', '—', '—', '—'];
            if ($match instanceof PartnerContributionSourceMatch) {
                $sourceMatchCells = [$match->outcome, $match->partner_contribution_id ?? '—', $match->disbursement_variance ?? '—', $match->match_checksum];
            }

            return ['id' => $exchange->id, 'status' => $exchange->status, 'meta' => ['countyId' => $exchange->county_id], 'cells' => [$exchange->contract->system->code, "{$exchange->contract->name} v{$exchange->contract->version}", $exchange->county_id ? $exchange->county->identityCell() : 'National', $exchange->correlation_id, $exchange->external_reference ?? '—', $exchange->idempotency_key, $exchange->payload_checksum, $exchange->attempt_count, $latestAttemptOutcome, $exchange->next_attempt_at?->toIso8601String() ?? '—', $latestAttemptChecksum, $exchange->http_status ?? '—', $exchange->accepted_at->toIso8601String(), ...$sourceMatchCells, $exchange->status]];
        }));
    }

    /** @return array<string, mixed> */
    public function integrationSystems(User $user, WorkspaceFilters $filters): array
    {
        $systems = $this->applyFilters(IntegrationSystem::query()
            ->with(['ownerOrganization:id,name', 'referenceDataRelease:id,version,effective_from,checksum', 'registrar:id,name'])
            ->withCount(['contracts', 'reconciliationRuns']), $filters, ['code', 'name', 'system_owner', 'status', 'environment'])
            ->orderBy('code')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Integration system catalogue', 'Registered authoritative systems, ownership, security boundary and reproducible master-data lineage.', ['Code', 'System', 'Authoritative owner', 'Owner organization', 'Reference release', 'Reference checksum', 'Environment', 'Transport', 'Authentication', 'Direction', 'Classification', 'Contracts', 'Reconciliation runs', 'Registrar', 'Status'], $systems->through(fn (IntegrationSystem $system): array => [
            'id' => $system->id,
            'status' => $system->status,
            'cells' => [$system->code, $system->name, $system->system_owner, $system->owner_organization_id ? $system->ownerOrganization->name : '—', $system->referenceDataRelease ? "v{$system->referenceDataRelease->version} · {$system->referenceDataRelease->effective_from?->toDateString()}" : 'Legacy · unpinned', $system->referenceDataRelease ? $system->referenceDataRelease->checksum : 'Legacy · unpinned', $system->environment, $system->transport, $system->auth_scheme, $system->direction, $system->data_classification, $system->contracts_count, $system->reconciliation_runs_count, $system->registered_by ? $system->registrar->name : '—', $system->status],
        ]));
    }

    /** @return array<string, mixed> */
    public function exchequer(User $user, WorkspaceFilters $filters): array
    {
        $requests = $this->applyFilters(ExchequerRequest::query()->whereIn('county_id', $this->countyScope->query($user)->select('id'))->when($filters->countyId, fn (Builder $query, string $countyId) => $query->where('county_id', $countyId))->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))->with(['county:id,name,code,logo_path', 'referenceDataRelease:id,version,checksum', 'grant:id,programme,financial_year', 'creator:id,name', 'events']), $filters, ['request_reference', 'tranche_reference', 'financial_year', 'current_stage', 'status'])->latest()->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Exchequer turnaround register', 'Treasury, OCoB and CBK stage telemetry with immutable source references and end-to-end elapsed time.', ['Reference', 'Tranche', 'County', 'Catalogue', 'Catalogue checksum', 'Programme', 'Financial year', 'Amount', 'Currency', 'Current stage', 'Status', 'Events', 'Elapsed hours', 'Stage due', 'Credited', 'Created by'], $requests->through(function (ExchequerRequest $request): array {
            $lastEvent = $request->events->last();

            return ['id' => $request->id, 'status' => $request->status, 'meta' => ['countyId' => $request->county_id], 'cells' => [$request->request_reference, $request->tranche_reference, $request->county->identityCell(), $request->reference_data_release_id ? "v{$request->referenceDataRelease->version}" : 'Legacy · unpinned', $request->reference_data_release_id ? $request->referenceDataRelease->checksum : 'Legacy · unpinned', $request->grant->programme, $request->financial_year, (float) $request->amount, $request->currency, str_replace('_', ' ', $request->current_stage), $request->status, $request->events->count(), $lastEvent ? round($lastEvent->elapsed_total_minutes / 60, 1) : 0, $request->stage_due_at?->toIso8601String() ?? 'Completed / not started', $request->credited_at?->toIso8601String() ?? 'Pending', $request->creator->name]];
        }));
    }

    /** @return array<string, mixed> */
    public function operations(User $user, WorkspaceFilters $filters): array
    {
        $backups = $this->applyFilters(OperationalBackup::query()->with(['initiator:id,name', 'restoreVerifier:id,name']), $filters, ['reference', 'database_name', 'status'])->latest('started_at')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Backup and recovery evidence', 'Checksummed PostgreSQL backup artifacts and isolated restore-verification outcomes.', ['Reference', 'Database', 'Format', 'Size (bytes)', 'SHA-256', 'Started', 'Completed', 'Restore verified', 'Restore duration (ms)', 'Tables', 'Initiator', 'Verifier', 'Status'], $backups->through(fn (OperationalBackup $backup): array => ['id' => $backup->id, 'status' => $backup->status, 'cells' => [$backup->reference, $backup->database_name, $backup->format, $backup->size_bytes ?? '—', $backup->sha256 ?? '—', $backup->started_at->toIso8601String(), $backup->completed_at?->toIso8601String() ?? '—', $backup->restore_verified_at?->toIso8601String() ?? 'Not verified', $backup->restore_duration_ms ?? '—', $backup->verified_table_count ?? '—', $backup->initiated_by ? $backup->initiator->name : 'Scheduler', $backup->restore_verified_by ? $backup->restoreVerifier->name : 'Scheduler', $backup->status]]));
    }

    /** @return array<string, mixed> */
    public function operationalAlerts(User $user, WorkspaceFilters $filters): array
    {
        $alerts = $this->applyFilters(
            OperationalAlert::query()->with(['acknowledger:id,name']),
            $filters,
            ['service', 'metric', 'severity', 'status'],
        )->latest('last_detected_at')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Operational alert evidence', 'Deduplicated provisional-threshold breaches, accountable acknowledgement and automatically retained recovery state.', ['Service', 'Metric', 'Severity', 'Latest value', 'Threshold', 'Unit', 'Occurrences', 'First detected', 'Last detected', 'Acknowledged by', 'Acknowledged', 'Acknowledgement note', 'Recovered', 'Evidence checksum', 'Status'], $alerts->through(fn (OperationalAlert $alert): array => [
            'id' => $alert->id,
            'status' => $alert->status,
            'cells' => [$alert->service, $alert->metric, $alert->severity, (float) $alert->latest_value, $alert->threshold === null ? 'Not configured' : (float) $alert->threshold, $alert->unit, $alert->occurrence_count, $alert->first_detected_at->toIso8601String(), $alert->last_detected_at->toIso8601String(), $alert->acknowledged_by ? $alert->acknowledger->name : 'Pending', $alert->acknowledged_at?->toIso8601String() ?? 'Pending', $alert->acknowledgement_note ?? 'Pending', $alert->recovered_at?->toIso8601String() ?? 'Open', $alert->evidence_checksum, $alert->status],
        ]));
    }

    /** @return array<string, mixed> */
    public function dataGovernance(User $user, WorkspaceFilters $filters): array
    {
        $activities = $this->applyFilters(ProcessingActivity::query()->with(['dataAsset:id,code,name,classification,contains_personal_data,contains_sensitive_personal_data', 'retentionSchedule:id,code,record_class', 'submitter:id,name', 'reviewer:id,name']), $filters, ['reference', 'name', 'purpose', 'status', 'lawful_basis', 'dpia_status'])->latest('submitted_at')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Data processing register', 'Purpose, lawful basis, ownership, DPIA, transfer, retention and independent-review evidence without data-subject identifiers.', ['Reference', 'Processing activity', 'Data asset', 'Classification', 'Personal data', 'Lawful basis', 'DPIA', 'Cross-border', 'Retention schedule', 'Submitter', 'Reviewer', 'Status'], $activities->through(fn (ProcessingActivity $activity): array => ['id' => $activity->id, 'status' => $activity->status, 'cells' => [$activity->reference, $activity->name, "{$activity->dataAsset->code} · {$activity->dataAsset->name}", $activity->dataAsset->classification, $activity->dataAsset->contains_sensitive_personal_data ? 'Sensitive personal data' : ($activity->dataAsset->contains_personal_data ? 'Personal data' : 'No personal data'), $activity->lawful_basis, $activity->dpia_status, $activity->cross_border_transfer ? implode(', ', $activity->transfer_countries ?? []) : 'No', $activity->retention_schedule_id ? "{$activity->retentionSchedule->code} · {$activity->retentionSchedule->record_class}" : 'Not linked', $activity->submitted_by ? $activity->submitter->name : '—', $activity->reviewed_by ? $activity->reviewer->name : 'Pending', $activity->status]]));
    }

    /** @return array<string, mixed> */
    public function privacyIncidents(User $user, WorkspaceFilters $filters): array
    {
        $incidents = $this->applyFilters(PrivacyIncident::query()->when($filters->countyId, fn (Builder $query, string $countyId) => $query->where('county_id', $countyId))->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))->with(['dataAsset:id,code,name,classification', 'county:id,name,code,logo_path', 'reporter:id,name', 'incidentLead:id,name', 'assessor:id,name', 'closer:id,name']), $filters, ['reference', 'title', 'breach_type', 'severity', 'status'])->latest('discovered_at')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Personal data breach register', 'Controlled incident metadata, independent risk decisions, statutory notification evidence and remediation closure.', ['Reference', 'Incident', 'County', 'Data asset', 'Role', 'Breach type', 'Subjects', 'Sensitive data', 'Discovered', 'Regulator due', 'Regulator notified', 'Real risk', 'Severity', 'Subject notice', 'Lead', 'Assessor', 'Closer', 'Status'], $incidents->through(fn (PrivacyIncident $incident): array => ['id' => $incident->id, 'status' => $incident->status, 'meta' => ['countyId' => $incident->county_id], 'cells' => [$incident->reference, $incident->title, $incident->county_id ? $incident->county->identityCell() : 'National', $incident->data_asset_id ? "{$incident->dataAsset->code} · {$incident->dataAsset->name}" : 'Not linked', $incident->controller_role, $incident->breach_type, $incident->estimated_data_subjects ?? 'Unknown', $incident->contains_sensitive_data ? 'Yes' : 'No', $incident->discovered_at->toIso8601String(), $incident->regulator_notification_due_at->toIso8601String(), $incident->regulator_notified_at?->toIso8601String() ?? 'Pending / not required', $incident->real_risk_of_harm, $incident->severity, $incident->subject_notification_decision, $incident->incidentLead->name, $incident->assessed_by ? $incident->assessor->name : 'Pending', $incident->closed_by ? $incident->closer->name : 'Pending', $incident->status]]));
    }

    /** @return array<string, mixed> */
    public function securityGovernance(User $user, WorkspaceFilters $filters): array
    {
        $items = $this->applyFilters(AccessReviewItem::query()->with(['campaign:id,reference,name,status,due_at,evidence_checksum', 'user:id,name', 'reviewer:id,name', 'reinstater:id,name', 'homeCounty:id,name,code,logo_path']), $filters, ['role_name', 'decision', 'rationale'])->latest()->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Access certification evidence', 'Point-in-time roles, permissions, county scope, strong-authentication posture, independent decisions and revocation outcomes.', ['Campaign', 'Identity', 'Role', 'Home county', 'Assigned counties', 'Permissions', 'MFA', 'Passkey', 'Last authenticated', 'Decision', 'Reviewer', 'Sessions revoked', 'Reinstated by', 'Evidence checksum'], $items->through(fn (AccessReviewItem $item): array => ['id' => $item->id, 'status' => $item->decision, 'cells' => [$item->campaign->reference, $item->user_id ? $item->user->name : 'Deleted identity', $item->role_name, $item->home_county_id ? $item->homeCounty->identityCell() : 'National / portfolio', ['kind' => 'county-list', 'items' => $item->assigned_county_snapshot], implode(', ', $item->permission_snapshot), $item->mfa_enabled ? 'Enabled' : 'Not enabled', $item->passkey_enabled ? 'Registered' : 'Not registered', $item->last_authenticated_at?->toIso8601String() ?? 'No active session evidence', $item->decision, $item->reviewed_by ? $item->reviewer->name : 'Pending', $item->sessions_revoked, $item->reinstated_by ? $item->reinstater->name : '—', $item->campaign->evidence_checksum ?? 'Open campaign']]));
    }

    /** @return array<string, mixed> */
    public function identityLifecycle(User $user, WorkspaceFilters $filters): array
    {
        $changes = $this->applyFilters(IdentityLifecycleRequest::query()->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))->with(['user:id,name,email', 'requester:id,name', 'decider:id,name', 'applier:id,name', 'proposedHomeCounty:id,name,code,logo_path']), $filters, ['source_system', 'source_event_id', 'source_evidence_reference', 'event_type', 'business_reason', 'status', 'application_error_code'])->latest()->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Identity lifecycle reconciliation', 'Source-referenced joiner, mover and leaver requests with access snapshots, independent decisions, scheduled application, controlled exceptions, revocation outcomes and immutable checksums.', ['Source system', 'Source event', 'Source evidence', 'Event type', 'Identity', 'Email', 'Effective', 'Current role', 'Current home county ID', 'Current assigned county IDs', 'Proposed role', 'Proposed home county', 'Proposed assigned county IDs', 'Requester', 'Decider', 'Decision rationale', 'Applied by', 'Application attempts', 'Last application attempt', 'Application exception', 'Sessions revoked', 'Source checksum', 'Evidence checksum', 'Status'], $changes->through(fn (IdentityLifecycleRequest $change): array => ['id' => $change->id, 'status' => $change->status, 'cells' => [$change->source_system, $change->source_event_id, $change->source_evidence_reference, $change->event_type, $change->user->name, $change->user->email, $change->effective_at->toIso8601String(), $change->current_access_snapshot['role'] ?? 'None', $change->current_access_snapshot['home_county_id'] ?? 'None', implode(', ', $change->current_access_snapshot['assigned_county_ids']), $change->proposed_role ?? 'Remove access', $change->proposed_home_county_id ? $change->proposedHomeCounty->identityCell() : 'None', implode(', ', $change->proposed_assigned_county_ids), $change->requester->name, $change->decided_by ? $change->decider->name : 'Pending', $change->decision_rationale ?? 'Pending', $change->applied_by ? $change->applier->name : 'Pending', $change->application_attempts, $change->last_application_attempt_at?->toIso8601String() ?? 'Not attempted', $change->application_error_code ?? 'None', $change->sessions_revoked, $change->source_checksum, $change->evidence_checksum ?? 'Pending', $change->status]]));
    }

    /** @return array<string, mixed> */
    public function securityIncidents(User $user, WorkspaceFilters $filters): array
    {
        $incidents = $this->applyFilters(SecurityIncident::query()->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))->with(['reporter:id,name', 'incidentLead:id,name', 'closer:id,name'])->withCount('events'), $filters, ['reference', 'title', 'record_type', 'playbook', 'severity', 'status', 'external_reference'])->latest('detected_at')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Security incident and exercise evidence', 'Live incidents and explicitly labelled exercises with response targets, separation of duties and immutable event evidence.', ['Reference', 'Record type', 'Playbook', 'Title', 'Affected services', 'Data exposure', 'Severity', 'Detected', 'Acknowledgement due', 'Acknowledged', 'Containment due', 'Contained', 'Eradicated', 'Recovered', 'Closed', 'Exercise outcome', 'Next exercise', 'Reporter', 'Incident lead', 'Closer', 'Events', 'External reference', 'Status'], $incidents->through(fn (SecurityIncident $incident): array => ['id' => $incident->id, 'status' => $incident->status, 'cells' => [$incident->reference, $incident->record_type, $incident->playbook, $incident->title, implode(', ', $incident->affected_services), $incident->data_exposure, $incident->severity, $incident->detected_at->toIso8601String(), $incident->acknowledgement_due_at->toIso8601String(), $incident->acknowledged_at?->toIso8601String() ?? 'Pending', $incident->containment_due_at->toIso8601String(), $incident->contained_at?->toIso8601String() ?? 'Pending', $incident->eradicated_at?->toIso8601String() ?? 'Pending', $incident->recovered_at?->toIso8601String() ?? 'Pending', $incident->closed_at?->toIso8601String() ?? 'Open', $incident->exercise_outcome, $incident->next_exercise_due_at?->toIso8601String() ?? 'Not scheduled', $incident->reporter->name, $incident->incidentLead->name, $incident->closed_by ? $incident->closer->name : 'Pending', $incident->events_count, $incident->external_reference ?? '—', $incident->status]]));
    }

    /** @return array<string, mixed> */
    public function supportTickets(User $user, WorkspaceFilters $filters): array
    {
        $tickets = $this->applyFilters(
            $this->supportTicketAccess->query($user)
                ->when($filters->countyId, fn (Builder $query, string $countyId) => $query->where('county_id', $countyId))
                ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))
                ->with(['referenceDataRelease:id,version,checksum', 'serviceDeskPolicy.businessCalendar:id,code,version,checksum', 'county', 'requester:id,name', 'assignee:id,name', 'resolver:id,name', 'closer:id,name'])
                ->withCount(['activities', 'documentLinks']),
            $filters,
            ['reference', 'subject', 'category', 'priority', 'channel', 'status'],
        )->latest('requested_at')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Service-desk support register', 'County-scoped requests, SLA response evidence, assignment, independent resolution acceptance and retained support history.', ['Reference', 'Subject', 'County', 'Requester', 'Assignee', 'Category', 'Priority', 'Channel', 'Requested', 'First response due', 'First response', 'Resolution due', 'Resolved', 'Resolver', 'Closed', 'Closer', 'Catalogue', 'Catalogue checksum', 'Service policy', 'Policy authority', 'Policy checksum', 'Business calendar', 'Calendar checksum', 'Activities', 'Documents', 'Status'], $tickets->through(fn (SupportTicket $ticket): array => [
            'id' => $ticket->id,
            'status' => $ticket->status,
            'meta' => ['countyId' => $ticket->county_id],
            'cells' => [
                $ticket->reference,
                $ticket->subject,
                $ticket->county_id ? $ticket->county->identityCell() : 'National',
                $ticket->requester->name,
                $ticket->assigned_to ? $ticket->assignee->name : 'Unassigned',
                str($ticket->category)->headline()->toString(),
                str($ticket->priority)->headline()->toString(),
                str($ticket->channel)->headline()->toString(),
                $ticket->requested_at->toIso8601String(),
                $ticket->first_response_due_at->toIso8601String(),
                $ticket->first_responded_at?->toIso8601String() ?? 'Pending',
                $ticket->resolution_due_at->toIso8601String(),
                $ticket->resolved_at?->toIso8601String() ?? 'Pending',
                $ticket->resolved_by ? $ticket->resolver->name : 'Pending',
                $ticket->closed_at?->toIso8601String() ?? 'Open',
                $ticket->closed_by ? $ticket->closer->name : 'Pending',
                "v{$ticket->referenceDataRelease->version}",
                $ticket->referenceDataRelease->checksum,
                $ticket->serviceDeskPolicy ? "{$ticket->serviceDeskPolicy->code} v{$ticket->serviceDeskPolicy->version}" : 'Legacy config-derived',
                $ticket->serviceDeskPolicy ? $ticket->serviceDeskPolicy->authority_status : 'Legacy ungoverned',
                $ticket->service_desk_policy_checksum ?? 'Legacy unpinned',
                $ticket->serviceDeskPolicy ? "{$ticket->serviceDeskPolicy->businessCalendar->code} v{$ticket->serviceDeskPolicy->businessCalendar->version}" : 'Legacy unpinned',
                $ticket->serviceDeskPolicy?->businessCalendar->checksum ?? 'Legacy unpinned',
                $ticket->activities_count,
                $ticket->document_links_count,
                $ticket->status,
            ],
        ]));
    }

    /** @return array<string, mixed> */
    public function serviceDeskPolicies(User $user, WorkspaceFilters $filters): array
    {
        abort_unless($user->can(ProgrammePermission::ConfigureSupportDesk->value), 403);
        $policies = $this->applyFilters(
            ServiceDeskPolicy::query()
                ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))
                ->with(['businessCalendar:id,code,version,timezone,checksum', 'creator:id,name', 'publisher:id,name', 'rosterMembers.user:id,name', 'rosterMembers.county']),
            $filters,
            ['code', 'name', 'description', 'authority_status', 'status'],
        )->latest('version')->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Governed service-desk policy register', 'Effective-dated service catalogue, targets, escalation matrix, roster and independently published checksum evidence.', ['Policy', 'Version', 'Authority status', 'Approval reference', 'Business calendar', 'Calendar checksum', 'Categories', 'Channels', 'Priority targets', 'Escalation matrix', 'Roster', 'Effective from', 'Effective to', 'Author', 'Publisher', 'Published', 'Checksum', 'Status'], $policies->through(fn (ServiceDeskPolicy $policy): array => [
            'id' => $policy->id,
            'status' => $policy->status,
            'cells' => [
                $policy->code.' · '.$policy->name,
                $policy->version,
                $policy->authority_status,
                $policy->approval_reference ?? 'Provisional / not approved',
                "{$policy->businessCalendar->code} v{$policy->businessCalendar->version} · {$policy->businessCalendar->timezone}",
                $policy->businessCalendar->checksum,
                collect($policy->categories)->map(fn (array $category): string => (is_string($category['code'] ?? null) ? $category['code'] : 'unknown').' · '.(is_string($category['name'] ?? null) ? $category['name'] : 'Unnamed'))->implode('; '),
                implode(', ', $policy->channels),
                collect($policy->priority_targets)->map(fn (array $target, string $priority): string => "{$priority}: response ".(is_numeric($target['first_response'] ?? null) ? $target['first_response'] : 'invalid').'h, resolution '.(is_numeric($target['resolution'] ?? null) ? $target['resolution'] : 'invalid').'h, reminder '.(is_numeric($target['reminder'] ?? null) ? $target['reminder'] : 'invalid').'h')->implode('; '),
                collect($policy->escalation_rules)->map(fn (array $rule): string => (is_string($rule['priority'] ?? null) ? $rule['priority'] : 'invalid').' '.(is_string($rule['stage'] ?? null) ? $rule['stage'] : 'invalid').' → tier '.(is_int($rule['tier'] ?? null) ? $rule['tier'] : 'invalid'))->implode('; '),
                $policy->rosterMembers->map(fn ($member): string => "Tier {$member->tier} {$member->duty_role}: {$member->user->name}".($member->county_id ? " ({$member->county->name})" : ' (National)'))->implode('; '),
                $policy->effective_from->toIso8601String(),
                $policy->effective_to?->toIso8601String() ?? 'Open ended',
                $policy->creator->name,
                $policy->published_by ? $policy->publisher->name : 'Pending',
                $policy->published_at?->toIso8601String() ?? 'Draft',
                $policy->checksum ?? 'Draft',
                $policy->status,
            ],
        ]));
    }

    /** @return array<string, mixed> */
    public function accessDelegations(User $user, WorkspaceFilters $filters): array
    {
        $delegations = $this->applyFilters(AccessDelegation::query()->when($filters->countyId, fn (Builder $query, string $countyId) => $query->whereJsonContains('county_scope_snapshot', [['id' => $countyId]]))->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))->with(['referenceDataRelease:id,version,checksum', 'requester:id,name', 'beneficiary:id,name,email', 'approver:id,name', 'revoker:id,name', 'reviewer:id,name']), $filters, ['reference', 'access_type', 'scope_type', 'business_justification', 'incident_reference', 'status'])->latest()->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Temporary and emergency access evidence', 'Time-bound least-privilege grants, county scope, independent approval, revocation and emergency post-use review evidence.', ['Reference', 'Type', 'Beneficiary', 'Email', 'Permissions', 'County scope', 'Catalogue', 'Catalogue checksum', 'Starts', 'Expires', 'Requester', 'Approver', 'Decision rationale', 'Revoker', 'Post-use reviewer', 'Post-use outcome', 'Approval checksum', 'Status'], $delegations->through(fn (AccessDelegation $delegation): array => ['id' => $delegation->id, 'status' => $delegation->status, 'cells' => [$delegation->reference, $delegation->access_type, $delegation->beneficiary->name, $delegation->beneficiary->email, implode(', ', $delegation->permission_scope), ['kind' => 'county-list', 'items' => $delegation->county_scope_snapshot], $delegation->reference_data_release_id ? "v{$delegation->referenceDataRelease->version}" : 'Legacy · unpinned', $delegation->reference_data_release_id ? $delegation->referenceDataRelease->checksum : 'Legacy · unpinned', $delegation->starts_at->toIso8601String(), $delegation->expires_at->toIso8601String(), $delegation->requester->name, $delegation->approved_by ? $delegation->approver->name : 'Pending', $delegation->decision_rationale ?? 'Pending', $delegation->revoked_by ? $delegation->revoker->name : '—', $delegation->reviewed_by ? $delegation->reviewer->name : '—', $delegation->post_use_outcome ?? '—', $delegation->approval_checksum ?? 'Pending', $delegation->status]]));
    }

    /** @return array<string, mixed> */
    public function businessCalendars(User $user, WorkspaceFilters $filters): array
    {
        $calendars = $this->applyFilters(BusinessCalendar::query()->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))->with(['holidays:id,business_calendar_id,holiday_date,name,category,source_reference', 'creator:id,name', 'publisher:id,name']), $filters, ['code', 'name', 'timezone', 'status'])->latest()->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Business calendar evidence', 'Versioned government working hours, source-referenced exceptions and checksum-bound publication evidence used by workflow SLAs.', ['Calendar', 'Version', 'Status', 'Timezone', 'Working days', 'Office hours', 'Effective from', 'Effective to', 'Exceptions', 'Authority sources', 'Author', 'Publisher', 'Published', 'Checksum'], $calendars->through(fn (BusinessCalendar $calendar): array => ['id' => $calendar->id, 'status' => $calendar->status, 'cells' => [$calendar->code.' · '.$calendar->name, $calendar->version, $calendar->status, $calendar->timezone, implode(', ', $calendar->working_days), mb_substr($calendar->workday_starts_at, 0, 5).'–'.mb_substr($calendar->workday_ends_at, 0, 5), $calendar->effective_from->toDateString(), $calendar->effective_to?->toDateString() ?? 'Open-ended', $calendar->holidays->map(fn ($holiday): string => $holiday->holiday_date->toDateString().' · '.$holiday->name.' ('.$holiday->category.')')->implode('; ') ?: 'None recorded', $calendar->holidays->pluck('source_reference')->implode('; ') ?: 'None recorded', $calendar->creator->name, $calendar->published_by ? $calendar->publisher->name : 'Pending', $calendar->published_at?->toIso8601String() ?? 'Draft', $calendar->checksum ?? 'Draft']]));
    }

    /** @return array<string, mixed> */
    public function changeReadiness(User $user, WorkspaceFilters $filters): array
    {
        $participants = $this->applyFilters(TrainingParticipant::query()->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereIn('county_id', $this->countyScope->query($user)->select('id')))->with(['cohort:id,rollout_wave_id,reference_data_release_id,code,name,audience_role,minimum_attendance_hours,passing_score', 'cohort.referenceDataRelease:id,version,checksum', 'cohort.wave:id,reference_data_release_id,code,name,status', 'cohort.wave.referenceDataRelease:id,version,checksum', 'user:id,name', 'county:id,name,code,logo_path', 'assessments.assessor:id,name']), $filters, ['participant_reference', 'role_title', 'attendance_status', 'competency_status'])->latest()->paginate($filters->perPage)->withQueryString();

        return $this->workspace('Training and rollout evidence', 'Distinguishes planned capacity, registered participants, verified attendance, competency outcomes and rollout approval.', ['Wave', 'Wave catalogue', 'Wave catalogue checksum', 'Cohort', 'Cohort catalogue', 'Cohort catalogue checksum', 'Participant reference', 'Participant', 'County', 'Role', 'Attendance hours', 'Attendance status', 'Competency', 'Latest score', 'Assessor', 'Completion'], $participants->through(fn (TrainingParticipant $participant): array => $this->changeReadinessRow($participant)));
    }

    /** @return array<string, mixed> */
    public function uatCampaigns(User $user, WorkspaceFilters $filters): array
    {
        $campaigns = UatCampaign::query()
            ->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereHas('counties', fn (Builder $query) => $query->whereIn('counties.id', $this->countyScope->query($user)->select('id'))))
            ->when($filters->countyId, fn (Builder $query, string $countyId) => $query->whereHas('counties', fn (Builder $query) => $query->whereKey($countyId)))
            ->when($filters->status, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate('ends_on', '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate('starts_on', '<=', $to))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query->where('code', 'ilike', '%'.$filters->search.'%')->orWhere('name', 'ilike', '%'.$filters->search.'%')->orWhere('objective', 'ilike', '%'.$filters->search.'%')))
            ->with(['referenceDataRelease:id,version,effective_from,checksum', 'creator:id,name', 'counties:id,name,code,logo_path', 'scenarios.executions.findings', 'acceptances.submitter:id,name', 'acceptances.decisionMaker:id,name'])
            ->latest('starts_on')
            ->paginate($filters->perPage)
            ->withQueryString();

        return $this->workspace('Pilot UAT and formal acceptance evidence', 'Representative county and role coverage, immutable scenario executions, independently verified findings and checksum-bound acceptance history.', ['Campaign', 'Objective', 'Environment', 'Period', 'Counties', 'Minimum counties', 'Required roles', 'Acceptance criteria', 'Catalogue', 'Catalogue checksum', 'Scenarios', 'Executions', 'Passing executions', 'Open findings', 'Latest acceptance', 'Submitter', 'Decision maker', 'Acceptance checksum', 'Created by', 'Status'], $campaigns->through(function (UatCampaign $campaign): array {
            $executions = $campaign->scenarios->flatMap->executions;
            $findings = $executions->flatMap->findings;
            $acceptance = $campaign->acceptances->sortByDesc('submitted_at')->first();

            return [
                'id' => $campaign->id,
                'status' => $campaign->status,
                'cells' => [
                    $campaign->code.' · '.$campaign->name,
                    $campaign->objective,
                    $campaign->environment,
                    $campaign->starts_on->toDateString().' – '.$campaign->ends_on->toDateString(),
                    ['kind' => 'county-list', 'items' => $campaign->counties->map->identityCell()->values()->all()],
                    $campaign->minimum_counties,
                    implode(', ', $campaign->required_roles),
                    implode('; ', $campaign->acceptance_criteria),
                    'v'.$campaign->referenceDataRelease->version.' · '.$campaign->referenceDataRelease->effective_from?->toDateString(),
                    $campaign->referenceDataRelease->checksum,
                    $campaign->scenarios->count(),
                    $executions->count(),
                    $executions->where('outcome', 'pass')->count(),
                    $findings->where('status', '!=', 'verified')->count(),
                    $acceptance?->decision ?? 'Not submitted',
                    $acceptance?->submitter->name ?? 'Not submitted',
                    $acceptance?->decisionMaker?->name ?? 'Pending',
                    $acceptance?->checksum ?? 'Not submitted',
                    $campaign->creator->name,
                    $campaign->status,
                ],
            ];
        }));
    }

    /** @return array{id: string, status: string, meta: array{countyId: string|null}, cells: list<mixed>} */
    private function changeReadinessRow(TrainingParticipant $participant): array
    {
        $hasAssessment = $participant->assessments->isNotEmpty();
        $latestAssessment = $participant->assessments->last();

        return [
            'id' => $participant->id,
            'status' => $participant->competency_status,
            'meta' => ['countyId' => $participant->county_id],
            'cells' => [
                $participant->cohort->wave->code,
                $participant->cohort->wave->reference_data_release_id ? $participant->cohort->wave->referenceDataRelease->version : 'Legacy unpinned',
                $participant->cohort->wave->reference_data_release_id ? $participant->cohort->wave->referenceDataRelease->checksum : 'Legacy unpinned',
                $participant->cohort->code,
                $participant->cohort->reference_data_release_id ? $participant->cohort->referenceDataRelease->version : 'Legacy unpinned',
                $participant->cohort->reference_data_release_id ? $participant->cohort->referenceDataRelease->checksum : 'Legacy unpinned',
                $participant->participant_reference,
                $participant->user_id ? $participant->user->name : 'External participant',
                $participant->county_id ? $participant->county->identityCell() : 'National',
                $participant->role_title,
                $participant->attended_hours,
                $participant->attendance_status,
                $participant->competency_status,
                $hasAssessment ? $latestAssessment->score : 'Not assessed',
                $hasAssessment ? $latestAssessment->assessor->name : '—',
                $participant->completed_at?->toIso8601String() ?? 'Not completed',
            ],
        ];
    }

    /**
     * @param  list<string>  $columns
     * @param  LengthAwarePaginator<int, covariant array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function workspace(string $title, string $description, array $columns, LengthAwarePaginator $rows): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'columns' => $columns,
            'rows' => $rows->items(),
            'pagination' => [
                'currentPage' => $rows->currentPage(),
                'lastPage' => $rows->lastPage(),
                'perPage' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ];
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $searchableColumns
     * @return Builder<TModel>
     */
    private function applyFilters(Builder $query, WorkspaceFilters $filters, array $searchableColumns): Builder
    {
        return $query
            ->when($filters->from, fn (Builder $query, string $from) => $query->whereDate($query->qualifyColumn('created_at'), '>=', $from))
            ->when($filters->to, fn (Builder $query, string $to) => $query->whereDate($query->qualifyColumn('created_at'), '<=', $to))
            ->when($filters->search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($filters, $searchableColumns): void {
                foreach ($searchableColumns as $index => $column) {
                    if ($index === 0) {
                        $query->where($query->qualifyColumn($column), 'ilike', '%'.$filters->search.'%');
                    } else {
                        $query->orWhere($query->qualifyColumn($column), 'ilike', '%'.$filters->search.'%');
                    }
                }
            }));
    }
}
