<?php

return [
    'privileged_roles' => ['county-admin', 'assessor', 'top-management', 'devolution-admin', 'platform-admin'],
    'access_review_due_days' => (int) env('ACCESS_REVIEW_DUE_DAYS', 21),
    'threat_review_months' => (int) env('THREAT_REVIEW_MONTHS', 6),
    'sbom_disk' => env('SECURITY_SBOM_DISK', 'local'),
    'sbom_path' => env('SECURITY_SBOM_PATH', 'security/sbom'),
    'scan_timeout_seconds' => (int) env('SECURITY_SCAN_TIMEOUT_SECONDS', 120),
    'additional_javascript_lockfiles' => ['pnpm-lock.yaml'],
    'incident_sla_minutes' => [
        'sev1' => ['acknowledge' => 15, 'contain' => 60],
        'sev2' => ['acknowledge' => 30, 'contain' => 240],
        'sev3' => ['acknowledge' => 120, 'contain' => 480],
        'sev4' => ['acknowledge' => 480, 'contain' => 1440],
    ],
    'incident_reminder_minutes' => (int) env('SECURITY_INCIDENT_REMINDER_MINUTES', 15),
    'identity_lifecycle_service_user_email' => env('IDENTITY_LIFECYCLE_SERVICE_USER_EMAIL'),
    'identity_lifecycle_max_application_attempts' => (int) env('IDENTITY_LIFECYCLE_MAX_APPLICATION_ATTEMPTS', 5),
];
