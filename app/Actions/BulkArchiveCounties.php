<?php

namespace App\Actions;

use App\Models\County;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BulkArchiveCounties
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @param list<string> $ids */
    public function handle(User $actor, array $ids): int
    {
        return DB::transaction(function () use ($actor, $ids): int {
            /** @var Collection<int, County> $counties */
            $counties = County::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            abort_unless($counties->count() === count($ids), 404, __('reference-data.governance.errors.counties_unavailable'));

            foreach ($counties as $county) {
                abort_if($county->code >= 1 && $county->code <= 47, 409, __('reference-data.governance.errors.constitutional_county', ['name' => $county->name]));
                abort_if($county->users()->exists() || $county->assignedUsers()->exists() || $county->assessments()->exists() || $county->documents()->exists() || $county->grants()->exists() || $county->programmeCoverages()->exists(), 409, __('reference-data.governance.errors.county_referenced', ['name' => $county->name]));
            }

            foreach ($counties as $county) {
                $this->auditLogger->record($actor, $county, 'reference.county.archived', __('reference-data.governance.audit.county_archived', ['name' => $county->name]), $county->id, ['code' => $county->code]);
                $county->delete();
            }

            return $counties->count();
        });
    }
}
