<?php

return [
    'sla_hours' => [
        'critical' => ['first_response' => 1, 'resolution' => 4],
        'high' => ['first_response' => 4, 'resolution' => 16],
        'medium' => ['first_response' => 8, 'resolution' => 40],
        'low' => ['first_response' => 16, 'resolution' => 80],
    ],
    'reminder_hours' => 2,
    'monitor_batch_limit' => (int) env('SERVICE_DESK_MONITOR_BATCH_LIMIT', 500),
    'monitor_candidate_multiplier' => 5,
    'monitor_max_candidates' => 5000,
    'monitor_max_lookahead_hours' => 168,
];
