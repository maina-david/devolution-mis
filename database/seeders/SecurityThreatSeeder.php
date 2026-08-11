<?php

namespace Database\Seeders;

use App\Models\SecurityThreat;
use App\Models\User;
use Illuminate\Database\Seeder;

class SecurityThreatSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal() || SecurityThreat::query()->exists()) {
            return;
        }

        $owner = User::query()->where('email', 'devolution.admin@idmis.test')->first();
        $submitter = User::query()->where('email', 'platform.admin@idmis.test')->first();
        if (! $owner || ! $submitter) {
            return;
        }

        $threats = [
            ['spoofing', 'Compromised privileged identity', 'Identity, sessions and restricted workspaces', 'An attacker reuses credentials or an unattended privileged session to act as a national administrator.', 'External attacker or malicious insider', ['login', 'password reset', 'active session'], 4, 5, ['rate limiting', 'MFA and passkeys', 'access certification', 'session revocation'], 'Require phishing-resistant authentication for privileged roles, monitor anomalous sessions and rehearse emergency revocation.'],
            ['tampering', 'County evidence altered after submission', 'Assessment evidence, scores and verification decisions', 'A submitted document or score is replaced without preserving its original digest and review history.', 'Compromised operator or application account', ['document upload', 'assessment workflow', 'object storage'], 3, 5, ['private storage', 'SHA-256 digest', 'immutable audit', 'separation of duties'], 'Verify content digests at every lifecycle transition and alert on object/database reconciliation failures.'],
            ['repudiation', 'Privileged export cannot be attributed', 'Restricted data exports and audit evidence', 'An operator exports sensitive records and later disputes the action because evidence is incomplete.', 'Privileged operator', ['workspace exports', 'document downloads'], 3, 4, ['authenticated exports', 'audit events', 'authorization policies'], 'Record purpose, subject scope, format, actor, timestamp and correlation identifier for every restricted export.'],
            ['information_disclosure', 'Cross-county authorization leakage', 'County-scoped operational and citizen records', 'A county user manipulates identifiers or filters to read another county’s records, map, documents or exports.', 'Authenticated county operator', ['county routes', 'search', 'exports', 'document preview'], 4, 5, ['county scope policies', 'route binding', 'private documents', 'negative authorization tests'], 'Apply query-level county scopes consistently and maintain cross-county isolation tests for every module and export.'],
            ['denial_of_service', 'Bulk upload or export exhausts service capacity', 'Public portal, document pipeline and reporting', 'Large files, repeated previews or expensive national exports exhaust workers, storage or database capacity.', 'External user or compromised account', ['uploads', 'preview generation', 'exports', 'API'], 3, 4, ['validation limits', 'queues', 'rate limiting', 'pagination'], 'Add workload quotas, back-pressure, capacity alerts, bounded export jobs and documented degraded-mode procedures.'],
            ['elevation_of_privilege', 'Role or county scope granted without independent approval', 'RBAC, county assignments and privileged permissions', 'A privileged actor assigns themselves or an associate broader roles or county scope without accountable review.', 'Privileged insider', ['user administration', 'role assignment', 'county assignment'], 3, 5, ['Spatie permissions', 'access campaigns', 'independent reviewer rule', 'audit events'], 'Introduce maker-checker approval for privileged grants and alert on direct permission or county-assignment changes.'],
            ['tampering', 'Authoritative integration payload manipulated or replayed', 'IFMIS, IPPD, OCoB and CBK exchanges', 'An intercepted, stale or forged exchange changes finance, payroll or exchequer status inside IDMIS.', 'External attacker or compromised integration credential', ['integration endpoint', 'retry queue', 'reconciliation import'], 3, 5, ['contract versioning', 'idempotency', 'checksums', 'reconciliation'], 'Use mTLS or signed messages, managed credential rotation, replay windows and source-owner reconciliation approval.'],
            ['information_disclosure', 'Backup, log or support artifact exposes protected data', 'Backups, logs, diagnostics and recovery evidence', 'Copies outside the primary access path retain secrets or personal data with weaker controls.', 'Cloud operator, supplier or compromised support identity', ['backups', 'application logs', 'support bundles'], 3, 5, ['encrypted backups', 'log redaction', 'restricted recovery workflow'], 'Validate encryption and restore access, scan logs for secrets, expire support artifacts and obtain supplier-control evidence.'],
        ];

        foreach ($threats as $index => [$category, $title, $asset, $scenario, $actor, $entryPoints, $likelihood, $impact, $controls, $treatment]) {
            SecurityThreat::create(['owner_id' => $owner->id, 'submitted_by' => $submitter->id, 'reference' => sprintf('THR-IDMIS-%03d', $index + 1), 'title' => $title, 'stride_category' => $category, 'asset' => $asset, 'scenario' => $scenario, 'threat_actor' => $actor, 'entry_points' => $entryPoints, 'likelihood' => $likelihood, 'impact' => $impact, 'inherent_risk_score' => $likelihood * $impact, 'existing_controls' => $controls, 'treatment_plan' => $treatment, 'status' => 'submitted', 'submitted_at' => now(), 'review_due_at' => now()->addMonths(6), 'evidence_references' => ['Engineering baseline — independent security validation pending']]);
        }
    }
}
