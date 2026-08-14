import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { globSync } from 'node:fs';

/* global process */

const limits = {
    frontendLiterals: 0,
    authenticatedSemanticLiterals: 465,
    backendMessages: 0,
};

let eslintOutput = '';

try {
    eslintOutput = execFileSync(
        'npx',
        [
            'eslint',
            'resources/js',
            '--rule',
            'react/jsx-no-literals:error',
            '--format',
            'json',
        ],
        { encoding: 'utf8', maxBuffer: 32 * 1024 * 1024 },
    );
} catch (error) {
    eslintOutput = error.stdout;
}

const eslintResults = JSON.parse(eslintOutput);
const frontendMessages = eslintResults.flatMap((result) =>
    result.messages
        .filter((message) => message.ruleId === 'react/jsx-no-literals')
        .map((message) => ({
            file: result.filePath,
            line: message.line,
            text: message.message,
        })),
);
const backendPatterns = [
    /['"](?:title|label)['"]\s*=>\s*f?['"][A-Z]/gu,
    /ValidationException::withMessages\(\s*\[\s*['"][^'"]+['"]\s*=>\s*f?['"]/gu,
    /->withErrors\(\s*\[\s*['"][^'"]+['"]\s*=>\s*f?['"]/gu,
    /->with\(\s*['"](?:success|error|status|warning)['"]\s*,\s*f?['"]/gu,
    /['"]message['"]\s*=>\s*f?['"]/gu,
    /abort\(\s*\d+\s*,\s*f?['"]/gu,
    /abort_(?:if|unless)\([^,]+,\s*\d+\s*,\s*f?['"]/gu,
    /throw new (?:RuntimeException|LogicException|DomainException|InvalidArgumentException)\(\s*f?['"]/gu,
];
const backendMessages = globSync('app/**/*.php').flatMap((file) => {
    const source = readFileSync(file, 'utf8');

    return backendPatterns.flatMap((pattern) =>
        [...source.matchAll(pattern)].map((match) => ({
            file,
            offset: match.index,
        })),
    );
});
const publicPagePatterns = [
    '/pages/auth/',
    '/pages/citizen-engagement/',
    '/pages/welcome.tsx',
    '/pages/help.tsx',
    '/pages/faqs.tsx',
    '/pages/privacy-notice.tsx',
    '/pages/data-rights/',
    '/pages/error.tsx',
];
const semanticAttributePattern =
    /\b(?:aria-label|aria-description|label|placeholder|title)="([^"\n]*[A-Za-z][^"\n]*)"/gu;
const semanticTextPattern = />\s*([A-Z][A-Za-z][^<{\n]*?)\s*</gu;
const authenticatedSemanticMessages = [
    ...globSync('resources/js/pages/**/*.tsx'),
    ...globSync('resources/js/components/**/*.tsx'),
    ...globSync('resources/js/layouts/**/*.tsx'),
].flatMap((file) => {
    const normalized = file.replaceAll('\\', '/');

    if (publicPagePatterns.some((pattern) => normalized.includes(pattern))) {
        return [];
    }

    const source = readFileSync(file, 'utf8');

    return [semanticAttributePattern, semanticTextPattern].flatMap((pattern) =>
        [...source.matchAll(pattern)].map((match) => ({
            file,
            offset: match.index,
            text: match[1].trim(),
        })),
    );
});
const report = {
    frontendLiterals: frontendMessages.length,
    frontendFiles: new Set(frontendMessages.map((message) => message.file))
        .size,
    authenticatedSemanticLiterals: authenticatedSemanticMessages.length,
    authenticatedSemanticFiles: new Set(
        authenticatedSemanticMessages.map((message) => message.file),
    ).size,
    backendMessages: backendMessages.length,
    limits,
};

console.log(JSON.stringify(report, null, 2));

if (
    report.frontendLiterals > limits.frontendLiterals ||
    report.authenticatedSemanticLiterals >
        limits.authenticatedSemanticLiterals ||
    report.backendMessages > limits.backendMessages
) {
    console.error(
        'Localization debt increased. Extract new user-facing copy before merging.',
    );
    process.exitCode = 1;
}
