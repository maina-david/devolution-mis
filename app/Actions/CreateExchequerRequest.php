<?php

namespace App\Actions;

use App\Enums\ProgrammePermission;
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
        abort_unless($actor->can(ProgrammePermission::ManageGrants->value), 403, __('exchequer.create_unauthorized'));

        return DB::transaction(function () use ($actor, $attributes): ExchequerRequest {
            $grant = CountyGrant::query()->with('county')->whereKey($attributes['county_grant_id'])->lockForUpdate()->sole();
            abort_unless($actor->canAccessCounty($grant->county), 403, __('exchequer.county_scope_denied'));
            if (($attributes['county_id'] ?? $grant->county_id) !== $grant->county_id) {
                abort(422, __('exchequer.grant_county_mismatch'));
            }
            $committed = (float) ExchequerRequest::query()->where('county_grant_id', $grant->id)->whereNotIn('status', ['returned'])->sum('amount');
            abort_if($committed + (float) $attributes['amount'] > (float) $grant->allocated_amount, 422, __('exchequer.allocation_exceeded'));
            $release = $this->referenceDataReleaseResolver->forExchequerRequest($grant->county_id, now());
            $request = ExchequerRequest::create(['county_grant_id' => $grant->id, 'county_id' => $grant->county_id, 'reference_data_release_id' => $release->id, 'created_by' => $actor->id, 'request_reference' => 'EXQ-'.now()->format('Y').'-'.mb_strtoupper(Str::random(8)), 'tranche_reference' => $attributes['tranche_reference'], 'financial_year' => $grant->financial_year, 'amount' => $attributes['amount'], 'currency' => ReferenceCatalogue::defaultCurrency(), 'current_stage' => 'prepared', 'status' => 'open']);
            $this->auditLogger->record($actor, $request, 'exchequer.request.created', __('exchequer.request_created_audit', ['reference' => $request->request_reference]), $request->county_id, ['grant_id' => $grant->id, 'amount' => $request->amount, 'currency' => $request->currency, 'reference_data_release_id' => $release->id, 'reference_data_release_version' => $release->version, 'reference_data_release_checksum' => $release->checksum]);

            return $request;
        });
    }
}
