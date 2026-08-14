import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import http from 'node:http';
import https from 'node:https';
import path from 'node:path';
import process from 'node:process';

const baseUrl = new URL(
    process.env.IDMIS_BASE_URL ?? 'https://devolution-mis.test',
);
const concurrency = Number(process.env.IDMIS_RECOVERY_CONCURRENCY ?? 5);
const recoveryRequests = Number(process.env.IDMIS_RECOVERY_REQUESTS ?? 30);
const injectedFailureRequests = Number(
    process.env.IDMIS_RECOVERY_INJECTED_FAILURES ?? 5,
);
const p95ThresholdMs = Number(
    process.env.IDMIS_RECOVERY_P95_THRESHOLD_MS ?? 2_000,
);
const recoveryTimeThresholdMs = Number(
    process.env.IDMIS_RECOVERY_TIME_THRESHOLD_MS ?? 5_000,
);
const requestTimeoutMs = Number(
    process.env.IDMIS_RECOVERY_TIMEOUT_MS ?? 10_000,
);
const paths = ['/up', '/health/ready'];
const outputDirectory = path.resolve('tmp/resilience-assurance');

if (
    baseUrl.protocol !== 'https:' ||
    !Number.isInteger(concurrency) ||
    concurrency < 2 ||
    concurrency > 100 ||
    !Number.isInteger(recoveryRequests) ||
    recoveryRequests < concurrency * 2 ||
    recoveryRequests > 10_000 ||
    !Number.isInteger(injectedFailureRequests) ||
    injectedFailureRequests < 1 ||
    injectedFailureRequests >= recoveryRequests ||
    !Number.isFinite(p95ThresholdMs) ||
    p95ThresholdMs < 1 ||
    !Number.isFinite(recoveryTimeThresholdMs) ||
    recoveryTimeThresholdMs < 1 ||
    !Number.isFinite(requestTimeoutMs) ||
    requestTimeoutMs < 100
) {
    throw new Error(
        'Failure-recovery assurance requires HTTPS and bounded positive request, fault, concurrency, latency and recovery-time values.',
    );
}

let inFlight = 0;
let maximumObservedConcurrency = 0;

const request = (targetUrl, sequence = null) =>
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
                sequence,
                routePath: targetUrl.pathname,
                durationMs: performance.now() - startedAt,
                completedAtMs: performance.now(),
                responseBytes,
                ...result,
            });
        };
        const transport = targetUrl.protocol === 'https:' ? https : http;
        const call = transport.get(
            targetUrl,
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
                        faultInjected:
                            response.headers['x-idmis-fault-injection'] ===
                            'transient',
                    }),
                );
            },
        );
        call.on('timeout', () => call.destroy(new Error('timeout')));
        call.on('error', (error) =>
            finish({
                status: 0,
                faultInjected: false,
                error: error.message,
            }),
        );
    });

const upstreamWarmup = [];

for (const routePath of paths) {
    upstreamWarmup.push(await request(new URL(routePath, baseUrl)));
}

if (
    upstreamWarmup.some(
        (result) =>
            result.status < 200 ||
            result.status >= 300 ||
            result.responseBytes === 0,
    )
) {
    throw new Error(
        `Failure-recovery upstream warm-up failed: ${JSON.stringify(upstreamWarmup)}`,
    );
}

let proxySequence = 0;
const faultProxy = http.createServer((incoming, outgoing) => {
    const currentSequence = proxySequence++;

    if (currentSequence < injectedFailureRequests) {
        outgoing.writeHead(503, {
            'content-type': 'application/json',
            'retry-after': '1',
            'x-idmis-fault-injection': 'transient',
        });
        outgoing.end(
            JSON.stringify({
                status: 'temporarily_unavailable',
                sequence: currentSequence,
            }),
        );

        return;
    }

    const upstream = https.get(
        new URL(incoming.url ?? '/health/ready', baseUrl),
        {
            rejectUnauthorized: false,
            timeout: requestTimeoutMs,
        },
        (response) => {
            outgoing.writeHead(response.statusCode ?? 502, {
                'content-type':
                    response.headers['content-type'] ??
                    'application/octet-stream',
            });
            response.pipe(outgoing);
        },
    );
    upstream.on('timeout', () => upstream.destroy(new Error('timeout')));
    upstream.on('error', (error) => {
        if (!outgoing.headersSent) {
            outgoing.writeHead(502, { 'content-type': 'application/json' });
        }

        outgoing.end(
            JSON.stringify({ status: 'upstream_error', error: error.name }),
        );
    });
});

