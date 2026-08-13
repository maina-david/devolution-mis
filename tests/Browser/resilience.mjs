import fs from 'node:fs/promises';
import https from 'node:https';
import path from 'node:path';
import process from 'node:process';

const baseUrl = new URL(process.env.IDMIS_BASE_URL ?? 'https://devolution-mis.test');
const requestCount = Number(process.env.IDMIS_RESILIENCE_REQUESTS ?? 100);
const concurrency = Number(process.env.IDMIS_RESILIENCE_CONCURRENCY ?? 10);
const paths = ['/up', '/health/ready'];
const outputDirectory = path.resolve('tmp/resilience-assurance');

if (baseUrl.protocol !== 'https:' || !Number.isInteger(requestCount) || requestCount < 2 || !Number.isInteger(concurrency) || concurrency < 1 || concurrency > requestCount) {
    throw new Error('The mixed-load probe requires an HTTPS base URL and bounded positive request/concurrency values.');
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

const work = Array.from({ length: requestCount }, (_, index) => paths[index % paths.length]);
const results = [];
const startedAt = performance.now();
let cursor = 0;
await Promise.all(Array.from({ length: concurrency }, async () => {
    while (cursor < work.length) {
        const index = cursor++;
        results[index] = await request(work[index]);
    }
}));
const durationMs = performance.now() - startedAt;
const orderedLatency = results.map((result) => result.durationMs).sort((left, right) => left - right);
const percentile = (value) => orderedLatency[Math.min(orderedLatency.length - 1, Math.ceil(orderedLatency.length * value) - 1)];
const failures = results.filter((result) => result.status < 200 || result.status >= 300);
const report = {
    baseUrl: baseUrl.origin,
    paths,
    requestCount,
    concurrency,
    durationMs: Math.round(durationMs),
    requestsPerSecond: Number((requestCount / (durationMs / 1000)).toFixed(2)),
    p50LatencyMs: Math.round(percentile(0.5)),
    p95LatencyMs: Math.round(percentile(0.95)),
    p99LatencyMs: Math.round(percentile(0.99)),
    failures,
    routeSummary: Object.fromEntries(paths.map((routePath) => [routePath, {
        requests: results.filter((result) => result.routePath === routePath).length,
        failures: failures.filter((result) => result.routePath === routePath).length,
    }])),
    completedAt: new Date().toISOString(),
};

await fs.mkdir(outputDirectory, { recursive: true });
await fs.writeFile(path.join(outputDirectory, 'mixed-route-report.json'), `${JSON.stringify(report, null, 2)}\n`);

if (failures.length > 0 || report.p95LatencyMs > 2_000 || report.requestsPerSecond < 5) {
    throw new Error(`Mixed-route resilience assurance failed: ${JSON.stringify(report)}`);
}

process.stdout.write(`Mixed-route resilience assurance passed: ${requestCount} requests, ${concurrency} concurrent, ${report.requestsPerSecond} req/s, p95 ${report.p95LatencyMs} ms.\n`);
