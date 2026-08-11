<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\IgrGapCategory;
use App\Models\User;
use App\Services\AuditLogger;

class CreateIgrGapCategory
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): IgrGapCategory
    {
        abort_unless($actor->can(ProgrammePermission::ManageIgrResolutions->value), 403);
        $category = IgrGapCategory::create([...$attributes, 'code' => strtoupper(trim((string) $attributes['code'])), 'created_by' => $actor->id]);
        $this->auditLogger->record($actor, $category, 'igr.gap_category.created', "IGR gap category {$category->code} created.");

        return $category;
    }
}
