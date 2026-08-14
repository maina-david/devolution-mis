<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
use App\Models\County;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\ProgrammeCountyCoverage;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CreateProgrammeCountyCoverage
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param array{programme_id: string, county_id: string, implementation_lead_id?: string|null, starts_on: string, ends_on?: string|null, status: string, funding_allocation?: int|float|string|null, currency: string, source_reference: string, notes?: string|null} $attributes */
    public function handle(User $actor, array $attributes): ProgrammeCountyCoverage
    {
        return DB::transaction(function () use ($actor, $attributes): ProgrammeCountyCoverage {
            abort_unless($actor->can(ProgrammePermission::ManageReferenceData->value), 403, __('reference-data.coverage.errors.unauthorized'));
            $programme = Programme::query()->lockForUpdate()->find($attributes['programme_id']);
            abort_unless($programme !== null, 409, __('reference-data.coverage.errors.programme_unavailable'));
            abort_unless(County::query()->lockForUpdate()->find($attributes['county_id']) !== null, 409, __('reference-data.coverage.errors.county_unavailable'));
            if (filled($attributes['implementation_lead_id'] ?? null)) {
                abort_unless(Organization::query()->lockForUpdate()->whereKey($attributes['implementation_lead_id'])->where('status', 'active')->first(['id']) !== null, 409, __('reference-data.coverage.errors.implementation_lead_unavailable'));
            }
            abort_if($programme->starts_on !== null && $programme->starts_on->isAfter($attributes['starts_on']), 409, __('reference-data.coverage.errors.before_programme_start'));
            abort_if($programme->ends_on !== null && (blank($attributes['ends_on'] ?? null) || $programme->ends_on->isBefore($attributes['ends_on'])), 409, __('reference-data.coverage.errors.after_programme_end'));

            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ["programme-coverage:{$attributes['programme_id']}:{$attributes['county_id']}"]);
            }

            $overlap = ProgrammeCountyCoverage::query()
                ->where('programme_id', $attributes['programme_id'])
                ->where('county_id', $attributes['county_id'])
                ->whereDate('starts_on', '<=', $attributes['ends_on'] ?? '9999-12-31')
                ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $attributes['starts_on']))
                ->exists();
            abort_if($overlap, 409, __('reference-data.coverage.errors.overlap'));

            $coverage = ProgrammeCountyCoverage::create([...$attributes, 'created_by' => $actor->id]);
            $this->auditLogger->record($actor, $coverage, 'reference.programme-coverage.created', __('reference-data.coverage.audit.created'), $coverage->county_id, [
                'programme_id' => $coverage->programme_id,
                'county_id' => $coverage->county_id,
                'starts_on' => $coverage->starts_on->toDateString(),
                'ends_on' => $coverage->ends_on?->toDateString(),
                'source_reference' => $coverage->source_reference,
            ]);

            return $coverage;
        });
    }
}
