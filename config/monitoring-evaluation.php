<?php

return [
    'finding_reminder_days_before_due' => (int) env('EVALUATION_FINDING_REMINDER_DAYS', 7),
    'finding_escalation_permission' => env('EVALUATION_FINDING_ESCALATION_PERMISSION', 'indicators:manage'),
];
