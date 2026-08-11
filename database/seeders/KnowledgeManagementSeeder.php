<?php

namespace Database\Seeders;

use App\Actions\CreateDevolutionInnovation;
use App\Actions\CreateInnovationExperimentMilestone;
use App\Actions\CreateKnowledgeItem;
use App\Actions\RecordInnovationFundingDecision;
use App\Actions\RecordInnovationPanelReview;
use App\Actions\TransitionDevolutionInnovation;
use App\Actions\TransitionKnowledgeItem;
use App\Models\County;
use App\Models\KnowledgeDiscussion;
use App\Models\KnowledgeItem;
use App\Models\KnowledgePost;
use App\Models\LearningCourse;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Seeder;

class KnowledgeManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(CreateKnowledgeItem $createItem, TransitionKnowledgeItem $transitionItem, CreateDevolutionInnovation $createInnovation, RecordInnovationPanelReview $recordPanelReview, RecordInnovationFundingDecision $recordFundingDecision, CreateInnovationExperimentMilestone $createMilestone, TransitionDevolutionInnovation $transitionInnovation): void
    {
        if (! app()->isLocal() || KnowledgeItem::query()->exists()) {
            return;
        }

        $author = User::query()->where('email', 'devolution.admin@idmis.test')->first();
        $curator = User::query()->where('email', 'management@idmis.test')->first();
        $secondReviewer = User::query()->where('email', 'platform.admin@idmis.test')->first();
        $contributor = User::query()->where('email', 'county.official@idmis.test')->first();
        $county = County::query()->where('name', 'Mombasa')->first();
        if (! $author || ! $curator || ! $secondReviewer || ! $contributor || ! $county) {
            return;
        }

        $course = LearningCourse::query()->where('code', 'DEV-FOUND-101')->first();
        $item = $createItem->handle($author, [
            'item_type' => 'best_practice', 'title' => 'Inclusive ward participation under constrained connectivity', 'summary' => 'A practical model for combining early notice, offline capture, facilitated participation and traceable response loops.', 'content_body' => 'Counties can expand meaningful participation by publishing accessible schedules early, supporting structured offline capture at ward level, recording provenance during synchronization, and publishing how public input changed the final plan.', 'tags' => 'citizen participation, constrained connectivity, planning, evidence', 'visibility' => 'national', 'source_organization' => 'State Department for Devolution', 'external_url' => 'https://www.devolution.go.ke', 'language' => ReferenceCatalogue::defaultLanguage(), 'course_ids' => $course ? [$course->id] : [],
        ]);
        $transitionItem->handle($item, $author, ['transition' => 'submit_review', 'rationale' => 'Practice, provenance, dissemination tags and learning links are complete.']);
        $transitionItem->handle($item->refresh(), $curator, ['transition' => 'publish', 'rationale' => 'Independent editorial and institutional-quality review completed.']);

        $discussion = KnowledgeDiscussion::create(['knowledge_item_id' => $item->id, 'county_id' => null, 'created_by' => $contributor->id, 'title' => 'Making public participation inclusive', 'prompt' => 'Which tested approaches improve inclusion and close the feedback loop in low-connectivity wards?', 'status' => 'open', 'visibility' => 'national', 'last_posted_at' => now()]);
        KnowledgePost::create(['knowledge_discussion_id' => $discussion->id, 'author_id' => $contributor->id, 'body' => 'Ward-level offline toolkits paired with published response matrices improved both reach and trust.', 'is_moderated' => false, 'posted_at' => now()]);

        $curator->assignedCounties()->syncWithoutDetaching([$county->id]);
        $innovation = $createInnovation->handle($contributor, ['county_id' => $county->id, 'sector_id' => null, 'title' => 'Offline ward participation capture', 'problem_statement' => 'Low-connectivity wards cannot reliably submit complete participation records.', 'proposed_solution' => 'An offline-first signed capture workflow with deferred synchronization and provenance checks.', 'expected_impact' => 'Higher participation coverage and auditable ward submissions.', 'maturity_level' => 'prototype', 'incubation_support' => 'Security review, accessibility testing and a controlled multi-county pilot.', 'evidence_reference' => 'KM-DEMO-PROTOTYPE-001']);
        $transitionInnovation->handle($innovation, $contributor, ['transition' => 'submit', 'rationale' => 'Prototype, problem and expected impact documented.']);
        $review = ['strategic_fit_score' => 85, 'feasibility_score' => 80, 'inclusion_score' => 90, 'evidence_score' => 75, 'recommendation' => 'advance', 'rationale' => 'The prototype addresses a documented inclusion gap with credible controls and measurable evidence.'];
        $recordPanelReview->handle($innovation->refresh(), $curator, $review);
        $recordPanelReview->handle($innovation->refresh(), $secondReviewer, [...$review, 'strategic_fit_score' => 80, 'feasibility_score' => 76]);
        $transitionInnovation->handle($innovation->refresh(), $curator, ['transition' => 'accept_incubation', 'rationale' => 'Independent screening confirms strategic fit and measurable value.', 'incubation_support' => 'Threat model, accessibility verification and controlled pilot.']);
        $recordFundingDecision->handle($innovation->refresh(), $author, ['decision' => 'approved', 'amount' => 1500000, 'currency' => ReferenceCatalogue::defaultCurrency(), 'funding_type' => 'grant', 'decision_reference' => 'IFD-DEMO-2026-001', 'rationale' => 'Funding is approved against the independently screened experiment protocol and public-value outcomes.']);
        $createMilestone->handle($innovation->refresh(), $author, ['owner_id' => $contributor->id, 'title' => 'Validate offline completion and inclusion', 'hypothesis' => 'Signed offline capture increases complete ward submissions without weakening provenance.', 'success_metric' => 'Complete verified ward submissions', 'baseline_value' => '54%', 'target_value' => '75%', 'due_at' => now()->addMonth()->toDateString()]);
        $transitionInnovation->handle($innovation->refresh(), $author, ['transition' => 'start_pilot', 'rationale' => 'Incubation controls and pilot protocol approved.', 'evidence_reference' => 'KM-PILOT-DEMO-001']);
    }
}
