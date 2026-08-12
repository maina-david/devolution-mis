<?php

return [
    'validation' => [
        'minimum_counties' => 'The minimum county threshold cannot exceed the selected counties.',
    ],
    'messages' => [
        'uat_campaign_created' => 'Pilot UAT campaign :code was created as a plan; no execution or acceptance is implied.',
        'uat_scenario_created' => 'UAT scenario :code was added to the campaign.',
        'uat_execution_recorded' => 'Immutable UAT execution evidence was recorded with outcome :outcome.',
        'uat_finding_transitioned' => 'The UAT finding is now :status.',
        'uat_campaign_submitted' => 'The campaign was submitted for independent acceptance.',
        'uat_campaign_decided' => 'The independent UAT decision was recorded as :decision.',
    ],
    'uat_errors' => [
        'county_missing' => 'Every selected UAT county must exist.', 'county_scope' => 'A selected UAT county is outside your authorized scope.', 'campaign_scope' => 'This UAT campaign is outside your authorized county portfolio.',
        'scenario_planning_only' => 'Scenarios can only be added while the campaign is in planning.', 'execution_closed' => 'Only ready scenarios in an open campaign may be executed.', 'actor_role' => 'This scenario must be executed by a representative user with the configured actor role.',
        'execution_county_scope' => 'The execution county is outside your authorized scope.', 'execution_county_campaign' => 'The execution county is not part of this UAT campaign.', 'finding_owner_scope' => 'The corrective-action owner is not authorized for the execution county.',
        'resolve_separation' => 'Only the independently assigned owner may resolve an open finding.', 'verify_separation' => 'Verification requires a resolved finding and an independent verifier.', 'reopen_separation' => 'Only an independent reviewer may reopen a resolved finding.',
        'submit_state' => 'Only an executed or rejected campaign without a pending review may be submitted.', 'submit_coverage' => 'The campaign requires its minimum county coverage and at least one required ready scenario.', 'submit_evidence' => 'Submission requires a passing latest execution for every required scenario and county pair, all required roles, and independently verified findings.',
        'decision_state' => 'Only a pending campaign review may be decided.', 'decision_separation' => 'The campaign author, submitter and scenario testers cannot independently decide acceptance.',
    ],
    'uat' => [
        'tab' => 'Pilot UAT',
        'eyebrow' => 'Representative testing and formal acceptance',
        'title' => 'Governed pilot acceptance',
        'description' => 'Plan representative scenarios, record immutable county execution evidence, close findings independently and retain formal acceptance history.',
        'new_campaign' => 'Plan UAT campaign',
        'empty_title' => 'No UAT campaigns in scope',
        'empty_description' => 'Create a governed pilot plan without implying that testing or acceptance has occurred.',
        'campaigns' => 'Campaigns in scope', 'scenarios' => 'Required scenarios', 'executions' => 'Recorded executions', 'open_findings' => 'Open findings',
        'search' => 'Search UAT campaigns', 'status' => 'Status', 'county' => 'County', 'campaign' => 'Campaign', 'environment' => 'Environment', 'counties' => 'Counties', 'acceptance' => 'Acceptance', 'not_submitted' => 'Not submitted',
        'export_evidence' => 'Export UAT evidence',
        'open_actions' => 'Open UAT campaign actions', 'open_record' => 'Open complete UAT record', 'catalogue' => 'Catalogue', 'period' => 'Pilot period', 'creator' => 'Created by', 'required_roles' => 'Required representative roles', 'acceptance_criteria' => 'Acceptance criteria',
        'no_scenarios' => 'No scenarios configured', 'no_scenarios_description' => 'Add representative end-to-end scenarios before recording pilot evidence.', 'national' => 'National',
        'new_campaign_description' => 'Define scope and acceptance criteria. Saving this form records planning evidence only.', 'catalogue_required' => 'Publish a complete effective reference catalogue first.', 'code' => 'Code', 'name' => 'Name', 'objective' => 'Objective', 'starts_on' => 'Starts on', 'ends_on' => 'Ends on', 'minimum_counties' => 'Minimum counties required', 'acceptance_criterion' => 'Acceptance criterion', 'save_plan' => 'Save pilot plan',
        'add_scenario' => 'Add scenario', 'add_scenario_description' => 'Define a role-specific, accessible and low-connectivity test journey.', 'title_label' => 'Scenario title', 'module' => 'ToR module', 'actor_role' => 'Representative actor role', 'priority' => 'Priority', 'journey' => 'End-to-end journey', 'precondition' => 'Precondition', 'step' => 'Test step', 'expected_result' => 'Expected result', 'accessibility_variant' => 'Accessibility test variant', 'low_connectivity_variant' => 'Constrained-connectivity variant', 'save_scenario' => 'Save scenario',
        'record_execution' => 'Record execution', 'representative_role' => 'Required representative role', 'outcome' => 'Outcome', 'actual_result' => 'Observed result', 'evidence_reference' => 'Evidence reference', 'started_at' => 'Started at', 'completed_at' => 'Completed at', 'finding_owner' => 'Corrective-action owner', 'severity' => 'Severity', 'finding_title' => 'Finding title', 'finding_description' => 'Finding description', 'finding_due_on' => 'Finding due on', 'record_immutable_evidence' => 'Record immutable evidence',
        'transition_finding' => 'Transition UAT finding', 'review_finding' => 'Review finding', 'action' => 'Action', 'resolution' => 'Resolution and evidence', 'save_transition' => 'Record transition',
        'submit_acceptance' => 'Submit for acceptance', 'submit_acceptance_description' => 'The platform will recheck every scenario/county pair, representative role and finding.', 'confirm_criteria' => 'I confirm the configured criteria are ready for an independent system check.',
        'decide_acceptance' => 'Decide acceptance', 'evidence_checksum' => 'Evidence checksum', 'decision' => 'Decision', 'decision_reason' => 'Independent decision rationale', 'record_decision' => 'Record independent decision',
    ],
];
