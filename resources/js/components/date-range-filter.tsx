import { router, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { CalendarIcon, SearchIcon, XIcon } from 'lucide-react';
import { useState } from 'react';
import type { DateRange } from 'react-day-picker';
import SearchableSelect from '@/components/searchable-select';
import type { SearchableSelectOption } from '@/components/searchable-select';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

type Props = {
    initialFrom?: string | null;
    initialTo?: string | null;
    initialSearch?: string;
    fromKey?: string;
    toKey?: string;
    searchKey?: string;
    searchPlaceholder?: string;
    initialCycleId?: string | null;
    cycles?: SearchableSelectOption[];
    selectFilters?: Array<{
        key: string;
        label: string;
        options: SearchableSelectOption[];
        value?: string | null;
    }>;
    perPageKey?: string;
};

export default function DateRangeFilter({
    initialFrom,
    initialTo,
    initialSearch = '',
    fromKey = 'from',
    toKey = 'to',
    searchKey = 'search',
    searchPlaceholder = 'Search authorized records',
    initialCycleId,
    cycles,
    selectFilters = [],
    perPageKey = 'per_page',
}: Props) {
    const page = usePage();
    const currentQuery = new URLSearchParams(page.url.split('?')[1]);
    const resolvedCycles = cycles ?? page.props.assessmentCycles;
    const [range, setRange] = useState<DateRange | undefined>({
        from: initialFrom ? new Date(`${initialFrom}T00:00:00`) : undefined,
        to: initialTo ? new Date(`${initialTo}T00:00:00`) : undefined,
    });
    const [search, setSearch] = useState(initialSearch);
    const [cycleId, setCycleId] = useState(
        initialCycleId ?? currentQuery.get('cycle_id') ?? '',
    );
    const [selectValues, setSelectValues] = useState<Record<string, string>>(
        () =>
            Object.fromEntries(
                selectFilters.map((filter) => [filter.key, filter.value ?? '']),
            ),
    );
    const currentPath = page.url.split('?')[0];
    const currentPerPage = currentQuery.get(perPageKey);

    const apply = () =>
        router.get(
            currentPath,
            {
                ...Object.fromEntries(currentQuery.entries()),
                [fromKey]: range?.from
                    ? format(range.from, 'yyyy-MM-dd')
                    : undefined,
                [toKey]: range?.to ? format(range.to, 'yyyy-MM-dd') : undefined,
                [searchKey]: search || undefined,
                cycle_id: cycleId || undefined,
                [perPageKey]: currentPerPage || undefined,
                ...Object.fromEntries(
                    Object.entries(selectValues).map(([key, value]) => [
                        key,
                        value || undefined,
                    ]),
                ),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    const clear = () => {
        setRange(undefined);
        setSearch('');
        setCycleId('');
        setSelectValues(
            Object.fromEntries(selectFilters.map((filter) => [filter.key, ''])),
        );
        const retainedQuery = new URLSearchParams(currentQuery);
        [
            fromKey,
            toKey,
            searchKey,
            'cycle_id',
            ...selectFilters.map((filter) => filter.key),
        ].forEach((key) => retainedQuery.delete(key));
        router.get(currentPath, Object.fromEntries(retainedQuery.entries()), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <div className="flex flex-col gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div className="relative min-w-0 flex-1 sm:min-w-64">
                <SearchIcon
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    onKeyDown={(event) => event.key === 'Enter' && apply()}
                    placeholder={searchPlaceholder}
                    className="pl-9"
                    aria-label="Search records"
                />
            </div>
            {resolvedCycles.length > 0 && (
                <div className="min-w-56">
                    <SearchableSelect
                        id="workspace-cycle"
                        name="cycle_id"
                        label="Assessment cycle"
                        options={resolvedCycles}
                        optional
                        value={cycleId}
                        onValueChange={setCycleId}
                    />
                </div>
            )}
            {selectFilters.map((filter) => (
                <div key={filter.key} className="min-w-52">
                    <SearchableSelect
                        id={`workspace-filter-${filter.key}`}
                        name={filter.key}
                        label={filter.label}
                        options={filter.options}
                        optional
                        value={selectValues[filter.key] ?? ''}
                        onValueChange={(value) =>
                            setSelectValues((current) => ({
                                ...current,
                                [filter.key]: value,
                            }))
                        }
                    />
                </div>
            ))}
            <div className="flex flex-wrap items-center gap-2">
                <Popover>
                    <PopoverTrigger asChild>
                        <Button
                            variant="outline"
                            className="justify-start font-normal"
                        >
                            <CalendarIcon data-icon="inline-start" />
                            {range?.from
                                ? `${format(range.from, 'dd MMM yyyy')}${range.to ? ` – ${format(range.to, 'dd MMM yyyy')}` : ''}`
                                : 'Filter by date'}
                        </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-auto p-0" align="end">
                        <Calendar
                            mode="range"
                            selected={range}
                            onSelect={setRange}
                            numberOfMonths={2}
                        />
                    </PopoverContent>
                </Popover>
                <Button onClick={apply}>Apply filters</Button>
                {(initialFrom ||
                    initialTo ||
                    initialSearch ||
                    initialCycleId ||
                    selectFilters.some((filter) => filter.value)) && (
                    <Button
                        variant="ghost"
                        onClick={clear}
                        aria-label="Clear filters"
                    >
                        <XIcon />
                    </Button>
                )}
            </div>
        </div>
    );
}
