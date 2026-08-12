<?php

return [
    'validation' => [
        'minimum_counties' => 'Le seuil minimal de comtés ne peut pas dépasser les comtés sélectionnés.',
    ],
    'messages' => [
        'uat_campaign_created' => 'La campagne pilote UAT :code a été créée comme plan, sans présumer une exécution ou une acceptation.',
        'uat_scenario_created' => 'Le scénario UAT :code a été ajouté à la campagne.',
        'uat_execution_recorded' => 'La preuve immuable d’exécution UAT a été enregistrée avec le résultat :outcome.',
        'uat_finding_transitioned' => 'La constatation UAT est maintenant :status.',
        'uat_campaign_submitted' => 'La campagne a été soumise à une acceptation indépendante.',
        'uat_campaign_decided' => 'La décision UAT indépendante a été enregistrée comme :decision.',
    ],
    'uat_errors' => [
        'county_missing' => 'Chaque comté UAT sélectionné doit exister.', 'county_scope' => 'Un comté UAT sélectionné est hors de votre périmètre.', 'campaign_scope' => 'Cette campagne UAT est hors de votre portefeuille autorisé.',
        'scenario_planning_only' => 'Les scénarios ne peuvent être ajoutés que pendant la planification.', 'execution_closed' => 'Seuls les scénarios prêts d’une campagne ouverte peuvent être exécutés.', 'actor_role' => 'Ce scénario doit être exécuté par un utilisateur représentatif du rôle configuré.',
        'execution_county_scope' => 'Le comté d’exécution est hors de votre périmètre.', 'execution_county_campaign' => 'Le comté d’exécution ne fait pas partie de cette campagne.', 'finding_owner_scope' => 'Le responsable de correction n’est pas autorisé pour ce comté.',
        'resolve_separation' => 'Seul le responsable attribué indépendamment peut résoudre une constatation ouverte.', 'verify_separation' => 'La vérification exige une constatation résolue et un vérificateur indépendant.', 'reopen_separation' => 'Seul un réviseur indépendant peut rouvrir une constatation.',
        'submit_state' => 'Seule une campagne exécutée ou rejetée sans examen en attente peut être soumise.', 'submit_coverage' => 'La campagne exige sa couverture minimale et au moins un scénario requis prêt.', 'submit_evidence' => 'La soumission exige une dernière exécution réussie pour chaque paire scénario-comté, tous les rôles requis et des constatations vérifiées indépendamment.',
        'decision_state' => 'Seul un examen de campagne en attente peut être décidé.', 'decision_separation' => 'L’auteur, le soumissionnaire et les testeurs ne peuvent pas décider indépendamment de l’acceptation.',
    ],
    'uat' => [
        'tab' => 'UAT pilote',
        'eyebrow' => 'Essais représentatifs et acceptation formelle',
        'title' => 'Acceptation pilote gouvernée',
        'description' => 'Planifier des scénarios représentatifs, enregistrer des preuves immuables par comté, clôturer indépendamment les constatations et conserver l’historique formel d’acceptation.',
        'new_campaign' => 'Planifier une campagne UAT',
        'empty_title' => 'Aucune campagne UAT dans le périmètre',
        'empty_description' => 'Créer un plan pilote gouverné sans laisser entendre que les tests ou l’acceptation ont eu lieu.',
        'campaigns' => 'Campagnes dans le périmètre', 'scenarios' => 'Scénarios requis', 'executions' => 'Exécutions enregistrées', 'open_findings' => 'Constatations ouvertes',
        'search' => 'Rechercher les campagnes UAT', 'status' => 'Statut', 'county' => 'Comté', 'campaign' => 'Campagne', 'environment' => 'Environnement', 'counties' => 'Comtés', 'acceptance' => 'Acceptation', 'not_submitted' => 'Non soumise',
        'export_evidence' => 'Exporter les preuves UAT',
        'open_actions' => 'Ouvrir les actions de la campagne UAT', 'open_record' => 'Ouvrir le dossier UAT complet', 'catalogue' => 'Catalogue', 'period' => 'Période pilote', 'creator' => 'Créée par', 'required_roles' => 'Rôles représentatifs requis', 'acceptance_criteria' => 'Critères d’acceptation',
        'no_scenarios' => 'Aucun scénario configuré', 'no_scenarios_description' => 'Ajouter des scénarios représentatifs de bout en bout avant d’enregistrer les preuves.', 'national' => 'National',
        'new_campaign_description' => 'Définir le périmètre et les critères. L’enregistrement constitue uniquement une preuve de planification.', 'catalogue_required' => 'Publier d’abord un catalogue effectif complet.', 'code' => 'Code', 'name' => 'Nom', 'objective' => 'Objectif', 'starts_on' => 'Début', 'ends_on' => 'Fin', 'minimum_counties' => 'Nombre minimal de comtés', 'acceptance_criterion' => 'Critère d’acceptation', 'save_plan' => 'Enregistrer le plan',
        'add_scenario' => 'Ajouter un scénario', 'add_scenario_description' => 'Définir un parcours par rôle, accessible et à faible connectivité.', 'title_label' => 'Titre du scénario', 'module' => 'Module du TdR', 'actor_role' => 'Rôle représentatif', 'priority' => 'Priorité', 'journey' => 'Parcours de bout en bout', 'precondition' => 'Précondition', 'step' => 'Étape de test', 'expected_result' => 'Résultat attendu', 'accessibility_variant' => 'Variante d’accessibilité', 'low_connectivity_variant' => 'Variante à connectivité limitée', 'save_scenario' => 'Enregistrer le scénario',
        'record_execution' => 'Enregistrer l’exécution', 'representative_role' => 'Rôle représentatif requis', 'outcome' => 'Résultat', 'actual_result' => 'Résultat observé', 'evidence_reference' => 'Référence de preuve', 'started_at' => 'Commencée à', 'completed_at' => 'Terminée à', 'finding_owner' => 'Responsable de correction', 'severity' => 'Sévérité', 'finding_title' => 'Titre de la constatation', 'finding_description' => 'Description de la constatation', 'finding_due_on' => 'Échéance', 'record_immutable_evidence' => 'Enregistrer la preuve immuable',
        'transition_finding' => 'Faire évoluer la constatation', 'review_finding' => 'Examiner la constatation', 'action' => 'Action', 'resolution' => 'Résolution et preuve', 'save_transition' => 'Enregistrer la transition',
        'submit_acceptance' => 'Soumettre pour acceptation', 'submit_acceptance_description' => 'La plateforme revérifiera chaque paire scénario-comté, rôle et constatation.', 'confirm_criteria' => 'Je confirme que les critères sont prêts pour un contrôle système indépendant.',
        'decide_acceptance' => 'Décider l’acceptation', 'evidence_checksum' => 'Somme de contrôle', 'decision' => 'Décision', 'decision_reason' => 'Motif indépendant de la décision', 'record_decision' => 'Enregistrer la décision indépendante',
    ],
];
