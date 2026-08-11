<?php

return [
    'allowed_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('INTEGRATION_ALLOWED_HOSTS', ''))))),
    'retry_service_user_email' => env('INTEGRATION_RETRY_SERVICE_USER_EMAIL'),
    'credentials' => [
        'ifmis' => env('IFMIS_API_TOKEN'),
        'ippd' => env('IPPD_API_TOKEN'),
        'ocob' => env('OCOB_API_TOKEN'),
        'cbk' => env('CBK_API_TOKEN'),
    ],
];
