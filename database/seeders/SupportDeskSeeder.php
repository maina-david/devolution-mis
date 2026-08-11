<?php

namespace Database\Seeders;

use App\Actions\RecordSupportTicketActivity;
use App\Enums\UserRole;
use App\Models\County;
use App\Models\ReferenceDataRelease;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupportDeskSeeder extends Seeder
{
    public function run(RecordSupportTicketActivity $recordActivity): void
    {
        $release = ReferenceDataRelease::query()
            ->where('status', 'published')
            ->where('effective_from', '<=', now())
            ->latest('version')
            ->firstOrFail();
        $county = County::query()->where('name', 'Mombasa')->firstOrFail();
        $requester = User::role(UserRole::CountyOfficial->value)
            ->whereBelongsTo($county)
            ->orderBy('name')
            ->firstOrFail();
        $resolver = User::role(UserRole::DevolutionAdmin->value)
            ->orderBy('name')
            ->firstOrFail();

        $scenarios = [
            [
                'reference' => 'SUP-202608-MSA-DATA-01',
                'category' => 'data_quality',
                'priority' => 'high',
                'subject' => 'Quarterly indicator workbook requires catalogue reconciliation',
                'description' => 'The Mombasa quarterly indicator workbook contains approved programme codes that require reconciliation against the currently published IDMIS reference catalogue before import.',
                'status' => 'open',
                'requested_at' => now()->subHours(3),
            ],
            [
                'reference' => 'SUP-202608-MSA-OCR-02',
                'category' => 'document',
                'priority' => 'medium',
                'subject' => 'Scanned public participation report awaiting OCR review',
                'description' => 'A scanned public participation report is legible in preview but its extracted text needs review before the record can be used in county reporting and search.',
                'status' => 'triaged',
                'requested_at' => now()->subDay(),
            ],
            [
                'reference' => 'SUP-202608-MSA-INT-03',
                'category' => 'integration',
                'priority' => 'critical',
                'subject' => 'IFMIS reconciliation exchange requires exception investigation',
                'description' => 'The latest controlled IFMIS reconciliation exchange returned a source checksum exception and requires investigation without bypassing the integration exception register.',
                'status' => 'in_progress',
                'requested_at' => now()->subHours(6),
            ],
            [
                'reference' => 'SUP-202608-MSA-TRN-04',
                'category' => 'training',
                'priority' => 'low',
                'subject' => 'County reporting cohort access restored after identity review',
                'description' => 'A county reporting cohort member could not open the assigned learning pathway. Identity scope and enrolment were reviewed and access was restored.',
                'status' => 'resolved',
                'requested_at' => now()->subDays(3),
                'resolution_summary' => 'The learner identity was reconciled with the active county assignment and the existing cohort enrolment was restored without creating a duplicate account.',
            ],
        ];

        DB::transaction(function () use ($scenarios, $release, $county, $requester, $resolver, $recordActivity): void {
            foreach ($scenarios as $scenario) {
                $requestedAt = $scenario['requested_at'];
                $status = $scenario['status'];
                $assigned = $status !== 'open';
                $resolved = $status === 'resolved';
                $ticket = SupportTicket::query()->firstOrCreate(
                    ['reference' => $scenario['reference']],
                    [
                        'reference_data_release_id' => $release->id,
                        'requester_id' => $requester->id,
                        'county_id' => $county->id,
                        'assigned_to' => $assigned ? $resolver->id : null,
                        'resolved_by' => $resolved ? $resolver->id : null,
                        'category' => $scenario['category'],
                        'priority' => $scenario['priority'],
                        'channel' => 'web',
                        'subject' => $scenario['subject'],
                        'description' => $scenario['description'],
                        'status' => $status,
                        'resolution_summary' => $scenario['resolution_summary'] ?? null,
                        'requested_at' => $requestedAt,
                        'first_response_due_at' => $requestedAt->copy()->addHours($scenario['priority'] === 'critical' ? 1 : 8),
                        'resolution_due_at' => $requestedAt->copy()->addHours($scenario['priority'] === 'critical' ? 4 : 40),
                        'first_responded_at' => $assigned ? $requestedAt->copy()->addHour() : null,
                        'resolved_at' => $resolved ? $requestedAt->copy()->addHours(12) : null,
                        'last_activity_at' => $resolved ? $requestedAt->copy()->addHours(12) : $requestedAt,
                    ],
                );

                if ($ticket->activities()->doesntExist()) {
                    $recordActivity->handle(
                        $ticket,
                        $requester,
                        'created',
                        'none',
                        $ticket->status,
                        'Operational support scenario imported from the approved development demonstration dataset.',
                        [
                            'seed_reference' => $scenario['reference'],
                            'reference_data_release_id' => $release->id,
                            'reference_data_checksum' => $release->checksum,
                        ],
                    );
                }
            }
        });
    }
}
