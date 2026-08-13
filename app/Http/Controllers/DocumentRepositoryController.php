<?php

namespace App\Http\Controllers;

use App\Actions\ManageDocumentFolder;
use App\Actions\MoveRepositoryDocuments;
use App\Actions\StoreRepositoryDocument;
use App\Enums\ProgrammePermission;
use App\Http\Requests\MoveRepositoryDocumentsRequest;
use App\Http\Requests\StoreDocumentFolderRequest;
use App\Http\Requests\StoreRepositoryDocumentRequest;
use App\Http\Requests\UpdateDocumentFolderRequest;
use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\DocumentFolder;
use App\Models\User;
use App\Services\DocumentAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DocumentRepositoryController extends Controller
{
    public function storeFolder(StoreDocumentFolderRequest $request, ManageDocumentFolder $folders): RedirectResponse
    {
        $actor = $this->user($request);
        $parent = $request->filled('parent_id') ? $this->authorizedFolder($actor, $request->string('parent_id')->toString()) : null;
        $countyId = $request->filled('county_id') ? $request->string('county_id')->toString() : null;
        if ($parent === null) {
            $this->authorizeCountyScope($actor, $countyId);
        } elseif ($countyId !== null) {
            abort_unless($countyId === $parent->county_id, 422, __('document-repository.errors.folder_scope_mismatch'));
        }

        $folder = $folders->create($actor, $request->string('name')->toString(), $parent, $countyId);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('document-repository.outcomes.folder_created', ['name' => $folder->name])]);

        return back();
    }

    public function updateFolder(UpdateDocumentFolderRequest $request, DocumentFolder $folder, ManageDocumentFolder $folders): RedirectResponse
    {
        $actor = $this->user($request);
        $this->authorizeFolder($actor, $folder);
        $parent = $request->filled('parent_id') ? $this->authorizedFolder($actor, $request->string('parent_id')->toString()) : null;
        $folders->update($folder, $actor, $request->string('name')->toString(), $parent);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('document-repository.outcomes.folder_updated', ['name' => $folder->name])]);

        return back();
    }

    public function destroyFolder(Request $request, DocumentFolder $folder, ManageDocumentFolder $folders): RedirectResponse
    {
        Gate::authorize(ProgrammePermission::ManageRecords->value);
        $actor = $this->user($request);
        $this->authorizeFolder($actor, $folder);
        $folders->delete($folder, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('document-repository.outcomes.folder_deleted')]);

        return redirect()->route('evidence.index');
    }

    public function storeDocument(StoreRepositoryDocumentRequest $request, StoreRepositoryDocument $store): RedirectResponse
    {
        $actor = $this->user($request);
        $folder = $this->authorizedFolder($actor, $request->string('folder_id')->toString());
        $document = $store->handle($folder, $actor, $request->file('document'), [
            'title' => $request->string('title')->toString(),
            'category' => $request->string('category')->toString(),
            'source_type' => $request->string('source_type')->toString(),
            'description' => $request->filled('description') ? $request->string('description')->toString() : null,
            'document_date' => $request->filled('document_date') ? $request->string('document_date')->toString() : null,
            'tags' => $request->filled('tags') ? $request->string('tags')->toString() : null,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('document-repository.outcomes.document_uploaded', ['title' => $document->title])]);

        return back();
    }

    public function moveDocuments(MoveRepositoryDocumentsRequest $request, MoveRepositoryDocuments $move, DocumentAccess $documentAccess): RedirectResponse
    {
        $actor = $this->user($request);
        $folder = $this->authorizedFolder($actor, $request->string('folder_id')->toString());
        /** @var list<string> $ids */
        $ids = $request->validated('ids');
        $documents = AssessmentDocument::query()->with(['county', 'legalHolds'])->whereIn('id', $ids)->get();
        abort_unless($documents->count() === count($ids), 404);
        foreach ($documents as $document) {
            abort_unless($documentAccess->allows($actor, $document), 403);
        }
        $move->handle(array_values($documents->all()), $folder, $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('document-repository.outcomes.documents_moved', $documents->count(), ['count' => $documents->count(), 'folder' => $folder->name])]);

        return back();
    }

    private function authorizedFolder(User $user, string $id): DocumentFolder
    {
        $folder = DocumentFolder::query()->with('county')->findOrFail($id);
        $this->authorizeFolder($user, $folder);

        return $folder;
    }

    private function authorizeFolder(User $user, DocumentFolder $folder): void
    {
        $this->authorizeCountyScope($user, $folder->county_id, $folder);
    }

    private function authorizeCountyScope(User $user, ?string $countyId, ?DocumentFolder $folder = null): void
    {
        if ($countyId === null) {
            abort_unless($user->programmeRole()->hasNationalScope(), 403);

            return;
        }

        $county = $folder->county ?? County::query()->findOrFail($countyId);
        abort_unless($user->canAccessCounty($county), 403);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
