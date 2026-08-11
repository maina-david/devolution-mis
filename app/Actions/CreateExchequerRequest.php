<?php

namespace App\Actions;

use App\Models\CountyGrant;
use App\Models\ExchequerRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use App\Support\ReferenceCatalogue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateExchequerRequest
{
    public function __construct(private AuditLogger $auditLogger, private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $actor, array $attributes): ExchequerRequest
    {
        return DB::transaction(function () use ($actor, $attributes): ExchequerRequest {
            $grant = CountyGrant::query()->with('county')->whereKey($attributes['county_grant_id'])->lockForUpdate()->sole();
            abort_unless($actor->canAccessCounty($grant->county), 403);
            if (($attributes['county_id'] ?? $grant->county_id) !== $grant->county_id) {
                abort(422, 'The selected grant does not belong to the selected county.');
            }
            $committed = (float) ExchequerRequest::query()->where('county_grant_id', $grant->id)->whereNotIn('status', ['returned'])->sum('amount');
            abort_if($committed + (float) $attributes['amount'] > (float) $grant->allocated_amount, 422, 'The tranche exceeds the uncommitted grant allocation.');
            $release = $this->referenceDataReleaseResolver->forExchequerRequest($grant->county_id, now());
            $request = ExchequerRequest::create(['county_grant_id' => $grant->id, 'county_id' => $grant->county_id, 'reference_data_release_id' => $release->id, 'created_by' => $actor->id, 'request_reference' => 'EXQ-'.now()->format('Y').'-'.mb_strtoupper(Str::random(8)), 'tranche_reference' => $attributes['tranche_reference'], 'financial_year' => $grant->financial_year, 'amount' => $attributes['amount'], 'currency' => ReferenceCatalogue::defaultCurrency(), 'current_stage' => 'prepared', 'status' => 'open']);
            $this->auditLogger->record($actor, $request, 'exchequer.request.created', "Exchequer request {$request->request_reference} created.", $request->county_id, ['grant_id' => $grant->id, 'amount' => $request->amount, 'currency' => $request->currency, 'reference_data_release_id' => $release->id, 'reference_data_release_version' => $release->version, 'reference_data_release_checksum' => $release->checksum]);

            return $request;
        });
    }
}
