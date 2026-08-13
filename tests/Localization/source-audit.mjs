import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { globSync } from 'node:fs';

/* global process */

const limits = {
    frontendLiterals: 102,
    backendMessages: 593,
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
const report = {
    frontendLiterals: frontendMessages.length,
    frontendFiles: new Set(frontendMessages.map((message) => message.file))
        .size,
    backendMessages: backendMessages.length,
    limits,
};

console.log(JSON.stringify(report, null, 2));

if (
    report.frontendLiterals > limits.frontendLiterals ||
    report.backendMessages > limits.backendMessages
) {
    console.error(
        'Localization debt increased. Extract new user-facing copy before merging.',
    );
    process.exitCode = 1;
}
