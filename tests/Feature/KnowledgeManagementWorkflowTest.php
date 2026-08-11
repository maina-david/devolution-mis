<?php

namespace Tests\Feature;

use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\DevolutionInnovation;
use App\Models\DocumentExtraction;
use App\Models\DocumentVersion;
use App\Models\KnowledgeDiscussion;
use App\Models\KnowledgeItem;
use App\Models\LearningCourse;
use App\Models\ReferenceDataRelease;
use App\Models\Sector;
use App\Models\User;
use App\Support\CanonicalJson;
use Database\Seeders\KnowledgeWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KnowledgeManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_resource_is_independently_published_linked_to_learning_and_exported(): void
    {
        $author = User::factory()->devolutionAdmin()->create();
        $curator = User::factory()->platformAdmin()->create();
        $county = County::factory()->create();
        $sector = Sector::factory()->create();
        $course = LearningCourse::factory()->create(['owner_id' => $author, 'created_by' => $author, 'status' => 'published']);
        $this->seed(KnowledgeWorkflowSeeder::class);
        $release = $this->publishedReferenceRelease([$county], [$sector], $author);

        $this->actingAs($author)->post(route('knowledge.items.store', $author->currentTeam->slug), $this->itemPayload(['county_id' => $county->id, 'sector_id' => $sector->id, 'course_ids' => [$course->id]]))->assertRedirect();
        $item = KnowledgeItem::query()->with('courses', 'workflowInstance.transitions')->sole();
        $this->assertTrue(Str::isUuid($item->id));
        $this->assertSame(['citizen participation', 'planning'], $item->tags);
        $this->assertSame($course->id, $item->courses->sole()->id);
        $this->assertSame($release->id, $item->reference_data_release_id);
        $this->assertSame('draft', $item->status);

        $this->actingAs($author)->patch(route('knowledge.items.transition', [$author->currentTeam->slug, $item]), ['transition' => 'submit_review', 'rationale' => 'Evidence, provenance, and learning links are ready for editorial review.'])->assertRedirect();
        $this->actingAs($author)->patch(route('knowledge.items.transition', [$author->currentTeam->slug, $item]), ['transition' => 'publish', 'rationale' => 'Attempted self-publication.'])->assertForbidden();
        $this->actingAs($curator)->patch(route('knowledge.items.transition', [$curator->currentTeam->slug, $item]), ['transition' => 'publish', 'rationale' => 'Independent editorial, accessibility, and provenance review passed.'])->assertRedirect();
        $this->assertSame('published', $item->refresh()->status);
        $this->assertNotNull($item->published_on);
        $this->assertNotNull($item->review_due_at);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $item->id, 'action' => 'knowledge.item.transitioned']);

        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($author)->get(route('workspace.export', [$author->currentTeam->slug, 'knowledge', $format]))->assertOk()->assertDownload();
        }
        $csv = $this->actingAs($author)->get(route('workspace.export', [$author->currentTeam->slug, 'knowledge', 'csv']))->streamedContent();
        $this->assertStringContainsString('Reference release', $csv);
        $this->assertStringContainsString($release->checksum, $csv);
        $this->actingAs($author)->get(route('knowledge.index', $author->currentTeam->slug))->assertOk()->assertInertia(fn ($page) => $page
            ->where('catalogue.available', true)
            ->where('items.data.0.referenceData.version', $release->version)
            ->where('items.data.0.referenceData.checksum', $release->checksum));
    }

    public function test_community_of_practice_discussions_and_posts_are_traceable(): void
    {
        $author = User::factory()->devolutionAdmin()->create();
        $curator = User::factory()->platformAdmin()->create();
        $participant = User::factory()->countyOfficial(County::factory()->create())->create();
        $this->seed(KnowledgeWorkflowSeeder::class);
        $this->publishedReferenceRelease([], [], $author);
        $this->actingAs($author)->post(route('knowledge.items.store', $author->currentTeam->slug), $this->itemPayload())->assertRedirect();
        $item = KnowledgeItem::query()->sole();
        $this->actingAs($author)->patch(route('knowledge.items.transition', [$author->currentTeam->slug, $item]), ['transition' => 'submit_review', 'rationale' => 'Ready for editorial review.'])->assertRedirect();
        $this->actingAs($curator)->patch(route('knowledge.items.transition', [$curator->currentTeam->slug, $item]), ['transition' => 'publish', 'rationale' => 'Approved for national learning.'])->assertRedirect();

        $this->actingAs($participant)->post(route('knowledge.discussions.store', $participant->currentTeam->slug), ['knowledge_item_id' => $item->id, 'title' => 'Making public participation inclusive', 'prompt' => 'Which tested approaches improve inclusion under constrained connectivity?', 'visibility' => 'national'])->assertRedirect();
        $discussion = KnowledgeDiscussion::query()->sole();
        $this->actingAs($participant)->post(route('knowledge.posts.store', [$participant->currentTeam->slug, $discussion]), ['body' => 'Ward-level offline toolkits and structured feedback loops improved participation.'])->assertRedirect();
        $this->assertDatabaseHas('knowledge_posts', ['knowledge_discussion_id' => $discussion->id, 'author_id' => $participant->id, 'is_moderated' => false]);
        $this->actingAs($participant)->get(route('knowledge.index', $participant->currentTeam->slug))->assertOk()->assertInertia(fn ($page) => $page->where('items.total', 1)->where('items.data.0.discussions.0.posts.0.author', $participant->name));
    }

    public function test_innovation_incubation_and_county_visibility_enforce_portfolio_scope_and_separation_of_duties(): void
    {
        $county = County::factory()->create();
        $submitter = User::factory()->countyOfficial($county)->create();
        User::factory()->devolutionAdmin()->create();
        $this->seed(KnowledgeWorkflowSeeder::class);
        $this->publishedReferenceRelease([$county], [], $submitter);

        $this->actingAs($submitter)->post(route('knowledge.innovations.store', $submitter->currentTeam->slug), ['county_id' => $county->id, 'title' => 'Offline ward participation capture', 'problem_statement' => 'Low-connectivity wards cannot reliably submit participation records.', 'proposed_solution' => 'An offline-first signed capture workflow with deferred synchronization.', 'expected_impact' => 'Higher inclusion and complete provenance for ward submissions.', 'maturity_level' => 'prototype', 'incubation_support' => 'Security review and three-county pilot.', 'evidence_reference' => 'https://innovation.example.test/prototypes/offline-capture'])->assertRedirect();
        $innovation = DevolutionInnovation::query()->sole();
        $this->actingAs($submitter)->patch(route('knowledge.innovations.transition', [$submitter->currentTeam->slug, $innovation]), ['transition' => 'submit', 'rationale' => 'Prototype and expected impact documented.'])->assertRedirect();
        $this->actingAs($submitter)->patch(route('knowledge.innovations.transition', [$submitter->currentTeam->slug, $innovation]), ['transition' => 'accept_incubation', 'rationale' => 'Attempted self-approval.'])->assertForbidden();
    }

    public function test_ranked_search_prioritizes_titles_and_discovers_linked_ocr_text(): void
    {
        $user = User::factory()->devolutionAdmin()->create();
        $titleMatch = KnowledgeItem::factory()->create(['author_id' => $user->id, 'status' => 'published', 'title' => 'Participatory budgeting handbook', 'summary' => 'Practical county guidance.', 'content_body' => null]);
        KnowledgeItem::factory()->create(['author_id' => $user->id, 'status' => 'published', 'title' => 'General planning guide', 'summary' => 'A short reference to participatory budgeting.', 'content_body' => null]);
        $document = AssessmentDocument::factory()->create();
        $version = DocumentVersion::factory()->create(['assessment_document_id' => $document->id]);
        $document->update(['current_version_id' => $version->id]);
        DocumentExtraction::factory()->create(['document_version_id' => $version->id, 'extracted_text' => 'Ward committees documented indigenous resilience practices through a scanned field report.']);
        $ocrMatch = KnowledgeItem::factory()->create(['author_id' => $user->id, 'status' => 'published', 'assessment_document_id' => $document->id, 'title' => 'County field report', 'summary' => 'Digitized programme evidence.', 'content_body' => null]);

        $this->actingAs($user)->get(route('knowledge.index', [$user->currentTeam->slug, 'search' => 'participatory budgeting']))->assertOk()->assertInertia(fn ($page) => $page
            ->where('items.total', 2)
            ->where('items.data.0.id', $titleMatch->id));

        $this->actingAs($user)->get(route('knowledge.index', [$user->currentTeam->slug, 'search' => 'indigenous resilience']))->assertOk()->assertInertia(fn ($page) => $page
            ->where('items.total', 1)
            ->where('items.data.0.id', $ocrMatch->id)
            ->where('items.data.0.searchExcerpt', 'Ward committees documented indigenous resilience practices through a scanned field report.'));
    }

    public function test_resource_creation_fails_closed_without_a_checksum_valid_effective_catalogue(): void
    {
        $author = User::factory()->devolutionAdmin()->create();
        $this->seed(KnowledgeWorkflowSeeder::class);

        $this->actingAs($author)->post(route('knowledge.items.store', $author->currentTeam->slug), $this->itemPayload())->assertStatus(409);
        $this->actingAs($author)->post(route('knowledge.innovations.store', $author->currentTeam->slug), $this->innovationPayload())->assertStatus(409);
        $this->assertDatabaseCount('knowledge_items', 0);
        $this->actingAs($author)->get(route('knowledge.index', $author->currentTeam->slug))->assertOk()->assertInertia(fn ($page) => $page->where('catalogue.available', false));

        $this->publishedReferenceRelease([], [], $author, str_repeat('0', 64));
        $this->actingAs($author)->post(route('knowledge.items.store', $author->currentTeam->slug), $this->itemPayload())->assertStatus(409);
        $this->actingAs($author)->post(route('knowledge.innovations.store', $author->currentTeam->slug), $this->innovationPayload())->assertStatus(409);
        $this->assertDatabaseCount('knowledge_items', 0);
    }

    public function test_county_contributor_cannot_target_a_governed_county_outside_their_portfolio(): void
    {
        $home = County::factory()->create();
        $outside = County::factory()->create();
        $contributor = User::factory()->countyOfficial($home)->create();
        $this->seed(KnowledgeWorkflowSeeder::class);
        $this->publishedReferenceRelease([$outside], [], $contributor);

        $this->actingAs($contributor)
            ->post(route('knowledge.items.store', $contributor->currentTeam->slug), $this->itemPayload(['county_id' => $outside->id, 'visibility' => 'county']))
            ->assertForbidden();
        $this->actingAs($contributor)
            ->post(route('knowledge.innovations.store', $contributor->currentTeam->slug), $this->innovationPayload(['county_id' => $outside->id]))
            ->assertForbidden();
        $this->assertDatabaseCount('knowledge_items', 0);
        $this->actingAs($contributor)->get(route('knowledge.index', $contributor->currentTeam->slug))->assertOk()->assertInertia(fn ($page) => $page
            ->where('catalogue.available', true)
            ->has('options.counties', 0));
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function itemPayload(array $overrides = []): array
    {
        return [...['item_type' => 'best_practice', 'title' => 'Inclusive public participation under constrained connectivity', 'summary' => 'A field-tested model combining ward facilitation, offline evidence capture and structured response loops.', 'content_body' => 'Counties can improve inclusion by publishing participation schedules early, supporting offline capture, and closing the feedback loop with traceable responses.', 'tags' => 'Citizen Participation, Planning, citizen participation', 'visibility' => 'national', 'source_organization' => 'State Department for Devolution', 'language' => 'en'], ...$overrides];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function innovationPayload(array $overrides = []): array
    {
        return [...['title' => 'Offline ward participation capture', 'problem_statement' => 'Low-connectivity wards cannot reliably submit participation records.', 'proposed_solution' => 'An offline-first signed capture workflow with deferred synchronization.', 'expected_impact' => 'Higher inclusion and complete provenance for ward submissions.', 'maturity_level' => 'prototype', 'incubation_support' => 'Security review and controlled pilot.', 'evidence_reference' => 'INNOVATION-EVIDENCE-001'], ...$overrides];
    }

    /**
     * @param  list<County>  $counties
     * @param  list<Sector>  $sectors
     */
    private function publishedReferenceRelease(array $counties, array $sectors, User $approver, ?string $checksum = null): ReferenceDataRelease
    {
        $snapshot = [
            'counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id])->all(),
            'organizations' => [],
            'sectors' => collect($sectors)->map(fn (Sector $sector): array => ['id' => $sector->id])->all(),
            'programmes' => [],
            'programme_county_coverages' => [],
        ];
        $version = ((int) ReferenceDataRelease::query()->max('version')) + 1;

        return ReferenceDataRelease::factory()->create([
            'version' => $version,
            'approved_by' => $approver->id,
            'status' => 'published',
            'snapshot' => $snapshot,
            'checksum' => $checksum ?? app(CanonicalJson::class)->checksum($snapshot),
            'approval_reference' => 'SDD-MDM-KNOWLEDGE-'.str_pad((string) $version, 3, '0', STR_PAD_LEFT),
            'effective_from' => now()->subMinute(),
            'published_at' => now(),
        ]);
    }
}
