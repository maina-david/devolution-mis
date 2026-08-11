<?php

namespace Tests\Feature;

use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_county_staff_can_upload_valid_evidence_to_their_assessment(): void
    {
        Storage::fake('local');
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::EvidenceCollection]);
        Notification::fake();

        $this->actingAs($official)->post(route('evidence.store', [$official->currentTeam->slug, $assessment]), [
            'title' => 'Annual Development Plan 2025/26',
            'category' => 'ADP',
            'source_type' => 'digital',
            'document' => UploadedFile::fake()->create('adp.pdf', 500, 'application/pdf'),
        ])->assertRedirect();

        $document = AssessmentDocument::query()->sole();
        $this->assertSame($county->id, $document->county_id);
        $this->assertSame($official->id, $document->uploaded_by);
        $this->assertSame('digital', $document->source_type);
        Storage::disk('local')->assertExists($document->path);
    }

    public function test_county_staff_can_upload_a_scanned_image_as_evidence(): void
    {
        Storage::fake('local');
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'status' => AssessmentStatus::EvidenceCollection]);
        Notification::fake();

        $this->actingAs($official)->post(route('evidence.store', [$official->currentTeam->slug, $assessment]), [
            'title' => 'Scanned public participation register',
            'category' => 'Public participation',
            'source_type' => 'scanned',
            'document' => UploadedFile::fake()->image('register.jpg'),
        ])->assertRedirect();

        $document = AssessmentDocument::query()->sole();
        $this->assertSame('scanned', $document->source_type);
        $this->assertSame('image/jpeg', $document->mime_type);
        Storage::disk('local')->assertExists($document->path);
        $this->actingAs($official)
            ->get(route('evidence.preview', [$official->currentTeam->slug, $document]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_county_staff_cannot_upload_to_another_county_or_locked_assessment(): void
    {
        Storage::fake('local');
        $home = County::factory()->create();
        $other = County::factory()->create();
        $official = User::factory()->countyOfficial($home)->create();
        $hidden = Assessment::factory()->create(['county_id' => $other->id, 'status' => AssessmentStatus::EvidenceCollection]);
        $locked = Assessment::factory()->create(['county_id' => $home->id, 'status' => AssessmentStatus::Approved]);
        $payload = ['title' => 'Evidence', 'category' => 'ADP', 'source_type' => 'digital', 'document' => UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf')];

        $this->actingAs($official)->post(route('evidence.store', [$official->currentTeam->slug, $hidden]), $payload)->assertForbidden();
        $payload['document'] = UploadedFile::fake()->create('locked.pdf', 100, 'application/pdf');
        $this->actingAs($official)->post(route('evidence.store', [$official->currentTeam->slug, $locked]), $payload)->assertStatus(409);
        $this->assertSame(0, AssessmentDocument::count());
    }

    public function test_assessor_can_verify_evidence_only_in_assigned_counties(): void
    {
        $assigned = County::factory()->create();
        $other = County::factory()->create();
        $assessor = User::factory()->assessor()->create();
        $assessor->assignedCounties()->attach($assigned);
        $assignedAssessment = Assessment::factory()->create(['county_id' => $assigned->id]);
        $otherAssessment = Assessment::factory()->create(['county_id' => $other->id]);
        $document = AssessmentDocument::factory()->create(['assessment_id' => $assignedAssessment->id, 'county_id' => $assigned->id, 'verification_status' => 'pending']);
        $hidden = AssessmentDocument::factory()->create(['assessment_id' => $otherAssessment->id, 'county_id' => $other->id, 'verification_status' => 'pending']);
        Notification::fake();

        $this->actingAs($assessor)->patch(route('evidence.verify', [$assessor->currentTeam->slug, $document]), ['status' => 'verified'])->assertRedirect();
        $this->actingAs($assessor)->patch(route('evidence.verify', [$assessor->currentTeam->slug, $hidden]), ['status' => 'rejected'])->assertForbidden();

        $this->assertSame('verified', $document->fresh()?->verification_status);
        $this->assertSame('pending', $hidden->fresh()?->verification_status);
    }

    public function test_non_assessor_cannot_verify_evidence(): void
    {
        $county = County::factory()->create();
        $official = User::factory()->countyOfficial($county)->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id]);
        $document = AssessmentDocument::factory()->create(['assessment_id' => $assessment->id, 'county_id' => $county->id]);

        $this->actingAs($official)->patch(route('evidence.verify', [$official->currentTeam->slug, $document]), ['status' => 'verified'])->assertForbidden();
    }

    public function test_authorized_users_can_download_evidence_only_in_their_county_scope(): void
    {
        Storage::fake('local');
        $home = County::factory()->create();
        $other = County::factory()->create();
        $official = User::factory()->countyOfficial($home)->create();
        $homeAssessment = Assessment::factory()->create(['county_id' => $home->id]);
        $otherAssessment = Assessment::factory()->create(['county_id' => $other->id]);
        $visible = AssessmentDocument::factory()->create([
            'assessment_id' => $homeAssessment->id,
            'county_id' => $home->id,
            'path' => 'assessment-evidence/visible.pdf',
        ]);
        $hidden = AssessmentDocument::factory()->create([
            'assessment_id' => $otherAssessment->id,
            'county_id' => $other->id,
            'path' => 'assessment-evidence/hidden.pdf',
        ]);
        Storage::disk('local')->put($visible->path, 'visible evidence');
        Storage::disk('local')->put($hidden->path, 'hidden evidence');
        $visible->update(['content_checksum' => hash('sha256', 'visible evidence')]);
        $hidden->update(['content_checksum' => hash('sha256', 'hidden evidence')]);

        $this->actingAs($official)
            ->get(route('evidence.download', [$official->currentTeam->slug, $visible]))
            ->assertOk()
            ->assertDownload();
        $this->actingAs($official)
            ->get(route('evidence.download', [$official->currentTeam->slug, $hidden]))
            ->assertForbidden();
    }
}
