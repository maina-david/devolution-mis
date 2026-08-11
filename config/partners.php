<?php

return [
    'agreement_expiry_notice_days' => (int) env('PARTNER_AGREEMENT_EXPIRY_NOTICE_DAYS', 60),
    'contribution_reconciliation_due_days' => (int) env('PARTNER_CONTRIBUTION_RECONCILIATION_DUE_DAYS', 30),
    'contribution_exchange_lookback_days' => (int) env('PARTNER_CONTRIBUTION_EXCHANGE_LOOKBACK_DAYS', 7),
    'reconciliation_service_user_email' => env('PARTNER_RECONCILIATION_SERVICE_USER_EMAIL'),
    'collaboration_action_reminder_days' => (int) env('PARTNER_COLLABORATION_ACTION_REMINDER_DAYS', 7),
];
