import { Check, ChevronsUpDown, Search } from 'lucide-react';
import { useId, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { interpolate, useCommonCopy } from '@/hooks/use-localization';
import { cn } from '@/lib/utils';

export type SearchableSelectOption = {
    id: string;
    name: string;
    logoUrl?: string | null;
};

export default function SearchableSelect({
    id,
    name,
    label,
    options,
    optional = false,
    error,
    defaultValue = '',
    value: controlledValue,
    onValueChange,
}: {
    id: string;
    name?: string;
    label: string;
    options: SearchableSelectOption[];
    optional?: boolean;
    error?: string;
    defaultValue?: string;
    value?: string;
    onValueChange?: (value: string) => void;
}) {
    const copy = useCommonCopy();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [internalValue, setInternalValue] = useState(defaultValue);
    const value = controlledValue ?? internalValue;
    const listboxId = useId();
    const controlId = `${id}-${listboxId}`;
    const errorId = `${controlId}-error`;
    const selected = options.find((option) => option.id === value);
    const selectValue = (nextValue: string) => {
        if (controlledValue === undefined) {
            setInternalValue(nextValue);
        }

        onValueChange?.(nextValue);
        setOpen(false);
    };
    const filtered = useMemo(() => {
        const normalizedQuery = query.trim().toLocaleLowerCase();

        return normalizedQuery
            ? options.filter((option) =>
                  option.name.toLocaleLowerCase().includes(normalizedQuery),
              )
            : options;
    }, [options, query]);

    return (
        <div className="flex flex-col gap-2">
            <Label
                htmlFor={controlId}
                className={label ? undefined : 'sr-only'}
            >
                {label || copy.select_option}
            </Label>
            {name && <input type="hidden" name={name} value={value} />}
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
                        className="w-full justify-between font-normal"
                    >
                        <span
                            className={cn(
                                'flex min-w-0 items-center gap-2',
                                !selected && 'text-muted-foreground',
                            )}
                        >
                            {selected?.logoUrl && (
                                <img
                                    src={selected.logoUrl}
                                    alt=""
                                    className="size-6 shrink-0 rounded-sm object-contain"
                                />
                            )}
                            <span className="truncate">
                                {selected?.name ??
                                    (optional
                                        ? copy.not_specified
                                        : copy.select_an_option)}
                            </span>
                        </span>
                        <ChevronsUpDown
                            className="opacity-50"
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
                                label: (label || copy.select_option).toLocaleLowerCase(),
                            })}
                            aria-label={interpolate(copy.search_options, {
                                label: (label || copy.select_option).toLocaleLowerCase(),
                            })}
                            className="pl-9"
                            autoFocus
                        />
                    </div>
                    <div
                        id={listboxId}
                        role="listbox"
                        aria-label={label}
                        className="mt-2 max-h-60 overflow-y-auto"
                    >
                        {optional && (
                            <OptionButton
                                option={{ id: '', name: copy.not_specified }}
                                value={value}
                                onSelect={selectValue}
                            />
                        )}
                        {filtered.map((option) => (
                            <OptionButton
                                key={option.id}
                                option={option}
                                value={value}
                                onSelect={selectValue}
                            />
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

function OptionButton({
    option,
    value,
    onSelect,
}: {
    option: SearchableSelectOption;
    value: string;
    onSelect: (value: string) => void;
}) {
    return (
        <button
            type="button"
            role="option"
            aria-selected={value === option.id}
            onClick={() => onSelect(option.id)}
            className="flex w-full items-center gap-2 rounded-md px-2 py-2 text-left text-sm hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
        >
            <Check
                className={cn(
                    'size-4',
                    value === option.id ? 'opacity-100' : 'opacity-0',
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
    );
}
