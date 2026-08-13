<?php

namespace App\Actions;

use App\Models\CitizenCase;
use App\Models\User;
use App\Notifications\ProgrammeAlert;
use App\Services\ProgrammeCountyScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BulkTriageCitizenCases
{
    public function __construct(
        private TriageCitizenCase $triageCase,
        private ProgrammeCountyScope $countyScope,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $actor, array $attributes): int
    {
        return DB::transaction(function () use ($actor, $attributes): int {
            $selectionMode = (string) $attributes['selection_mode'];
            $ids = is_array($attributes['ids'] ?? null) ? $attributes['ids'] : [];
            $search = trim((string) ($attributes['search'] ?? ''));
            $query = CitizenCase::query()
                ->whereIn('county_id', $this->countyScope->query($actor)->select('id'))
                ->when($selectionMode === 'selected', fn ($query) => $query->whereKey($ids))
                ->when($selectionMode === 'filtered' && filled($attributes['from'] ?? null), fn ($query) => $query->whereDate('created_at', '>=', $attributes['from']))
                ->when($selectionMode === 'filtered' && filled($attributes['to'] ?? null), fn ($query) => $query->whereDate('created_at', '<=', $attributes['to']))
                ->when($selectionMode === 'filtered' && $search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('reference', 'ilike', "%{$search}%")
                    ->orWhere('subject', 'ilike', "%{$search}%")
                    ->orWhere('category', 'ilike', "%{$search}%")
                    ->orWhere('status', 'ilike', "%{$search}%")));
            /** @var Collection<int, CitizenCase> $cases */
            $cases = $query
                ->with('county')
                ->orderBy('id')
                ->limit(101)
                ->lockForUpdate()
                ->get();
            abort_if($cases->count() > 100, 422, __('citizen.casework.errors.bulk_limit'));
            abort_if($cases->isEmpty(), 422, __('citizen.casework.errors.bulk_empty'));
            if ($selectionMode === 'selected') {
                abort_unless($cases->count() === count($ids), 403, __('citizen.casework.errors.bulk_unauthorized'));
            }
            abort_if($cases->contains(fn (CitizenCase $case): bool => $case->status !== 'received' || $case->workflow_instance_id !== null), 409, __('citizen.casework.errors.bulk_untriaged_required'));

            $assignee = User::query()->whereKey($attributes['assigned_to'])->firstOrFail();
            abort_unless($assignee->can('citizen-cases:respond'), 422, __('citizen.casework.errors.assignee_response_permission'));
            abort_if($cases->contains(fn (CitizenCase $case): bool => ! $assignee->canAccessCounty($case->county)), 422, __('citizen.casework.errors.assignee_all_counties'));

            $triageAttributes = Arr::only($attributes, ['assigned_to', 'assigned_organization_id', 'sector_id', 'priority', 'is_sensitive', 'triage_note']);
            foreach ($cases as $case) {
                $this->triageCase->handle($case, $actor, $triageAttributes);
                DB::afterCommit(fn () => $assignee->notify(ProgrammeAlert::translated('citizen.casework.notifications.assigned_title', 'citizen.casework.notifications.assigned_message', 'citizen-cases', messageParameters: ['reference' => $case->reference, 'subject' => $case->subject])));
            }

            return $cases->count();
        });
    }
}
