<?php

return [
    'lineage_proposed' => 'Reference-lineage disposition proposed for independent review.',
    'lineage_reviewed' => 'Reference-lineage review decision recorded.',
    'lineage_applied' => 'Approved reference-lineage disposition applied.',
    'import' => [
        'csv_or_xlsx' => 'Upload a CSV or XLSX source file.', 'one_worksheet' => 'The XLSX source must contain exactly one worksheet.', 'formula_forbidden' => 'Spreadsheet formulas are not allowed (row :row). Replace formulas with their approved values.', 'xlsx_unreadable' => 'The XLSX source could not be read. Use an unencrypted workbook generated from the controlled template.', 'invalid_archive' => 'The XLSX source is not a valid workbook archive.', 'too_many_entries' => 'The XLSX source contains too many archive entries.', 'archive_uninspectable' => 'The XLSX source archive could not be inspected.', 'external_content' => 'External workbook links and executable spreadsheet content are not allowed.', 'expanded_limit' => 'The XLSX source expands beyond the 50 MB processing limit.', 'unsupported_value' => 'The XLSX source contains an unsupported value at row :row.', 'source_unavailable' => 'The uploaded source file is no longer available.', 'checksum_failed' => 'The source file checksum could not be calculated.', 'upload_checksum_failed' => 'The uploaded source checksum could not be calculated.', 'private_store_failed' => 'The source file could not be stored privately.', 'header_unreadable' => 'The source header could not be read.', 'required_columns' => 'Use the required columns in this exact order: :columns.', 'legacy_acpa_columns' => 'Use the required legacy ACPA columns in this exact order: :columns.', 'migration_row_limit' => 'A migration batch may contain at most 5,000 data rows.', 'bulk_row_limit' => 'A bulk-import batch may contain at most 5,000 data rows.', 'no_rows' => 'The source file contains no data rows.', 'unsupported_dataset' => 'The selected bulk-import dataset is not supported.',
    ],
    'ui' => [
        'inventory_title' => 'Legacy and unpinned inventory',
        'inventory_description' => 'Explicit records without governed reference-release lineage. Inventory does not silently assign a modern catalogue to historical records.',
        'record_type' => 'Record type to reconcile',
        'open' => 'open', 'pending' => 'pending', 'applied' => 'applied',
        'register_title' => 'Lineage disposition register',
        'register_description' => 'Checksum-bound proposals, independent decisions, successor links and final application evidence.',
        'empty_title' => 'No lineage dispositions recorded',
        'empty_description' => 'Propose a controlled decision for an explicitly legacy or unpinned record.',
        'reconcile' => 'Reconcile record', 'actions_for' => 'Actions for :reference', 'view_details' => 'View details', 'review_decision' => 'Review decision', 'apply_disposition' => 'Apply disposition',
        'proposal_title' => 'Propose lineage disposition',
        'proposal_description' => 'Record an evidence-backed decision for independent review. This proposal does not mutate the selected record.',
        'record' => 'Legacy or unpinned record', 'disposition_decision' => 'Disposition decision', 'retain_legacy' => 'Retain as explicit legacy', 'pin_release' => 'Pin verified catalogue release', 'deprecate' => 'Deprecate from future operational use', 'published_release' => 'Published catalogue release', 'version' => 'Version :version', 'successor' => 'Successor record (optional)', 'source_reference' => 'Source or approval reference', 'rationale' => 'Evidence-based rationale', 'recording' => 'Recording proposal…', 'submit_review' => 'Submit for independent review',
        'reference' => 'Reference', 'created_at' => 'Created at',
        'details_title' => 'Lineage disposition details', 'review_title' => 'Review lineage disposition', 'apply_title' => 'Apply lineage disposition', 'checksum_bound' => ':reference · checksum-bound decision', 'record_type_value' => 'Record type', 'decision' => 'Decision', 'status' => 'Status', 'catalogue' => 'Catalogue', 'explicitly_unpinned' => 'Explicitly unpinned', 'proposed_by' => 'Proposed by', 'reviewed_by' => 'Reviewed by', 'applied_by' => 'Applied by', 'business_rationale' => 'Business rationale', 'controlled_successor' => 'Controlled successor', 'source_checksum' => 'Source record SHA-256', 'decision_checksum' => 'Decision SHA-256', 'independent_notes' => 'Independent review notes', 'approve' => 'Approve', 'reject' => 'Reject', 'record_independent_decision' => 'Record independent decision', 'final_application' => 'Final controlled application', 'final_application_description' => 'IDMIS will recheck the source record, decision and catalogue checksums. The proposer and reviewer cannot perform this action.', 'not_pinned' => 'Not pinned',
    ],
];
