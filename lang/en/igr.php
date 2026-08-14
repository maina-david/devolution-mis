<?php

return ['ui' => [
    'eyebrow' => 'Intergovernmental accountability', 'title' => 'IGR resolutions tracking',
    'description' => 'Turn summit, council and committee resolutions into uniquely identified, deadline-bound commitments with named parties, implementation evidence, gap reporting, reminders and independent closure.',
    'gap_risk_profile' => 'Implementation-gap risk profile', 'gap_risk_profile_description' => 'Filter-aware exposure across the resolutions and counties this role is authorized to see.',
    'dependency_paths' => 'Resolution dependency paths', 'dependency_paths_description' => 'Scope-safe prerequisite chains and unresolved blocking relationships that determine whether a resolution can proceed to closure.',
    'arrow' => '→', 'separator' => '·', 'blocked' => 'Blocked', 'gap_lifecycle_trend' => 'Gap lifecycle trend',
    'gap_lifecycle_trend_description' => 'New gaps versus independently accepted resolutions over the latest six calendar months.',
    'active_gap_aging' => 'Active-gap aging', 'active_gap_aging_description' => 'Time since reporting for gaps that still need independent acceptance.',
    'risk_concentration' => 'Risk concentration', 'risk_concentration_description' => 'Ranked categories, severities and affected counties for targeted intergovernmental intervention.',
    'county_bottlenecks' => 'County bottlenecks', 'national_multi_county' => 'National / multi-county', 'active' => 'active', 'overdue' => 'overdue',
    'no_county_gaps' => 'No county-specific gaps match the selected filters.', 'implementation_workspace' => 'Resolution implementation workspace',
    'implementation_workspace_description' => 'Current commitments and their recent implementation history.', 'no_matching_data' => 'No data matches the selected filters.',
    'create_forum' => 'Create forum', 'confirm_quorum' => 'Confirm that the formal meeting achieved quorum under the forum mandate.', 'record_meeting' => 'Record meeting',
    'create_category' => 'Create category', 'responsible_parties' => 'Responsible parties', 'add_party' => 'Add party', 'register_notify_parties' => 'Register and notify parties',
    'resolved' => 'resolved', 'due' => 'due', 'implementation' => 'Implementation', 'percent' => '%', 'formal_meeting_provenance' => 'Formal meeting provenance',
    'minutes_label' => 'Minutes:', 'historical_meeting_unlinked' => 'Historical record — formal meeting not linked', 'implementation_gap' => 'Implementation gap',
    'governed_implementation_gaps' => 'Governed implementation gaps', 'assign_gap' => 'Assign gap', 'add_dependency' => 'Add dependency', 'record_progress' => 'Record progress',
    'recent_history_open' => 'Recent implementation history (', 'close_parenthesis' => ')', 'evidence_label' => 'Evidence:', 'none_recorded' => 'None recorded.',
    'owner_label' => 'Owner:', 'impact_label' => 'Impact:', 'mitigation_label' => 'Mitigation:', 'resolution_label' => 'Resolution:',
], 'outcomes' => [
    'meeting_recorded' => 'Formal IGR meeting recorded.', 'gap_category_created' => 'IGR gap category created.', 'forum_created' => 'IGR forum created.', 'resolution_registered' => 'Resolution registered and responsible parties notified.', 'implementation_updated' => 'Implementation update recorded.', 'dependency_recorded' => 'Resolution dependency recorded.', 'gap_recorded' => 'Implementation gap recorded and assigned.', 'gap_updated' => 'Implementation gap lifecycle updated.', 'resolution_updated' => 'Resolution lifecycle updated.',
], 'errors' => [
    'workflow_unavailable' => 'Resolution workflow is unavailable.', 'blocking_prerequisites_open' => 'All blocking prerequisite resolutions must be closed before closure review.', 'gaps_not_accepted' => 'All implementation gaps require independent acceptance before closure review.',
    'resolution_create_unauthorized' => 'You are not authorized to register IGR resolutions.', 'meeting_forum_mismatch' => 'The selected meeting must belong to the resolution forum.', 'meeting_quorum_required' => 'A resolution can only be linked to a quorum-confirmed meeting.',
    'county_outside_scope' => 'The selected county is outside your authorized scope.', 'single_lead_required' => 'Exactly one lead assignment is required.', 'dependency_create_unauthorized' => 'You are not authorized to manage resolution dependencies.',
    'resolution_outside_scope' => 'This resolution is outside your authorized scope.', 'dependency_self_reference' => 'A resolution cannot depend on itself.', 'dependency_after_closure_review' => 'Dependencies cannot be added after closure review starts.',
    'dependency_exists' => 'This dependency already exists.', 'dependency_cycle' => 'This dependency would create a circular resolution chain.', 'gap_create_unauthorized' => 'You are not authorized to report implementation gaps.',
    'gap_implementation_inactive' => 'Gaps can be reported only while implementation is active.', 'gap_owner_responsible' => 'The gap owner must be a responsible party for this resolution.', 'gap_county_assignment' => 'The affected county must be assigned to this resolution.',
    'gap_outside_scope' => 'This implementation gap is outside your authorized scope.', 'gap_transition_unavailable' => 'This gap transition is not available from the current state.', 'gap_independent_acceptance' => 'Independent acceptance is required.',
    'gap_concurrent_change' => 'The gap changed while this decision was being made.', 'progress_active_required' => 'Updates are accepted only while implementation is active.', 'progress_regression' => 'Progress cannot move backwards.',
], 'audit' => [
    'forum_created' => 'IGR forum :code created.', 'resolution_transitioned' => 'Resolution :number transitioned to :state.',
    'resolution_created' => 'IGR resolution :number registered.', 'dependency_created' => ':dependent linked to prerequisite :prerequisite.', 'gap_reported' => 'Implementation gap reported for :number.', 'gap_transitioned' => 'IGR gap transitioned to :status.', 'progress_reported' => 'Progress reported for :number.',
], 'notifications' => [
    'assignment_title' => 'New IGR resolution assignment', 'assignment_message' => 'You are responsible for :number: :title.',
    'gap_assigned_title' => 'IGR implementation gap assigned', 'gap_assigned_message' => 'You own :title for :number.',
], 'gap_statuses' => [
    'open' => 'open', 'mitigating' => 'under mitigation', 'resolved' => 'resolved', 'accepted' => 'accepted',
]];
