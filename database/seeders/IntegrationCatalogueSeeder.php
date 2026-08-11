<?php

namespace Database\Seeders;

use App\Actions\DispatchIntegrationExchange;
use App\Actions\PublishIntegrationContract;
use App\Models\County;
use App\Models\IntegrationContract;
use App\Models\IntegrationSystem;
use App\Models\ReconciliationException;
use App\Models\ReconciliationRun;
use App\Models\User;
use Illuminate\Database\Seeder;

class IntegrationCatalogueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(PublishIntegrationContract $publishContract, DispatchIntegrationExchange $dispatchExchange): void
    {
        if (! app()->isLocal() || IntegrationSystem::query()->exists()) {
            return;
        }
        $registrar = User::query()->where('email', 'devolution.admin@idmis.test')->first();
        $reviewer = User::query()->where('email', 'platform.admin@idmis.test')->first() ?? User::query()->where('email', 'management@idmis.test')->first();
        $county = County::query()->where('name', 'Mombasa')->first();
        if (! $registrar || ! $reviewer) {
            return;
        }

        $catalogue = [
            ['code' => 'IFMIS-SBX', 'name' => 'IFMIS controlled sandbox', 'owner' => 'The National Treasury', 'purpose' => 'Validate finance commitment and exchequer event contracts without connecting to production IFMIS.', 'resource' => 'finance_commitment', 'required' => ['commitment_reference', 'amount', 'currency'], 'mapping' => ['commitment_reference' => 'travel_requests.finance_commitment_reference']],
            ['code' => 'IPPD-SBX', 'name' => 'IPPD controlled sandbox', 'owner' => 'State Department for Public Service', 'purpose' => 'Validate employee identity and employment-status reconciliation contracts without production payroll data.', 'resource' => 'employee_status', 'required' => ['employee_reference', 'employment_status'], 'mapping' => ['employee_reference' => 'performance_plans.hris_employee_reference']],
            ['code' => 'OCOB-SBX', 'name' => 'OCoB controlled sandbox', 'owner' => 'Office of the Controller of Budget', 'purpose' => 'Validate exchequer authorization event contracts and turnaround-time telemetry.', 'resource' => 'exchequer_authorization', 'required' => ['request_reference', 'authorization_status', 'occurred_at'], 'mapping' => ['request_reference' => 'county_grants.external_reference']],
            ['code' => 'CBK-SBX', 'name' => 'CBK controlled sandbox', 'owner' => 'Central Bank of Kenya', 'purpose' => 'Validate county account credit confirmation contracts and end-to-end exchequer timing.', 'resource' => 'county_credit_confirmation', 'required' => ['payment_reference', 'county_code', 'credited_at'], 'mapping' => ['county_code' => 'counties.code']],
        ];
        foreach ($catalogue as $definition) {
            $system = IntegrationSystem::create(['registered_by' => $registrar->id, 'code' => $definition['code'], 'name' => $definition['name'], 'purpose' => $definition['purpose'], 'system_owner' => $definition['owner'], 'environment' => 'sandbox', 'transport' => 'fixture', 'auth_scheme' => 'none', 'direction' => 'bidirectional', 'data_classification' => 'confidential', 'status' => 'contract_review']);
            $properties = collect($definition['required'])->mapWithKeys(fn (string $field): array => [$field => ['type' => 'string']])->all();
            $contractData = ['name' => $definition['resource'].' exchange', 'resource_name' => $definition['resource'], 'http_method' => 'POST', 'path' => '/v1/'.$definition['resource'], 'request_schema' => ['type' => 'object', 'required' => $definition['required'], 'properties' => $properties], 'response_schema' => ['type' => 'object', 'required' => ['accepted'], 'properties' => ['accepted' => ['type' => 'boolean']]], 'field_mappings' => $definition['mapping'], 'required_headers' => ['X-Correlation-ID', 'Idempotency-Key'], 'idempotency_field' => $definition['required'][0], 'retry_policy' => ['max_attempts' => 3, 'backoff_seconds' => [60, 300, 1800]], 'rate_limit_per_minute' => 60];
            $contract = IntegrationContract::create([...$contractData, 'integration_system_id' => $system->id, 'submitted_by' => $registrar->id, 'version' => 1, 'status' => 'review', 'content_checksum' => hash('sha256', json_encode($contractData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))]);
            $publishContract->handle($contract, $reviewer, ['effective_from' => now()->subMinute()]);
        }

        $ippdContract = IntegrationContract::query()->whereHas('system', fn ($query) => $query->where('code', 'IPPD-SBX'))->firstOrFail();
        $exchange = $dispatchExchange->handle($ippdContract, $registrar, ['county_id' => $county?->id, 'external_reference' => 'IPPD-DEMO-001', 'idempotency_key' => 'IPPD-DEMO-001-v1', 'payload' => ['employee_reference' => 'IPPD-DEMO-001', 'employment_status' => 'active']]);
        $run = ReconciliationRun::create(['integration_system_id' => $ippdContract->integration_system_id, 'integration_contract_id' => $ippdContract->id, 'initiated_by' => $registrar->id, 'reference' => 'REC-IPPD-DEMO-001', 'period_from' => today()->startOfMonth(), 'period_to' => today(), 'source_count' => 2, 'target_count' => 1, 'matched_count' => 1, 'exception_count' => 1, 'status' => 'exceptions', 'started_at' => now(), 'metadata' => ['mode' => 'controlled-sandbox']]);
        ReconciliationException::create(['reconciliation_run_id' => $run->id, 'integration_exchange_id' => $exchange->id, 'county_id' => $county?->id, 'external_reference' => 'IPPD-DEMO-002', 'local_reference' => null, 'exception_type' => 'missing_target', 'field_name' => 'employee_reference', 'severity' => 'high', 'expected_value' => 'IPPD-DEMO-002', 'description' => 'Sandbox authoritative employee reference has no corresponding active IDMIS identity.', 'status' => 'open']);
    }
}
