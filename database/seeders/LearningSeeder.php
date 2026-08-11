<?php

namespace Database\Seeders;

use App\Actions\CreateLearningCourse;
use App\Actions\EnrollLearner;
use App\Actions\GradeLearningAssessment;
use App\Actions\RecordLearningProgress;
use App\Actions\StoreLinkedDocument;
use App\Actions\TransitionLearningCourse;
use App\Models\LearningCourse;
use App\Models\LearningLesson;
use App\Models\User;
use App\Models\VirtualClassroom;
use App\Services\AuditLogger;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class LearningSeeder extends Seeder
{
    public function run(CreateLearningCourse $createCourse, TransitionLearningCourse $transitionCourse, EnrollLearner $enroll, RecordLearningProgress $progress, GradeLearningAssessment $grade, StoreLinkedDocument $storeDocument, AuditLogger $auditLogger): void
    {
        if (! app()->isLocal() || LearningCourse::query()->exists()) {
            return;
        }$author = User::query()->where('email', 'devolution.admin@idmis.test')->first();
        $reviewer = User::query()->where('email', 'management@idmis.test')->first();
        $learner = User::query()->where('email', 'county.official@idmis.test')->first();
        if (! $author || ! $reviewer || ! $learner) {
            return;
        }$course = $createCourse->handle($author, ['code' => 'DEV-FOUND-101', 'title' => 'Foundations of Accountable Devolution Delivery', 'summary' => 'Core constitutional, delivery and evidence practices for national and county practitioners.', 'description' => 'A blended capacity-building course combining guided reading, a practical implementation manual, a live workshop and an assessed knowledge check.', 'category' => 'Devolution foundations', 'level' => 'foundation', 'delivery_mode' => 'blended', 'language' => ReferenceCatalogue::defaultLanguage(), 'passing_score' => 70, 'maximum_attempts' => 3, 'sector_id' => null, 'county_id' => null, 'modules' => [['title' => 'Core foundations', 'description' => 'Self-paced foundations and operational resources.', 'lessons' => [['title' => 'Principles of accountable devolution', 'summary' => 'Read the core constitutional and service-delivery principles.', 'content_type' => 'text', 'content_body' => 'Accountable devolution connects assigned functions, public resources, citizen participation, transparent evidence and measurable service-delivery results.', 'content_url' => null, 'estimated_minutes' => 20, 'is_downloadable' => false], ['title' => 'County implementation toolkit', 'summary' => 'Use the toolkit to structure an evidence-backed delivery review.', 'content_type' => 'manual', 'content_url' => null, 'estimated_minutes' => 25, 'is_downloadable' => true], ['title' => 'Foundations knowledge check', 'summary' => 'Demonstrate understanding of accountable devolved delivery.', 'content_type' => 'quiz', 'content_url' => null, 'estimated_minutes' => 10, 'is_downloadable' => false, 'questions' => [['question' => 'Which combination best demonstrates accountable devolution?', 'options' => ['A' => 'Clear functions, evidence, participation and measurable results', 'B' => 'Unattributed decisions without public evidence'], 'correct_option' => 'A', 'explanation' => 'Accountability requires traceable responsibilities, evidence, participation and results.', 'points' => 1]]]]]]]);
        $manual = $course->modules()->with('lessons')->get()->flatMap->lessons->firstWhere('content_type', 'manual');
        if (! $manual instanceof LearningLesson) {
            throw new RuntimeException('The local learning baseline is missing its required manual lesson.');
        }
        $this->storeManualAsset($manual, $author, $storeDocument, $auditLogger);
        $transitionCourse->handle($course, $author, ['transition' => 'submit_review', 'rationale' => 'Multimedia course and knowledge check submitted for quality assurance.']);
        $transitionCourse->handle($course->refresh(), $reviewer, ['transition' => 'publish', 'rationale' => 'Content, learning outcomes and assessment independently reviewed and approved.']);
        VirtualClassroom::create(['learning_course_id' => $course->id, 'facilitator_id' => $reviewer->id, 'title' => 'Live devolution delivery clinic', 'description' => 'Facilitated workshop applying the course toolkit to county delivery scenarios.', 'starts_at' => now()->addWeeks(2), 'ends_at' => now()->addWeeks(2)->addHours(2), 'platform' => 'Microsoft Teams', 'join_url' => 'https://teams.microsoft.com/l/meetup-join/idmis-demo', 'capacity' => 100, 'status' => 'scheduled', 'created_by' => $author->id]);
        $enrollment = $enroll->handle($course->refresh(), $learner);
        foreach ($course->modules()->with('lessons')->get()->flatMap->lessons->where('content_type', '!=', 'quiz') as $lesson) {
            $progress->handle($enrollment, $lesson, $learner, ['time_spent_seconds' => max(60, $lesson->estimated_minutes * 60), 'state' => ['seeded' => true]]);
        }$question = $course->modules()->with('lessons.questions')->get()->flatMap->lessons->flatMap->questions->sole();
        $grade->handle($enrollment->refresh(), $learner, [$question->id => 'A']);
    }

    private function storeManualAsset(LearningLesson $lesson, User $author, StoreLinkedDocument $storeDocument, AuditLogger $auditLogger): void
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'idmis-learning-manual-');
        if (! is_string($temporaryPath)) {
            throw new RuntimeException('Unable to create the local learning manual seed file.');
        }

        try {
            $contents = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n".str_repeat('County implementation toolkit: accountable delivery evidence and review guidance. ', 20)."\n%%EOF";
            if (file_put_contents($temporaryPath, $contents) === false) {
                throw new RuntimeException('Unable to write the local learning manual seed file.');
            }
            $file = new UploadedFile($temporaryPath, 'county-implementation-toolkit.pdf', 'application/pdf', null, true);
            $document = $storeDocument->handle($lesson, $author, $file, ['title' => 'County implementation toolkit governed asset', 'category' => 'Learning manual', 'source_type' => 'digital', 'purpose' => 'learning-lesson-asset', 'county_id' => null, 'mime_type' => 'application/pdf']);
            $metadata = ['repository_asset_id' => $document->id, 'rights_holder' => 'State Department for Devolution', 'licence' => 'permission_granted', 'accessible_alternative' => 'Structured text alternative covering the toolkit objectives, evidence checklist and delivery-review steps.', 'transcript_available' => false, 'asset_source_type' => 'digital', 'uploaded_at' => now()->toIso8601String()];
            $lesson->update(['mime_type' => $document->mime_type, 'content_checksum' => $document->content_checksum, 'is_downloadable' => true, 'metadata' => $metadata]);
            $auditLogger->record($author, $lesson, 'learning.lesson_asset_registered', "Governed asset registered for lesson {$lesson->title}.", metadata: ['document_id' => $document->id, 'content_checksum' => $document->content_checksum, 'licence' => $metadata['licence']]);
        } finally {
            @unlink($temporaryPath);
        }
    }
}
