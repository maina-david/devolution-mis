<?php

namespace Tests\Feature;

use App\Actions\ApplyReferenceLineageDisposition;
use App\Actions\CreateReferenceLineageDisposition;
use App\Actions\ReviewReferenceLineageDisposition;
use App\Models\AccessDelegation;
use App\Models\Assessment;
use App\Models\County;
use App\Models\ReferenceDataRelease;
use App\Models\ReferenceLineageDisposition;
use App\Models\User;
use App\Support\CanonicalJson;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReferenceLineageDispositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_independent_operators_pin_a_checksum_verified_release(): void
    {
        [$assessment, $release, $proposer, $reviewer, $applier] = $this->scenario();

        $disposition = app(CreateReferenceLineageDisposition::class)->handle($proposer, $this->payload($assessment, $release));
        app(ReviewReferenceLineageDisposition::class)->handle($disposition, $reviewer, 'approve', 'The source register and catalogue membership were independently verified.');
        app(ApplyReferenceLineageDisposition::class)->handle($disposition, $applier);

        $this->assertSame($release->id, $assessment->refresh()->reference_data_release_id);
        $this->assertSame('applied', $disposition->refresh()->status);
        $this->assertSame($reviewer->id, $disposition->reviewed_by);
        $this->assertSame($applier->id, $disposition->applied_by);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $disposition->id, 'action' => 'reference_lineage.applied']);
    }

    public function test_proposer_cannot_review_and_reviewer_cannot_apply(): void
    {
        [$assessment, $release, $proposer, $reviewer] = $this->scenario();
        $disposition = app(CreateReferenceLineageDisposition::class)->handle($proposer, $this->payload($assessment, $release));

        $this->expectHttpFailure(403, fn () => app(ReviewReferenceLineageDisposition::class)->handle($disposition, $proposer, 'approve', 'Attempted self-review of the proposed lineage disposition.'));
        app(ReviewReferenceLineageDisposition::class)->handle($disposition, $reviewer, 'approve', 'The independent source and catalogue review has been completed.');
        $this->expectHttpFailure(403, fn () => app(ApplyReferenceLineageDisposition::class)->handle($disposition, $reviewer));
    }

    public function test_application_fails_closed_when_the_source_record_changes_after_proposal(): void
    {
        [$assessment, $release, $proposer, $reviewer, $applier] = $this->scenario();
        $disposition = app(CreateReferenceLineageDisposition::class)->handle($proposer, $this->payload($assessment, $release));
        app(ReviewReferenceLineageDisposition::class)->handle($disposition, $reviewer, 'approve', 'The source evidence was independently reviewed before application.');
        $assessment->update(['cycle' => 'Corrected historical cycle']);

        $this->expectHttpFailure(409, fn () => app(ApplyReferenceLineageDisposition::class)->handle($disposition, $applier));
        $this->assertNull($assessment->refresh()->reference_data_release_id);
        $this->assertSame('approved', $disposition->refresh()->status);
    }

    public function test_release_must_be_effective_checksum_valid_and_contain_the_record_references(): void
    {
        [$assessment, , $proposer, $reviewer] = $this->scenario();
        $release = ReferenceDataRelease::factory()->create();
        $release->update(['approved_by' => $reviewer->id, 'status' => 'published', 'checksum' => str_repeat('0', 64), 'effective_from' => now()->subMinute(), 'published_at' => now()->subMinute()]);

        $this->expectHttpFailure(409, fn () => app(CreateReferenceLineageDisposition::class)->handle($proposer, $this->payload($assessment, $release)));
        $this->assertSame(0, ReferenceLineageDisposition::query()->count());
    }

    public function test_applied_retain_legacy_decision_is_immutable_and_does_not_backfill(): void
    {
        [$assessment, , $proposer, $reviewer, $applier] = $this->scenario();
        $payload = $this->payload($assessment, null);
        $payload['decision'] = 'retain_legacy';
        $disposition = app(CreateReferenceLineageDisposition::class)->handle($proposer, $payload);
        app(ReviewReferenceLineageDisposition::class)->handle($disposition, $reviewer, 'approve', 'Historical status is authoritative and must remain explicitly unpinned.');
        app(ApplyReferenceLineageDisposition::class)->handle($disposition, $applier);

        $this->assertNull($assessment->refresh()->reference_data_release_id);
        $this->expectException(QueryException::class);
        $disposition->refresh()->update(['business_reason' => 'Attempted mutation of terminal evidence.']);
    }

    public function test_unprivileged_user_cannot_propose_a_lineage_disposition(): void
    {
        [$assessment, $release] = $this->scenario();
        $assessor = User::factory()->assessor()->create();

        $this->actingAs($assessor)->post(route('data-migrations.lineage-dispositions.store'), $this->payload($assessment, $release))->assertForbidden();
        $this->assertSame(0, ReferenceLineageDisposition::query()->count());
    }

    public function test_unprivileged_user_cannot_review_or_apply_a_lineage_disposition(): void
    {
        [$assessment, $release, $proposer, $reviewer] = $this->scenario();
        $assessor = User::factory()->assessor()->create();
        $disposition = app(CreateReferenceLineageDisposition::class)->handle($proposer, $this->payload($assessment, $release));

        $this->actingAs($assessor)->patch(route('data-migrations.lineage-dispositions.review', $disposition), [
            'decision' => 'approve',
            'notes' => 'An unauthorized assessor attempted to approve this controlled reconciliation decision.',
        ])->assertForbidden();

        app(ReviewReferenceLineageDisposition::class)->handle($disposition, $reviewer, 'approve', 'The authorized reviewer independently verified the catalogue and retained source evidence.');

        $this->actingAs($assessor)->post(route('data-migrations.lineage-dispositions.apply', $disposition))->assertForbidden();
        $this->assertNull($assessment->refresh()->reference_data_release_id);
        $this->assertSame('approved', $disposition->refresh()->status);
    }

    public function test_reconciliation_failures_and_audit_descriptions_follow_the_active_locale(): void
    {
        [$assessment, $release, $proposer] = $this->scenario();
        App::setLocale('sw');

        $disposition = app(CreateReferenceLineageDisposition::class)->handle($proposer, $this->payload($assessment, $release));

        try {
            app(CreateReferenceLineageDisposition::class)->handle($proposer, $this->payload($assessment, $release));
            $this->fail('Expected the duplicate lineage decision to fail.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertSame(trans('migration.lineage_errors.active_disposition', locale: 'sw'), $exception->getMessage());
        }

        $this->assertDatabaseHas('audit_events', [
            'subject_id' => $disposition->id,
            'description' => trans('migration.lineage_audit.proposed', ['reference' => $disposition->reference], 'sw'),
        ]);
    }

    public function test_access_delegation_cannot_be_pinned_to_a_release_missing_a_scoped_county(): void
    {
        [$assessment, $release, $proposer] = $this->scenario();
        $delegation = AccessDelegation::factory()->create(['reference_data_release_id' => null]);
        $payload = $this->payload($assessment, $release);
        $payload['record_type'] = 'access_delegation';
        $payload['record_id'] = $delegation->id;

        $this->expectHttpFailure(409, fn () => app(CreateReferenceLineageDisposition::class)->handle($proposer, $payload));

        $this->assertNull($delegation->refresh()->reference_data_release_id);
        $this->assertSame(0, ReferenceLineageDisposition::query()->count());
    }

    public function test_three_independent_operators_can_pin_an_access_delegation_when_every_scoped_county_is_in_the_release(): void
    {
        [, , $proposer, $reviewer, $applier] = $this->scenario();
        $delegation = AccessDelegation::factory()->create(['reference_data_release_id' => null]);
        $snapshot = ['counties' => $delegation->county_scope_snapshot, 'organizations' => [], 'sectors' => [], 'programmes' => []];
        $release = ReferenceDataRelease::factory()->create(['approved_by' => $reviewer->id, 'status' => 'published', 'snapshot' => $snapshot, 'checksum' => app(CanonicalJson::class)->checksum($snapshot), 'effective_from' => now()->subMinute(), 'published_at' => now()->subMinute()]);
        $payload = [
            'record_type' => 'access_delegation',
            'record_id' => $delegation->id,
            'decision' => 'pin_release',
            'reference_data_release_id' => $release->id,
            'successor_record_type' => null,
            'successor_record_id' => null,
            'business_reason' => 'The historical emergency access scope was reconciled against the approved county catalogue.',
            'source_reference' => 'IAM-LEGACY-APPROVAL-2025-07',
        ];

        $disposition = app(CreateReferenceLineageDisposition::class)->handle($proposer, $payload);
        app(ReviewReferenceLineageDisposition::class)->handle($disposition, $reviewer, 'approve', 'Every delegated county and the retained approval source were independently verified.');
        app(ApplyReferenceLineageDisposition::class)->handle($disposition, $applier);

        $this->assertSame($release->id, $delegation->refresh()->reference_data_release_id);
        $this->assertSame('applied', $disposition->refresh()->status);
    }

    /** @return array{Assessment, ReferenceDataRelease, User, User, User} */
    private function scenario(): array
    {
        $county = County::factory()->create();
        $assessment = Assessment::factory()->create(['county_id' => $county->id, 'reference_data_release_id' => null]);
        $proposer = User::factory()->devolutionAdmin()->create();
        $reviewer = User::factory()->platformAdmin()->create();
        $applier = User::factory()->platformAdmin()->create();
        $snapshot = ['counties' => [$county->identityCell()], 'organizations' => [], 'sectors' => [], 'programmes' => []];
        $release = ReferenceDataRelease::factory()->create(['approved_by' => $reviewer->id, 'status' => 'published', 'snapshot' => $snapshot, 'checksum' => app(CanonicalJson::class)->checksum($snapshot), 'effective_from' => now()->subMinute(), 'published_at' => now()->subMinute()]);

        return [$assessment, $release, $proposer, $reviewer, $applier];
    }

    /** @return array<string, mixed> */
    private function payload(Assessment $assessment, ?ReferenceDataRelease $release): array
    {
        return [
            'record_type' => 'assessment',
            'record_id' => $assessment->id,
            'decision' => $release ? 'pin_release' : 'retain_legacy',
            'reference_data_release_id' => $release?->id,
            'successor_record_type' => null,
            'successor_record_id' => null,
            'business_reason' => 'The retained historical source has been reconciled against the approved catalogue evidence.',
            'source_reference' => 'ACPA-ARCHIVE-2018-APPROVAL-04',
        ];
    }

    private function expectHttpFailure(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected an HTTP {$status} failure.");
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }
}
