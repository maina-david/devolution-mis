<?php

namespace App\Http\Controllers;

use App\Actions\RunAuditAssurance;
use App\Enums\ProgrammePermission;
use App\Http\Requests\RunAuditAssuranceRequest;
use App\Http\Requests\WorkspaceIndexRequest;
use App\Models\AuditAssuranceRun;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ProgrammeWorkspaceData;
use App\Support\WorkspaceFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditAssuranceController extends Controller
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function index(WorkspaceIndexRequest $request, ProgrammeWorkspaceData $workspaceData): Response
    {
        $user = $this->authorizedNationalViewer($request);

        return Inertia::render('programme/workspace', ['workspace' => $workspaceData->auditAssurance($user, WorkspaceFilters::fromRequest($request)), 'workspaceType' => 'audit-assurance', 'filters' => $request->safe()->only(['from', 'to', 'search', 'status', 'per_page']), 'capabilities' => ['run' => $user->can(ProgrammePermission::ManageSecurityGovernance->value), 'download' => true]]);
    }

    public function store(RunAuditAssuranceRequest $request, RunAuditAssurance $action): RedirectResponse
    {
        $run = $action->handle($this->user($request));

        return back()->with($run->outcome === 'fail' ? 'error' : 'success', "Audit assurance evidence retained with outcome {$run->outcome}.");
    }

    public function download(Request $request, string $currentTeam, AuditAssuranceRun $auditAssuranceRun): StreamedResponse
    {
        $user = $this->authorizedNationalViewer($request);
        abort_unless(is_string($auditAssuranceRun->path) && Storage::disk($auditAssuranceRun->disk)->exists($auditAssuranceRun->path), 404, 'Audit assurance artifact is unavailable.');
        $contents = Storage::disk($auditAssuranceRun->disk)->get($auditAssuranceRun->path);
        abort_unless(is_string($auditAssuranceRun->artifact_checksum) && hash_equals($auditAssuranceRun->artifact_checksum, hash('sha256', $contents)), 409, 'Audit assurance artifact failed integrity verification.');
        if ($auditAssuranceRun->signature !== null && $auditAssuranceRun->signing_key_reference !== null) {
            $keys = config('audit.signing_keys', []);
            $key = is_array($keys) ? ($keys[$auditAssuranceRun->signing_key_reference] ?? null) : null;
            abort_unless(is_string($key) && $key !== '', 409, 'The retained signing key is unavailable for signature verification.');
            abort_unless(hash_equals($auditAssuranceRun->signature, hash_hmac('sha256', $auditAssuranceRun->artifact_checksum, $key)), 409, 'Audit assurance signature verification failed.');
        }
        $this->auditLogger->record($user, $auditAssuranceRun, 'audit.assurance.downloaded', 'Audit assurance artifact downloaded.', metadata: ['artifact_checksum' => $auditAssuranceRun->artifact_checksum, 'signing_key_reference' => $auditAssuranceRun->signing_key_reference]);

        return Storage::disk($auditAssuranceRun->disk)->download($auditAssuranceRun->path, "idmis-audit-assurance-{$auditAssuranceRun->id}.json", ['Content-Type' => $auditAssuranceRun->mime_type]);
    }

    private function authorizedNationalViewer(Request $request): User
    {
        Gate::authorize(ProgrammePermission::ViewAuditTrail->value);
        $user = $this->user($request);
        abort_unless($user->programmeRole()->hasNationalScope(), 403);

        return $user;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
