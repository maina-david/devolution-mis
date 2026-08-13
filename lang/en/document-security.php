<?php

return [
    'errors' => [
        'upload_unavailable' => 'The uploaded file is unavailable for malware inspection.',
        'checksum_failed' => 'The uploaded file checksum could not be calculated.',
        'scanner_unsupported' => 'The configured document malware scanner is not supported.',
        'production_clamav_required' => 'Production document scanning requires the approved ClamAV scanner.',
        'clamav_unavailable' => 'The ClamAV scanner dependency is unavailable.',
        'scan_failed' => 'The document malware scan could not be completed.',
        'development_gate_prohibited' => 'The development signature gate is prohibited in production.',
        'upload_open_failed' => 'The uploaded file could not be opened for malware inspection.',
        'upload_read_failed' => 'The uploaded file could not be read for malware inspection.',
    ],
    'readiness' => [
        'development_gate' => 'Development-only EICAR signature gate is active; production requires ClamAV.',
        'clamav_available' => 'ClamAV is available: :version',
        'version_reported' => 'version reported',
    ],
];
