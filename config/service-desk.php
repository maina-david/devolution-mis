<?php

return [
    'sla_hours' => [
        'critical' => ['first_response' => 1, 'resolution' => 4],
        'high' => ['first_response' => 4, 'resolution' => 16],
        'medium' => ['first_response' => 8, 'resolution' => 40],
        'low' => ['first_response' => 16, 'resolution' => 80],
    ],
    'reminder_hours' => 2,
];
