import { usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Search } from 'lucide-react';
import { useId, useMemo, useState } from 'react';
import type { SearchableSelectOption } from '@/components/searchable-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { interpolate } from '@/hooks/use-localization';
import { cn } from '@/lib/utils';

export default function SearchableMultiSelect({
    name,
    label,
    options,
    error,
    optional = false,
    defaultValues = [],
}: {
    name: string;
    label: string;
    options: SearchableSelectOption[];
    error?: string;
    optional?: boolean;
    defaultValues?: string[];
}) {
    const copy = usePage().props.localization.common;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [values, setValues] = useState<string[]>(() =>
        defaultValues.filter((value) =>
            options.some((option) => option.id === value),
        ),
    );
    const generatedId = useId();
    const controlId = `${name.replaceAll(/[^a-zA-Z0-9_-]/g, '-')}-${generatedId}`;
    const listboxId = `${controlId}-listbox`;
    const errorId = `${controlId}-error`;
    const filtered = useMemo(
        () =>
            options.filter((option) =>
                option.name
                    .toLocaleLowerCase()
                    .includes(query.trim().toLocaleLowerCase()),
            ),
        [options, query],
    );
    const baseName = name.endsWith('[]') ? name.slice(0, -2) : name;

    const toggle = (value: string) =>
        setValues((current) =>
            current.includes(value)
                ? current.filter((item) => item !== value)
                : [...current, value],
        );

    return (
        <div className="grid gap-2">
            <Label
                htmlFor={controlId}
                className={label ? undefined : 'sr-only'}
            >
                {label || copy.select_options}
            </Label>
            {values.map((value) => (
                <input
                    key={value}
                    type="hidden"
                    name={`${baseName}[]`}
                    value={value}
                />
            ))}
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        id={controlId}
                        type="button"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        aria-controls={listboxId}
                        aria-required={!optional}
                        aria-invalid={Boolean(error)}
                        aria-describedby={error ? errorId : undefined}
                        className="h-auto min-h-9 w-full justify-between font-normal"
                    >
                        <span className="flex flex-wrap gap-1">
                            {values.length ? (
                                values.map((value) => (
                                    <Badge
                                        key={value}
                                        variant="secondary"
                                        className="gap-1.5"
                                    >
                                        {options.find(
                                            (option) => option.id === value,
                                        )?.logoUrl && (
                                            <img
                                                src={
                                                    options.find(
                                                        (option) =>
                                                            option.id === value,
                                                    )?.logoUrl ?? undefined
                                                }
                                                alt=""
                                                className="size-4 rounded-sm object-contain"
                                            />
                                        )}
                                        {
                                            options.find(
                                                (option) => option.id === value,
                                            )?.name
                                        }
                                    </Badge>
                                ))
                            ) : (
                                <span className="text-muted-foreground">
                                    {optional
                                        ? copy.not_specified
                                        : copy.select_one_or_more}
                                </span>
                            )}
                        </span>
                        <ChevronsUpDown
                            className="shrink-0 opacity-50"
                            aria-hidden="true"
                        />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-2">
                    <div className="relative">
                        <Search
                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder={interpolate(copy.search_options, {
                                label: label || copy.select_options,
                            })}
                            aria-label={interpolate(copy.search_options, {
                                label: label || copy.select_options,
                            })}
                            className="pl-9"
                        />
                    </div>
                    <div
                        id={listboxId}
                        role="listbox"
                        aria-label={label || copy.select_options}
                        aria-multiselectable="true"
                        className="mt-2 max-h-60 overflow-y-auto"
                    >
                        {filtered.map((option) => (
                            <button
                                key={option.id}
                                type="button"
                                role="option"
                                aria-selected={values.includes(option.id)}
                                onClick={() => toggle(option.id)}
                                className="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm hover:bg-accent"
                            >
                                <Check
                                    className={cn(
                                        'size-4',
                                        values.includes(option.id)
                                            ? 'opacity-100'
                                            : 'opacity-0',
                                    )}
                                    aria-hidden="true"
                                />
                                {option.logoUrl && (
                                    <img
                                        src={option.logoUrl}
                                        alt=""
                                        className="size-7 shrink-0 rounded-sm object-contain"
                                        loading="lazy"
                                    />
                                )}
                                <span className="truncate">{option.name}</span>
                            </button>
                        ))}
                        {filtered.length === 0 && (
                            <p
                                role="status"
                                className="px-2 py-6 text-center text-sm text-muted-foreground"
                            >
                                {copy.no_matching_options}
                            </p>
                        )}
                    </div>
                </PopoverContent>
            </Popover>
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="text-xs text-destructive"
                >
                    {error}
                </p>
            )}
        </div>
    );
}
