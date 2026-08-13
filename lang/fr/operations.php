<?php

return [
    'ui' => [
        'eyebrow' => 'Assurance et reprise des services', 'title' => 'Centre de préparation opérationnelle',
        'description' => 'Sondes de dépendance, mesures des SLO, sauvegardes avec sommes de contrôle, preuves de restauration isolée, contrôles planifiés et historique des versions et retours validé indépendamment.',
        'ms' => 'ms', 'operational_alerts' => 'Alertes opérationnelles', 'operational_alerts_description' => 'alertes de seuil gouvernées avec déduplication des récurrences, acquittement et preuve de récupération automatique. Les seuils restent provisoires jusqu’à leur approbation par le responsable du service.',
        'failed_queue_jobs' => 'Tâches de file échouées', 'failed_queue_jobs_description' => 'échecs conservés. Le contenu des charges et exceptions reste masqué ; les opérateurs reçoivent des sommes de contrôle et des classifications sûres.',
        'immutable_recovery_evidence' => 'Preuve de récupération immuable', 'immutable_recovery_evidence_description' => 'Derniers résultats de remise en file attribués aux opérateurs ; les tâches réussies peuvent quitter le registre des échecs, mais cette preuve demeure.',
        'performance_assurance_evidence' => 'Preuve d’assurance des performances', 'performance_assurance_evidence_description' => 'exécutions HTTP concurrentes immuables avec somme de contrôle. Les seuils sont des instantanés d’environnement et ne constituent pas une certification de production Konza.',
        'release_rollback_evidence' => 'Preuve de version et de retour', 'release_rollback_evidence_description' => 'Les déploiements nécessitent une validation indépendante avant de devenir des cibles de retour approuvées.',
        'latest_service_measurements' => 'Dernières mesures de service', 'separator' => '·', 'measurements_empty' => 'Les mesures apparaîtront après la sonde opérationnelle planifiée.', 'scheduled_controls' => 'Contrôles planifiés',
        'view_alert_evidence' => 'Voir la preuve de l’alerte', 'immutable_timeline' => 'Chronologie immuable', 'showing_latest' => 'Affichage des derniers', 'of' => 'sur', 'retained_events' => 'événements conservés.',
        'accountable_response_note' => 'Note de réponse responsable', 'acknowledge_alert' => 'Acquitter l’alerte', 'view_evidence' => 'Voir la preuve', 'performance_run_evidence' => 'Preuve d’exécution des performances', 'threshold_snapshot' => 'Instantané des seuils',
        'view_recovery_evidence' => 'Voir la preuve de récupération', 'failed' => 'échoué', 'requeue_description' => 'Remettre la charge conservée en file sans l’exposer. L’échec initial ne quitte le registre actif qu’après acceptation par la file, et une tentative immuable attribuée est conservée dans tous les cas.',
        'retry_failed_job' => 'Réessayer la tâche échouée', 'backup_request_description' => 'Le worker consignera la taille de l’artefact, la somme SHA-256, les horodatages et tout échec. La vérification de restauration est une action contrôlée distincte.', 'queue_backup' => 'Mettre la sauvegarde en file',
        'record_deployment' => 'Enregistrer le déploiement', 'restore_description' => 'Mettre en file une restauration isolée dans une base temporaire générée. Le vérificateur compte les tables restaurées et ne supprime que cette cible temporaire validée.',
        'verify_isolated_restore' => 'Vérifier la restauration isolée', 'independently_validate' => 'Valider indépendamment', 'record_rollback' => 'Enregistrer le retour', 'validate_release' => 'Valider la version',
        'record_rollback_decision' => 'Enregistrer la décision de retour', 'backup_restore_evidence' => 'Preuve de sauvegarde et restauration', 'recovery_artifacts' => 'artefacts de récupération', 'export' => 'Exporter',
    ],
    'readiness' => [
        'search_indexes_available' => ':count index de recherche requis sont disponibles.',
        'search_indexes_missing' => 'Les index de recherche requis sont indisponibles : :indexes',
    ],
    'labels' => ['unknown_queued_job' => 'Tâche de file inconnue'],
    'outcomes' => [
        'release_recorded' => 'L’enregistrement du déploiement a été créé pour validation indépendante.', 'release_validated' => 'La version a été validée indépendamment.',
        'rollback_recorded' => 'La décision de retour a été enregistrée. Exécutez le manuel de déploiement approuvé et joignez la preuve de plateforme.', 'backup_queued' => 'La sauvegarde de la base de données a été mise en file.',
        'restore_verification_queued' => 'La vérification de restauration isolée a été mise en file.', 'failed_job_requeued' => 'La tâche échouée a été remise en file avec une preuve de récupération immuable.',
        'failed_job_rejected' => 'Le fournisseur de file a rejeté la demande de récupération ; la tâche échouée reste disponible.', 'alert_acknowledged' => 'L’alerte opérationnelle a été acquittée avec une preuve de réponse immuable.',
    ],
    'audit' => ['release_recorded' => 'Version :version enregistrée pour l’environnement :environment.'],
    'backup' => ['errors' => [
        'temporary_backup_path' => 'Impossible d’allouer un chemin temporaire pour la sauvegarde.', 'persist_backup' => 'Impossible de conserver la sauvegarde de la base de données sur le disque configuré.',
        'completed_required' => 'Seules les sauvegardes terminées peuvent être vérifiées.', 'temporary_restore_path' => 'Impossible d’allouer un chemin temporaire pour la restauration.',
        'read_artifact' => 'Impossible de lire l’artefact de sauvegarde pour vérification.', 'checksum_failed' => 'La vérification de la somme de contrôle de la sauvegarde a échoué.',
        'manifest_parse_failed' => 'Le manifeste de sauvegarde n’a pas pu être analysé.', 'manifest_empty' => 'Le manifeste de sauvegarde ne contient aucune table applicative.',
        'unsafe_restore_target' => 'La cible de la sonde de restauration n’est pas sûre.', 'restored_table_count' => 'Le nombre de tables restaurées est inférieur à celui du manifeste de sauvegarde.',
        'postgresql_required' => 'La sauvegarde opérationnelle exige actuellement PostgreSQL.',
    ]],
];
