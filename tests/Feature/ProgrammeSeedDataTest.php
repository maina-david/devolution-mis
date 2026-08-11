<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\AssessmentCycle;
use App\Models\AssessmentDocument;
use App\Models\AssessmentFunction;
use App\Models\AssessmentScorecardVersion;
use App\Models\County;
use App\Models\CountyGrant;
use App\Models\User;
use App\Services\ProgrammeAuthorization;
use Database\Seeders\AssessmentScorecardSeeder;
use Database\Seeders\CountySeeder;
use Database\Seeders\DemoProgrammeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgrammeSeedDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_governed_programme_seed_data_is_complete_deterministic_and_previewable(): void
    {
        Storage::fake();
        User::factory()->create([
            'name' => 'Samuel Mutua',
            'email' => 'devolution.admin@idmis.test',
        ]);
        $this->seed([
            RolePermissionSeeder::class,
            CountySeeder::class,
        ]);
        $assessor = User::factory()->create(['name' => 'Test IVA Assessor', 'email' => 'assessor@idmis.test']);
        app(ProgrammeAuthorization::class)->assignRole($assessor, UserRole::Assessor);
        $assessor->assignedCounties()->sync(County::query()->pluck('id'));
        $county = County::query()->firstOrFail();
        $legacyAssessment = Assessment::factory()->create(['county_id' => $county->id, 'cycle' => '2025/26 ACPA']);
        $legacyDocument = AssessmentDocument::factory()->create(['assessment_id' => $legacyAssessment->id, 'county_id' => $county->id, 'title' => 'Lorem factory evidence']);
        $legacyGrant = CountyGrant::factory()->create(['county_id' => $county->id, 'programme' => 'KDSP II', 'financial_year' => '2025/26']);

        $this->seed([AssessmentScorecardSeeder::class, DemoProgrammeSeeder::class]);

        $this->assertSame(4, AssessmentCycle::query()->count());
        $this->assertSame(1, AssessmentScorecardVersion::query()->where('status', 'published')->count());
        $this->assertSame(14, AssessmentFunction::query()->count());
        $this->assertSame(188, Assessment::query()->count());
        $this->assertSame(0, Assessment::query()->whereNull('assessor_id')->count());
        $this->assertSame(235, AssessmentDocument::query()->count());
        $this->assertSame(94, CountyGrant::query()->count());
        $this->assertFalse(AssessmentDocument::query()->where('title', 'like', '%Lorem%')->exists());
        $this->assertTrue(Assessment::withTrashed()->findOrFail($legacyAssessment->id)->trashed());
        $this->assertTrue(AssessmentDocument::withTrashed()->findOrFail($legacyDocument->id)->trashed());
        $this->assertTrue(CountyGrant::withTrashed()->findOrFail($legacyGrant->id)->trashed());

        $document = AssessmentDocument::query()->where('title', 'like', 'Mombasa%')->firstOrFail();
        Storage::assertExists($document->path);
        $this->assertStringContainsString('not represented as an official county submission', Storage::get($document->path));

        $this->seed([AssessmentScorecardSeeder::class, DemoProgrammeSeeder::class]);

        $this->assertSame(4, AssessmentCycle::query()->count());
        $this->assertSame(188, Assessment::query()->count());
        $this->assertSame(235, AssessmentDocument::query()->count());
        $this->assertSame(94, CountyGrant::query()->count());
    }
}
