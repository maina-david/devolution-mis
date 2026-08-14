import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { globSync } from 'node:fs';

const violations = globSync('resources/js/pages/**/*.tsx').flatMap((file) => {
    const source = readFileSync(file, 'utf8');
    const assignedLayouts = [
        ...source.matchAll(/\.layout\s*=\s*([A-Z][A-Za-z0-9]*Layout)\s*;/gu),
    ];

    return assignedLayouts
        .filter(([, layoutName]) => {
            const functionStart = source.indexOf(`function ${layoutName}(`);

            if (functionStart === -1) {
                return false;
            }

            const functionSource = source.slice(
                functionStart,
                functionStart + 800,
            );

            return /return\s*\{\s*breadcrumbs\s*:/u.test(functionSource);
        })
        .map(([, layoutName]) => `${file}: ${layoutName}`);
});

assert.deepEqual(
    violations,
    [],
    `Breadcrumb metadata functions must use setLayoutProps instead of being assigned as React layouts:\n${violations.join('\n')}`,
);

console.log('Inertia layout-props contract passed.');
