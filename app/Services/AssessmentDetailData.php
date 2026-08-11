<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentCorrectiveAction;
use App\Models\AssessmentCorrectiveUpdate;
use App\Models\AssessmentCriterion;
use App\Models\AssessmentFunction;
use App\Models\AssessmentStandard;
use App\Models\AssessmentThematicArea;
use App\Models\User;

class AssessmentDetailData
{
    public function __construct(private AssessmentBenchmarkService $benchmarkService, private ProgrammeCountyScope $countyScope) {}

    /** @return array<string, mixed> */
    public function for(Assessment $assessment, User $user): array
    {
        $authorizedCountyIds = [];
        foreach ($this->countyScope->query($user)->pluck('id') as $countyId) {
            $authorizedCountyIds[] = (string) $countyId;
        }
        $assessment->load([
            'county:id,name,code,logo_path',
            'assessmentCycle:id,code,name,period_start,period_end',
            'referenceDataRelease:id,version,effective_from,checksum',
            'creator:id,name',
            'scorecardVersion.scorecard:id,name',
            'scorecardVersion.functions' => fn ($query) => $query->orderBy('sequence'),
            'scorecardVersion.functions.thematicAreas' => fn ($query) => $query->orderBy('sequence'),
            'scorecardVersion.functions.thematicAreas.standards' => fn ($query) => $query->orderBy('sequence'),
            'scorecardVersion.functions.thematicAreas.standards.criteria' => fn ($query) => $query->orderBy('sequence'),
            'scorecardVersion.functions.thematicAreas.standards.criteria.evidenceRequirements',
            'criterionResults', 'documents', 'findings', 'attestations', 'appeals', 'publication',
            'correctivePlans.finding:id,code,title,severity,status',
            'correctivePlans.appeal:id,status,grounds',
            'correctivePlans.submitter:id,name',
            'correctivePlans.reviewer:id,name',
            'correctivePlans.closer:id,name',
            'correctivePlans.actions.owner:id,name',
            'correctivePlans.actions.updates.document:id,title,verification_status,scan_status',
            'correctivePlans.actions.updates.submitter:id,name',
            'correctivePlans.actions.updates.verifier:id,name',
        ]);

        return [
            'assessment' => [
                'id' => $assessment->id,
                'county' => $assessment->county->identityCell(),
                'cycle' => $assessment->assessmentCycle ? ['code' => $assessment->assessmentCycle->code, 'name' => $assessment->assessmentCycle->name, 'periodStart' => $assessment->assessmentCycle->period_start->toDateString(), 'periodEnd' => $assessment->assessmentCycle->period_end->toDateString()] : ['code' => $assessment->cycle, 'name' => $assessment->cycle, 'periodStart' => null, 'periodEnd' => null],
                'scorecard' => $assessment->scorecardVersion ? ['name' => $assessment->scorecardVersion->scorecard->name, 'version' => $assessment->scorecardVersion->version, 'checksum' => $assessment->scorecardVersion->checksum] : null,
                'referenceRelease' => $assessment->referenceDataRelease ? ['version' => $assessment->referenceDataRelease->version, 'effectiveFrom' => $assessment->referenceDataRelease->effective_from?->toDateString(), 'checksum' => $assessment->referenceDataRelease->checksum] : null,
                'createdBy' => $assessment->created_by ? $assessment->creator->name : null,
                'status' => $assessment->status->value,
                'score' => $assessment->score,
                'completeness' => $assessment->completeness_percentage,
                'attestationStatus' => $assessment->attestation_status,
                'functions' => $this->functions($assessment),
                'findings' => $assessment->findings->map->only(['id', 'code', 'severity', 'status', 'title', 'description', 'county_response', 'response_due_at']),
                'appeals' => $assessment->appeals->map->only(['id', 'status', 'grounds', 'requested_remedy', 'decision']),
                'attestations' => $assessment->attestations->map->only(['id', 'attestor_title', 'statement', 'content_checksum', 'attested_at']),
                'publication' => $assessment->publication ? ['id' => $assessment->publication->id, 'score' => $assessment->publication->score, 'performanceBand' => $assessment->publication->performance_band, 'checksum' => $assessment->publication->checksum, 'functionProfile' => $assessment->publication->function_profile, 'publishedAt' => $assessment->publication->published_at->toIso8601String()] : null,
                'rankings' => $assessment->assessment_cycle_id ? $this->benchmarkService->rankings($assessment->assessment_cycle_id, $authorizedCountyIds) : [],
                'correctivePlans' => $this->correctivePlans($assessment),
                'correctiveOptions' => $this->correctiveOptions($assessment),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function correctivePlans(Assessment $assessment): array
    {
        $plans = [];
        foreach ($assessment->correctivePlans as $plan) {
            $actions = [];
            foreach ($plan->actions as $action) {
                $updates = [];
                foreach ($action->updates as $update) {
                    $updates[] = $this->correctiveUpdate($update);
                }
                $actions[] = $this->correctiveAction($action, $updates);
            }
            $plans[] = [
                'id' => $plan->id, 'reference' => $plan->reference, 'title' => $plan->title, 'rootCause' => $plan->root_cause, 'expectedOutcome' => $plan->expected_outcome, 'status' => $plan->status, 'dueAt' => $plan->due_at->toDateString(), 'checksum' => $plan->checksum,
                'source' => $plan->assessment_finding_id ? ['type' => 'finding', 'id' => $plan->assessment_finding_id, 'label' => "{$plan->finding->code} · {$plan->finding->title}"] : ['type' => 'appeal', 'id' => $plan->assessment_appeal_id, 'label' => "Accepted appeal · {$plan->appeal->grounds}"],
                'submittedBy' => $plan->submitter->name, 'reviewedBy' => $plan->reviewed_by ? $plan->reviewer->name : null, 'reviewNote' => $plan->review_note, 'closedBy' => $plan->closed_by ? $plan->closer->name : null, 'closureDecision' => $plan->closure_decision, 'actions' => $actions,
            ];
        }

        return $plans;
    }

    /**
     * @param  list<array<string, mixed>>  $updates
     * @return array<string, mixed>
     */
    private function correctiveAction(AssessmentCorrectiveAction $action, array $updates): array
    {
        return ['id' => $action->id, 'code' => $action->code, 'title' => $action->title, 'description' => $action->description, 'successIndicator' => $action->success_indicator, 'target' => $action->target, 'dueAt' => $action->due_at->toDateString(), 'progress' => (float) $action->progress_percentage, 'status' => $action->status, 'owner' => $action->owner->name, 'updates' => $updates];
    }

    /** @return array<string, mixed> */
    private function correctiveUpdate(AssessmentCorrectiveUpdate $update): array
    {
        return ['id' => $update->id, 'progress' => (float) $update->progress_percentage, 'narrative' => $update->narrative, 'status' => $update->status, 'decisionNote' => $update->decision_note, 'checksum' => $update->checksum, 'document' => ['id' => $update->document->id, 'title' => $update->document->title], 'submittedBy' => $update->submitter->name, 'verifiedBy' => $update->verified_by ? $update->verifier->name : null];
    }

    /** @return array{sources: list<array{value: string, label: string}>, evidence: list<array{value: string, label: string}>, owners: list<array{value: string, label: string}>} */
    private function correctiveOptions(Assessment $assessment): array
    {
        $sources = [];
        foreach ($assessment->findings as $finding) {
            if ($finding->status === 'resolved' && in_array($finding->severity, ['major', 'critical'], true) && ! $assessment->correctivePlans->contains('assessment_finding_id', $finding->id)) {
                $sources[] = ['value' => "finding:{$finding->id}", 'label' => "Finding {$finding->code} · {$finding->title}"];
            }
        }
        foreach ($assessment->appeals as $appeal) {
            if ($appeal->status === 'accepted' && ! $assessment->correctivePlans->contains('assessment_appeal_id', $appeal->id)) {
                $sources[] = ['value' => "appeal:{$appeal->id}", 'label' => "Accepted appeal · {$appeal->grounds}"];
            }
        }
        $evidence = [];
        foreach ($assessment->documents as $document) {
            if ($document->verification_status === 'verified' && $document->scan_status === 'clean' && $document->record_status !== 'disposed') {
                $evidence[] = ['value' => $document->id, 'label' => $document->title];
            }
        }
        $owners = [];
        foreach ($assessment->county->users()->orderBy('name')->get(['users.id', 'users.name']) as $owner) {
            $owners[] = ['value' => $owner->id, 'label' => $owner->name];
        }

        return ['sources' => $sources, 'evidence' => $evidence, 'owners' => $owners];
    }

    /** @return list<array<string, mixed>> */
    private function functions(Assessment $assessment): array
    {
        if ($assessment->assessment_scorecard_version_id === null) {
            return [];
        }

        $items = [];
        foreach ($assessment->scorecardVersion->functions as $function) {
            $items[] = ['id' => $function->id, 'code' => $function->code, 'name' => $function->name, 'weight' => $function->weight, 'themes' => $this->themes($assessment, $function)];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function themes(Assessment $assessment, AssessmentFunction $function): array
    {
        $items = [];
        foreach ($function->thematicAreas as $theme) {
            $items[] = ['id' => $theme->id, 'code' => $theme->code, 'name' => $theme->name, 'standards' => $this->standards($assessment, $theme)];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function standards(Assessment $assessment, AssessmentThematicArea $theme): array
    {
        $items = [];
        foreach ($theme->standards as $standard) {
            $items[] = ['id' => $standard->id, 'code' => $standard->code, 'name' => $standard->name, 'normReference' => $standard->norm_reference, 'criteria' => $this->criteria($assessment, $standard)];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function criteria(Assessment $assessment, AssessmentStandard $standard): array
    {
        $items = [];
        foreach ($standard->criteria as $criterion) {
            $result = $assessment->criterionResults->firstWhere('assessment_criterion_id', $criterion->id);
            $items[] = ['id' => $criterion->id, 'code' => $criterion->code, 'name' => $criterion->name, 'maximumScore' => $criterion->maximum_score, 'weight' => $criterion->weight, 'submittedScore' => $result?->submitted_score, 'verifiedScore' => $result?->verified_score, 'overrideScore' => $result?->override_score, 'weightedScore' => $result?->weighted_score, 'resultId' => $result?->id, 'requirements' => $this->requirements($assessment, $criterion)];
        }

        return $items;
    }

    /** @return list<array<string, mixed>> */
    private function requirements(Assessment $assessment, AssessmentCriterion $criterion): array
    {
        $items = [];
        foreach ($criterion->evidenceRequirements as $requirement) {
            $items[] = ['id' => $requirement->id, 'code' => $requirement->code, 'name' => $requirement->name, 'minimumDocuments' => $requirement->minimum_documents, 'verifiedDocuments' => $assessment->documents->where('criterion_evidence_requirement_id', $requirement->id)->where('verification_status', 'verified')->count(), 'allowedCategories' => $requirement->allowed_categories, 'acceptedMimeTypes' => $requirement->accepted_mime_types];
        }

        return $items;
    }
}
