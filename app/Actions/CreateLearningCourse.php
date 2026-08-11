<?php

namespace App\Actions;

use App\Models\County;
use App\Models\LearningCourse;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Services\AuditLogger;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateLearningCourse
{
    public function __construct(
        private StartWorkflow $startWorkflow,
        private AuditLogger $auditLogger,
        private EffectiveReferenceDataReleaseResolver $referenceDataReleaseResolver,
    ) {}

    /** @param array<string,mixed> $attributes */
    public function handle(User $actor, array $attributes): LearningCourse
    {
        $countyId = is_string($attributes['county_id'] ?? null) ? $attributes['county_id'] : null;
        if ($countyId !== null) {
            abort_unless($actor->canAccessCounty(County::query()->findOrFail($countyId)), 403);
        }

        return DB::transaction(function () use ($actor, $attributes): LearningCourse {
            $countyId = is_string($attributes['county_id'] ?? null) ? $attributes['county_id'] : null;
            $sectorId = is_string($attributes['sector_id'] ?? null) ? $attributes['sector_id'] : null;
            $referenceDataRelease = $this->referenceDataReleaseResolver->forLearningCourse($countyId, $sectorId, now());
            $modules = collect($this->records($attributes['modules'] ?? null, 'modules'));
            $minutes = $modules->sum(fn (array $module): int => (int) collect($this->records($module['lessons'] ?? null, 'modules.*.lessons'))->sum('estimated_minutes'));
            $course = LearningCourse::create([...collect($attributes)->except('modules')->all(), 'reference_data_release_id' => $referenceDataRelease->id, 'slug' => Str::slug($attributes['code'].'-'.$attributes['title']), 'owner_id' => $actor->id, 'estimated_minutes' => $minutes, 'status' => 'draft', 'created_by' => $actor->id]);
            foreach ($modules->values() as $moduleIndex => $moduleData) {
                $lessons = collect($this->records($moduleData['lessons'] ?? null, 'modules.*.lessons'));
                $module = $course->modules()->create([...collect($moduleData)->except('lessons')->all(), 'sequence' => $moduleIndex + 1, 'is_required' => true]);
                foreach ($lessons->values() as $lessonIndex => $lessonData) {
                    $questions = collect($this->records($lessonData['questions'] ?? [], 'modules.*.lessons.*.questions'));
                    $source = (string) ($lessonData['content_body'] ?? $lessonData['content_url'] ?? '');
                    $lesson = $module->lessons()->create([...collect($lessonData)->except('questions')->all(), 'content_checksum' => hash('sha256', $source), 'sequence' => $lessonIndex + 1, 'is_required' => true]);
                    foreach ($questions->values() as $questionIndex => $question) {
                        $lesson->questions()->create([...$question, 'sequence' => $questionIndex + 1]);
                    }
                }
            }
            $definition = WorkflowDefinition::query()->where('code', 'LEARNING-COURSE-PUBLICATION')->firstOrFail();
            $lessonCount = $course->modules()->withCount('lessons')->get()->sum('lessons_count');
            $questionCount = $course->modules()->with('lessons.questions')->get()->sum(fn ($module) => $module->lessons->sum(fn ($lesson) => $lesson->questions->count()));
            $instance = $this->startWorkflow->handle($definition, $course, $actor, ['lesson_count' => $lessonCount, 'question_count' => $questionCount]);
            $course->update(['workflow_instance_id' => $instance->id]);
            $this->auditLogger->record($actor, $course, 'learning.course.created', "Course {$course->code} created with {$lessonCount} lessons.", $course->county_id, [
                'reference_data_release_id' => $referenceDataRelease->id,
                'reference_data_release_version' => $referenceDataRelease->version,
                'reference_data_release_checksum' => $referenceDataRelease->checksum,
            ]);

            return $course->refresh();
        });
    }

    /** @return list<array<string, mixed>> */
    private function records(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("{$field} must be an array.");
        }

        return array_values(array_map(function (mixed $record) use ($field): array {
            if (! is_array($record)) {
                throw new InvalidArgumentException("Every {$field} entry must be an object.");
            }

            return $record;
        }, $value));
    }
}
