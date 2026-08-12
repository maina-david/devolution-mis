<?php

namespace Tests\Feature;

use App\Actions\CreateEvaluationFinding;
use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\EvaluationFinding;
use App\Models\EvaluationFindingAction;
use App\Models\EvaluationFindingActionUpdate;
use App\Models\ProgrammeEvaluation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EvaluationFindingActionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_weighted_multi_owner_action_plan_reaches_independently_verified_closure(): void
    {
        Storage::fake('local');
        [$county, $issuer, $coordinator, $evaluation] = $this->context();
        $firstOwner = User::factory()->countyOfficial($county)->create();
        $secondOwner = User::factory()->countyOfficial($county)->create();
        $verifier = User::factory()->assessor()->create();
        $verifier->assignedCounties()->attach($county);
        $closer = User::factory()->devolutionAdmin()->create();
        $finding = $this->finding($evaluation, $issuer, $coordinator);

        $this->actingAs($coordinator)->post(route('monitoring-evaluation.findings.actions.store', [$finding]), $this->actionPayload($firstOwner, 'ACT-01', 40))->assertRedirect();
        $this->actingAs($coordinator)->post(route('monitoring-evaluation.findings.actions.store', [$finding]), $this->actionPayload($secondOwner, 'ACT-02', 60))->assertRedirect();
        $actions = EvaluationFindingAction::query()->orderBy('code')->get();
        $this->assertCount(2, $actions);
        $this->assertTrue($actions->every(fn (EvaluationFindingAction $action): bool => Str::isUuid($action->id) && strlen($action->checksum) === 64));

        $this->submitAndVerify($actions[0], $firstOwner, $verifier, 100, 'scanned');
        $this->assertSame('40.00', $finding->refresh()->progress_percentage);
        $this->actingAs($closer)->patch(route('monitoring-evaluation.findings.close', [$finding]), ['note' => 'Premature closure.'])->assertStatus(409);

        $this->submitAndVerify($actions[1], $secondOwner, $verifier, 50, 'digital');
        $this->assertSame('70.00', $finding->refresh()->progress_percentage);
        $this->submitAndVerify($actions[1]->refresh(), $secondOwner, $verifier, 100, 'digital');
        $this->assertSame('100.00', $finding->refresh()->progress_percentage);
        $this->actingAs($closer)->patch(route('monitoring-evaluation.findings.close', [$finding]), ['note' => 'All weighted actions have independent completion evidence.'])->assertRedirect();

        $this->assertSame('closed', $finding->refresh()->status);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $actions[0]->id, 'action' => 'evaluation.finding_action.created']);
        $this->assertDatabaseHas('audit_events', ['action' => 'evaluation.finding_action.progress_verified']);
        $actionDocument = $actions[0]->documentLinks()->firstOrFail()->document;
        $this->actingAs($coordinator)->get(route('evidence.preview', [$actionDocument]))->assertOk();
        $outsider = User::factory()->countyAdmin(County::factory()->create())->create();
        $this->actingAs($outsider)->get(route('evidence.preview', [$actionDocument]))->assertForbidden();
        $this->actingAs($coordinator)->get(route('monitoring-evaluation.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('options.findings.0.actions', 2)
            ->where('options.findings.0.actions.0.owner', $firstOwner->name)
            ->where('options.findings.0.actions.0.weight', 40)
            ->where('options.findings.0.actions.0.documents.0.sourceType', 'scanned')
            ->where('options.findings.0.actions.1.documents.0.sourceType', 'digital'));

        $decided = EvaluationFindingActionUpdate::query()->where('status', 'verified')->firstOrFail();
        $this->expectException(QueryException::class);
        DB::table('evaluation_finding_action_updates')->where('id', $decided->id)->update(['narrative' => 'Tampered']);
    }

    public function test_action_plan_rejects_over_allocation_cross_county_owner_and_unlinked_evidence(): void
    {
        [$county, $issuer, $coordinator, $evaluation] = $this->context();
        $owner = User::factory()->countyOfficial($county)->create();
        $otherOwner = User::factory()->countyOfficial(County::factory()->create())->create();
        $finding = $this->finding($evaluation, $issuer, $coordinator);

        $this->actingAs($coordinator)->post(route('monitoring-evaluation.findings.actions.store', [$finding]), $this->actionPayload($owner, 'ACT-01', 70))->assertRedirect();
        $this->actingAs($coordinator)->post(route('monitoring-evaluation.findings.actions.store', [$finding]), $this->actionPayload($owner, 'ACT-02', 40))->assertSessionHasErrors('weight_percentage');
        $this->actingAs($coordinator)->post(route('monitoring-evaluation.findings.actions.store', [$finding]), $this->actionPayload($otherOwner, 'ACT-03', 30))->assertStatus(422);

        $action = EvaluationFindingAction::query()->sole();
        $unlinked = AssessmentDocument::factory()->create(['assessment_id' => null, 'county_id' => $county->id, 'scan_status' => 'clean', 'record_status' => 'active']);
        $this->actingAs($owner)->post(route('monitoring-evaluation.finding-actions.updates.store', [$action]), ['assessment_document_id' => $unlinked->id, 'progress_percentage' => 50, 'narrative' => 'Unlinked evidence.'])->assertStatus(409);

        $closer = User::factory()->devolutionAdmin()->create();
        $this->actingAs($closer)->patch(route('monitoring-evaluation.findings.close', [$finding]), ['note' => 'Underweighted plan.'])->assertStatus(409);
    }

    /** @return array{County, User, User, ProgrammeEvaluation} */
    private function context(): array
    {
        $county = County::factory()->create();
        $issuer = User::factory()->devolutionAdmin()->create();
        $coordinator = User::factory()->countyAdmin($county)->create();
        $evaluation = ProgrammeEvaluation::factory()->create(['county_id' => $county->id, 'status' => 'approved', 'approved_by' => User::factory()->assessor()->create()->id, 'approved_at' => now()]);

        return [$county, $issuer, $coordinator, $evaluation];
    }

    private function finding(ProgrammeEvaluation $evaluation, User $issuer, User $owner): EvaluationFinding
    {
        return app(CreateEvaluationFinding::class)->handle($evaluation, $issuer, ['reference' => fake()->unique()->bothify('EVAL-ACT-###'), 'title' => 'Delayed exchequer processing', 'finding' => 'Processing exceeded the approved service level.', 'recommendation' => 'Implement a coordinated control improvement plan.', 'severity' => 'high', 'accountable_owner_id' => $owner->id, 'due_at' => now()->addMonth()->toDateString()]);
    }

    /** @return array<string, mixed> */
    private function actionPayload(User $owner, string $code, int $weight): array
    {
        return ['accountable_owner_id' => $owner->id, 'code' => $code, 'title' => "Implement {$code}", 'description' => 'Implement and evidence the assigned control improvement.', 'success_indicator' => 'Share of assigned control milestones completed', 'target' => '100 percent', 'due_at' => now()->addWeeks(3)->toDateString(), 'weight_percentage' => $weight];
    }

    private function submitAndVerify(EvaluationFindingAction $action, User $owner, User $verifier, int $progress, string $sourceType): void
    {
        $upload = ['title' => "{$action->code} evidence {$progress}", 'category' => 'implementation_evidence', 'source_type' => $sourceType, 'document' => UploadedFile::fake()->create("{$action->code}-{$progress}.pdf", 120, 'application/pdf')];
        $this->actingAs($owner)->post(route('monitoring-evaluation.finding-actions.documents.store', [$action]), $upload)->assertRedirect();
        $document = $action->documentLinks()->latest()->firstOrFail()->document;
        $this->actingAs($owner)->post(route('monitoring-evaluation.finding-actions.updates.store', [$action]), ['assessment_document_id' => $document->id, 'progress_percentage' => $progress, 'narrative' => "Implementation progress reached {$progress} percent."])->assertRedirect();
        $update = EvaluationFindingActionUpdate::query()->where('status', 'pending_verification')->sole();
        $this->actingAs($owner)->patch(route('monitoring-evaluation.finding-action-updates.verify', [$update]), ['decision' => 'verified', 'note' => 'Self verification.'])->assertForbidden();
        $this->actingAs($verifier)->patch(route('monitoring-evaluation.finding-action-updates.verify', [$update]), ['decision' => 'verified', 'note' => 'Evidence confirms the reported progress.'])->assertRedirect();

        Storage::disk('local')->assertExists($document->path);
    }
}
