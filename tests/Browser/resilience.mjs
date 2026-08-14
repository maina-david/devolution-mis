import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import https from 'node:https';
import path from 'node:path';
import process from 'node:process';

const baseUrl = new URL(
    process.env.IDMIS_BASE_URL ?? 'https://devolution-mis.test',
);
const requestCount = Number(process.env.IDMIS_RESILIENCE_REQUESTS ?? 100);
const concurrency = Number(process.env.IDMIS_RESILIENCE_CONCURRENCY ?? 10);
const p95ThresholdMs = Number(
    process.env.IDMIS_RESILIENCE_P95_THRESHOLD_MS ?? 2_000,
);
const minimumRequestsPerSecond = Number(
    process.env.IDMIS_RESILIENCE_MINIMUM_RPS ?? 5,
);
const requestTimeoutMs = Number(
    process.env.IDMIS_RESILIENCE_TIMEOUT_MS ?? 10_000,
);
const paths = ['/up', '/health/ready'];
const outputDirectory = path.resolve('tmp/resilience-assurance');

if (
    baseUrl.protocol !== 'https:' ||
    !Number.isInteger(requestCount) ||
    requestCount < paths.length ||
    requestCount > 10_000 ||
    !Number.isInteger(concurrency) ||
    concurrency < 2 ||
    concurrency > Math.min(requestCount, 100) ||
    !Number.isFinite(p95ThresholdMs) ||
    p95ThresholdMs < 1 ||
    !Number.isFinite(minimumRequestsPerSecond) ||
    minimumRequestsPerSecond <= 0 ||
    !Number.isFinite(requestTimeoutMs) ||
    requestTimeoutMs < 100
) {
    throw new Error(
        'The mixed-load probe requires an HTTPS base URL and bounded request, concurrency, latency, throughput and timeout values.',
    );
}

let inFlight = 0;
let maximumObservedConcurrency = 0;

const request = (routePath) =>
    new Promise((resolve) => {
        const startedAt = performance.now();
        let settled = false;
        let responseBytes = 0;
        inFlight++;
        maximumObservedConcurrency = Math.max(
            maximumObservedConcurrency,
            inFlight,
        );

        const finish = (result) => {
            if (settled) {
                return;
            }

            settled = true;
            inFlight--;
            resolve({
                routePath,
                durationMs: performance.now() - startedAt,
                responseBytes,
                ...result,
            });
        };
        const call = https.get(
            new URL(routePath, baseUrl),
            {
                rejectUnauthorized: false,
                timeout: requestTimeoutMs,
            },
            (response) => {
                response.on('data', (chunk) => {
                    responseBytes += chunk.length;
                });
                response.on('end', () =>
                    finish({
                        status: response.statusCode ?? 0,
                        contentType: response.headers['content-type'] ?? null,
                    }),
                );
            },
        );
        call.on('timeout', () => call.destroy(new Error('timeout')));
        call.on('error', (error) =>
            finish({ status: 0, error: error.message }),
        );
    });

const warmup = [];

for (const routePath of paths) {
    warmup.push(await request(routePath));
}

const warmupFailures = warmup.filter(
    (result) =>
        result.status < 200 ||
        result.status >= 300 ||
        result.responseBytes === 0,
);

if (warmupFailures.length > 0) {
    throw new Error(
        `Mixed-load warm-up failed: ${JSON.stringify(warmupFailures)}`,
    );
}

maximumObservedConcurrency = 0;
const work = Array.from(
    { length: requestCount },
    (_, index) => paths[index % paths.length],
);
const results = [];
const startedAt = performance.now();
let cursor = 0;

await Promise.all(
    Array.from({ length: concurrency }, async () => {
        while (cursor < work.length) {
            const index = cursor++;
            results[index] = await request(work[index]);
        }
    }),
);
const durationMs = performance.now() - startedAt;
const orderedLatency = results
    .map((result) => result.durationMs)
    .sort((left, right) => left - right);
const percentile = (value) =>
    orderedLatency[
        Math.min(
            orderedLatency.length - 1,
            Math.ceil(orderedLatency.length * value) - 1,
        )
    ];
const failures = results.filter(
    (result) =>
        result.status < 200 ||
        result.status >= 300 ||
        result.responseBytes === 0,
);
const routeSummary = Object.fromEntries(
    paths.map((routePath) => {
        const routeResults = results.filter(
            (result) => result.routePath === routePath,
        );
        const routeLatencies = routeResults
            .map((result) => result.durationMs)
            .sort((left, right) => left - right);
        const routeP95 =
            routeLatencies[
                Math.min(
                    routeLatencies.length - 1,
                    Math.ceil(routeLatencies.length * 0.95) - 1,
                )
            ];

        return [
            routePath,
            {
                requests: routeResults.length,
                failures: failures.filter(
                    (result) => result.routePath === routePath,
                ).length,
                p95LatencyMs: Math.round(routeP95),
                responseBytes: routeResults.reduce(
                    (total, result) => total + result.responseBytes,
                    0,
                ),
            },
        ];
    }),
);
const evidence = {
    baseUrl: baseUrl.origin,
    paths,
    requestCount,
    configuredConcurrency: concurrency,
    maximumObservedConcurrency,
    durationMs: Math.round(durationMs),
    requestsPerSecond: Number((requestCount / (durationMs / 1_000)).toFixed(2)),
    p50LatencyMs: Math.round(percentile(0.5)),
    p95LatencyMs: Math.round(percentile(0.95)),
    p99LatencyMs: Math.round(percentile(0.99)),
    thresholds: {
        p95LatencyMs: p95ThresholdMs,
        minimumRequestsPerSecond,
        requestTimeoutMs,
    },
    warmup,
    failures,
    routeSummary,
    completedAt: new Date().toISOString(),
};
const report = {
    ...evidence,
    evidenceChecksum: crypto
        .createHash('sha256')
        .update(JSON.stringify(evidence))
        .digest('hex'),
};

await fs.mkdir(outputDirectory, { recursive: true });
await fs.writeFile(
    path.join(outputDirectory, 'mixed-route-report.json'),
    `${JSON.stringify(report, null, 2)}\n`,
);

if (
    failures.length > 0 ||
    maximumObservedConcurrency < concurrency ||
    report.p95LatencyMs > p95ThresholdMs ||
    report.requestsPerSecond < minimumRequestsPerSecond
) {
    throw new Error(
        `Mixed-route resilience assurance failed: ${JSON.stringify(report)}`,
    );
}

process.stdout.write(
    `Mixed-route resilience assurance passed: ${requestCount} requests, ${maximumObservedConcurrency}/${concurrency} observed concurrency, ${report.requestsPerSecond} req/s, p95 ${report.p95LatencyMs} ms, evidence ${report.evidenceChecksum}.\n`,
);
