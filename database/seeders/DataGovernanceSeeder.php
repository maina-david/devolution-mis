<?php

namespace Database\Seeders;

use App\Models\DataAsset;
use App\Models\ProcessingActivity;
use App\Models\RetentionSchedule;
use App\Models\User;
use App\Support\ReferenceCatalogue;
use Illuminate\Database\Seeder;

class DataGovernanceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal() || DataAsset::query()->exists()) {
            return;
        }

        $owner = User::query()->where('email', 'devolution.admin@idmis.test')->first();
        $steward = User::query()->where('email', 'platform.admin@idmis.test')->first();
        if (! $owner || ! $steward) {
            return;
        }

        $definitions = [
            ['code' => 'DA-CITIZEN-CASES', 'name' => 'Citizen feedback and grievance register', 'module' => 'Citizen Feedback and Grievance Redress', 'source' => 'Citizen submissions and controlled case workflow', 'classification' => 'restricted', 'personal' => true, 'sensitive' => true, 'personal_categories' => ['identity', 'contact', 'case narrative', 'accessibility needs'], 'subjects' => ['citizens', 'representatives'], 'retention_code' => 'RET-CITIZEN-CASES', 'record_class' => 'Citizen case records', 'months' => 84, 'disposition' => 'review_then_destroy', 'activity' => 'Citizen case intake and resolution', 'purpose' => 'Receive, route, investigate and resolve citizen feedback and grievances about devolution services.', 'dpia' => 'required'],
            ['code' => 'DA-PERFORMANCE', 'name' => 'Departmental performance records', 'module' => 'SDD Departmental Performance Management', 'source' => 'Approved performance plans, IPPD references and review decisions', 'classification' => 'confidential', 'personal' => true, 'sensitive' => false, 'personal_categories' => ['name', 'official email', 'employee reference', 'performance outcome'], 'subjects' => ['State Department officers'], 'retention_code' => 'RET-PERFORMANCE', 'record_class' => 'Employee performance records', 'months' => 120, 'disposition' => 'transfer_to_archive', 'activity' => 'Performance planning and review', 'purpose' => 'Set, independently review and report departmental performance commitments and outcomes.', 'dpia' => 'required'],
            ['code' => 'DA-ACPA-EVIDENCE', 'name' => 'County assessment evidence repository', 'module' => 'Devolution Performance Assessment', 'source' => 'County submissions, verification decisions and published scorecards', 'classification' => 'official', 'personal' => false, 'sensitive' => false, 'personal_categories' => [], 'subjects' => [], 'retention_code' => 'RET-ACPA', 'record_class' => 'Assessment evidence and results', 'months' => 120, 'disposition' => 'transfer_to_archive', 'activity' => 'ACPA evidence verification and scoring', 'purpose' => 'Assess county performance against versioned scorecards using complete, verified and reproducible evidence.', 'dpia' => 'not_required'],
            ['code' => 'DA-INTEGRATION-EXCHANGES', 'name' => 'Integration exchange and reconciliation register', 'module' => 'Shared integration control plane', 'source' => 'Approved source-system contracts and exchange metadata', 'classification' => 'confidential', 'personal' => true, 'sensitive' => false, 'personal_categories' => ['external employee reference', 'system correlation identifiers'], 'subjects' => ['government officers'], 'retention_code' => 'RET-INTEGRATIONS', 'record_class' => 'Interface exchange evidence', 'months' => 84, 'disposition' => 'review_then_destroy', 'activity' => 'Authoritative system exchange and reconciliation', 'purpose' => 'Exchange and reconcile approved data elements with IFMIS, IPPD, OCoB and CBK under source-owner contracts.', 'dpia' => 'required'],
        ];

        foreach ($definitions as $definition) {
            $isApproved = $definition['dpia'] === 'not_required';
            $schedule = RetentionSchedule::create(['approved_by' => $owner->id, 'code' => $definition['retention_code'], 'record_class' => $definition['record_class'], 'trigger_event' => 'Closure of the associated case, cycle or exchange and any related appeal, audit or investigation', 'retention_months' => $definition['months'], 'disposition_action' => $definition['disposition'], 'legal_authority' => 'Engineering baseline pending approval by the State Department records authority and DPO.', 'legal_hold_rule' => 'Suspend disposition while a legal hold, audit, investigation, appeal or preservation duty is active.', 'status' => 'approved', 'effective_from' => now(), 'approved_at' => now(), 'next_review_at' => now()->addYear()]);
            $asset = DataAsset::create(['data_owner_id' => $owner->id, 'steward_id' => $steward->id, 'code' => $definition['code'], 'name' => $definition['name'], 'description' => $definition['source'].'. Registered as an engineering baseline for accountable owner validation.', 'module' => $definition['module'], 'authoritative_source' => $definition['source'], 'classification' => $definition['classification'], 'contains_personal_data' => $definition['personal'], 'contains_sensitive_personal_data' => $definition['sensitive'], 'personal_data_categories' => $definition['personal_categories'], 'data_subject_categories' => $definition['subjects'], 'storage_locations' => ['postgresql', 'private_object_storage'], 'residency_country' => ReferenceCatalogue::defaultCountryCode(), 'quality_standard' => 'Complete, valid, timely, deduplicated and traceable to the authoritative source.', 'status' => 'active', 'reviewed_at' => now()]);
            ProcessingActivity::create(['data_asset_id' => $asset->id, 'retention_schedule_id' => $schedule->id, 'submitted_by' => $owner->id, 'reviewed_by' => $isApproved ? $steward->id : null, 'reference' => 'ROPA-'.$definition['code'], 'name' => $definition['activity'], 'purpose' => $definition['purpose'], 'lawful_basis' => 'public_task', 'lawful_basis_reference' => 'Official-authority/legal-obligation assessment pending validation by the State Department DPO and legal lead.', 'controller_name' => 'State Department for Devolution', 'processor_names' => ['Government hosting operator at Konza — contractual role pending'], 'recipient_categories' => ['authorized national and county operators'], 'processing_operations' => ['collect', 'validate', 'store', 'review', 'report', 'archive'], 'automated_decision_making' => false, 'cross_border_transfer' => false, 'dpia_status' => $definition['dpia'], 'dpia_reference' => null, 'risk_summary' => 'Unauthorized access, excessive collection, incorrect decisions and retention beyond approved purpose require owner review.', 'security_measures' => 'County/portfolio RBAC, separation of duties, encryption, private documents, malware quarantine, checksums and immutable audit.', 'status' => $isApproved ? 'approved' : 'submitted', 'submitted_at' => now()->subMinute(), 'reviewed_at' => $isApproved ? now() : null, 'next_review_at' => now()->addYear()]);
        }
    }
}
