<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\DocumentDisposition;
use App\Models\DocumentLegalHold;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentDispositionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_aware_three_person_disposition_removes_objects_and_retains_immutable_evidence(): void
    {
        Storage::fake('local');
        [$requester, $reviewer, $executor] = [User::factory()->devolutionAdmin()->create(), User::factory()->platformAdmin()->create(), User::factory()->devolutionAdmin()->create()];
        $county = County::factory()->create();
        $document = $this->documentWithVersions($county, $requester);

        $this->actingAs($requester)->post(route('evidence.dispositions.store', [$document]), $this->requestPayload())->assertRedirect();
        $disposition = DocumentDisposition::query()->sole();
        $this->assertSame('pending', $disposition->status);

        $decision = ['decision' => 'approved', 'decision_reason' => 'Retention expiry, authority and absence of legal hold were independently reviewed.'];
        $this->actingAs($requester)->patch(route('evidence.dispositions.decide', [$document, $disposition]), $decision)->assertStatus(409);
        $this->actingAs($reviewer)->patch(route('evidence.dispositions.decide', [$document, $disposition]), $decision)->assertRedirect();
        $disposition->refresh();

        $this->actingAs($requester)->post(route('evidence.dispositions.execute', [$document, $disposition]))->assertStatus(409);
        $this->actingAs($reviewer)->post(route('evidence.dispositions.execute', [$document, $disposition]))->assertStatus(409);
        $this->actingAs($executor)->post(route('evidence.dispositions.execute', [$document, $disposition]))->assertRedirect(route('evidence.index'));

        $disposition->refresh();
        $this->assertSame('executed', $disposition->status);
        $this->assertSame(2, $disposition->object_count);
        $this->assertSame(64, strlen((string) $disposition->manifest_checksum));
        $this->assertCount(2, $disposition->object_manifest ?? []);
        Storage::assertMissing('assessment-evidence/disposition-v1.pdf');
        Storage::assertMissing('assessment-evidence/disposition-v2.pdf');
        $this->assertSoftDeleted($document);
        $this->assertDatabaseHas('assessment_documents', ['id' => $document->id, 'record_status' => 'disposed']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $disposition->id, 'action' => 'document.disposition_requested']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $disposition->id, 'action' => 'document.disposition_approved']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $disposition->id, 'action' => 'document.disposition_executed']);

        $this->actingAs($executor)->get(route('evidence.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('workspace.rows', 1)
            ->where('workspace.rows.0.meta.recordStatus', 'disposed')
            ->where('workspace.rows.0.meta.dispositions.0.status', 'executed')
            ->where('workspace.rows.0.meta.dispositions.0.manifestChecksum', $disposition->manifest_checksum));

        $this->expectException(QueryException::class);
        DB::table('document_dispositions')->where('id', $disposition->id)->update(['reason' => 'Tampered evidence']);
    }

    public function test_legal_hold_retention_and_integrity_failures_stop_disposition(): void
    {
        Storage::fake('local');
        $requester = User::factory()->devolutionAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $executor = User::factory()->devolutionAdmin()->create();
        $county = County::factory()->create();
        $document = $this->documentWithVersions($county, $requester);

        $this->actingAs($requester)->post(route('evidence.dispositions.store', [$document]), [...$this->requestPayload(), 'scheduled_for' => now()->subDays(2)->toDateString()])->assertSessionHasErrors('scheduled_for');
        DocumentLegalHold::factory()->create(['assessment_document_id' => $document->id, 'placed_by' => $reviewer->id]);
        $this->actingAs($requester)->post(route('evidence.dispositions.store', [$document]), $this->requestPayload())->assertStatus(409);
        $document->legalHolds()->update(['released_by' => $executor->id, 'released_at' => now(), 'release_reason' => 'Hold authority released preservation.']);

        $this->actingAs($requester)->post(route('evidence.dispositions.store', [$document]), $this->requestPayload())->assertRedirect();
        $disposition = DocumentDisposition::query()->sole();
        $this->actingAs($reviewer)->patch(route('evidence.dispositions.decide', [$document, $disposition]), ['decision' => 'approved', 'decision_reason' => 'Approved after hold release.'])->assertRedirect();

        Storage::put('assessment-evidence/disposition-v1.pdf', '%PDF tampered');
        $this->actingAs($executor)->post(route('evidence.dispositions.execute', [$document, $disposition]))->assertStatus(409);
        $this->assertSame('execution_failed', $disposition->fresh()->status);
        $this->assertNotNull($disposition->fresh()->execution_error);
        Storage::assertExists('assessment-evidence/disposition-v1.pdf');
        Storage::assertExists('assessment-evidence/disposition-v2.pdf');
        $this->assertDatabaseHas('assessment_documents', ['id' => $document->id, 'deleted_at' => null]);
    }

    public function test_disposition_routes_require_records_permission_and_matching_document(): void
    {
        Storage::fake('local');
        $county = County::factory()->create();
        $requester = User::factory()->devolutionAdmin()->create();
        $countyAdmin = User::factory()->countyAdmin($county)->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $document = $this->documentWithVersions($county, $requester);
        $otherAssessment = Assessment::factory()->create(['county_id' => $county->id, 'cycle' => 'Disposition mismatch fixture']);
        $otherDocument = AssessmentDocument::factory()->create(['assessment_id' => $otherAssessment->id, 'county_id' => $county->id]);
        $disposition = DocumentDisposition::factory()->create(['assessment_document_id' => $document->id, 'requested_by' => $requester->id]);

        $this->actingAs($countyAdmin)->post(route('evidence.dispositions.store', [$document]), $this->requestPayload())->assertForbidden();
        $this->actingAs($reviewer)->patch(route('evidence.dispositions.decide', [$otherDocument, $disposition]), ['decision' => 'approved', 'decision_reason' => 'Wrong document.'])->assertNotFound();
    }

    private function documentWithVersions(County $county, User $uploader): AssessmentDocument
    {
        $assessment = Assessment::factory()->create(['county_id' => $county->id]);
        $document = AssessmentDocument::factory()->create(['assessment_id' => $assessment->id, 'county_id' => $county->id, 'retention_until' => now()->subDay(), 'record_status' => 'active']);
        Storage::put('assessment-evidence/disposition-v1.pdf', '%PDF retained version one');
        Storage::put('assessment-evidence/disposition-v2.pdf', '%PDF retained version two');
        DocumentVersion::factory()->create(['assessment_document_id' => $document->id, 'version_number' => 1, 'path' => 'assessment-evidence/disposition-v1.pdf', 'size_bytes' => strlen('%PDF retained version one'), 'content_checksum' => hash('sha256', '%PDF retained version one'), 'uploaded_by' => $uploader->id]);
        $versionTwo = DocumentVersion::factory()->create(['assessment_document_id' => $document->id, 'version_number' => 2, 'path' => 'assessment-evidence/disposition-v2.pdf', 'size_bytes' => strlen('%PDF retained version two'), 'content_checksum' => hash('sha256', '%PDF retained version two'), 'uploaded_by' => $uploader->id]);
        $document->update(['current_version_id' => $versionTwo->id, 'path' => $versionTwo->path, 'content_checksum' => $versionTwo->content_checksum, 'size_bytes' => $versionTwo->size_bytes, 'version' => 2]);

        return $document->fresh() ?? $document;
    }

    /** @return array{authority_reference: string, scheduled_for: string, reason: string} */
    private function requestPayload(): array
    {
        return ['authority_reference' => 'RET-SCHEDULE-ACPA-001', 'scheduled_for' => now()->toDateString(), 'reason' => 'The approved retention period has elapsed and no continuing business or legal need remains.'];
    }
}
