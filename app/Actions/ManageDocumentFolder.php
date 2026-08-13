<?php

namespace App\Actions;

use App\Models\DocumentFolder;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ManageDocumentFolder
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function create(User $actor, string $name, ?DocumentFolder $parent, ?string $countyId): DocumentFolder
    {
        $resolvedCountyId = $parent->county_id ?? $countyId;
        $this->assertUniqueName($name, $parent?->id, $resolvedCountyId);

        $folder = DocumentFolder::create([
            'parent_id' => $parent?->id,
            'county_id' => $resolvedCountyId,
            'name' => trim($name),
            'created_by' => $actor->id,
        ]);
        $this->auditLogger->record($actor, $folder, 'document.folder.created', __('document-repository.audit.folder_created', ['name' => $folder->name]), $folder->county_id);

        return $folder;
    }

    public function update(DocumentFolder $folder, User $actor, string $name, ?DocumentFolder $parent): DocumentFolder
    {
        abort_if($parent?->id === $folder->id, 422, __('document-repository.errors.folder_cycle'));
        abort_if($parent !== null && $this->hasAncestor($parent, $folder->id), 422, __('document-repository.errors.folder_cycle'));
        abort_if($parent !== null && $parent->county_id !== $folder->county_id, 422, __('document-repository.errors.folder_scope_mismatch'));
        $this->assertUniqueName($name, $parent?->id, $folder->county_id, $folder->id);

        $folder->update(['name' => trim($name), 'parent_id' => $parent?->id, 'updated_by' => $actor->id]);
        $this->auditLogger->record($actor, $folder, 'document.folder.updated', __('document-repository.audit.folder_updated', ['name' => $folder->name]), $folder->county_id);

        return $folder;
    }

    public function delete(DocumentFolder $folder, User $actor): void
    {
        DB::transaction(function () use ($folder, $actor): void {
            $locked = DocumentFolder::query()->whereKey($folder)->lockForUpdate()->firstOrFail();
            abort_if($locked->children()->exists() || $locked->documents()->exists(), 409, __('document-repository.errors.folder_not_empty'));
            $this->auditLogger->record($actor, $locked, 'document.folder.deleted', __('document-repository.audit.folder_deleted', ['name' => $locked->name]), $locked->county_id);
            $locked->delete();
        });
    }

    private function hasAncestor(DocumentFolder $candidate, string $folderId): bool
    {
        $cursor = $candidate;
        while ($cursor->parent_id !== null) {
            if ($cursor->parent_id === $folderId) {
                return true;
            }
            $cursor = DocumentFolder::query()->findOrFail($cursor->parent_id);
        }

        return false;
    }

    private function assertUniqueName(string $name, ?string $parentId, ?string $countyId, ?string $exceptId = null): void
    {
        $exists = DocumentFolder::query()
            ->when($exceptId !== null, fn (Builder $query) => $query->where($query->qualifyColumn('id'), '!=', $exceptId))
            ->where('parent_id', $parentId)
            ->where('county_id', $countyId)
            ->whereRaw('lower(name) = lower(?)', [trim($name)])
            ->exists();
        abort_if($exists, 422, __('document-repository.errors.folder_name_taken'));
    }
}
