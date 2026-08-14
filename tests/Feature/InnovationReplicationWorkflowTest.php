<?php

namespace Tests\Feature;

use App\Actions\CreateInnovationReplication;
use App\Actions\UpdateInnovationReplication;
use App\Actions\VerifyInnovationReplication;
use App\Models\County;
use App\Models\DevolutionInnovation;
use App\Models\InnovationReplication;
use App\Models\ReferenceDataRelease;
use App\Models\User;
use App\Support\CanonicalJson;
use Database\Seeders\KnowledgeWorkflowSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InnovationReplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_scale_ready_innovation_is_adapted_evidenced_and_independently_verified_in_another_county(): void
    {
        Storage::fake();
        $sourceCounty = County::factory()->create();
        $targetCounty = County::factory()->create(['logo_path' => '/images/counties/mombasa.webp']);
        $creator = User::factory()->devolutionAdmin()->create();
        $adopter = User::factory()->countyOfficial($targetCounty)->create();
        $verifier = User::factory()->platformAdmin()->create();
        $release = $this->publishedReferenceRelease([$sourceCounty, $targetCounty], $creator);
        $innovation = DevolutionInnovation::factory()->create(['county_id' => $sourceCounty->id, 'reference_data_release_id' => $release->id, 'submitted_by' => $creator->id, 'status' => 'scaling', 'stage' => 'scale']);
        $this->seed(KnowledgeWorkflowSeeder::class);

        $this->actingAs($creator)->post(route('knowledge.innovation-replications.store'), $this->payload($innovation, $targetCounty, $adopter))->assertRedirect();
        $replication = InnovationReplication::query()->with('workflowInstance.transitions')->sole();
        $this->assertTrue(Str::isUuid($replication->id));
        $this->assertSame($sourceCounty->id, $replication->source_county_id);
        $this->assertSame($targetCounty->id, $replication->target_county_id);
        $this->assertSame($release->id, $replication->reference_data_release_id);
        $this->assertSame('planned', $replication->status);
        $this->assertNotNull($replication->workflow_instance_id);

        $this->actingAs($creator)->patch(route('knowledge.innovation-replications.update', [$replication]), ['transition' => 'activate', 'rationale' => 'Target-county adoption authority, accountable owner and measurable implementation plan confirmed.'])->assertRedirect();
        $this->actingAs($adopter)->patch(route('knowledge.innovation-replications.update', [$replication]), ['transition' => 'start_pilot', 'rationale' => 'Local process adaptation and implementation team readiness checks are complete.'])->assertRedirect();
        $this->actingAs($adopter)->patch(route('knowledge.innovation-replications.update', [$replication]), ['transition' => 'submit_verification', 'actual_value' => 87, 'outcome_summary' => 'The target county achieved complete and timely submissions in 87 percent of participating wards.', 'rationale' => 'Measured pilot outcome is ready for independent verification.'])->assertSessionHasErrors('document');

        $this->actingAs($adopter)->post(route('knowledge.innovation-replications.documents.store', [$replication]), ['title' => 'Signed target-county pilot outcome report', 'category' => 'Replication evidence', 'source_type' => 'scanned', 'document' => UploadedFile::fake()->image('signed-outcome.jpg')])->assertRedirect();
        $this->actingAs($adopter)->patch(route('knowledge.innovation-replications.update', [$replication]), ['transition' => 'submit_verification', 'actual_value' => 87, 'outcome_summary' => 'The target county achieved complete and timely submissions in 87 percent of participating wards.', 'rationale' => 'Measured outcome and clean signed evidence are ready for independent verification.'])->assertRedirect();
        $this->assertSame('verification', $replication->refresh()->status);

        $this->actingAs($adopter)->patch(route('knowledge.innovation-replications.verify', [$replication]), ['decision' => 'approve', 'rationale' => 'Attempted self-verification of the submitted target-county outcome evidence.'])->assertForbidden();
        $this->actingAs($verifier)->patch(route('knowledge.innovation-replications.verify', [$replication]), ['decision' => 'approve', 'rationale' => 'Independent review confirmed the measure, signed evidence, local adaptation and target-county outcome.'])->assertRedirect();
        $replication->refresh();
        $this->assertSame('adopted', $replication->status);
        $this->assertSame('approved', $replication->verification_decision);
        $this->assertSame(64, strlen((string) $replication->decision_checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $replication->id, 'action' => 'knowledge.innovation_replication.verified']);

        $this->actingAs($verifier)->get(route('knowledge.innovation-replications.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.adopted', 1)
            ->where('replications.data.0.targetCounty.kind', 'county')
            ->where('replications.data.0.targetCounty.logoUrl', '/images/counties/mombasa.webp')
            ->where('replications.data.0.referenceData.version', $release->version)
            ->where('replications.data.0.documents.0.scanStatus', 'clean'));
        foreach (['csv', 'xlsx', 'json', 'pdf'] as $format) {
            $this->actingAs($verifier)->get(route('knowledge.innovation-replications.export', [$format]))->assertOk()->assertDownload();
        }
        $this->actingAs($verifier)->getJson(route('search.global', ['q' => $replication->reference]))->assertOk()->assertJsonFragment(['category' => 'Innovation replication', 'id' => $replication->id]);

        $page = file_get_contents(resource_path('js/pages/knowledge/innovation-replications.tsx'));
        $this->assertIsString($page);
        foreach (['<FormSheet', '<Sheet', '<SearchableSelect', '<DatePickerField', '<WorkspaceDataTable', 'storeDocument.form', 'previewEvidence.url', "['csv', 'xlsx', 'json', 'pdf']"] as $contract) {
            $this->assertStringContainsString($contract, $page);
        }
        $this->assertStringNotContainsString('type="date"', $page);
    }

    public function test_replication_rejects_invalid_source_target_and_cross_county_scope(): void
    {
        $sourceCounty = County::factory()->create();
        $targetCounty = County::factory()->create();
        $outsideCounty = County::factory()->create();
        $creator = User::factory()->devolutionAdmin()->create();
        $targetAdmin = User::factory()->countyAdmin($targetCounty)->create();
        $outsideUser = User::factory()->countyOfficial($outsideCounty)->create();
        $adopter = User::factory()->countyOfficial($targetCounty)->create();
        $release = $this->publishedReferenceRelease([$sourceCounty, $targetCounty, $outsideCounty], $creator);
        $draft = DevolutionInnovation::factory()->create(['county_id' => $sourceCounty->id, 'reference_data_release_id' => $release->id, 'submitted_by' => $creator->id, 'status' => 'draft']);
        $scaled = DevolutionInnovation::factory()->create(['county_id' => $sourceCounty->id, 'reference_data_release_id' => $release->id, 'submitted_by' => $creator->id, 'status' => 'scaling', 'stage' => 'scale']);
        $this->seed(KnowledgeWorkflowSeeder::class);

        $this->actingAs($creator)->post(route('knowledge.innovation-replications.store'), $this->payload($draft, $targetCounty, $adopter))->assertSessionHasErrors('devolution_innovation_id');
        $this->actingAs($creator)->post(route('knowledge.innovation-replications.store'), $this->payload($scaled, $sourceCounty, $adopter))->assertSessionHasErrors('target_county_id');
        $this->actingAs($creator)->post(route('knowledge.innovation-replications.store'), $this->payload($scaled, $targetCounty, $outsideUser))->assertSessionHasErrors('accountable_user_id');
        $this->actingAs($creator)->post(route('knowledge.innovation-replications.store'), $this->payload($scaled, $targetCounty, $adopter))->assertRedirect();

        $this->actingAs($targetAdmin)->get(route('knowledge.innovation-replications.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('replications.total', 1));
        $this->actingAs($outsideUser)->get(route('knowledge.innovation-replications.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('replications.total', 0));
        $this->actingAs($outsideUser)->get(route('knowledge.innovation-replications.index', ['county_id' => $targetCounty->id]))->assertForbidden();
    }

    public function test_adopted_replication_is_database_immutable(): void
    {
        $replication = InnovationReplication::factory()->create(['status' => 'adopted', 'verification_decision' => 'approved', 'decision_checksum' => str_repeat('a', 64)]);

        $this->expectException(QueryException::class);
        DB::table('innovation_replications')->where('id', $replication->id)->update(['outcome_summary' => 'Tampered outcome']);
    }

    public function test_replication_fails_closed_without_lineage_bearing_source_and_effective_catalogue(): void
    {
        $source = County::factory()->create();
        $target = County::factory()->create();
        $creator = User::factory()->devolutionAdmin()->create();
        $adopter = User::factory()->countyOfficial($target)->create();
        $legacyInnovation = DevolutionInnovation::factory()->create(['county_id' => $source->id, 'reference_data_release_id' => null, 'submitted_by' => $creator->id, 'status' => 'scaling', 'stage' => 'scale']);

        $this->actingAs($creator)->post(route('knowledge.innovation-replications.store'), $this->payload($legacyInnovation, $target, $adopter))->assertSessionHasErrors('devolution_innovation_id');
        $this->assertDatabaseCount('innovation_replications', 0);
    }

    public function test_replication_safeguards_follow_the_active_locale_and_catalogs_remain_in_parity(): void
    {
        $source = County::factory()->create();
        $target = County::factory()->create();
        $creator = User::factory()->devolutionAdmin()->create();
        $adopter = User::factory()->countyOfficial($target)->create();
        $draft = DevolutionInnovation::factory()->create([
            'county_id' => $source->id,
            'submitted_by' => $creator->id,
            'status' => 'draft',
        ]);

        $this->actingAs($creator)
            ->withSession(['locale' => 'fr'])
            ->post(route('knowledge.innovation-replications.store'), $this->payload($draft, $target, $adopter))
            ->assertSessionHasErrors([
                'devolution_innovation_id' => 'Seules les innovations vérifiées indépendamment et approuvées pour le déploiement peuvent être répliquées.',
            ]);

        $release = $this->publishedReferenceRelease([$source, $target], $creator);
        $draft->update(['status' => 'scaling', 'stage' => 'scale', 'reference_data_release_id' => $release->id]);
        $this->seed(KnowledgeWorkflowSeeder::class);
        $this->actingAs($creator)
            ->withSession(['locale' => 'fr'])
            ->post(route('knowledge.innovation-replications.store'), $this->payload($draft, $target, $adopter))
            ->assertRedirect()
            ->assertSessionHas('success', fn (string $message): bool => str_starts_with($message, 'Réplication REP-') && str_ends_with($message, ' créée.'));
        $replication = InnovationReplication::query()->sole();
        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $replication->id,
            'action' => 'knowledge.innovation_replication.created',
            'description' => "Réplication {$replication->reference} créée pour {$target->name}.",
        ]);
        $csv = $this->actingAs($creator)
            ->withSession(['locale' => 'fr'])
            ->get(route('knowledge.innovation-replications.export', ['csv']))
            ->assertOk()
            ->assertDownload();
        $this->assertStringContainsString('Référence', $csv->streamedContent());
        $this->assertStringContainsString('Comté cible', $csv->streamedContent());
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $creator->id,
            'action' => 'knowledge.innovation_replication.exported',
            'description' => 'Portefeuille de réplication des innovations exporté au format CSV.',
        ]);

        $englishKeys = array_keys(Arr::dot(require lang_path('en/innovation-replications.php')));
        sort($englishKeys);
        foreach (['sw', 'fr'] as $locale) {
            $localizedKeys = array_keys(Arr::dot(require lang_path("{$locale}/innovation-replications.php")));
            sort($localizedKeys);
            $this->assertSame($englishKeys, $localizedKeys, "Innovation replication catalog keys differ for {$locale}.");
        }
    }

    public function test_replication_actions_enforce_localized_permissions_before_target_or_payload_processing(): void
    {
        $unauthorizedActor = User::factory()->create();
        $unauthorizedActor->syncRoles([]);
        $replication = InnovationReplication::factory()->create();

        app()->setLocale('fr');
        $this->assertForbiddenAction(
            fn () => app(CreateInnovationReplication::class)->handle($unauthorizedActor, []),
            'Vous n’êtes pas autorisé à créer des réplications d’innovation.',
        );
        $this->assertForbiddenAction(
            fn () => app(UpdateInnovationReplication::class)->handle($replication, $unauthorizedActor, []),
            'Vous n’êtes pas autorisé à mettre à jour les réplications d’innovation.',
        );
        $this->assertForbiddenAction(
            fn () => app(VerifyInnovationReplication::class)->handle($replication, $unauthorizedActor, []),
            'Vous n’êtes pas autorisé à vérifier les réplications d’innovation.',
        );

        $this->assertDatabaseCount('innovation_replications', 1);
        $this->assertDatabaseCount('audit_events', 0);
    }

    /** @return array<string, mixed> */
    private function payload(DevolutionInnovation $innovation, County $targetCounty, User $adopter): array
    {
        return ['devolution_innovation_id' => $innovation->id, 'target_county_id' => $targetCounty->id, 'accountable_user_id' => $adopter->id, 'adaptation_plan' => 'Adapt the validated workflow to local approval, language, connectivity and ward-support arrangements while preserving evidence controls.', 'success_measure' => 'Percentage of participating wards submitting complete records on time', 'baseline_value' => 42, 'target_value' => 85, 'starts_on' => today()->toDateString(), 'target_completion_on' => today()->addMonths(3)->toDateString()];
    }

    /** @param list<County> $counties */
    private function publishedReferenceRelease(array $counties, User $approver): ReferenceDataRelease
    {
        $snapshot = ['counties' => collect($counties)->map(fn (County $county): array => ['id' => $county->id])->all(), 'organizations' => [], 'sectors' => [], 'programmes' => []];

        return ReferenceDataRelease::factory()->create(['approved_by' => $approver->id, 'status' => 'published', 'snapshot' => $snapshot, 'checksum' => app(CanonicalJson::class)->checksum($snapshot), 'effective_from' => now()->subMinute(), 'published_at' => now()]);
    }

    /** @param callable(): mixed $action */
    private function assertForbiddenAction(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('The innovation-replication action did not enforce its permission boundary.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame($message, $exception->getMessage());
        }
    }
}
