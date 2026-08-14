<?php

return [
    'ui' => [
        'eyebrow' => 'Service assurance and recovery', 'title' => 'Operational readiness centre',
        'description' => 'Dependency probes, SLO measurements, checksummed backups, isolated restore evidence, scheduled controls, and independently validated release and rollback history.',
        'ms' => 'ms', 'operational_alerts' => 'Operational alerts', 'operational_alerts_description' => 'governed threshold alerts with deduplicated recurrence, acknowledgement and automatic recovery evidence. Thresholds remain provisional until service-owner approval.',
        'failed_queue_jobs' => 'Failed queue jobs', 'failed_queue_jobs_description' => 'retained failures. Payload and exception contents remain hidden; operators receive checksums and safe classifications.',
        'immutable_recovery_evidence' => 'Immutable recovery evidence', 'immutable_recovery_evidence_description' => 'Latest operator-attributed requeue outcomes; successful jobs may leave the failed register, but this evidence remains.',
        'performance_assurance_evidence' => 'Performance assurance evidence', 'performance_assurance_evidence_description' => 'immutable, checksum-bound HTTP concurrency runs. Thresholds are environment snapshots and do not constitute Konza production certification.',
        'release_rollback_evidence' => 'Release and rollback evidence', 'release_rollback_evidence_description' => 'Deployments require independent validation before they become approved rollback targets.',
        'latest_service_measurements' => 'Latest service measurements', 'separator' => '·', 'measurements_empty' => 'Measurements will appear after the scheduled operational probe.', 'scheduled_controls' => 'Scheduled controls',
        'view_alert_evidence' => 'View alert evidence', 'immutable_timeline' => 'Immutable timeline', 'showing_latest' => 'Showing the latest', 'of' => 'of', 'retained_events' => 'retained events.',
        'accountable_response_note' => 'Accountable response note', 'acknowledge_alert' => 'Acknowledge alert', 'view_evidence' => 'View evidence', 'performance_run_evidence' => 'Performance run evidence', 'threshold_snapshot' => 'Threshold snapshot',
        'view_recovery_evidence' => 'View recovery evidence', 'failed' => 'failed', 'requeue_description' => 'Requeue the retained payload without exposing it. The original failure leaves the active register only after the queue accepts it, and an immutable attributed attempt is retained either way.',
        'retry_failed_job' => 'Retry failed job', 'backup_request_description' => 'The queue worker will record artifact size, SHA-256 checksum, timestamps and any failure. Restore verification is a separate controlled action.', 'queue_backup' => 'Queue backup',
        'record_deployment' => 'Record deployment', 'restore_description' => 'Queue an isolated restore into a generated temporary database. The verifier counts restored tables and drops only that validated temporary target.',
        'verify_isolated_restore' => 'Verify isolated restore', 'independently_validate' => 'Independently validate', 'record_rollback' => 'Record rollback', 'validate_release' => 'Validate release',
        'record_rollback_decision' => 'Record rollback decision', 'backup_restore_evidence' => 'Backup and restore evidence', 'recovery_artifacts' => 'recovery artifacts', 'export' => 'Export',
    ],
    'readiness' => [
        'search_indexes_available' => ':count required discovery indexes are available.',
        'search_indexes_missing' => 'Required discovery indexes are unavailable: :indexes',
    ],
    'labels' => ['unknown_queued_job' => 'Unknown queued job'],
    'outcomes' => [
        'release_recorded' => 'Deployment record created for independent validation.', 'release_validated' => 'Release independently validated.',
        'rollback_recorded' => 'Rollback decision recorded. Execute the approved deployment runbook and attach platform evidence.', 'backup_queued' => 'Database backup queued.',
        'restore_verification_queued' => 'Isolated restore verification queued.', 'failed_job_requeued' => 'Failed job requeued with immutable recovery evidence.',
        'failed_job_rejected' => 'Queue provider rejected the recovery request; the failed job remains available.', 'alert_acknowledged' => 'Operational alert acknowledged with immutable response evidence.',
    ],
    'audit' => ['release_recorded' => 'Release :version recorded for :environment.'],
    'performance' => [
        'errors' => ['base_url_required' => 'A configured base URL and route path are required.', 'request_count_range' => 'Request count is outside the configured safe range.', 'concurrency_range' => 'Concurrency is outside the configured safe range.', 'route_not_approved' => 'The requested route is not approved for performance probing.', 'target_not_approved' => 'The target must be an approved same-environment HTTPS host.'],
        'cli' => ['evidence' => 'Evidence', 'outcome' => 'Outcome', 'requests_per_second' => 'Requests/sec', 'p95_ms' => 'P95 ms', 'failures' => 'Failures', 'checksum' => 'Checksum', 'unavailable' => 'unavailable'],
    ],
    'backup' => ['errors' => [
        'temporary_backup_path' => 'Unable to allocate a temporary backup path.', 'persist_backup' => 'Unable to persist the database backup on the configured backup disk.',
        'completed_required' => 'Only completed backups can be verified.', 'temporary_restore_path' => 'Unable to allocate a temporary restore path.',
        'read_artifact' => 'Unable to read the backup artifact for verification.', 'checksum_failed' => 'Backup checksum verification failed.',
        'manifest_parse_failed' => 'Backup manifest could not be parsed.', 'manifest_empty' => 'Backup manifest contains no application tables.',
        'unsafe_restore_target' => 'Unsafe restore probe target.', 'restored_table_count' => 'Restored database table count is below the backup manifest count.',
        'postgresql_required' => 'Operational backup currently requires PostgreSQL.',
    ]],
];
