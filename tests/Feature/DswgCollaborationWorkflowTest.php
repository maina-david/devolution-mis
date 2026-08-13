<?php

namespace Tests\Feature;

use App\Enums\ProgrammePermission;
use App\Models\AuditEvent;
use App\Models\County;
use App\Models\DswgCollaborationMessage;
use App\Models\DswgCollaborationThread;
use App\Models\DswgWorkingGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DswgCollaborationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_create_attributed_threads_and_post_checksummed_contributions(): void
    {
        [$group, $administrator, $member] = $this->fixture();

        $this->actingAs($administrator)->post(route('dswg.collaboration-threads.store'), [
            'dswg_working_group_id' => $group->id,
            'title' => 'County evidence reconciliation',
            'topic' => 'Agree the controlled evidence reconciliation steps before the next meeting.',
        ])->assertRedirect();

        $thread = DswgCollaborationThread::query()->sole();
        $opening = DswgCollaborationMessage::query()->sole();
        $this->assertSame($administrator->id, $opening->author_id);
        $this->assertSame(64, strlen($opening->checksum));
        $this->assertDatabaseHas('audit_events', ['subject_id' => $thread->id, 'action' => 'dswg.collaboration_thread.created']);

        $this->actingAs($member)->post(route('dswg.collaboration-messages.store', $thread), [
            'body' => 'The county register has been reconciled and the exception list is ready for review.',
        ])->assertRedirect();

        $this->assertSame(2, $thread->messages()->count());
        $message = $thread->messages()->latest('posted_at')->firstOrFail();
        $this->assertSame($member->id, $message->author_id);
        $this->assertDatabaseHas('audit_events', ['subject_id' => $message->id, 'action' => 'dswg.collaboration_message.posted']);

        $this->actingAs($member)->get(route('dswg.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('threads.0.id', $thread->id)
            ->where('threads.0.messageCount', 2)
            ->has('threads.0.messages', 2));
    }

    public function test_non_members_and_closed_threads_fail_closed(): void
    {
        [$group, $administrator] = $this->fixture();
        $outsider = User::factory()->countyOfficial($group->counties()->firstOrFail())->create();
        $this->assertFalse($outsider->can(ProgrammePermission::ManageDswg->value));
        $this->assertFalse($group->members()->where('users.id', $outsider->id)->exists());

        $this->actingAs($outsider)->post(route('dswg.collaboration-threads.store'), [
            'dswg_working_group_id' => $group->id,
            'title' => 'Unauthorized thread',
            'topic' => 'This contribution must not be stored.',
        ])->assertForbidden();

        $thread = DswgCollaborationThread::factory()->create([
            'dswg_working_group_id' => $group->id,
            'created_by' => $administrator->id,
            'status' => 'closed',
        ]);
        $this->actingAs($administrator)->post(route('dswg.collaboration-messages.store', $thread), [
            'body' => 'Closed threads reject later contributions.',
        ])->assertStatus(409);

        $this->assertSame(0, $thread->messages()->count());
        $this->assertSame(0, AuditEvent::query()->where('subject_type', DswgCollaborationMessage::class)->count());
    }

    /** @return array{DswgWorkingGroup, User, User} */
    private function fixture(): array
    {
        $county = County::factory()->create();
        $administrator = User::factory()->devolutionAdmin()->create();
        $member = User::factory()->countyOfficial($county)->create();
        $group = DswgWorkingGroup::factory()->create([
            'secretariat_user_id' => $administrator->id,
            'created_by' => $administrator->id,
        ]);
        $group->counties()->attach($county);
        $group->members()->attach([
            $administrator->id => ['membership_role' => 'secretariat', 'status' => 'active'],
            $member->id => ['membership_role' => 'member', 'status' => 'active'],
        ]);

        return [$group, $administrator, $member];
    }
}
