<?php

namespace Database\Seeders;

use App\Actions\CreateDswgAction;
use App\Actions\CreateDswgCollaborationThread;
use App\Actions\StartWorkflow;
use App\Actions\TransitionWorkflow;
use App\Enums\UserRole;
use App\Models\County;
use App\Models\DswgMeeting;
use App\Models\DswgWorkingGroup;
use App\Models\Organization;
use App\Models\Sector;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;

class DswgCoordinationSeeder extends Seeder
{
    public function run(StartWorkflow $startWorkflow, TransitionWorkflow $transitionWorkflow, CreateDswgAction $createAction, CreateDswgCollaborationThread $createThread): void
    {
        if (! app()->isLocal()) {
            return;
        }

        $administrator = User::query()->whereHas('roles', fn ($query) => $query->where('name', UserRole::DevolutionAdmin->value))->first();
        $countyRepresentative = User::query()->where('email', 'county.admin@idmis.test')->first();
        $partnerRepresentative = User::query()->where('email', 'partner@idmis.test')->first();
        $counties = County::query()->whereIn('name', ['Mombasa', 'Kwale', 'Kilifi'])->get();

        if (! $administrator || ! $countyRepresentative || ! $partnerRepresentative || $counties->isEmpty()) {
            return;
        }

        $this->call(DswgWorkflowSeeder::class);

        $sector = Sector::query()->firstOrCreate(
            ['code' => 'WASH'],
            ['name' => 'Water, sanitation and irrigation', 'description' => 'Water security and county service delivery.', 'is_active' => true],
        );
        $leadOrganization = Organization::query()->firstOrCreate(
            ['code' => 'SDD-DSWG-WASH'],
            ['name' => 'State Department for Devolution — WASH Secretariat', 'type' => 'national_government', 'email' => 'dswg@devolution.go.ke', 'status' => 'active'],
        );
        $group = DswgWorkingGroup::query()->firstOrCreate(['code' => 'DSWG-WASH-01'], [
            'name' => 'Water, sanitation and climate resilience working group',
            'mandate' => 'Coordinate national, county and development-partner interventions for resilient county water services.',
            'scope' => 'regional',
            'lead_organization_id' => $leadOrganization->id,
            'secretariat_user_id' => $administrator->id,
            'meeting_frequency' => 'Quarterly',
            'status' => 'active',
            'created_by' => $administrator->id,
        ]);
        $group->counties()->syncWithoutDetaching($counties->pluck('id'));
        $group->sectors()->syncWithoutDetaching([$sector->id]);
        $group->members()->syncWithoutDetaching([
            $administrator->id => ['membership_role' => 'chair', 'status' => 'active'],
            $countyRepresentative->id => ['membership_role' => 'county_focal_point', 'status' => 'active'],
            $partnerRepresentative->id => ['membership_role' => 'development_partner', 'status' => 'active'],
        ]);

        if ($group->collaborationThreads()->doesntExist()) {
            $thread = $createThread->handle($group->id, $administrator, [
                'title' => 'Common coastal evidence schedule',
                'topic' => 'Confirm the repository records, county owners and reconciliation checkpoints required before the next quarterly review.',
            ]);
            $postedAt = now()->subDay();
            $thread->messages()->create([
                'author_id' => $countyRepresentative->id,
                'body' => 'Mombasa has nominated the county focal point and mapped the approved work plan, expenditure extract and safeguards evidence.',
                'posted_at' => $postedAt,
                'checksum' => $createThread->checksum($thread->id, $countyRepresentative->id, 'Mombasa has nominated the county focal point and mapped the approved work plan, expenditure extract and safeguards evidence.', $postedAt->toIso8601String()),
            ]);
            $thread->update(['last_activity_at' => $postedAt]);
        }

        $meeting = DswgMeeting::query()->where('reference', 'DSWG-WASH-2026-Q3')->first();
        if (! $meeting) {
            $meeting = $group->meetings()->create([
                'reference' => 'DSWG-WASH-2026-Q3',
                'title' => 'Coastal water resilience delivery review',
                'starts_at' => now()->subWeek()->setTime(9, 0),
                'ends_at' => now()->subWeek()->setTime(12, 0),
                'meeting_mode' => 'hybrid',
                'venue' => 'State Department for Devolution conference room',
                'virtual_link' => 'https://meet.example.org/dswg-wash-q3',
                'agenda' => 'Review county delivery status, resolve cross-county dependencies, and assign accountable actions.',
                'quorum_required' => 2,
                'organized_by' => $administrator->id,
            ]);
            $meeting->invitees()->sync([
                $administrator->id => ['invitation_status' => 'accepted', 'attendance_status' => 'present', 'meeting_role' => 'chair', 'invited_at' => now()->subWeeks(2), 'responded_at' => now()->subDays(10)],
                $countyRepresentative->id => ['invitation_status' => 'accepted', 'attendance_status' => 'present', 'meeting_role' => 'member', 'invited_at' => now()->subWeeks(2), 'responded_at' => now()->subDays(9)],
                $partnerRepresentative->id => ['invitation_status' => 'accepted', 'attendance_status' => 'present', 'meeting_role' => 'member', 'invited_at' => now()->subWeeks(2), 'responded_at' => now()->subDays(8)],
            ]);
            $definition = WorkflowDefinition::query()->where('code', 'DSWG-MEETING-LIFECYCLE')->firstOrFail();
            $instance = $startWorkflow->handle($definition, $meeting, $administrator);
            $instance = $transitionWorkflow->handle($instance, 'record_outcomes', $administrator, ['minutes_present' => true, 'quorum_met' => true]);
            $meeting->update([
                'workflow_instance_id' => $instance->id,
                'status' => $instance->current_state,
                'minutes' => 'Members confirmed delivery priorities and adopted a single county evidence-submission schedule.',
                'minutes_recorded_by' => $administrator->id,
                'minutes_recorded_at' => now()->subWeek()->addHours(4),
            ]);
        }

        $decision = $meeting->decisions()->firstOrCreate(['code' => 'DSWG-DEC-WASH-001'], [
            'title' => 'Adopt a common coastal evidence schedule',
            'decision_text' => 'All participating counties will submit verified water-project evidence using the common DSWG schedule.',
            'decision_type' => 'resolution',
            'status' => 'adopted',
            'decided_at' => $meeting->starts_at,
            'created_by' => $administrator->id,
        ]);

        if (! $meeting->actions()->where('code', 'DSWG-ACT-WASH-001')->exists()) {
            $createAction->handle($meeting, $administrator, [
                'dswg_decision_id' => $decision->id,
                'code' => 'DSWG-ACT-WASH-001',
                'title' => 'Submit Mombasa water-project evidence pack',
                'description' => 'Compile the approved work plan, expenditure extracts, safeguards records and geotagged delivery evidence.',
                'accountable_user_id' => $countyRepresentative->id,
                'accountable_organization_id' => null,
                'county_id' => $counties->firstWhere('name', 'Mombasa')?->id,
                'due_on' => now()->addDays(21)->toDateString(),
                'priority' => 'high',
                'status' => 'open',
                'progress_percentage' => 0,
            ]);
        }
    }
}
