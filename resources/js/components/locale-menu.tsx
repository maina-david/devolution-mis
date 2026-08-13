import { Link, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { update as updateLocale } from '@/routes/locale';

export function LocaleMenu({
    inverse = false,
    className,
}: {
    inverse?: boolean;
    className?: string;
}) {
    const { localization } = usePage().props;
    const selectedLocale = localization.supported.find(
        (locale) => locale.code === localization.current,
    );

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    className={cn(
                        'h-9 gap-1.5 px-2',
                        inverse &&
                            'text-primary-foreground hover:bg-primary-foreground/10 hover:text-primary-foreground',
                        className,
                    )}
                    title={localization.copy.chooseLanguage}
                    aria-label={`${localization.copy.chooseLanguage}. ${localization.copy.currentLanguage}: ${selectedLocale?.nativeLabel ?? localization.current}`}
                >
                    <span aria-hidden="true">{selectedLocale?.flag}</span>
                    <span className="hidden text-sm font-medium sm:inline">
                        {selectedLocale?.nativeLabel ?? localization.current}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuLabel>
                    {localization.copy.language}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {localization.supported.map((locale) => {
                    const active = locale.code === localization.current;

                    return (
                        <DropdownMenuItem key={locale.code} asChild>
                            <Link
                                href={updateLocale()}
                                method="patch"
                                data={{ locale: locale.code }}
                                preserveScroll
                                aria-current={active ? 'true' : undefined}
                                lang={locale.code}
                            >
                                <span aria-hidden="true">{locale.flag}</span>
                                <span>{locale.nativeLabel}</span>
                                {locale.label !== locale.nativeLabel && (
                                    <span className="text-muted-foreground">
                                        {locale.label}
                                    </span>
                                )}
                                {active && (
                                    <Check
                                        className="ml-auto"
                                        aria-hidden="true"
                                    />
                                )}
                            </Link>
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
            <span className="sr-only" role="status" aria-live="polite">
                {localization.copy.currentLanguage}
                {':' + ' '}
                {selectedLocale?.nativeLabel ?? localization.current}
            </span>
        </DropdownMenu>
    );
}
