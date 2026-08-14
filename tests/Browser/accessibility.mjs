import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { pathToFileURL } from 'node:url';

const projectRoot = process.cwd();
const playwrightCandidates = [
    path.join(projectRoot, 'node_modules/playwright/index.mjs'),
    path.join(
        projectRoot,
        'tmp/manual/runtime/node_modules/playwright/index.mjs',
    ),
];
let playwrightPath = null;

for (const candidate of playwrightCandidates) {
    try {
        await fs.access(candidate);
        playwrightPath = candidate;
        break;
    } catch {
        // Continue to the controlled fallback path.
    }
}

if (playwrightPath === null) {
    throw new Error(
        'Playwright is unavailable. Restore the local manual runtime or install the approved browser-test dependency.',
    );
}

const { chromium } = await import(pathToFileURL(playwrightPath).href);
const baseURL =
    process.env.IDMIS_BROWSER_BASE_URL ?? 'https://devolution-mis.test';
const outputDirectory = path.join(projectRoot, 'tmp/accessibility-assurance');
await fs.mkdir(outputDirectory, { recursive: true });

const allJourneys = [
    { name: 'public-welcome', path: '/' },
    { name: 'public-login', path: '/login' },
    {
        name: 'county-dashboard',
        path: '/dashboard',
        email: 'county.official@idmis.test',
    },
    {
        name: 'assessor-assessments',
        path: '/assessments',
        email: 'assessor@idmis.test',
    },
    {
        name: 'partner-analytics',
        path: '/analytics',
        email: 'partner@idmis.test',
    },
    {
        name: 'admin-operations',
        path: '/operations',
        email: 'platform.admin@idmis.test',
    },
    {
        name: 'admin-data-migrations',
        path: '/data-migrations',
        email: 'platform.admin@idmis.test',
    },
];
const requestedJourney = process.env.IDMIS_ACCESSIBILITY_JOURNEY;
const journeys = requestedJourney
    ? allJourneys.filter((journey) => journey.name === requestedJourney)
    : allJourneys;

if (journeys.length === 0) {
    throw new Error(`Unknown accessibility journey: ${requestedJourney}`);
}

const findings = [];
const browser = await chromium.launch({ channel: 'chrome', headless: true });

