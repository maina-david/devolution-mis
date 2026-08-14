<?php

namespace Tests\Feature;

use App\Actions\BulkArchiveCounties;
use App\Actions\CreateReferenceDataRelease;
use App\Actions\PublishReferenceDataRelease;
use App\Models\County;
use App\Models\Organization;
use App\Models\Programme;
use App\Models\ReferenceDataRelease;
use App\Models\Sector;
use App\Models\User;
use App\Services\EffectiveReferenceDataReleaseResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReferenceDataManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_release_resolution_and_county_archive_guards_use_the_active_locale(): void
    {
        App::setLocale('fr');
        $administrator = User::factory()->platformAdmin()->create();
        $constitutionalCounty = County::factory()->create(['code' => 1, 'name' => 'Mombasa']);

        try {
            app(BulkArchiveCounties::class)->handle($administrator, [$constitutionalCounty->id]);
            $this->fail('The constitutional county registry must remain protected.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame('Mombasa fait partie du registre constitutionnel des 47 comtés du Kenya et ne peut pas être archivé.', $exception->getMessage());
        }

        try {
            app(EffectiveReferenceDataReleaseResolver::class)->forProject(['sector_id' => Str::uuid()], [], now());
            $this->fail('Reference-bound creation must require an effective published catalogue.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame('Aucune version publiée des données de référence n’est actuellement en vigueur. Publiez un catalogue approuvé avant de lancer un projet.', $exception->getMessage());
        }

        $this->assertNotSoftDeleted($constitutionalCounty);
        $this->assertDatabaseMissing('audit_events', ['subject_id' => $constitutionalCounty->id, 'action' => 'reference.county.archived']);
    }

    public function test_reference_release_separation_and_audit_evidence_use_the_active_locale(): void
    {
        App::setLocale('fr');
        $submitter = User::factory()->platformAdmin()->create();
        $publisher = User::factory()->platformAdmin()->create();
        $release = app(CreateReferenceDataRelease::class)->handle($submitter, 'Publication contrôlée du catalogue de référence.');
        $attributes = ['approval_reference' => 'SDD-REF-2026-001', 'effective_from' => now()->addDay()->toDateString()];

        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $release->id,
            'action' => 'reference.release.submitted',
            'description' => 'La version v1 des données de référence a été soumise pour publication indépendante.',
        ]);

        try {
            app(PublishReferenceDataRelease::class)->handle($release, $submitter, $attributes);
            $this->fail('The release submitter must not publish the same snapshot.');
        } catch (AuthorizationException $exception) {
            $this->assertSame('L’auteur de la soumission ne peut pas publier indépendamment le même instantané des données de référence.', $exception->getMessage());
        }

        $published = app(PublishReferenceDataRelease::class)->handle($release, $publisher, $attributes);

        $this->assertSame('published', $published->status);
        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $release->id,
            'action' => 'reference.release.published',
            'description' => 'La version v1 des données de référence a été publiée de manière indépendante.',
        ]);
    }

    public function test_reference_governance_outcomes_and_audit_descriptions_use_the_active_locale(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->withSession(['locale' => 'fr'])
            ->actingAs($admin)
            ->post(route('reference-data.organizations.store'), [
                'code' => 'KSG',
                'name' => 'Kenya School of Government',
                'type' => 'national',
                'status' => 'active',
            ])
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Organisation créée.');

        $organization = Organization::query()->sole();
        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $organization->id,
            'action' => 'reference.organization.created',
            'description' => 'Les données de référence de Kenya School of Government ont été modifiées.',
        ]);

        $this->actingAs($admin)
            ->withSession(['locale' => 'fr'])
            ->delete(route('reference-data.organizations.destroy', [$organization]))
            ->assertRedirect()
            ->assertInertiaFlash('toast.message', 'Organisation archivée.');
    }

    public function test_authorized_administrator_can_manage_canonical_reference_data(): void
    {
        $admin = User::factory()->devolutionAdmin()->create();
        $county = County::factory()->create(['name' => 'Mombasa', 'logo_path' => '/images/counties/mombasa.webp']);

        $this->actingAs($admin)->post(route('reference-data.organizations.store'), [
            'code' => 'SDD',
            'name' => 'State Department for Devolution',
            'type' => 'county',
            'county_id' => $county->id,
            'status' => 'active',
        ])->assertRedirect();
        $organization = Organization::query()->sole();

        $this->actingAs($admin)->post(route('reference-data.sectors.store'), [
            'code' => 'GOV',
            'name' => 'Governance',
            'description' => 'Devolution governance and coordination.',
            'is_active' => true,
        ])->assertRedirect();
        $sector = Sector::query()->sole();

        $this->actingAs($admin)->post(route('reference-data.programmes.store'), [
            'code' => 'KDSP-II',
            'name' => 'Second Kenya Devolution Support Program',
            'lead_organization_id' => $organization->id,
            'sector_id' => $sector->id,
            'status' => 'active',
            'currency' => 'KES',
        ])->assertRedirect();

        $programme = Programme::query()->sole();
        $this->assertTrue(Str::isUuid($organization->id));
        $this->assertTrue(Str::isUuid($sector->id));
        $this->assertTrue(Str::isUuid($programme->id));
        $this->assertSame($organization->id, $programme->lead_organization_id);
        $this->assertSame($sector->id, $programme->sector_id);

        $this->actingAs($admin)->get(route('reference-data.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reference-data/index')
                ->where('organizations.data.0.code', 'SDD')
                ->where('organizations.data.0.county.kind', 'county')
                ->where('organizations.data.0.county.logoUrl', '/images/counties/mombasa.webp')
                ->where('sectors.data.0.code', 'GOV')
                ->where('programmes.data.0.code', 'KDSP-II'));
    }

    public function test_reference_data_is_restricted_and_linked_records_cannot_be_archived(): void
    {
        $official = User::factory()->countyOfficial()->create();
        $admin = User::factory()->platformAdmin()->create();
        $programme = Programme::factory()->create();

        $this->actingAs($official)->get(route('reference-data.index'))->assertForbidden();
        $this->actingAs($official)->post(route('reference-data.sectors.store'), [])->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('reference-data.organizations.destroy', [$programme->leadOrganization]))
            ->assertStatus(409);
        $this->actingAs($admin)
            ->delete(route('reference-data.sectors.destroy', [$programme->sector]))
            ->assertStatus(409);

        $this->assertNotSoftDeleted($programme->leadOrganization);
        $this->assertNotSoftDeleted($programme->sector);

        $this->actingAs($admin)
            ->delete(route('reference-data.programmes.destroy', [$programme]))
            ->assertRedirect();

        $this->assertSoftDeleted($programme);
    }

    public function test_sector_hierarchy_rejects_cycles_and_is_reproducible_in_catalogue_releases(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $root = Sector::factory()->create(['code' => 'SOC', 'name' => 'Social services']);

        $this->actingAs($admin)->post(route('reference-data.sectors.store'), [
            'parent_sector_id' => $root->id,
            'code' => 'HLT',
            'name' => 'Health services',
            'description' => 'County and national health-service delivery classification.',
            'is_active' => true,
        ])->assertRedirect();

        $child = Sector::query()->where('code', 'HLT')->sole();
        $this->assertSame($root->id, $child->parent_sector_id);

        $this->actingAs($admin)->patch(route('reference-data.sectors.update', [$root]), [
            'parent_sector_id' => $child->id,
            'code' => $root->code,
            'name' => $root->name,
            'description' => $root->description,
            'is_active' => true,
        ])->assertSessionHasErrors('parent_sector_id');

        $this->actingAs($admin)
            ->delete(route('reference-data.sectors.destroy', [$root]))
            ->assertStatus(409);

        if ($this->getConnection()->getDriverName() === 'pgsql') {
            $this->expectException(QueryException::class);
            $child->update(['parent_sector_id' => $child->id]);
        }
    }

    public function test_sector_parent_is_exposed_and_retained_in_release_history(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $root = Sector::factory()->create(['code' => 'ECON', 'name' => 'Economic services']);
        $child = Sector::factory()->create(['parent_sector_id' => $root->id, 'code' => 'AGR', 'name' => 'Agriculture']);

        $this->actingAs($admin)->get(route('reference-data.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('sectors.data', fn ($sectors): bool => collect($sectors)->contains(
                    fn (array $sector): bool => $sector['id'] === $child->id
                        && $sector['parent']['id'] === $root->id
                        && $sector['parent']['code'] === 'ECON',
                )));

        $this->actingAs($admin)->post(route('reference-data.releases.store'), [
            'change_summary' => 'Publish governed sector hierarchy lineage.',
        ])->assertRedirect();

        $release = ReferenceDataRelease::query()->sole();
        $snapshot = collect($release->snapshot['sectors'])->firstWhere('id', $child->id);

        $this->assertIsArray($snapshot);
        $this->assertSame($root->id, $snapshot['parent_sector_id']);
    }

    public function test_governed_identifiers_can_be_checked_for_uniqueness_on_blur(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $official = User::factory()->countyOfficial()->create();
        Sector::factory()->create(['code' => 'GOV', 'name' => 'Governance']);

        $this->actingAs($admin)->postJson(route('reference-data.unique-value'), [
            'resource' => 'sectors',
            'field' => 'code',
            'value' => 'GOV',
        ])->assertOk()->assertExactJson([
            'available' => false,
            'message' => 'Code is already in use.',
        ]);

        $this->actingAs($admin)->postJson(route('reference-data.unique-value'), [
            'resource' => 'sectors',
            'field' => 'code',
            'value' => 'PFM',
        ])->assertOk()->assertExactJson([
            'available' => true,
            'message' => 'Code is available.',
        ]);

        $this->actingAs($admin)->postJson(route('reference-data.unique-value'), [
            'resource' => 'users',
            'field' => 'email',
            'value' => 'hidden@example.test',
        ])->assertUnprocessable();

        $this->actingAs($official)->postJson(route('reference-data.unique-value'), [
            'resource' => 'sectors',
            'field' => 'code',
            'value' => 'GOV',
        ])->assertForbidden();

        $component = file_get_contents(resource_path('js/components/unique-value-input.tsx'));
        $this->assertIsString($component);
        $this->assertStringContainsString('onBlur={() => void checkAvailability()}', $component);
        $this->assertStringContainsString('request.submit()', $component);
        $this->assertStringContainsString('serverError ?? result?.message', $component);
    }

    public function test_reference_catalogue_release_is_checksummed_effective_dated_and_independently_published(): void
    {
        County::factory()->count(2)->create();
        Organization::factory()->create();
        Sector::factory()->create();
        Programme::factory()->create();
        $submitter = User::factory()->platformAdmin()->create();
        $publisher = User::factory()->platformAdmin()->create();

        $this->actingAs($submitter)->post(route('reference-data.releases.store'), [
            'change_summary' => 'Initial governed catalogue snapshot for controlled downstream exchange.',
        ])->assertRedirect();

        $release = ReferenceDataRelease::query()->sole();
        $this->assertTrue(Str::isUuid($release->id));
        $this->assertSame('submitted', $release->status);
        $this->assertCount(2, $release->snapshot['counties']);
        $this->assertCount(2, $release->snapshot['organizations']);
        $this->assertCount(2, $release->snapshot['sectors']);
        $this->assertCount(1, $release->snapshot['programmes']);
        $this->assertSame(64, Str::length($release->checksum));

        $publishPayload = ['approval_reference' => 'SDD-MDM-APPROVAL-001', 'effective_from' => '2026-08-15'];
        $this->actingAs($submitter)->patch(route('reference-data.releases.publish', [$release]), $publishPayload)->assertForbidden();
        $this->actingAs($publisher)->patch(route('reference-data.releases.publish', [$release]), $publishPayload)->assertRedirect();

        $release->refresh();
        $this->assertSame('published', $release->status);
        $this->assertSame($publisher->id, $release->approved_by);
        $this->assertSame('2026-08-15', $release->effective_from?->toDateString());
        $this->assertDatabaseHas('audit_events', ['subject_id' => $release->id, 'action' => 'reference.release.submitted']);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $release->id, 'action' => 'reference.release.published']);
        $this->actingAs($publisher)->get(route('reference-data.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('releases.0.version', 1)
            ->where('releases.0.status', 'published')
            ->where('releases.0.counts.counties', 2)
            ->where('capabilities.approve', true));
        $this->actingAs($publisher)->patch(route('reference-data.releases.publish', [$release]), $publishPayload)->assertStatus(409);
    }

    public function test_published_reference_catalogue_snapshot_is_database_immutable(): void
    {
        $release = ReferenceDataRelease::factory()->create(['status' => 'published', 'effective_from' => now(), 'published_at' => now()]);

        $this->expectException(QueryException::class);
        $release->update(['change_summary' => 'A published snapshot must never be rewritten.']);
    }
}
