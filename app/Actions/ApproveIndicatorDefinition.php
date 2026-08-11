<?php

namespace App\Actions;

use App\Models\IndicatorDefinition;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveIndicatorDefinition
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(User $actor, IndicatorDefinition $indicator): IndicatorDefinition
    {
        return DB::transaction(function () use ($actor, $indicator): IndicatorDefinition {
            $draft = IndicatorDefinition::query()->lockForUpdate()->findOrFail($indicator->id);

            if ($draft->created_by === $actor->id) {
                throw ValidationException::withMessages(['indicator' => 'The author cannot approve their own indicator definition.']);
            }

            if ($draft->status !== 'draft') {
                throw ValidationException::withMessages(['indicator' => 'Only a draft indicator definition can be approved.']);
            }

            if ($draft->supersedes_id !== null) {
                $prior = IndicatorDefinition::query()->lockForUpdate()->find($draft->supersedes_id);
                if (! $prior instanceof IndicatorDefinition || ! $prior->isCurrentApprovedVersion() || $draft->version !== $prior->version + 1) {
                    throw ValidationException::withMessages(['indicator' => 'The supersession lineage is no longer valid.']);
                }
                if ($draft->effective_from === null || ($prior->effective_from !== null && $draft->effective_from->lessThanOrEqualTo($prior->effective_from))) {
                    throw ValidationException::withMessages(['effective_from' => 'The successor must take effect after the prior version.']);
                }
            }

            $draft->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now(), 'effective_from' => $draft->effective_from ?? now()]);

            if ($draft->supersedes_id !== null) {
                DB::table('devolution_project_indicator')
                    ->where('indicator_definition_id', $draft->supersedes_id)
                    ->orderBy('devolution_project_id')
                    ->get()
                    ->each(fn (object $link) => DB::table('devolution_project_indicator')->insertOrIgnore([
                        'devolution_project_id' => $link->devolution_project_id,
                        'indicator_definition_id' => $draft->id,
                        'is_primary' => $link->is_primary,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
            }

            $this->auditLogger->record($actor, $draft, 'indicator.definition.approved', "Indicator {$draft->code} version {$draft->version} approved.", metadata: ['supersedes_id' => $draft->supersedes_id]);

            return $draft;
        });
    }
}