await new Promise((resolve, reject) => {
    faultProxy.once('error', reject);
    faultProxy.listen(0, '127.0.0.1', resolve);
});

const address = faultProxy.address();

if (!address || typeof address === 'string') {
    faultProxy.close();

    throw new Error('The bounded loopback fault proxy failed to bind.');
}

const proxyBaseUrl = new URL(`http://127.0.0.1:${address.port}`);
const results = [];
const recoveryStartedAt = performance.now();
let cursor = 0;
maximumObservedConcurrency = 0;

try {
    await Promise.all(
        Array.from({ length: concurrency }, async () => {
            while (cursor < recoveryRequests) {
                const index = cursor++;
                results[index] = await request(
                    new URL(paths[index % paths.length], proxyBaseUrl),
                    index,
                );
            }
        }),
    );
} finally {
    await new Promise((resolve) => faultProxy.close(resolve));
}

const recoveryDurationMs = performance.now() - recoveryStartedAt;
const expectedFailures = results.filter(
    (result) => result.status === 503 && result.faultInjected,
);
const unexpectedFailures = results.filter(
    (result) =>
        (result.status < 200 || result.status >= 300) &&
        !(result.status === 503 && result.faultInjected),
);
const successes = results.filter(
    (result) => result.status >= 200 && result.status < 300,
);
const firstRecovery = successes
    .slice()
    .sort((left, right) => left.completedAtMs - right.completedAtMs)[0];
const recoveryTimeMs = firstRecovery
    ? Math.round(firstRecovery.completedAtMs - recoveryStartedAt)
    : null;
const postRecoveryFailures = firstRecovery
    ? results.filter(
          (result) =>
              result.completedAtMs > firstRecovery.completedAtMs &&
              (result.status < 200 || result.status >= 300),
      )
    : results;
const successLatencies = successes
    .map((result) => result.durationMs)
    .sort((left, right) => left - right);
const p95LatencyMs =
    successLatencies.length === 0
        ? null
        : Math.round(
              successLatencies[
                  Math.min(
                      successLatencies.length - 1,
                      Math.ceil(successLatencies.length * 0.95) - 1,
                  )
              ],
          );
const steadyStateWindow = results.slice(-Math.min(10, results.length));
const evidence = {
    baseUrl: baseUrl.origin,
    faultModel: 'bounded-loopback-transient-http-503',
    paths,
    injectedFailureRequests,
    observedInjectedFailures: expectedFailures.length,
    recoveryRequests,
    configuredConcurrency: concurrency,
    maximumObservedConcurrency,
    recoveryDurationMs: Math.round(recoveryDurationMs),
    recoveryTimeMs,
    recoveryRequestsPerSecond: Number(
        (recoveryRequests / (recoveryDurationMs / 1_000)).toFixed(2),
    ),
    p95LatencyMs,
    thresholds: {
        p95LatencyMs: p95ThresholdMs,
        recoveryTimeMs: recoveryTimeThresholdMs,
        requestTimeoutMs,
    },
    upstreamWarmup,
    unexpectedFailures,
    postRecoveryFailures,
    steadyStateSuccesses: steadyStateWindow.filter(
        (result) => result.status >= 200 && result.status < 300,
    ).length,
    steadyStateWindowSize: steadyStateWindow.length,
    recovered:
        expectedFailures.length === injectedFailureRequests &&
        unexpectedFailures.length === 0 &&
        postRecoveryFailures.length === 0 &&
        steadyStateWindow.every(
            (result) => result.status >= 200 && result.status < 300,
        ) &&
        maximumObservedConcurrency >= concurrency &&
        recoveryTimeMs !== null &&
        recoveryTimeMs <= recoveryTimeThresholdMs &&
        p95LatencyMs !== null &&
        p95LatencyMs <= p95ThresholdMs,
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
    path.join(outputDirectory, 'failure-recovery-report.json'),
    `${JSON.stringify(report, null, 2)}\n`,
);

if (!report.recovered) {
    throw new Error(
        `Failure-recovery assurance failed: ${JSON.stringify(report)}`,
    );
}

process.stdout.write(
    `Failure-recovery assurance passed: ${expectedFailures.length} injected HTTP 503 responses, ${successes.length} successful requests, ${maximumObservedConcurrency}/${concurrency} observed concurrency, recovery ${recoveryTimeMs} ms, p95 ${p95LatencyMs} ms, evidence ${report.evidenceChecksum}.\n`,
);
