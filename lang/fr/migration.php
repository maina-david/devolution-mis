<?php

return [
    'lineage_proposed' => 'La décision de traçabilité des référentiels a été proposée pour examen indépendant.',
    'lineage_reviewed' => 'La décision d’examen de la traçabilité des référentiels a été enregistrée.',
    'lineage_applied' => 'La décision approuvée de traçabilité des référentiels a été appliquée.',
    'ui' => [
        'inventory_title' => 'Inventaire historique et non rattaché',
        'inventory_description' => 'Enregistrements explicites sans traçabilité vers une version gouvernée. L’inventaire n’attribue jamais silencieusement un catalogue actuel aux données historiques.',
        'record_type' => 'Type d’enregistrement à rapprocher',
        'open' => 'ouverts', 'pending' => 'en attente', 'applied' => 'appliqués',
        'register_title' => 'Registre des décisions de traçabilité',
        'register_description' => 'Propositions liées à une empreinte, décisions indépendantes, liens successeurs et preuve d’application finale.',
        'empty_title' => 'Aucune décision de traçabilité enregistrée',
        'empty_description' => 'Proposez une décision contrôlée pour un enregistrement historique ou non rattaché.',
        'reconcile' => 'Rapprocher un enregistrement', 'actions_for' => 'Actions pour :reference', 'view_details' => 'Voir les détails', 'review_decision' => 'Examiner la décision', 'apply_disposition' => 'Appliquer la décision',
        'proposal_title' => 'Proposer une décision de traçabilité', 'proposal_description' => 'Enregistrez une décision étayée pour examen indépendant. Cette proposition ne modifie pas l’enregistrement sélectionné.',
        'record' => 'Enregistrement historique ou non rattaché', 'disposition_decision' => 'Décision de traitement', 'retain_legacy' => 'Conserver explicitement comme historique', 'pin_release' => 'Rattacher une version de catalogue vérifiée', 'deprecate' => 'Retirer des futurs usages opérationnels', 'published_release' => 'Version de catalogue publiée', 'version' => 'Version :version', 'successor' => 'Enregistrement successeur (facultatif)', 'source_reference' => 'Référence de source ou d’approbation', 'rationale' => 'Justification fondée sur les preuves', 'recording' => 'Enregistrement de la proposition…', 'submit_review' => 'Soumettre à un examen indépendant',
        'reference' => 'Référence', 'created_at' => 'Créé le',
        'details_title' => 'Détails de la décision de traçabilité', 'review_title' => 'Examiner la décision de traçabilité', 'apply_title' => 'Appliquer la décision de traçabilité', 'checksum_bound' => ':reference · décision liée à une empreinte', 'record_type_value' => 'Type d’enregistrement', 'decision' => 'Décision', 'status' => 'Statut', 'catalogue' => 'Catalogue', 'explicitly_unpinned' => 'Explicitement non rattaché', 'proposed_by' => 'Proposé par', 'reviewed_by' => 'Examiné par', 'applied_by' => 'Appliqué par', 'business_rationale' => 'Justification métier', 'controlled_successor' => 'Successeur contrôlé', 'source_checksum' => 'SHA-256 de l’enregistrement source', 'decision_checksum' => 'SHA-256 de la décision', 'independent_notes' => 'Notes d’examen indépendant', 'approve' => 'Approuver', 'reject' => 'Rejeter', 'record_independent_decision' => 'Enregistrer la décision indépendante', 'final_application' => 'Application finale contrôlée', 'final_application_description' => 'IDMIS revérifiera l’enregistrement source, la décision et les empreintes du catalogue. Le proposant et l’examinateur ne peuvent pas exécuter cette action.', 'not_pinned' => 'Non rattaché',
    ],
];
