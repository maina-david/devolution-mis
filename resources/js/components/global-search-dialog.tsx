import { router, usePage } from '@inertiajs/react';
import { FileTextIcon, LayoutGridIcon, SearchIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import GlobalSearchController from '@/actions/App/Http/Controllers/GlobalSearchController';
import { Button } from '@/components/ui/button';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import { Spinner } from '@/components/ui/spinner';
import { appNavigationGroups } from '@/lib/app-navigation';
import { toUrl } from '@/lib/utils';

type SearchResult = {
    category: string;
    id: string;
    title: string;
    description: string;
    url: string;
};

export function GlobalSearchDialog() {
    const { auth } = usePage().props;
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResult[]>([]);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);
    const pages = useMemo(
        () =>
            appNavigationGroups(auth.user.permissions).flatMap((group) =>
                group.items.map((item) => ({
                    ...item,
                    group: group.title,
                })),
            ),
        [auth.user.permissions],
    );
    const matchingPages = pages.filter((item) =>
        `${item.title} ${item.group}`
            .toLocaleLowerCase()
            .includes(query.toLocaleLowerCase()),
    );
    const resultCategories = [
        ...new Set(results.map((result) => result.category)),
    ];

    useEffect(() => {
        const keydown = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLocaleLowerCase() === 'k'
            ) {
                event.preventDefault();
                setOpen((value) => !value);
            }
        };

        document.addEventListener('keydown', keydown);

        return () => document.removeEventListener('keydown', keydown);
    }, []);

    useEffect(() => {
        if (!open || query.trim().length < 2) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setLoading(true);
            setFailed(false);

            try {
                const response = await fetch(
                    GlobalSearchController.url({
                        query: { q: query.trim() },
                    }),
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error('Search request failed');
                }

                const payload = (await response.json()) as {
                    results: SearchResult[];
                };

                setResults(payload.results);
            } catch (error) {
                if (!(
                    error instanceof DOMException && error.name === 'AbortError'
                )) {
                    setFailed(true);
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [open, query]);

    const visit = (url: string) => {
        setOpen(false);
        setQuery('');
        router.visit(url);
    };

    const changeOpen = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (!nextOpen) {
            setQuery('');
            setResults([]);
            setLoading(false);
            setFailed(false);
        }
    };

    const changeQuery = (value: string) => {
        setQuery(value);

        if (value.trim().length < 2) {
            setResults([]);
            setLoading(false);
            setFailed(false);
        }
    };

    return (
        <>
            <Button
                variant="outline"
                className="h-9 justify-start gap-2 border-primary-foreground/30 bg-background text-foreground hover:bg-background/90 hover:text-foreground sm:w-48 lg:w-64"
                onClick={() => setOpen(true)}
                aria-label="Search IDMIS"
            >
                <SearchIcon data-icon="inline-start" />
                <span className="hidden sm:inline">Search IDMIS…</span>
                <kbd className="ml-auto hidden rounded border bg-muted px-1.5 py-0.5 font-mono text-[10px] font-medium lg:inline">
                    ⌘K
                </kbd>
            </Button>
            <CommandDialog
                open={open}
                onOpenChange={changeOpen}
                title="Search IDMIS"
                description="Search pages and records available within your authorized portfolio."
            >
                <CommandInput
                    value={query}
                    onValueChange={changeQuery}
                    placeholder="Search counties, assessments, documents, projects…"
                    autoFocus
                />
                <CommandList>
                    {loading && (
                        <div className="flex items-center justify-center gap-2 py-10 text-sm text-muted-foreground">
                            <Spinner /> Searching authorized records…
                        </div>
                    )}
                    {failed && (
                        <div className="py-10 text-center text-sm text-destructive">
                            Search is temporarily unavailable. Please try again.
                        </div>
                    )}
                    {!loading &&
                        !failed &&
                        query.length > 0 &&
                        matchingPages.length === 0 &&
                        results.length === 0 && (
                            <CommandEmpty>
                                No authorized pages or records found.
                            </CommandEmpty>
                        )}
                    {!loading && matchingPages.length > 0 && (
                        <CommandGroup heading="Pages & navigation">
                            {matchingPages.map((item) => (
                                <CommandItem
                                    key={`${item.group}-${item.title}`}
                                    value={`${item.group} ${item.title}`}
                                    onSelect={() => visit(toUrl(item.href))}
                                >
                                    <LayoutGridIcon />
                                    <span>{item.title}</span>
                                    <span className="ml-auto text-xs text-muted-foreground">
                                        {item.group}
                                    </span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    )}
                    {!loading &&
                        matchingPages.length > 0 &&
                        results.length > 0 && <CommandSeparator />}
                    {!loading &&
                        resultCategories.map((category) => (
                            <CommandGroup key={category} heading={category}>
                                {results
                                    .filter(
                                        (result) =>
                                            result.category === category,
                                    )
                                    .map((result) => (
                                        <CommandItem
                                            key={`${result.category}-${result.id}`}
                                            value={`${result.category} ${result.title} ${result.description}`}
                                            onSelect={() => visit(result.url)}
                                        >
                                            <FileTextIcon />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-medium">
                                                    {result.title}
                                                </span>
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {result.description}
                                                </span>
                                            </span>
                                        </CommandItem>
                                    ))}
                            </CommandGroup>
                        ))}
                    {!query && (
                        <div className="px-4 py-8 text-center text-sm text-muted-foreground">
                            Type at least two characters to search every record
                            you are authorized to view.
                        </div>
                    )}
                </CommandList>
            </CommandDialog>
        </>
    );
}
