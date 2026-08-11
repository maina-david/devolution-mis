<?php

namespace Tests\Feature;

use App\Models\County;
use App\Models\User;
use Database\Seeders\CountySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CountyIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_counties_have_verified_government_registry_logos_with_matching_local_checksums(): void
    {
        $this->seed(CountySeeder::class);

        $this->assertSame(47, County::query()->count());
        County::query()->orderBy('code')->each(function (County $county): void {
            $this->assertSame('The National Treasury – Bajeti Yetu', $county->logo_source_authority);
            $this->assertSame('government_registry', $county->logo_source_kind);
            $this->assertNotNull($county->logo_verified_at);
            $this->assertNotNull($county->logo_path);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $county->logo_source_checksum_sha256);

            $path = public_path(ltrim((string) $county->logo_path, '/'));
            $this->assertFileExists($path);
            $this->assertSame($county->logo_checksum_sha256, hash_file('sha256', $path));
        });
    }

    public function test_county_identity_is_present_in_authorized_tables_and_structured_exports(): void
    {
        $county = County::factory()->create([
            'code' => 1,
            'name' => 'Mombasa',
            'logo_path' => '/images/counties/mombasa.webp',
            'logo_source_authority' => 'The National Treasury – Bajeti Yetu',
            'logo_source_kind' => 'government_registry',
            'logo_checksum_sha256' => hash_file('sha256', public_path('images/counties/mombasa.webp')),
            'logo_source_checksum_sha256' => str_repeat('a', 64),
            'logo_verified_at' => '2026-08-10',
        ]);
        $admin = User::factory()->countyAdmin($county)->create();

        $this->actingAs($admin)
            ->get(route('counties.index', $admin->currentTeam->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.rows.0.cells.0.kind', 'county')
                ->where('workspace.rows.0.cells.0.name', 'Mombasa')
                ->where('workspace.rows.0.cells.0.logoUrl', '/images/counties/mombasa.webp')
                ->where('workspace.rows.0.cells.0.logoSourceAuthority', 'The National Treasury – Bajeti Yetu')
                ->where('workspace.rows.0.cells.0.officialWebsiteUrl', $county->official_website_url)
                ->where('workspace.rows.0.cells.0.logoSourceChecksum', $county->logo_source_checksum_sha256)
                ->where('workspace.rows.0.cells.0.logoVerifiedAt', '2026-08-10'));

        $json = $this->actingAs($admin)
            ->get(route('workspace.export', [$admin->currentTeam->slug, 'counties', 'json']))
            ->assertOk()
            ->assertDownload()
            ->streamedContent();
        $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('county', $payload['rows'][0][0]['kind']);
        $this->assertSame('/images/counties/mombasa.webp', $payload['rows'][0][0]['logoUrl']);
        $this->assertSame('The National Treasury – Bajeti Yetu', $payload['rows'][0][0]['logoSourceAuthority']);
        $this->assertSame(str_repeat('a', 64), $payload['rows'][0][0]['logoSourceChecksum']);
        $this->assertSame('2026-08-10', $payload['rows'][0][0]['logoVerifiedAt']);

        $pdf = $this->actingAs($admin)
            ->get(route('workspace.export', [$admin->currentTeam->slug, 'counties', 'pdf']))
            ->assertOk()
            ->assertDownload()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());
        $this->assertGreaterThan(5_000, strlen((string) $pdf->getContent()));
    }
}
