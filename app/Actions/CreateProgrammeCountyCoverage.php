<?php

namespace App\Actions;

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
            $programme = Programme::query()->lockForUpdate()->find($attributes['programme_id']);
            abort_unless($programme !== null, 409, 'The selected programme is no longer available.');
            abort_if($programme->starts_on !== null && $programme->starts_on->isAfter($attributes['starts_on']), 409, 'County coverage cannot begin before the programme starts.');
            abort_if($programme->ends_on !== null && (blank($attributes['ends_on'] ?? null) || $programme->ends_on->isBefore($attributes['ends_on'])), 409, 'County coverage must end on or before the programme end date.');

            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ["programme-coverage:{$attributes['programme_id']}:{$attributes['county_id']}"]);
            }

            $overlap = ProgrammeCountyCoverage::query()
                ->where('programme_id', $attributes['programme_id'])
                ->where('county_id', $attributes['county_id'])
                ->whereDate('starts_on', '<=', $attributes['ends_on'] ?? '9999-12-31')
                ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $attributes['starts_on']))
                ->exists();
            abort_if($overlap, 409, 'This programme already has overlapping coverage for the selected county.');

            $coverage = ProgrammeCountyCoverage::create([...$attributes, 'created_by' => $actor->id]);
            $this->auditLogger->record($actor, $coverage, 'reference.programme-coverage.created', 'Programme county coverage created.', $coverage->county_id, [
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
