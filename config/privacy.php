<?php

return [
    'public_notice' => [
        'version' => env('PRIVACY_PUBLIC_NOTICE_VERSION', '2026-08'),
        'issued_on' => env('PRIVACY_PUBLIC_NOTICE_ISSUED_ON', '2026-08-12'),
        'approval_status' => env('PRIVACY_PUBLIC_NOTICE_APPROVAL_STATUS', 'draft_pending_dpo_legal_approval'),
    ],
    'data_subject_request_target_days' => (int) env('PRIVACY_DSR_TARGET_DAYS', 30),
    'processing_review_months' => (int) env('PRIVACY_PROCESSING_REVIEW_MONTHS', 12),
    'controller_notification_hours' => (int) env('PRIVACY_CONTROLLER_NOTIFICATION_HOURS', 48),
    'regulator_notification_hours' => (int) env('PRIVACY_REGULATOR_NOTIFICATION_HOURS', 72),
    'breach_reminder_hours' => (int) env('PRIVACY_BREACH_REMINDER_HOURS', 12),
];
