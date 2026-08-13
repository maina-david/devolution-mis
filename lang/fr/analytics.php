<?php

return [
    'filter_saved' => 'La vue de filtre :name a été enregistrée.',
    'filter_deleted' => 'La vue de filtre a été supprimée.',
    'dashboard_created' => 'Le tableau de bord :code a été créé comme brouillon gouverné.',
    'widget_added' => 'Le widget analytique gouverné a été ajouté.',
    'dashboard_published' => 'Le tableau de bord a été publié indépendamment avec une somme de contrôle de configuration.',
    'schedule_created' => 'Le calendrier de rapport :code a été créé en attente d’une activation indépendante.',
    'schedule_activated' => 'Le rapport planifié a été activé indépendamment.',
    'report_queued' => 'Une tâche privée de génération de rapport a été mise en file d’attente.',
    'audit' => [
        'filter_deleted' => 'La vue de filtre analytique :name a été supprimée.',
    ],
    'errors' => [
        'active_schedule_required' => 'Seuls les calendriers actifs peuvent être exécutés.',
        'artifact_not_ready' => 'L’artefact du rapport n’est pas prêt.',
        'artifact_integrity_failed' => 'L’artefact du rapport a échoué au contrôle d’intégrité.',
    ],
    'report_generator' => [
        'errors' => [
            'configuration_unavailable' => 'La configuration approuvée du rapport planifié n’est plus exécutable.', 'artifact_storage_failed' => 'L’artefact privé du rapport planifié n’a pas pu être stocké.',
            'unsupported_format' => 'Le format du rapport planifié n’est pas pris en charge.', 'csv_stream_failed' => 'Le flux du rapport CSV n’a pas pu être ouvert.', 'csv_render_failed' => 'Le rapport CSV n’a pas pu être généré.',
            'spreadsheet_create_failed' => 'Le fichier tableur du rapport n’a pas pu être créé.', 'spreadsheet_read_failed' => 'Le fichier tableur du rapport n’a pas pu être lu.',
        ],
        'audit' => ['generated' => 'Le rapport planifié :code a été généré au format :format.'],
        'notifications' => ['ready_title' => 'Rapport planifié prêt', 'ready_message' => ':name est disponible au téléchargement autorisé.'],
    ],
    'dashboard_filter' => 'Analyse détaillée du tableau de bord',
    'widget_filter' => 'Analyse détaillée du widget',
    'visualization' => 'Visualisation',
    'time_grain' => 'Granularité temporelle',
    'month' => 'Mensuelle',
    'quarter' => 'Trimestrielle',
    'year' => 'Annuelle',
    'trend_chart' => 'Tendance de :title dans le temps',
    'eyebrow' => 'Preuves gouvernées et aide à la décision', 'title' => 'Centre d’analyse et de rapports', 'description' => 'Configurez des tableaux de bord sécurisés par comté, préservez la provenance des indicateurs, approuvez indépendamment la publication et générez des rapports privés vérifiés.', 'schedules_title' => 'Contrôles de livraison planifiée', 'schedules_description' => 'Les calendriers provisoires nécessitent un autre acteur autorisé avant le lancement de la génération en arrière-plan.', 'runs_title' => 'Registre des rapports générés', 'immutable_runs' => ':count enregistrements d’exécution immuables', 'saved_views' => 'Vues de filtres enregistrées', 'saved_views_description' => 'Réutilisez un contexte analytique personnel sans modifier les filtres d’un autre utilisateur.', 'view_name' => 'Nom de la vue', 'default_view' => 'Utiliser comme vue analytique par défaut', 'default' => 'Par défaut', 'configured_by' => 'Configuré par', 'publish_independently' => 'Publier indépendamment', 'measured' => 'Mesuré le :date', 'first_widget' => 'Premier widget gouverné', 'save_draft' => 'Enregistrer le brouillon gouverné', 'add_widget' => 'Ajouter le widget gouverné', 'save_activation' => 'Enregistrer pour activation indépendante', 'next_run' => 'Prochaine exécution :date', 'activate_independently' => 'Activer indépendamment', 'queue_now' => 'Mettre en file maintenant', 'recipients' => ':count destinataire(s)', 'national_recipients' => 'Portée nationale · destinataires nationaux uniquement', 'created_by' => 'Créé par :name', 'view_execution' => 'Voir les preuves d’exécution', 'download_artifact' => 'Télécharger l’artefact vérifié', 'report_run' => 'Exécution du rapport :code', 'optional' => '(facultatif)',
];
