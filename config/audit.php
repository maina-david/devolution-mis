<?php

$signingKeys = json_decode((string) env('AUDIT_ASSURANCE_SIGNING_KEYS', '{}'), true);

return [
    'assurance_disk' => env('AUDIT_ASSURANCE_DISK', 'local'),
    'assurance_path' => env('AUDIT_ASSURANCE_PATH', 'audit/assurance'),
    'active_signing_key' => env('AUDIT_ASSURANCE_ACTIVE_KEY'),
    'signing_keys' => is_array($signingKeys) ? $signingKeys : [],
];
