<?php

return ['ui' => [
    'eyebrow' => 'Responsabilité intergouvernementale', 'title' => 'Suivi des résolutions IGR',
    'description' => 'Transformer les résolutions des sommets, conseils et comités en engagements identifiés, assortis de délais, de responsables nommés, de preuves de mise en œuvre, de signalements de lacunes, de rappels et d’une clôture indépendante.',
    'gap_risk_profile' => 'Profil de risque des lacunes de mise en œuvre', 'gap_risk_profile_description' => 'Exposition tenant compte des filtres pour les résolutions et comtés accessibles à ce rôle.',
    'dependency_paths' => 'Chemins de dépendance des résolutions', 'dependency_paths_description' => 'Chaînes de prérequis respectant le périmètre et relations bloquantes non résolues qui déterminent si une résolution peut être clôturée.',
    'arrow' => '→', 'separator' => '·', 'blocked' => 'Bloqué', 'gap_lifecycle_trend' => 'Tendance du cycle des lacunes',
    'gap_lifecycle_trend_description' => 'Nouvelles lacunes par rapport aux résolutions acceptées indépendamment durant les six derniers mois.',
    'active_gap_aging' => 'Ancienneté des lacunes actives', 'active_gap_aging_description' => 'Temps écoulé depuis le signalement des lacunes nécessitant encore une acceptation indépendante.',
    'risk_concentration' => 'Concentration des risques', 'risk_concentration_description' => 'Catégories, gravités et comtés affectés classés pour une intervention intergouvernementale ciblée.',
    'county_bottlenecks' => 'Goulets d’étranglement des comtés', 'national_multi_county' => 'National / plusieurs comtés', 'active' => 'actif', 'overdue' => 'en retard',
    'no_county_gaps' => 'Aucune lacune propre à un comté ne correspond aux filtres.', 'implementation_workspace' => 'Espace de mise en œuvre des résolutions',
    'implementation_workspace_description' => 'Engagements actuels et historique récent de leur mise en œuvre.', 'no_matching_data' => 'Aucune donnée ne correspond aux filtres.',
    'create_forum' => 'Créer le forum', 'confirm_quorum' => 'Confirmer que la réunion officielle a atteint le quorum prévu par le mandat du forum.', 'record_meeting' => 'Enregistrer la réunion',
    'create_category' => 'Créer la catégorie', 'responsible_parties' => 'Parties responsables', 'add_party' => 'Ajouter une partie', 'register_notify_parties' => 'Enregistrer et notifier les parties',
    'resolved' => 'adoptée', 'due' => 'échéance', 'implementation' => 'Mise en œuvre', 'percent' => '%', 'formal_meeting_provenance' => 'Provenance de la réunion officielle',
    'minutes_label' => 'Procès-verbal :', 'historical_meeting_unlinked' => 'Dossier historique — réunion officielle non liée', 'implementation_gap' => 'Lacune de mise en œuvre',
    'governed_implementation_gaps' => 'Lacunes de mise en œuvre gouvernées', 'assign_gap' => 'Affecter la lacune', 'add_dependency' => 'Ajouter la dépendance', 'record_progress' => 'Enregistrer l’avancement',
    'recent_history_open' => 'Historique récent de mise en œuvre (', 'close_parenthesis' => ')', 'evidence_label' => 'Preuve :', 'none_recorded' => 'Aucun élément enregistré.',
    'owner_label' => 'Responsable :', 'impact_label' => 'Impact :', 'mitigation_label' => 'Atténuation :', 'resolution_label' => 'Résolution :',
], 'outcomes' => [
    'meeting_recorded' => 'La réunion formelle IGR a été enregistrée.', 'gap_category_created' => 'La catégorie d’écart IGR a été créée.', 'forum_created' => 'Le forum IGR a été créé.', 'resolution_registered' => 'La résolution a été enregistrée et les responsables notifiés.', 'implementation_updated' => 'La mise à jour d’exécution a été enregistrée.', 'dependency_recorded' => 'La dépendance de la résolution a été enregistrée.', 'gap_recorded' => 'L’écart d’exécution a été enregistré et attribué.', 'gap_updated' => 'Le cycle de l’écart d’exécution a été mis à jour.', 'resolution_updated' => 'Le cycle de la résolution a été mis à jour.',
], 'errors' => [
    'workflow_unavailable' => 'Le flux de travail de la résolution est indisponible.', 'blocking_prerequisites_open' => 'Toutes les résolutions préalables bloquantes doivent être clôturées avant l’examen de clôture.', 'gaps_not_accepted' => 'Toutes les lacunes de mise en œuvre doivent être acceptées indépendamment avant l’examen de clôture.',
], 'audit' => [
    'forum_created' => 'Forum IGR :code créé.', 'resolution_transitioned' => 'Résolution :number passée à l’état :state.',
], 'notifications' => [
    'assignment_title' => 'Nouvelle affectation de résolution IGR', 'assignment_message' => 'Vous êtes responsable de :number : :title.',
]];
