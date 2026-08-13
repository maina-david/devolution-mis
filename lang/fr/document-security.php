<?php

return [
    'errors' => [
        'upload_unavailable' => 'Le fichier téléversé n’est pas disponible pour l’inspection antimalware.',
        'checksum_failed' => 'La somme de contrôle du fichier téléversé n’a pas pu être calculée.',
        'scanner_unsupported' => 'L’analyseur antimalware de documents configuré n’est pas pris en charge.',
        'production_clamav_required' => 'L’analyse des documents en production nécessite l’analyseur ClamAV approuvé.',
        'clamav_unavailable' => 'La dépendance de l’analyseur ClamAV n’est pas disponible.',
        'scan_failed' => 'L’analyse antimalware du document n’a pas pu être effectuée.',
        'development_gate_prohibited' => 'Le contrôle de signature de développement est interdit en production.',
        'upload_open_failed' => 'Le fichier téléversé n’a pas pu être ouvert pour l’inspection antimalware.',
        'upload_read_failed' => 'Le fichier téléversé n’a pas pu être lu pour l’inspection antimalware.',
    ],
    'readiness' => [
        'development_gate' => 'Le contrôle de signature EICAR réservé au développement est actif ; la production nécessite ClamAV.',
        'clamav_available' => 'ClamAV est disponible : :version',
        'version_reported' => 'version signalée',
    ],
];
