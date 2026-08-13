import { usePage } from '@inertiajs/react';

export function interpolate(
    message: string,
    replacements: Record<string, string | number>,
): string {
    return Object.entries(replacements).reduce(
        (translated, [key, value]) =>
            translated.replaceAll(`:${key}`, String(value)),
        message,
    );
}

export function useCommonCopy(): Record<string, string> {
    return usePage().props.localization.common;
}
