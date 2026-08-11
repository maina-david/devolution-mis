---
paths:
  - 'app/Actions/RunHttpPerformanceProbe.php,app/Console/Commands/RunHttpPerformanceProbeCommand.php,app/Models/PerformanceTestRun.php,database/migrations/*performance_test_runs*,tests/Feature/PerformanceAssuranceTest.php,resources/js/pages/operations/**'
---

# Pages Operations

## Bounded performance assurance evidence
Use synthetic non-production reference volumes for authenticated query/latency tests. HTTP concurrency probes must target only configured same-environment HTTPS hosts and allowlisted public readiness paths, accept dynamic response lengths, enforce request/concurrency caps, and append immutable checksum-bound evidence for both passes and failures. Local engineering results never constitute Konza production certification.
