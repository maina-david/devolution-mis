<?php

namespace Database\Seeders;

use App\Actions\PublishWorkflowVersion;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

class KnowledgeWorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $publisher = User::query()->whereHas('roles', fn ($query) => $query->where('name', UserRole::DevolutionAdmin->value))->first();
        if (! $publisher) {
            return;
        }

        $publication = WorkflowDefinition::query()->updateOrCreate(['code' => 'KNOWLEDGE-PUBLICATION'], ['name' => 'Knowledge publication', 'module' => 'knowledge-management', 'description' => 'Editorial review and controlled publication of institutional knowledge.', 'status' => 'active']);
        if (! $publication->versions()->where('status', 'published')->exists()) {
            $version = $publication->versions()->create(['version' => 1, 'configuration' => [
                'initial_state' => 'draft', 'states' => ['draft', 'editorial_review', 'published', 'archived'], 'terminal_states' => ['archived'], 'state_slas' => ['editorial_review' => 72], 'start_permission' => ProgrammePermission::ContributeKnowledge->value,
                'transitions' => [
                    ['name' => 'submit_review', 'from' => 'draft', 'to' => 'editorial_review', 'permission' => ProgrammePermission::ContributeKnowledge->value, 'rules' => [['field' => 'has_content', 'operator' => 'eq', 'value' => true], ['field' => 'repository_ready', 'operator' => 'eq', 'value' => true]]],
                    ['name' => 'publish', 'from' => 'editorial_review', 'to' => 'published', 'permission' => ProgrammePermission::CurateKnowledge->value, 'separation_from' => ['submit_review']],
                    ['name' => 'return', 'from' => 'editorial_review', 'to' => 'draft', 'permission' => ProgrammePermission::CurateKnowledge->value, 'separation_from' => ['submit_review']],
                    ['name' => 'archive', 'from' => 'published', 'to' => 'archived', 'permission' => ProgrammePermission::ManageKnowledge->value, 'terminal' => true],
                ], 'rules' => [],
            ]]);
            app(PublishWorkflowVersion::class)->handle($version, $publisher);
        }

        $innovation = WorkflowDefinition::query()->updateOrCreate(['code' => 'KNOWLEDGE-INNOVATION-INCUBATION'], ['name' => 'Devolution innovation incubation', 'module' => 'knowledge-management', 'description' => 'Screening, incubation, piloting and scale-up governance for devolution innovations.', 'status' => 'active']);
        if ((int) $innovation->versions()->max('version') < 2) {
            $version = $innovation->versions()->create(['version' => 2, 'configuration' => [
                'initial_state' => 'draft', 'states' => ['draft', 'screening', 'incubating', 'piloting', 'scaling', 'rejected'], 'terminal_states' => ['scaling', 'rejected'], 'state_slas' => ['screening' => 240, 'incubating' => 720, 'piloting' => 2160], 'start_permission' => ProgrammePermission::ContributeKnowledge->value,
                'transitions' => [
                    ['name' => 'submit', 'from' => 'draft', 'to' => 'screening', 'permission' => ProgrammePermission::ContributeKnowledge->value, 'rules' => [['field' => 'problem_defined', 'operator' => 'eq', 'value' => true], ['field' => 'solution_defined', 'operator' => 'eq', 'value' => true], ['field' => 'impact_defined', 'operator' => 'eq', 'value' => true]]],
                    ['name' => 'accept_incubation', 'from' => 'screening', 'to' => 'incubating', 'permission' => ProgrammePermission::CurateKnowledge->value, 'separation_from' => ['submit'], 'rules' => [['field' => 'panel_ready', 'operator' => 'eq', 'value' => true]]],
                    ['name' => 'reject', 'from' => 'screening', 'to' => 'rejected', 'permission' => ProgrammePermission::CurateKnowledge->value, 'separation_from' => ['submit'], 'terminal' => true],
                    ['name' => 'start_pilot', 'from' => 'incubating', 'to' => 'piloting', 'permission' => ProgrammePermission::ManageKnowledge->value, 'rules' => [['field' => 'funding_ready', 'operator' => 'eq', 'value' => true], ['field' => 'milestones_defined', 'operator' => 'eq', 'value' => true]]],
                    ['name' => 'scale', 'from' => 'piloting', 'to' => 'scaling', 'permission' => ProgrammePermission::CurateKnowledge->value, 'rules' => [['field' => 'pilot_verified', 'operator' => 'eq', 'value' => true]], 'terminal' => true],
                ], 'rules' => [],
            ]]);
            app(PublishWorkflowVersion::class)->handle($version, $publisher);
        }

        $replication = WorkflowDefinition::query()->updateOrCreate(['code' => 'KNOWLEDGE-INNOVATION-REPLICATION'], ['name' => 'Cross-county innovation replication', 'module' => 'knowledge-management', 'description' => 'Target-county adaptation, piloting, evidence submission and independent adoption verification for scale-ready innovations.', 'status' => 'active']);
        if (! $replication->versions()->where('status', 'published')->exists()) {
            $version = $replication->versions()->create(['version' => 1, 'configuration' => [
                'initial_state' => 'planned', 'states' => ['planned', 'adapting', 'piloting', 'verification', 'adopted', 'abandoned'], 'terminal_states' => ['adopted', 'abandoned'], 'state_slas' => ['planned' => 240, 'adapting' => 720, 'piloting' => 2160, 'verification' => 240], 'start_permission' => ProgrammePermission::ManageKnowledge->value,
                'transitions' => [
                    ['name' => 'activate', 'from' => 'planned', 'to' => 'adapting', 'permission' => ProgrammePermission::ManageKnowledge->value, 'rules' => [['field' => 'adaptation_ready', 'operator' => 'eq', 'value' => true], ['field' => 'measure_ready', 'operator' => 'eq', 'value' => true]]],
                    ['name' => 'start_pilot', 'from' => 'adapting', 'to' => 'piloting', 'permission' => ProgrammePermission::ContributeKnowledge->value],
                    ['name' => 'submit_verification', 'from' => 'piloting', 'to' => 'verification', 'permission' => ProgrammePermission::ContributeKnowledge->value, 'rules' => [['field' => 'outcome_ready', 'operator' => 'eq', 'value' => true], ['field' => 'evidence_ready', 'operator' => 'eq', 'value' => true]]],
                    ['name' => 'approve', 'from' => 'verification', 'to' => 'adopted', 'permission' => ProgrammePermission::CurateKnowledge->value, 'separation_from' => ['start', 'activate', 'start_pilot', 'submit_verification'], 'rules' => [['field' => 'independent_verifier', 'operator' => 'eq', 'value' => true]], 'terminal' => true],
                    ['name' => 'return', 'from' => 'verification', 'to' => 'adapting', 'permission' => ProgrammePermission::CurateKnowledge->value, 'separation_from' => ['start', 'activate', 'start_pilot', 'submit_verification'], 'rules' => [['field' => 'independent_verifier', 'operator' => 'eq', 'value' => true]]],
                    ['name' => 'abandon', 'from' => 'planned', 'to' => 'abandoned', 'permission' => ProgrammePermission::ManageKnowledge->value, 'terminal' => true],
                    ['name' => 'abandon', 'from' => 'adapting', 'to' => 'abandoned', 'permission' => ProgrammePermission::ManageKnowledge->value, 'terminal' => true],
                    ['name' => 'abandon', 'from' => 'piloting', 'to' => 'abandoned', 'permission' => ProgrammePermission::ManageKnowledge->value, 'terminal' => true],
                ], 'rules' => [],
            ]]);
            app(PublishWorkflowVersion::class)->handle($version, $publisher);
        }

        $moderation = WorkflowDefinition::query()->updateOrCreate(['code' => 'KNOWLEDGE-COMMUNITY-MODERATION'], ['name' => 'Knowledge community moderation', 'module' => 'knowledge-management', 'description' => 'Reported community contributions enter independent triage, investigation and decision with SLA escalation.', 'status' => 'active']);
        if (! $moderation->versions()->where('status', 'published')->exists()) {
            $version = $moderation->versions()->create(['version' => 1, 'configuration' => [
                'initial_state' => 'reported', 'states' => ['reported', 'investigating', 'resolved', 'dismissed'], 'terminal_states' => ['resolved', 'dismissed'], 'state_slas' => ['reported' => 24, 'investigating' => 72], 'start_permission' => ProgrammePermission::ContributeKnowledge->value,
                'transitions' => [
                    ['name' => 'triage', 'from' => 'reported', 'to' => 'investigating', 'permission' => ProgrammePermission::CurateKnowledge->value, 'separation_from' => ['start']],
                    ['name' => 'resolve', 'from' => 'investigating', 'to' => 'resolved', 'permission' => ProgrammePermission::ManageKnowledge->value, 'separation_from' => ['triage'], 'rules' => [['field' => 'resolution_present', 'operator' => 'eq', 'value' => true]], 'terminal' => true],
                    ['name' => 'dismiss', 'from' => 'investigating', 'to' => 'dismissed', 'permission' => ProgrammePermission::ManageKnowledge->value, 'separation_from' => ['triage'], 'rules' => [['field' => 'resolution_present', 'operator' => 'eq', 'value' => true]], 'terminal' => true],
                ], 'rules' => [],
            ]]);
            app(PublishWorkflowVersion::class)->handle($version, $publisher);
        }
    }
}
