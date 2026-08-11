<?php

namespace App\Http\Controllers;

use App\Actions\AddLearningCohortMember;
use App\Actions\CreateLearningCohort;
use App\Actions\CreateLearningCourse;
use App\Actions\DecideLearningOfflineSync;
use App\Actions\EnrollLearner;
use App\Actions\GenerateLearningOfflinePackage;
use App\Actions\GradeLearningAssessment;
use App\Actions\RecordLearningProgress;
use App\Actions\RecordVirtualClassroomAttendance;
use App\Actions\StoreLinkedDocument;
use App\Actions\SubmitLearningOfflineSync;
use App\Actions\TransitionLearningCohort;
use App\Actions\TransitionLearningCourse;
use App\Enums\ProgrammePermission;
use App\Http\Requests\CompleteLearningLessonRequest;
use App\Http\Requests\DecideLearningOfflineSyncRequest;
use App\Http\Requests\GenerateLearningOfflinePackageRequest;
use App\Http\Requests\RecordVirtualClassroomAttendanceRequest;
use App\Http\Requests\StoreLearningAssetRequest;
use App\Http\Requests\StoreLearningCohortMembershipRequest;
use App\Http\Requests\StoreLearningCohortRequest;
use App\Http\Requests\StoreLearningCourseRequest;
use App\Http\Requests\StoreLearningEnrollmentRequest;
use App\Http\Requests\StoreVirtualClassroomRequest;
use App\Http\Requests\SubmitLearningAssessmentRequest;
use App\Http\Requests\SubmitLearningOfflineSyncRequest;
use App\Http\Requests\TransitionLearningCohortRequest;
use App\Http\Requests\TransitionLearningCourseRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\County;
use App\Models\DocumentLink;
use App\Models\LearningCertificate;
use App\Models\LearningCohort;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\LearningModule;
use App\Models\LearningOfflinePackage;
use App\Models\LearningOfflineSync;
use App\Models\Sector;
use App\Models\User;
use App\Models\VirtualClassroom;
use App\Services\AuditLogger;
use App\Services\DocumentIntegrityVerifier;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Services\ProgrammeWorkspaceData;
use App\Services\VirtualClassroomAccess;
use App\Support\WorkspaceFilters;
use Dompdf\Dompdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LearningController extends Controller
{
    public function index(WorkspaceIndexRequest $request, VirtualClassroomAccess $classroomAccess, EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver): InertiaResponse
    {
        Gate::authorize(ProgrammePermission::ViewLearning->value);
        $user = $this->user($request);
        $canManage = $user->can(ProgrammePermission::ManageLearning->value) || $user->can(ProgrammePermission::ReviewLearning->value);
        $courses = LearningCourse::query()->when(! $canManage, fn (Builder $query) => $query->where('status', 'published'))->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('county_id')->orWhereIn('county_id', $this->countyIds($user))))->with(['sector:id,name', 'county:id,name,code,logo_path', 'owner:id,name', 'referenceDataRelease:id,version,effective_from,checksum', 'latestOfflinePackage', 'latestReadyOfflinePackage', 'modules' => fn ($query) => $query->orderBy('sequence'), 'modules.lessons' => fn ($query) => $query->orderBy('sequence'), 'modules.lessons.questions:id,learning_lesson_id,question,options,points,sequence', 'modules.lessons.documentLinks.document:id,title,category,source_type,original_name,mime_type,content_checksum,scan_status,ocr_status,record_status,current_version_id', 'modules.lessons.documentLinks.document.currentVersion', 'classrooms' => fn ($query) => $query->whereIn('status', ['scheduled', 'live', 'completed'])->orderBy('starts_at'), 'classrooms.facilitator:id,name', 'classrooms.attendances' => fn ($query) => $query->where('user_id', $user->id)])->withCount(['modules', 'enrollments'])->when($request->filled('from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('from')))->when($request->filled('to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('to')))->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->string('category')))->when($request->filled('search'), function (Builder $query) use ($request): void {
            $search = $request->string('search')->trim()->toString();
            $query->where(fn (Builder $query) => $query->where('title', 'ilike', "%{$search}%")->orWhere('code', 'ilike', "%{$search}%")->orWhere('summary', 'ilike', "%{$search}%"));
        })->latest()->paginate($request->integer('per_page', 12))->withQueryString();
        $courseCollection = new EloquentCollection($courses->items());
        $courseCollection->load(['knowledgeItems' => fn ($query) => $query->where('status', 'published')->when(! $user->programmeRole()->hasNationalScope(), fn ($scope) => $scope->where(fn ($countyScope) => $countyScope->whereNull('county_id')->orWhereIn('county_id', $this->countyIds($user))))->orderBy('title')]);
        $courses->setCollection($courseCollection);
        $enrollments = $this->visibleEnrollments($user)->with(['course:id,code,title,passing_score,maximum_attempts', 'progress', 'attempts', 'certificate'])->latest()->get()->keyBy('learning_course_id');

        $offlineSyncs = $this->visibleOfflineSyncs($user)
            ->with(['offlinePackage:id,learning_course_id,package_version,manifest_checksum', 'offlinePackage.course:id,code,title', 'enrollment:id,user_id,learning_course_id', 'enrollment.user:id,name', 'county:id,name,code,logo_path', 'reviewer:id,name'])
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('submitted_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('submitted_at', '<=', $request->date('to')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->toString();
                $query->where(fn (Builder $query) => $query->where('client_sync_id', 'ilike', "%{$search}%")->orWhere('submitted_by_name', 'ilike', "%{$search}%")->orWhereHas('offlinePackage.course', fn (Builder $query) => $query->where('code', 'ilike', "%{$search}%")->orWhere('title', 'ilike', "%{$search}%")));
            })->latest('submitted_at')->paginate($request->integer('per_page', 10), ['*'], 'sync_page')->withQueryString();

        $cohorts = $this->visibleCohorts($user)
            ->with(['course:id,code,title', 'instructor:id,name', 'county:id,name,code,logo_path', 'memberships.enrollment.user:id,name'])
            ->withCount('memberships')
            ->when($request->filled('from'), fn (Builder $query) => $query->whereDate('starts_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->whereDate('starts_at', '<=', $request->date('to')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->trim()->toString();
                $query->where(fn (Builder $query) => $query->where('code', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%")->orWhereHas('course', fn (Builder $query) => $query->where('code', 'ilike', "%{$search}%")->orWhere('title', 'ilike', "%{$search}%")));
            })->latest('starts_at')->paginate($request->integer('per_page', 10), ['*'], 'cohort_page')->withQueryString();

        $countyIds = $this->countyIds($user);
        $eligibleEnrollments = LearningEnrollment::query()->with(['course:id,code,title', 'user:id,name', 'county:id,name'])->whereIn('status', ['enrolled', 'in_progress'])->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereIn('county_id', $countyIds))->latest('enrolled_at')->limit(1000)->get();
        $instructors = User::query()->with('county:id,name')->orderBy('name')->get()->filter(fn (User $candidate): bool => $candidate->can(ProgrammePermission::ManageLearning->value) && ($user->programmeRole()->hasNationalScope() || ($candidate->county_id !== null && in_array($candidate->county_id, $countyIds, true))));
        $referenceDataRelease = $referenceDataReleaseResolver->availableForSelection(now());
        $governedCountyIds = $this->snapshotIds($referenceDataRelease?->snapshot['counties'] ?? []);
        $governedSectorIds = $this->snapshotIds($referenceDataRelease?->snapshot['sectors'] ?? []);

        return Inertia::render('learning/index', ['courses' => $courses->through(fn (LearningCourse $course): array => $this->coursePayload($course, $enrollments->get($course->id), $user, $classroomAccess)), 'cohorts' => $cohorts->through(fn (LearningCohort $cohort): array => $this->cohortPayload($cohort)), 'offlineSyncs' => $offlineSyncs->through(fn (LearningOfflineSync $sync): array => $this->offlineSyncPayload($sync)), 'filters' => $request->safe()->only(['from', 'to', 'search', 'status', 'category', 'per_page']), 'capabilities' => ['manage' => $user->can(ProgrammePermission::ManageLearning->value), 'review' => $user->can(ProgrammePermission::ReviewLearning->value), 'enroll' => $user->can(ProgrammePermission::EnrollLearning->value)], 'catalogue' => ['available' => $referenceDataRelease !== null, 'version' => $referenceDataRelease?->version, 'effectiveFrom' => $referenceDataRelease?->effective_from?->toIso8601String()], 'options' => ['sectors' => Sector::query()->whereIn('id', $governedSectorIds)->where('is_active', true)->orderBy('name')->get(['id', 'name']), 'counties' => County::query()->whereIn('id', $governedCountyIds)->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereIn('id', $countyIds))->orderBy('code')->get(['id', 'name']), 'facilitators' => User::query()->orderBy('name')->get(['id', 'name']), 'cohortCourses' => LearningCourse::query()->where('status', 'published')->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('county_id')->orWhereIn('county_id', $countyIds)))->orderBy('code')->get(['id', 'code', 'title'])->map(fn (LearningCourse $course): array => ['id' => $course->id, 'name' => "{$course->code} · {$course->title}"]), 'instructors' => $instructors->map(fn (User $instructor): array => ['id' => $instructor->id, 'name' => $instructor->county ? "{$instructor->name} · {$instructor->county->name}" : $instructor->name])->values(), 'cohortEnrollments' => $eligibleEnrollments->map(fn (LearningEnrollment $enrollment): array => ['id' => $enrollment->id, 'name' => $enrollment->user->name.' · '.$enrollment->course->code.($enrollment->county ? ' · '.$enrollment->county->name : '')])]]);
    }

    public function store(StoreLearningCourseRequest $request, CreateLearningCourse $action): RedirectResponse
    {
        $course = $action->handle($this->user($request), $request->validated());

        return back()->with('success', "Course {$course->code} created.");
    }

    public function storeCohort(StoreLearningCohortRequest $request, CreateLearningCohort $action): RedirectResponse
    {
        $cohort = $action->handle($this->user($request), $request->validated());

        return back()->with('success', "Learning cohort {$cohort->code} created.");
    }

    public function addCohortMember(StoreLearningCohortMembershipRequest $request, string $currentTeam, LearningCohort $cohort, AddLearningCohortMember $action): RedirectResponse
    {
        $enrollmentId = $request->validated('learning_enrollment_id');
        abort_unless(is_string($enrollmentId), 422);
        $action->handle($cohort, LearningEnrollment::query()->findOrFail($enrollmentId), $this->user($request));

        return back()->with('success', 'Learner added to the cohort roster.');
    }

    public function transitionCohort(TransitionLearningCohortRequest $request, string $currentTeam, LearningCohort $cohort, TransitionLearningCohort $action): RedirectResponse
    {
        /** @var array{transition:string, rationale:string} $attributes */
        $attributes = $request->validated();
        $action->handle($cohort, $this->user($request), $attributes);

        return back()->with('success', 'Learning cohort lifecycle updated.');
    }

    public function transition(TransitionLearningCourseRequest $request, string $currentTeam, LearningCourse $course, TransitionLearningCourse $action): RedirectResponse
    {
        $action->handle($course, $this->user($request), $request->validated());

        return back()->with('success', 'Course publication lifecycle updated.');
    }

    public function storeAsset(StoreLearningAssetRequest $request, string $currentTeam, LearningCourse $course, LearningLesson $lesson, StoreLinkedDocument $storeDocument, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($lesson->module()->where('learning_course_id', $course->id)->exists(), 404);
        abort_unless($course->status === 'draft', 409, 'Learning assets are locked after quality review begins.');
        abort_unless($user->programmeRole()->hasNationalScope() || $course->owner_id === $user->id, 403);
        abort_if($lesson->documentLinks()->whereHas('document', fn (Builder $query) => $query->where('record_status', 'active'))->exists(), 409, 'Replace the existing governed asset through document version control.');
        abort_unless(in_array($lesson->content_type, ['video', 'audio', 'toolkit', 'manual'], true), 409, 'Repository assets are supported for multimedia, toolkit and manual lessons.');
        $file = $request->file('document');
        $detectedMimeType = (string) $file->getMimeType();
        $mimeType = match ($detectedMimeType) {
            'application/mp4', 'application/x-mp4' => 'video/mp4',
            default => $detectedMimeType,
        };
        if ($lesson->content_type === 'video') {
            abort_unless($request->string('source_type')->toString() === 'digital' && str_starts_with($mimeType, 'video/') && $request->boolean('transcript_available'), 422, 'Video lessons require a digital video asset and transcript.');
        }
        if ($lesson->content_type === 'audio') {
            abort_unless($request->string('source_type')->toString() === 'digital' && str_starts_with($mimeType, 'audio/') && $request->boolean('transcript_available'), 422, 'Audio lessons require a digital audio asset and transcript.');
        }

        $document = $storeDocument->handle($lesson, $user, $file, ['title' => $request->string('title')->toString(), 'category' => 'Learning '.$lesson->content_type, 'source_type' => $request->string('source_type')->toString(), 'purpose' => 'learning-lesson-asset', 'county_id' => $course->county_id, 'mime_type' => $mimeType]);
        $existingMetadata = $lesson->assetMetadata();
        $metadata = [...$existingMetadata, 'repository_asset_id' => $document->id, 'rights_holder' => $request->string('rights_holder')->toString(), 'licence' => $request->string('licence')->toString(), 'accessible_alternative' => $request->string('accessible_alternative')->toString(), 'transcript_available' => $request->boolean('transcript_available'), 'asset_source_type' => $request->string('source_type')->toString(), 'uploaded_at' => now()->toIso8601String()];
        $lesson->update(['content_url' => null, 'mime_type' => $document->mime_type, 'content_checksum' => $document->content_checksum, 'is_downloadable' => $request->boolean('is_downloadable'), 'metadata' => $metadata]);
        $auditLogger->record($user, $lesson, 'learning.lesson_asset_registered', "Governed asset registered for lesson {$lesson->title}.", $course->county_id, ['document_id' => $document->id, 'content_checksum' => $document->content_checksum, 'licence' => $metadata['licence']]);

        return back()->with('success', 'Learning asset uploaded securely.');
    }

    public function enroll(StoreLearningEnrollmentRequest $request, EnrollLearner $action): RedirectResponse
    {
        $courseId = $request->validated('learning_course_id');
        abort_unless(is_string($courseId), 422);
        $course = LearningCourse::query()->findOrFail($courseId);
        $action->handle($course, $this->user($request));

        return back()->with('success', 'Course enrolment confirmed.');
    }

    public function generateOfflinePackage(GenerateLearningOfflinePackageRequest $request, string $currentTeam, LearningCourse $course, GenerateLearningOfflinePackage $action): RedirectResponse
    {
        $package = $action->handle($course, $this->user($request));

        return back()->with('success', "Offline package v{$package->package_version} generated with verified course content.");
    }

    public function downloadOfflinePackage(Request $request, string $currentTeam, LearningOfflinePackage $offlinePackage, DocumentIntegrityVerifier $integrityVerifier, AuditLogger $auditLogger): StreamedResponse
    {
        $user = $this->user($request);
        $offlinePackage->load('course.county');
        $hasGovernanceAccess = $user->canAny([ProgrammePermission::ManageLearning->value, ProgrammePermission::ReviewLearning->value]);
        $isEnrolled = $offlinePackage->course->enrollments()->where('user_id', $user->id)->whereIn('status', ['enrolled', 'in_progress', 'completed'])->exists();
        abort_unless($hasGovernanceAccess || $isEnrolled, 403);
        abort_unless($offlinePackage->course->county_id === null || $user->programmeRole()->hasNationalScope() || $user->canAccessCounty($offlinePackage->course->county), 403);
        abort_unless($offlinePackage->status === 'ready' && $offlinePackage->storage_disk !== null && $offlinePackage->path !== null && $offlinePackage->original_name !== null, 404);
        abort_unless($integrityVerifier->matches($offlinePackage->storage_disk, $offlinePackage->path, $offlinePackage->content_checksum), 409, 'Offline package integrity verification failed.');
        $auditLogger->record($user, $offlinePackage, 'learning.offline-package.downloaded', "Offline package v{$offlinePackage->package_version} downloaded for {$offlinePackage->course->code}.", $offlinePackage->course->county_id, ['content_checksum' => $offlinePackage->content_checksum, 'manifest_checksum' => $offlinePackage->manifest_checksum]);

        return Storage::disk($offlinePackage->storage_disk)->download($offlinePackage->path, $offlinePackage->original_name, ['Content-Type' => 'application/zip']);
    }

    public function submitOfflineSync(SubmitLearningOfflineSyncRequest $request, string $currentTeam, LearningEnrollment $enrollment, SubmitLearningOfflineSync $action): RedirectResponse
    {
        $sync = $action->handle($enrollment, $this->user($request), $request->syncPayload());

        return back()->with('success', $sync->status === 'pending' ? 'Offline activity submitted for independent reconciliation.' : 'The existing synchronization record was retained.');
    }

    public function decideOfflineSync(DecideLearningOfflineSyncRequest $request, string $currentTeam, LearningOfflineSync $offlineSync, DecideLearningOfflineSync $action): RedirectResponse
    {
        /** @var array{decision: string, rationale: string} $attributes */
        $attributes = $request->validated();
        $decided = $action->handle($offlineSync, $this->user($request), $attributes);

        return back()->with('success', $decided->status === 'conflict' ? 'A newer official record caused a reconciliation conflict; no progress was changed.' : 'Offline synchronization decision recorded.');
    }

    public function completeLesson(CompleteLearningLessonRequest $request, string $currentTeam, LearningEnrollment $enrollment, LearningLesson $lesson, RecordLearningProgress $action): RedirectResponse
    {
        $action->handle($enrollment, $lesson, $this->user($request), $request->validated());

        return back()->with('success', 'Lesson completion recorded.');
    }

    public function assess(SubmitLearningAssessmentRequest $request, string $currentTeam, LearningEnrollment $enrollment, GradeLearningAssessment $action): RedirectResponse
    {
        $action->handle($enrollment, $this->user($request), $request->validated('answers'));

        return back()->with('success', 'Assessment graded and progress updated.');
    }

    public function storeClassroom(StoreVirtualClassroomRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $classroom = VirtualClassroom::create([...$request->validated(), 'created_by' => $user->id]);

        return back()->with('success', "Virtual classroom {$classroom->title} scheduled.");
    }

    public function showClassroom(WorkspaceIndexRequest $request, string $currentTeam, VirtualClassroom $classroom, ProgrammeWorkspaceData $workspaceData): InertiaResponse
    {
        $classroom->load('course.county', 'facilitator:id,name');
        $filters = WorkspaceFilters::fromRequest($request, $classroom->id);
        $register = $workspaceData->learningAttendance($this->user($request), $filters);

        return Inertia::render('learning/classrooms/show', ['classroom' => ['id' => $classroom->id, 'title' => $classroom->title, 'course' => ['id' => $classroom->course->id, 'code' => $classroom->course->code, 'title' => $classroom->course->title, 'county' => $classroom->course->county?->identityCell()], 'facilitator' => $classroom->facilitator->name, 'startsAt' => $classroom->starts_at->toIso8601String(), 'endsAt' => $classroom->ends_at->toIso8601String(), 'platform' => $classroom->platform, 'capacity' => $classroom->capacity, 'status' => $classroom->status], 'roster' => ['rows' => $register['rows'], 'pagination' => $register['pagination']], 'filters' => $request->safe()->only(['from', 'to', 'search', 'status', 'per_page'])]);
    }

    public function recordClassroomAttendance(RecordVirtualClassroomAttendanceRequest $request, string $currentTeam, VirtualClassroom $classroom, RecordVirtualClassroomAttendance $action): RedirectResponse
    {
        $attendance = $action->handle($classroom, $this->user($request), $request->validated());

        return back()->with('success', "{$attendance->attendance_status} attendance recorded.");
    }

    public function certificate(Request $request, string $currentTeam, LearningCertificate $certificate): Response
    {
        $user = $this->user($request);
        $certificate->load('enrollment.course', 'enrollment.user');
        abort_unless($certificate->enrollment->user_id === $user->id || $user->can(ProgrammePermission::ManageLearning->value), 403);
        $escape = fn (mixed $value): string => e((string) $value);
        $dompdf = new Dompdf;
        $verificationUrl = route('learning.certificates.verify', ['code' => $certificate->verification_code]);
        $dompdf->loadHtml('<style>body{font-family:sans-serif;text-align:center;color:#12304a;padding:60px}.seal{border:8px double #147a55;padding:60px}h1{font-size:34px}.score{font-size:20px;color:#147a55}a{color:#147a55}</style><div class="seal"><p>Republic of Kenya · State Department for Devolution</p><h1>Certificate of Completion</h1><p>This certifies that</p><h2>'.$escape($certificate->enrollment->user->name).'</h2><p>successfully completed</p><h2>'.$escape($certificate->enrollment->course->title).'</h2><p class="score">Final score '.$escape($certificate->final_score).'%</p><p>Certificate '.$escape($certificate->certificate_number).'</p><p>Verification '.$escape($certificate->verification_code).' · Issued '.$escape($certificate->issued_at->toDateString()).'</p><p><a href="'.$escape($verificationUrl).'">Verify this certificate online</a></p></div>');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$certificate->certificate_number.'.pdf"']);
    }

    /** @return Builder<LearningEnrollment> */
    private function visibleEnrollments(User $user): Builder
    {
        return LearningEnrollment::query()->when(! $user->can(ProgrammePermission::ManageLearning->value), fn (Builder $query) => $query->where('user_id', $user->id));
    }

    /** @return Builder<LearningOfflineSync> */
    private function visibleOfflineSyncs(User $user): Builder
    {
        $canGovern = $user->canAny([ProgrammePermission::ManageLearning->value, ProgrammePermission::ReviewLearning->value]);

        return LearningOfflineSync::query()
            ->when(! $canGovern, fn (Builder $query) => $query->where('submitted_by', $user->id))
            ->when($canGovern && ! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereIn('county_id', $this->countyIds($user)));
    }

    /** @return Builder<LearningCohort> */
    private function visibleCohorts(User $user): Builder
    {
        return LearningCohort::query()->when(! $user->programmeRole()->hasNationalScope(), fn (Builder $query) => $query->whereIn('county_id', $this->countyIds($user)));
    }

    /** @return list<string> */
    private function countyIds(User $user): array
    {
        return array_values(array_unique(array_filter([$user->county_id, ...$user->assignedCounties()->pluck('counties.id')->all()], is_string(...))));
    }

    /** @return array<string, mixed> */
    private function cohortPayload(LearningCohort $cohort): array
    {
        return ['id' => $cohort->id, 'code' => $cohort->code, 'name' => $cohort->name, 'description' => $cohort->description, 'course' => ['id' => $cohort->course->id, 'code' => $cohort->course->code, 'title' => $cohort->course->title], 'instructor' => $cohort->instructor->name, 'county' => $cohort->county?->identityCell(), 'capacity' => $cohort->capacity, 'membersCount' => $cohort->memberships_count, 'members' => $cohort->memberships->map(fn ($membership): array => ['id' => $membership->id, 'name' => $membership->enrollment->user->name])->values(), 'enrollmentOpensOn' => $cohort->enrollment_opens_on->toDateString(), 'enrollmentClosesOn' => $cohort->enrollment_closes_on->toDateString(), 'startsAt' => $cohort->starts_at->toIso8601String(), 'endsAt' => $cohort->ends_at->toIso8601String(), 'status' => $cohort->status];
    }

    /** @return array<string,mixed> */
    private function coursePayload(LearningCourse $course, ?LearningEnrollment $enrollment, User $user, VirtualClassroomAccess $classroomAccess): array
    {
        $latestAttempt = $course->latestOfflinePackage;
        $package = $course->latestReadyOfflinePackage;
        $recommendations = $course->knowledgeItems->map(fn ($item): array => ['id' => $item->id, 'reference' => $item->reference, 'title' => $item->title, 'summary' => $item->summary, 'type' => $item->item_type])->values()->all();

        return ['id' => $course->id, 'code' => $course->code, 'title' => $course->title, 'summary' => $course->summary, 'description' => $course->description, 'category' => $course->category, 'level' => $course->level, 'deliveryMode' => $course->delivery_mode, 'language' => $course->language, 'estimatedMinutes' => $course->estimated_minutes, 'passingScore' => $course->passing_score, 'maximumAttempts' => $course->maximum_attempts, 'status' => $course->status, 'sector' => $course->sector?->name, 'county' => $course->county?->identityCell(), 'referenceData' => $course->referenceDataRelease ? ['version' => $course->referenceDataRelease->version, 'effectiveFrom' => $course->referenceDataRelease->effective_from?->toIso8601String(), 'checksum' => $course->referenceDataRelease->checksum] : null, 'owner' => $course->owner->name, 'moduleCount' => $course->modules_count, 'enrollmentCount' => $course->enrollments_count, 'knowledgeRecommendations' => $recommendations, 'offlinePackageAttempt' => $latestAttempt ? ['version' => $latestAttempt->package_version, 'status' => $latestAttempt->status, 'failedAt' => $latestAttempt->failed_at?->toIso8601String(), 'failureMessage' => $latestAttempt->failure_message] : null, 'offlinePackage' => $package ? ['id' => $package->id, 'version' => $package->package_version, 'status' => $package->status, 'sizeBytes' => $package->size_bytes, 'checksum' => $package->content_checksum, 'manifestChecksum' => $package->manifest_checksum, 'generatedAt' => $package->generated_at?->toIso8601String(), 'canDownload' => $enrollment !== null || $user->canAny([ProgrammePermission::ManageLearning->value, ProgrammePermission::ReviewLearning->value])] : null, 'modules' => $course->modules->map(fn ($module): array => $this->modulePayload($module))->values()->all(), 'classrooms' => $course->classrooms->map(fn (VirtualClassroom $classroom): array => $this->classroomPayload($classroom, $user, $classroomAccess))->values()->all(), 'enrollment' => $enrollment ? $this->enrollmentPayload($enrollment) : null];
    }

    /** @return array<string, mixed> */
    private function modulePayload(LearningModule $module): array
    {
        return ['id' => $module->id, 'title' => $module->title, 'description' => $module->description, 'lessons' => $module->lessons->map(fn (LearningLesson $lesson): array => $this->lessonPayload($lesson))->values()->all()];
    }

    /** @return array<string, mixed> */
    private function lessonPayload(LearningLesson $lesson): array
    {
        return ['id' => $lesson->id, 'title' => $lesson->title, 'summary' => $lesson->summary, 'contentType' => $lesson->content_type, 'contentBody' => $lesson->content_body, 'contentUrl' => $lesson->content_url, 'downloadable' => $lesson->is_downloadable, 'estimatedMinutes' => $lesson->estimated_minutes, 'assetMetadata' => $lesson->metadata, 'assets' => $lesson->documentLinks->filter(fn (DocumentLink $link): bool => $link->document->record_status === 'active')->map(fn (DocumentLink $link): array => ['id' => $link->document->id, 'title' => $link->document->title, 'originalName' => $link->document->original_name, 'mimeType' => $link->document->mime_type, 'sourceType' => $link->document->source_type, 'scanStatus' => $link->document->scan_status, 'ocrStatus' => $link->document->ocr_status, 'checksum' => $link->document->content_checksum])->values()->all(), 'questions' => $lesson->questions->map(fn ($question): array => ['id' => $question->id, 'question' => $question->question, 'options' => $question->options, 'points' => $question->points])->values()->all()];
    }

    /** @return array<string, mixed> */
    private function classroomPayload(VirtualClassroom $classroom, User $user, VirtualClassroomAccess $classroomAccess): array
    {
        $attendance = $classroom->attendances->first();

        return ['id' => $classroom->id, 'title' => $classroom->title, 'facilitator' => $classroom->facilitator->name, 'startsAt' => $classroom->starts_at->toIso8601String(), 'endsAt' => $classroom->ends_at->toIso8601String(), 'platform' => $classroom->platform, 'joinUrl' => $classroom->join_url, 'capacity' => $classroom->capacity, 'status' => $classroom->status, 'canRecordAttendance' => $classroomAccess->canManageAttendance($user, $classroom), 'attendance' => $attendance ? ['status' => $attendance->attendance_status, 'minutes' => $attendance->attended_minutes, 'recordedAt' => $attendance->recorded_at->toIso8601String()] : null];
    }

    /** @return array<string, mixed> */
    private function enrollmentPayload(LearningEnrollment $enrollment): array
    {
        return ['id' => $enrollment->id, 'status' => $enrollment->status, 'progress' => (string) $enrollment->progress_percentage, 'bestScore' => $enrollment->best_score, 'attempts' => $enrollment->attempts->count(), 'completedLessonIds' => $enrollment->progress->where('status', 'completed')->pluck('learning_lesson_id')->values()->all(), 'certificate' => $enrollment->certificate ? ['id' => $enrollment->certificate->id, 'number' => $enrollment->certificate->certificate_number, 'verificationCode' => $enrollment->certificate->verification_code] : null];
    }

    /** @return array<string, mixed> */
    private function offlineSyncPayload(LearningOfflineSync $sync): array
    {
        return [
            'id' => $sync->id,
            'clientSyncId' => $sync->client_sync_id,
            'course' => ['id' => $sync->offlinePackage->course->id, 'code' => $sync->offlinePackage->course->code, 'title' => $sync->offlinePackage->course->title],
            'packageVersion' => $sync->offlinePackage->package_version,
            'learner' => $sync->enrollment->user->name,
            'county' => $sync->county?->identityCell(),
            'status' => $sync->status,
            'eventCount' => $sync->event_count,
            'payloadChecksum' => $sync->payload_checksum,
            'decisionChecksum' => $sync->decision_checksum,
            'decisionReason' => $sync->decision_reason,
            'submittedAt' => $sync->submitted_at->toIso8601String(),
            'reviewedAt' => $sync->reviewed_at?->toIso8601String(),
            'reviewer' => $sync->reviewer?->name,
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<string>
     */
    private function snapshotIds(array $records): array
    {
        return array_values(collect($records)->pluck('id')->filter(fn (mixed $id): bool => is_string($id))->all());
    }
}
