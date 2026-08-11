<?php

namespace Database\Seeders;

use App\Actions\PublishWorkflowVersion;
use App\Enums\ProgrammePermission;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

class LearningWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $publisher = User::query()->whereHas('roles', fn ($query) => $query->where('name', UserRole::DevolutionAdmin->value))->first();
        if (! $publisher) {
            return;
        }$definition = WorkflowDefinition::query()->updateOrCreate(['code' => 'LEARNING-COURSE-PUBLICATION'], ['name' => 'Learning course publication', 'module' => 'e-learning', 'description' => 'Independent quality review before course publication.', 'status' => 'active']);
        if ($definition->versions()->where('status', 'published')->exists()) {
            return;
        }$version = $definition->versions()->create(['version' => 1, 'configuration' => ['initial_state' => 'draft', 'states' => ['draft', 'quality_review', 'published', 'retired'], 'terminal_states' => ['retired'], 'state_slas' => ['quality_review' => 72], 'start_permission' => ProgrammePermission::ManageLearning->value, 'transitions' => [['name' => 'submit_review', 'from' => 'draft', 'to' => 'quality_review', 'permission' => ProgrammePermission::ManageLearning->value, 'rules' => [['field' => 'lesson_count', 'operator' => 'gte', 'value' => 1], ['field' => 'question_count', 'operator' => 'gte', 'value' => 1]]], ['name' => 'publish', 'from' => 'quality_review', 'to' => 'published', 'permission' => ProgrammePermission::ReviewLearning->value, 'separation_from' => ['submit_review']], ['name' => 'return', 'from' => 'quality_review', 'to' => 'draft', 'permission' => ProgrammePermission::ReviewLearning->value, 'separation_from' => ['submit_review']], ['name' => 'retire', 'from' => 'published', 'to' => 'retired', 'permission' => ProgrammePermission::ManageLearning->value, 'terminal' => true]], 'rules' => []]]);
        app(PublishWorkflowVersion::class)->handle($version, $publisher);
    }
}
