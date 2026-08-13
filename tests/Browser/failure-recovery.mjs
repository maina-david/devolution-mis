import fs from 'node:fs/promises';
import https from 'node:https';
import path from 'node:path';
import process from 'node:process';

const baseUrl = new URL(process.env.IDMIS_BASE_URL ?? 'https://devolution-mis.test');
const concurrency = Number(process.env.IDMIS_RECOVERY_CONCURRENCY ?? 5);
const recoveryRequests = Number(process.env.IDMIS_RECOVERY_REQUESTS ?? 30);
const outputDirectory = path.resolve('tmp/resilience-assurance');

if (baseUrl.protocol !== 'https:' || !Number.isInteger(concurrency) || concurrency < 1 || !Number.isInteger(recoveryRequests) || recoveryRequests < concurrency) {
    throw new Error('Failure-recovery assurance requires HTTPS and bounded positive request/concurrency values.');
}

const request = (routePath) => new Promise((resolve) => {
    const startedAt = performance.now();
    const call = https.get(new URL(routePath, baseUrl), { rejectUnauthorized: false, timeout: 10_000 }, (response) => {
        response.resume();
        response.on('end', () => resolve({ routePath, status: response.statusCode ?? 0, durationMs: performance.now() - startedAt }));
    });
    call.on('timeout', () => call.destroy(new Error('timeout')));
    call.on('error', (error) => resolve({ routePath, status: 0, durationMs: performance.now() - startedAt, error: error.message }));
});

const controlledFailure = await request('/__idmis-controlled-unavailable');

if (controlledFailure.status < 400) {
    throw new Error(`Controlled failure was not detected; received HTTP ${controlledFailure.status}.`);
}

const recoveryStartedAt = performance.now();
const results = [];
let cursor = 0;
await Promise.all(Array.from({ length: concurrency }, async () => {
    while (cursor < recoveryRequests) {
        const index = cursor++;
        results[index] = await request(index % 2 === 0 ? '/up' : '/health/ready');
    }
}));
const recoveryDurationMs = performance.now() - recoveryStartedAt;
const failures = results.filter((result) => result.status < 200 || result.status >= 300);
const latencies = results.map((result) => result.durationMs).sort((left, right) => left - right);
const p95LatencyMs = Math.round(latencies[Math.min(latencies.length - 1, Math.ceil(latencies.length * 0.95) - 1)]);
const report = {
    baseUrl: baseUrl.origin,
    controlledFailure,
    recoveryRequests,
    concurrency,
    recoveryDurationMs: Math.round(recoveryDurationMs),
    recoveryRequestsPerSecond: Number((recoveryRequests / (recoveryDurationMs / 1000)).toFixed(2)),
    p95LatencyMs,
    failures,
    recovered: failures.length === 0 && p95LatencyMs <= 2_000,
    completedAt: new Date().toISOString(),
};

await fs.mkdir(outputDirectory, { recursive: true });
await fs.writeFile(path.join(outputDirectory, 'failure-recovery-report.json'), `${JSON.stringify(report, null, 2)}\n`);

if (!report.recovered) {
    throw new Error(`Failure-recovery assurance failed: ${JSON.stringify(report)}`);
}

process.stdout.write(`Failure-recovery assurance passed: controlled HTTP ${controlledFailure.status}, then ${recoveryRequests} successful recovery requests at concurrency ${concurrency}, p95 ${p95LatencyMs} ms.\n`);
