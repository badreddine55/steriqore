import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import cycles from '@/routes/cycles';

/**
 * A single, honest entry point today: cycle number lookup, since Cycles
 * is the only screen that exists yet. The full multi-criteria evidence
 * search (patient ref / cycle / batch / device / period / operator) is
 * its own dedicated screen later in the build order (S7 item 5) — this
 * isn't a preview of that, just the header search this milestone can
 * actually back with a real query.
 */
export function GlobalSearch() {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (event.key === 'k' && (event.metaKey || event.ctrlKey)) {
                event.preventDefault();
                setOpen((value) => !value);
            }
        }

        document.addEventListener('keydown', onKeyDown);

        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);

    function runSearch() {
        if (!query.trim()) {
            return;
        }

        setOpen(false);
        router.visit(
            cycles.index({ query: { filter: { q: query.trim() } } }).url,
        );
    }

    return (
        <>
            <Button
                variant="outline"
                className="h-8 w-full max-w-64 justify-start gap-2 px-3 font-normal text-muted-foreground sm:w-56"
                onClick={() => setOpen(true)}
            >
                <Search className="size-4" />
                <span className="hidden sm:inline">Search cycle number…</span>
                <span className="ml-auto hidden text-xs sm:inline">⌘K</span>
            </Button>
            <CommandDialog
                open={open}
                onOpenChange={setOpen}
                title="Search"
                description="Search by cycle number"
            >
                <CommandInput
                    placeholder="Cycle number…"
                    value={query}
                    onValueChange={setQuery}
                />
                <CommandList>
                    <CommandEmpty>
                        Type a cycle number, then press Enter.
                    </CommandEmpty>
                    {query.trim() && (
                        <CommandGroup heading="Cycles">
                            <CommandItem onSelect={runSearch}>
                                <Search />
                                Search cycles for &ldquo;{query.trim()}&rdquo;
                            </CommandItem>
                        </CommandGroup>
                    )}
                </CommandList>
            </CommandDialog>
        </>
    );
}
