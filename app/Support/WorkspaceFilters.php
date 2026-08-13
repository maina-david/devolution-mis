<?php

namespace App\Support;

use App\Http\Requests\WorkspaceIndexRequest;

class WorkspaceFilters
{
    public function __construct(
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly string $search,
        public readonly int $perPage,
        public readonly ?string $cycleId = null,
        public readonly ?string $countyId = null,
        public readonly ?string $sectorId = null,
        public readonly ?string $status = null,
        public readonly ?string $classroomId = null,
        public readonly ?string $severity = null,
        public readonly ?string $gapCategoryId = null,
        public readonly ?string $folderId = null,
    ) {}

    public static function fromRequest(WorkspaceIndexRequest $request, ?string $classroomId = null): self
    {
        return new self(
            $request->filled('from') ? $request->date('from')?->toDateString() : null,
            $request->filled('to') ? $request->date('to')?->toDateString() : null,
            $request->string('search')->trim()->toString(),
            $request->integer('per_page', 15),
            $request->filled('cycle_id') ? $request->string('cycle_id')->toString() : null,
            $request->filled('county_id') ? $request->string('county_id')->toString() : null,
            $request->filled('sector_id') ? $request->string('sector_id')->toString() : null,
            $request->filled('status') ? $request->string('status')->toString() : null,
            $classroomId ?? ($request->filled('classroom_id') ? $request->string('classroom_id')->toString() : null),
            $request->filled('severity') ? $request->string('severity')->toString() : null,
            $request->filled('gap_category_id') ? $request->string('gap_category_id')->toString() : null,
            $request->filled('folder_id') ? $request->string('folder_id')->toString() : null,
        );
    }
}