for (const journey of journeys) {
    const context = await browser.newContext({
        baseURL,
        ignoreHTTPSErrors: true,
        viewport: { width: 1280, height: 800 },
        colorScheme: 'light',
        reducedMotion: 'reduce',
    });
    const page = await context.newPage();

    if (journey.email) {
        await page.goto('/login', { waitUntil: 'networkidle' });
        await page.getByLabel(/email/i).fill(journey.email);
        await page.locator('input[name="password"]').fill('password');
        await page.locator('[data-test="login-button"]').click();
        await page.waitForURL(/dashboard/, { timeout: 30_000 });
    }

    const response = await page.goto(journey.path, {
        waitUntil: 'networkidle',
    });

    if (!response || response.status() >= 400) {
        findings.push({
            journey: journey.name,
            rule: 'response',
            detail: `HTTP ${response?.status() ?? 'none'}`,
        });
        await context.close();
        continue;
    }

    await page.keyboard.press('Tab');
    const firstFocus = await page.evaluate(() => {
        const element = document.activeElement;

        if (!(element instanceof HTMLElement)) {
            return null;
        }

        const style = getComputedStyle(element);

        return {
            text: element.innerText || element.getAttribute('aria-label') || '',
            href: element.getAttribute('href'),
            visible:
                element.getBoundingClientRect().width > 0 &&
                element.getBoundingClientRect().height > 0,
            focusVisible:
                style.outlineStyle !== 'none' || style.boxShadow !== 'none',
        };
    });

    if (
        !firstFocus?.visible ||
        firstFocus.href !== '#main-content' ||
        !firstFocus.focusVisible
    ) {
        findings.push({
            journey: journey.name,
            rule: 'keyboard-bypass',
            detail: firstFocus,
        });
    } else {
        await page.keyboard.press('Enter');
        const bypassTarget = await page.evaluate(
            () => document.activeElement?.id ?? null,
        );

        if (bypassTarget !== 'main-content') {
            findings.push({
                journey: journey.name,
                rule: 'keyboard-bypass-target',
                detail: bypassTarget,
            });
        }
    }

    await page.evaluate(() => {
        if (document.activeElement instanceof HTMLElement) {
            document.activeElement.blur();
        }

        const selectors = [
            'a[href]',
            'button:not([disabled])',
            'input:not([disabled]):not([type="hidden"])',
            'select:not([disabled])',
            'textarea:not([disabled])',
            '[tabindex]:not([tabindex="-1"])',
        ].join(',');
        let index = 0;

        for (const element of document.querySelectorAll(selectors)) {
            const style = getComputedStyle(element);
            const rect = element.getBoundingClientRect();

            if (
                element instanceof HTMLElement &&
                element.tabIndex >= 0 &&
                element.getAttribute('role') !== 'tablist' &&
                style.display !== 'none' &&
                style.visibility !== 'hidden' &&
                rect.width > 0 &&
                rect.height > 0 &&
                !element.closest('[inert]') &&
                element.getAttribute('aria-hidden') !== 'true'
            ) {
                element.dataset.accessibilityProbe = String(index++);
            }
        }
    });
    const expectedKeyboardOrder = await page.evaluate(() =>
        [...document.querySelectorAll('[data-accessibility-probe]')].map(
            (element) => element.dataset.accessibilityProbe,
        ),
    );
    const visitedKeyboardOrder = [];
    const focusFailures = [];

    for (
        let step = 0;
        step < Math.min(expectedKeyboardOrder.length + 3, 180);
        step++
    ) {
        await page.keyboard.press('Tab');
        const focusState = await page.evaluate(() => {
            const element = document.activeElement;

            if (!(element instanceof HTMLElement)) {
                return null;
            }

            const style = getComputedStyle(element);
            const rect = element.getBoundingClientRect();
            const outlineVisible =
                style.outlineStyle !== 'none' &&
                Number.parseFloat(style.outlineWidth) > 0;
            const ringVisible = style.boxShadow !== 'none';

            return {
                probe: element.dataset.accessibilityProbe ?? null,
                tag: element.tagName.toLowerCase(),
                name:
                    element.getAttribute('aria-label') ||
                    element.textContent?.trim().slice(0, 80) ||
                    element.getAttribute('name') ||
                    '',
                visible: rect.width > 0 && rect.height > 0,
                focusVisible: outlineVisible || ringVisible,
            };
        });

        if (!focusState || focusState.probe === null) {
            continue;
        }

        if (visitedKeyboardOrder.includes(focusState.probe)) {
            break;
        }

        visitedKeyboardOrder.push(focusState.probe);

        if (!focusState.visible || !focusState.focusVisible) {
            focusFailures.push(focusState);
        }
    }

    const missingKeyboardTargets = expectedKeyboardOrder.filter(
        (probe) => !visitedKeyboardOrder.includes(probe),
    );

    if (missingKeyboardTargets.length > 0) {
        const missingDetails = await page.evaluate(
            (probes) =>
                probes.map((probe) => {
                    const element = document.querySelector(
                        `[data-accessibility-probe="${probe}"]`,
                    );

                    return {
                        probe,
                        element: element?.outerHTML.slice(0, 240) ?? null,
                    };
                }),
            missingKeyboardTargets.slice(0, 20),
        );
        findings.push({
            journey: journey.name,
            rule: 'keyboard-reachability',
            detail: {
                expected: expectedKeyboardOrder.length,
                visited: visitedKeyboardOrder.length,
                missing: missingDetails,
            },
        });
    }

    const traversalStart = expectedKeyboardOrder.indexOf(
        visitedKeyboardOrder[0],
    );
    const expectedTraversalOrder =
        traversalStart < 0
            ? expectedKeyboardOrder
            : [
                  ...expectedKeyboardOrder.slice(traversalStart),
                  ...expectedKeyboardOrder.slice(0, traversalStart),
              ];

    if (
        visitedKeyboardOrder.some(
            (probe, index) => expectedTraversalOrder[index] !== probe,
        )
    ) {
        findings.push({
            journey: journey.name,
            rule: 'focus-order',
            detail: {
                expected: expectedTraversalOrder.slice(0, 30),
                visited: visitedKeyboardOrder.slice(0, 30),
            },
        });
    }

    if (focusFailures.length > 0) {
        findings.push({
            journey: journey.name,
            rule: 'focus-visible',
            detail: focusFailures.slice(0, 20),
        });
    }

    const structuralFindings = await page.evaluate(() => {
        const result = [];
        const visible = (element) => {
            const style = getComputedStyle(element);
            const rect = element.getBoundingClientRect();

            return (
                style.display !== 'none' &&
                style.visibility !== 'hidden' &&
                rect.width > 0 &&
                rect.height > 0
            );
        };
        const accessibleName = (element) =>
            element.getAttribute('aria-label') ||
            (element.getAttribute('aria-labelledby')
                ? element
                      .getAttribute('aria-labelledby')
                      .split(/\s+/)
                      .map(
                          (id) =>
                              document.getElementById(id)?.textContent ?? '',
                      )
                      .join(' ')
                : '') ||
            (element instanceof HTMLInputElement && element.labels
                ? [...element.labels]
                      .map((label) => label.textContent ?? '')
                      .join(' ')
                : '') ||
            element.textContent ||
            element.getAttribute('title') ||
            element.getAttribute('alt') ||
            '';

        const mainLandmarks = [
            ...document.querySelectorAll('main, [role="main"]'),
        ];

        if (mainLandmarks.length !== 1) {
            result.push({
                rule: 'main-landmark',
                detail: mainLandmarks.map((element) =>
                    element.outerHTML.slice(0, 180),
                ),
            });
        }

        if (!document.querySelector('h1')) {
            result.push({
                rule: 'heading',
                detail: 'Page has no level-one heading.',
            });
        }

        for (const element of document.querySelectorAll(
            'button, a[href], input, select, textarea, [role="button"], [role="link"], [role="combobox"]',
        )) {
            if (
                element.getAttribute('aria-hidden') !== 'true' &&
                visible(element) &&
                accessibleName(element).trim() === ''
            ) {
                result.push({
                    rule: 'accessible-name',
                    detail: element.outerHTML.slice(0, 240),
                });
            }

            if (
                element.getAttribute('tabindex') &&
                Number(element.getAttribute('tabindex')) > 0
            ) {
                result.push({
                    rule: 'positive-tabindex',
                    detail: element.outerHTML.slice(0, 240),
                });
            }
        }

        const reduceMotion = matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;

        if (!reduceMotion) {
            result.push({
                rule: 'reduced-motion',
                detail: 'Reduced-motion media query was not active.',
            });
        }

        return result;
    });
    findings.push(
        ...structuralFindings.map((finding) => ({
            journey: journey.name,
            ...finding,
        })),
    );

    const dialogTrigger = page
        .locator('[aria-haspopup="dialog"]:visible')
        .first();

    if ((await dialogTrigger.count()) > 0) {
        await dialogTrigger.click();
        const dialog = page.locator('[role="dialog"]:visible').first();

        try {
            await dialog.waitFor({ state: 'visible', timeout: 2_000 });
            const dialogFocus = await page.evaluate(() => {
                const active = document.activeElement;
                const openDialog = document.querySelector(
                    '[role="dialog"]:not([hidden])',
                );

                return {
                    hasDialog: openDialog instanceof HTMLElement,
                    initialFocusInside:
                        openDialog instanceof HTMLElement &&
                        active instanceof HTMLElement &&
                        openDialog.contains(active),
                };
            });

            if (!dialogFocus.hasDialog || !dialogFocus.initialFocusInside) {
                findings.push({
                    journey: journey.name,
                    rule: 'dialog-initial-focus',
                    detail: dialogFocus,
                });
            }

            const dialogTabStops = await dialog
                .locator(
                    'a[href]:visible, button:not([disabled]):visible, input:not([disabled]):visible, select:not([disabled]):visible, textarea:not([disabled]):visible, [tabindex]:not([tabindex="-1"]):visible',
                )
                .count();

            for (let step = 0; step < dialogTabStops + 2; step++) {
                await page.keyboard.press('Tab');
                const focusInside = await page.evaluate(() => {
                    const active = document.activeElement;
                    const openDialog = document.querySelector(
                        '[role="dialog"]:not([hidden])',
                    );

                    return (
                        openDialog instanceof HTMLElement &&
                        active instanceof HTMLElement &&
                        openDialog.contains(active)
                    );
                });

                if (!focusInside) {
                    findings.push({
                        journey: journey.name,
                        rule: 'dialog-focus-trap',
                        detail: { step, dialogTabStops },
                    });
                    break;
                }
            }

            await page.keyboard.press('Escape');
            await dialog.waitFor({ state: 'hidden', timeout: 2_000 });

            if (
                !(await dialogTrigger.evaluate(
                    (element) => element === document.activeElement,
                ))
            ) {
                findings.push({
                    journey: journey.name,
                    rule: 'dialog-focus-restoration',
                    detail: 'Dialog trigger did not regain focus after Escape.',
                });
            }
        } catch (error) {
            findings.push({
                journey: journey.name,
                rule: 'dialog-behavior',
                detail: error instanceof Error ? error.message : String(error),
            });
            await page.keyboard.press('Escape');
        }
    }

    for (const reflowCase of [
        { width: 640, effectiveZoom: 200 },
        { width: 320, effectiveZoom: 400 },
    ]) {
        await page.setViewportSize({ width: reflowCase.width, height: 800 });
        await page.waitForTimeout(100);
        const reflow = await page.evaluate(() => ({
            viewport: document.documentElement.clientWidth,
            content: document.documentElement.scrollWidth,
            body: document.body.scrollWidth,
        }));

        if (
            reflow.content > reflow.viewport + 2 ||
            reflow.body > reflow.viewport + 2
        ) {
            findings.push({
                journey: journey.name,
                rule: 'zoom-reflow',
                detail: { ...reflowCase, ...reflow },
            });
        }
    }

    await page.setViewportSize({ width: 1280, height: 800 });

    for (const colorScheme of ['light', 'dark']) {
        await page.emulateMedia({ colorScheme });
        await page.evaluate((scheme) => {
            document.documentElement.classList.toggle(
                'dark',
                scheme === 'dark',
            );
            document.documentElement.style.colorScheme = scheme;
        }, colorScheme);
        await page.waitForTimeout(50);
        const contrastFindings = await page.evaluate(() => {
            const parse = (value) => {
                const match = value.match(
                    /rgba?\((\d+(?:\.\d+)?)[, ]+(\d+(?:\.\d+)?)[, ]+(\d+(?:\.\d+)?)/,
                );

                return match
                    ? [Number(match[1]), Number(match[2]), Number(match[3])]
                    : null;
            };
            const luminance = (rgb) => {
                const channels = rgb.map((value) => {
                    const channel = value / 255;

                    return channel <= 0.04045
                        ? channel / 12.92
                        : ((channel + 0.055) / 1.055) ** 2.4;
                });

                return (
                    0.2126 * channels[0] +
                    0.7152 * channels[1] +
                    0.0722 * channels[2]
                );
            };
            const background = (element) => {
                let current = element;

                while (current instanceof HTMLElement) {
                    const color = getComputedStyle(current).backgroundColor;

                    if (
                        color !== 'rgba(0, 0, 0, 0)' &&
                        color !== 'transparent'
                    ) {
                        return parse(color);
                    }

                    current = current.parentElement;
                }

                return [255, 255, 255];
            };
            const failures = [];

            for (const element of document.querySelectorAll(
                'p, span, a, button, label, h1, h2, h3, th, td',
            )) {
                const style = getComputedStyle(element);
                const rect = element.getBoundingClientRect();
                const text = (element.textContent ?? '').trim();

                if (
                    !text ||
                    rect.width === 0 ||
                    rect.height === 0 ||
                    style.visibility === 'hidden' ||
                    Number(style.opacity) < 0.95
                ) {
                    continue;
                }

                const foreground = parse(style.color);
                const surface = background(element);

                if (!foreground || !surface) {
                    continue;
                }

                const light = Math.max(
                    luminance(foreground),
                    luminance(surface),
                );
                const dark = Math.min(
                    luminance(foreground),
                    luminance(surface),
                );
                const ratio = (light + 0.05) / (dark + 0.05);
                const large =
                    Number.parseFloat(style.fontSize) >= 24 ||
                    (Number.parseFloat(style.fontSize) >= 18.66 &&
                        Number(style.fontWeight) >= 700);

                if (ratio < (large ? 3 : 4.5)) {
                    failures.push({
                        text: text.slice(0, 80),
                        ratio: Number(ratio.toFixed(2)),
                    });
                }
            }

            return failures.slice(0, 25);
        });
        findings.push(
            ...contrastFindings.map((detail) => ({
                journey: journey.name,
                rule: 'contrast',
                detail: { colorScheme, ...detail },
            })),
        );
    }

    await page.screenshot({
        path: path.join(outputDirectory, `${journey.name}.png`),
        fullPage: true,
        animations: 'disabled',
    });
    await context.close();
}

await browser.close();
const report = {
    generatedAt: new Date().toISOString(),
    baseURL,
    assuranceProfile: {
        keyboard: [
            'skip-link activation',
            'focus reachability',
            'DOM-consistent focus order',
            'visible focus indication',
        ],
        dialogs: [
            'initial focus',
            'focus containment',
            'Escape dismissal',
            'trigger focus restoration',
        ],
        effectiveZoomPercent: [200, 400],
        colorSchemes: ['light', 'dark'],
        contrastThresholds: {
            normalText: 4.5,
            largeText: 3,
        },
    },
    journeys: journeys.map(({ name, path: journeyPath }) => ({
        name,
        path: journeyPath,
    })),
    findings,
};
await fs.writeFile(
    path.join(outputDirectory, 'report.json'),
    JSON.stringify(report, null, 2),
);

if (findings.length > 0) {
    process.stderr.write(`${JSON.stringify(report, null, 2)}\n`);
    process.exitCode = 1;
} else {
    process.stdout.write(
        `Accessibility assurance passed for ${journeys.length} journeys.\n`,
    );
}
