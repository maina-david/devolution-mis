<?php

namespace Database\Seeders;

use App\Enums\AssessmentStatus;
use App\Enums\UserRole;
use App\Models\Assessment;
use App\Models\AssessmentCriterion;
use App\Models\AssessmentCycle;
use App\Models\AssessmentDocument;
use App\Models\County;
use App\Models\CountyGrant;
use App\Models\CriterionEvidenceRequirement;
use App\Models\ExchequerEvent;
use App\Models\ExchequerRequest;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DemoProgrammeSeeder extends Seeder
{
    /**
     * Populate deterministic, internally consistent programme records.
     *
     * Values are demonstration data, not asserted county submissions.
     */
    public function run(): void
    {
        $this->removeLegacyFactoryRecords();

        $cycles = AssessmentCycle::query()->orderBy('period_start')->get()->keyBy('code');
        $criterion = AssessmentCriterion::query()->where('code', 'F08-C01')->first();
        $requirement = $criterion ? CriterionEvidenceRequirement::query()->where('assessment_criterion_id', $criterion->id)->first() : null;
        $uploader = User::query()->where('email', 'devolution.admin@idmis.test')->first();
        $assessors = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', UserRole::Assessor->value))
            ->with('assignedCounties:id')
            ->get();

        if ($cycles->count() !== 4 || ! $criterion || ! $requirement || ! $uploader || $assessors->isEmpty()) {
            throw new RuntimeException('The governed assessment configuration and local access profiles must be seeded before programme records.');
        }

        County::query()->orderBy('code')->each(function (County $county) use ($cycles, $criterion, $requirement, $uploader, $assessors): void {
            $assessor = $assessors->first(fn (User $candidate): bool => $candidate->assignedCounties->contains('id', $county->id));
            if (! $assessor) {
                throw new RuntimeException("No assessor is authorized for {$county->name} County.");
            }
            foreach ($cycles as $cycle) {
                [$status, $score] = $this->assessmentOutcome($county->code, $cycle->code);
                $assessment = Assessment::query()->updateOrCreate(
                    ['county_id' => $county->id, 'cycle' => $cycle->code],
                    [
                        'assessment_cycle_id' => $cycle->id,
                        'assessment_scorecard_version_id' => $cycle->assessment_scorecard_version_id,
                        'status' => $status,
                        'score' => $score,
                        'completeness_percentage' => $this->completeness($status),
                        'attestation_status' => in_array($status, [AssessmentStatus::Approved, AssessmentStatus::Assessed], true) ? 'attested' : 'pending',
                        'assessor_id' => $assessor->id,
                        'assessed_at' => $score === null ? null : $cycle->period_end->copy()->addMonths(2),
                    ],
                );

                if ($cycle->code !== 'ACPA-2026-27') {
                    $this->seedEvidence($assessment, $county, $criterion, $requirement, $uploader, $cycle->code === 'ACPA-2025-26');
                }
            }

            $this->seedGrants($county);
        });

        $this->seedExchequerPipeline($uploader);
    }

    private function removeLegacyFactoryRecords(): void
    {
        AssessmentDocument::query()
            ->whereHas('assessment', fn ($query) => $query->where('cycle', '2025/26 ACPA'))
            ->each(function (AssessmentDocument $document): void {
                if (str_starts_with($document->path, 'demo-evidence/')) {
                    Storage::delete($document->path);
                }
                $document->delete();
            });
        Assessment::query()->where('cycle', '2025/26 ACPA')->each(fn (Assessment $assessment) => $assessment->delete());
        CountyGrant::query()
            ->where('programme', 'KDSP II')
            ->where('financial_year', '2025/26')
            ->each(fn (CountyGrant $grant) => $grant->delete());
    }

    /** @return array{AssessmentStatus, int|null} */
    private function assessmentOutcome(int $countyCode, string $cycleCode): array
    {
        return match ($cycleCode) {
            'ACPA-2023-24' => [AssessmentStatus::Approved, 57 + ($countyCode % 30)],
            'ACPA-2024-25' => [AssessmentStatus::Approved, 62 + ($countyCode % 28)],
            'ACPA-2025-26' => match ($countyCode % 4) {
                0 => [AssessmentStatus::Approved, 68 + ($countyCode % 24)],
                1 => [AssessmentStatus::Assessed, 64 + ($countyCode % 25)],
                2 => [AssessmentStatus::UnderAssessment, null],
                default => [AssessmentStatus::EvidenceCollection, null],
            },
            default => [AssessmentStatus::Draft, null],
        };
    }

    private function completeness(AssessmentStatus $status): int
    {
        return match ($status) {
            AssessmentStatus::Approved, AssessmentStatus::Assessed, AssessmentStatus::Published => 100,
            AssessmentStatus::UnderAssessment => 85,
            AssessmentStatus::Submitted => 75,
            AssessmentStatus::EvidenceCollection => 55,
            AssessmentStatus::Draft => 10,
        };
    }

    private function seedEvidence(Assessment $assessment, County $county, AssessmentCriterion $criterion, CriterionEvidenceRequirement $requirement, User $uploader, bool $includeCurrentCycleSet): void
    {
        $definitions = [
            [
                'category' => 'plan',
                'title' => "{$county->name} Annual Development Plan linkage matrix",
                'description' => 'Demonstration register showing how county priorities, budgets and expected results can be indexed for digital ACPA review.',
                'body' => ['Instrument: Annual Development Plan linkage matrix', 'Responsible office: County Department of Finance and Economic Planning', 'Review controls: approval reference, programme code, budget line, output indicator and responsible officer'],
            ],
        ];

        if ($includeCurrentCycleSet) {
            $definitions[] = [
                'category' => 'report',
                'title' => "{$county->name} quarterly implementation progress report",
                'description' => 'Demonstration implementation report with financial and physical progress fields for IDMIS preview and verification workflows.',
                'body' => ['Instrument: Quarterly implementation progress report', 'Coverage: projects, service outputs, expenditure and implementation risks', 'Verification controls: source register, reporting period, accountable officer and supporting attachments'],
            ];
            $definitions[] = [
                'category' => 'minutes',
                'title' => "{$county->name} public participation evidence register",
                'description' => 'Demonstration evidence index for notices, attendance, minutes, memoranda and county response to citizen input.',
                'body' => ['Instrument: Public participation evidence register', 'Coverage: notice, venue, ward representation, attendance and issues raised', 'Accountability controls: response matrix, publication record and responsible office'],
            ];
        }

        foreach ($definitions as $definition) {
            $path = sprintf('demo-evidence/%s/%s/%s.txt', $county->slug, strtolower($assessment->cycle), str($definition['title'])->slug());
            $contents = implode(PHP_EOL, [
                'IDMIS GOVERNED DEMONSTRATION EVIDENCE',
                'This file is a realistic training and workflow-validation record. It is not represented as an official county submission.',
                '',
                "County: {$county->name} (Code {$county->code})",
                "Assessment cycle: {$assessment->cycle}",
                "Document title: {$definition['title']}",
                "Evidence category: {$definition['category']}",
                'KDSP II context: minimum conditions, performance measures and independently verifiable county results.',
                '',
                ...$definition['body'],
                '',
                'Required supporting artefacts:',
                '- Signed approval or adoption reference',
                '- Source-system or physical register reference',
                '- Reporting period and responsible office',
                '- Evidence of review, verification and follow-up action',
            ]);
            Storage::put($path, $contents);

            AssessmentDocument::query()->updateOrCreate(
                ['assessment_id' => $assessment->id, 'title' => $definition['title']],
                [
                    'assessment_criterion_id' => $criterion->id,
                    'criterion_evidence_requirement_id' => $requirement->id,
                    'county_id' => $county->id,
                    'category' => $definition['category'],
                    'source_type' => 'soft_copy',
                    'path' => $path,
                    'original_name' => basename($path),
                    'mime_type' => 'text/plain',
                    'size_bytes' => Storage::size($path),
                    'content_checksum' => hash('sha256', $contents),
                    'scan_status' => 'clean',
                    'ocr_status' => 'not_required',
                    'security_classification' => 'official',
                    'record_status' => 'active',
                    'description' => $definition['description'],
                    'document_date' => $assessment->assessmentCycle->period_end,
                    'version' => 1,
                    'tags' => ['KDSP II', 'ACPA', $assessment->cycle, 'demonstration-evidence'],
                    'retention_until' => today()->addYears(7),
                    'verification_status' => in_array($assessment->status, [AssessmentStatus::Approved, AssessmentStatus::Assessed], true) ? 'verified' : 'pending',
                    'uploaded_by' => $uploader->id,
                ],
            );
        }
    }

    private function seedGrants(County $county): void
    {
        $baseAllocation = 38_000_000 + ($county->code * 1_150_000);
        foreach ([
            ['2024/25', $baseAllocation, (int) round($baseAllocation * (0.72 + (($county->code % 8) / 100))), 'disbursed'],
            ['2025/26', $baseAllocation + 6_500_000, (int) round(($baseAllocation + 6_500_000) * (0.48 + (($county->code % 12) / 100))), 'partially_disbursed'],
        ] as [$financialYear, $allocated, $disbursed, $status]) {
            CountyGrant::query()->updateOrCreate(
                ['county_id' => $county->id, 'programme' => 'Second Kenya Devolution Support Program (KDSP II)', 'financial_year' => $financialYear],
                ['allocated_amount' => $allocated, 'disbursed_amount' => $disbursed, 'status' => $status],
            );
        }
    }

    private function seedExchequerPipeline(User $administrator): void
    {
        CountyGrant::query()->where('financial_year', '2025/26')->with('county')->each(function (CountyGrant $grant) use ($administrator): void {
            $request = ExchequerRequest::query()->updateOrCreate(
                ['request_reference' => sprintf('EXQ-2026-%03d', $grant->county->code)],
                ['county_grant_id' => $grant->id, 'county_id' => $grant->county_id, 'created_by' => $administrator->id, 'tranche_reference' => 'ISDIG-TRANCHE-01', 'financial_year' => '2025/26', 'amount' => (float) $grant->disbursed_amount, 'currency' => ReferenceCatalogue::defaultCurrency(), 'current_stage' => 'credited', 'status' => 'completed', 'stage_due_at' => null, 'last_event_at' => '2026-06-24 14:00:00+03', 'credited_at' => '2026-06-24 14:00:00+03'],
            );
            foreach ([
                ['TREASURY', 'submitted_to_treasury', '2026-06-17 09:00:00+03', 0, 0],
                ['OCOB', 'ocob_authorized', '2026-06-21 11:00:00+03', 5880, 5880],
                ['CBK', 'cbk_credited', '2026-06-24 14:00:00+03', 4500, 10380],
            ] as $index => [$source, $event, $occurredAt, $elapsed, $total]) {
                $reference = sprintf('%s-%03d-%02d', $source, $grant->county->code, $index + 1);
                ExchequerEvent::query()->firstOrCreate(['source_system' => $source, 'source_event_reference' => $reference], ['exchequer_request_id' => $request->id, 'recorded_by' => $administrator->id, 'event_type' => $event, 'occurred_at' => $occurredAt, 'received_at' => $occurredAt, 'elapsed_from_previous_minutes' => $elapsed, 'elapsed_total_minutes' => $total, 'notes' => 'Deterministic KDSP II exchequer workflow evidence for local validation.', 'evidence_checksum' => hash('sha256', "{$request->request_reference}|{$reference}|{$occurredAt}")]);
            }
        });
    }
}
