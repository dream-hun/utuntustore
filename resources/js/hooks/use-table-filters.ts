import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type FilterValues = Record<string, string | null>;

/**
 * Drives the filter toolbar above a `DataTable`.
 *
 * Every index page had rewritten this by hand, and each copy re-derived the same
 * four decisions: keep component state across the visit, keep scroll position,
 * replace the history entry instead of stacking one per keystroke, and drop empty
 * values so the URL stays readable.
 *
 * Two behaviours worth knowing:
 *
 * - Text filters are debounced; `immediate` skips it. A select has no in-between
 *   states so it should commit at once, while a search box would otherwise fire a
 *   request per character.
 * - Only the filter keys are sent, so `page` falls away on every change. That is
 *   deliberate: filtering from page 5 down to three results must not leave the
 *   reader on a page that no longer exists.
 */
export function useTableFilters<T extends FilterValues>({
    url,
    filters,
    debounceMs = 300,
    only,
}: {
    url: string;
    /** The filter state as the server currently understands it. */
    filters: T;
    debounceMs?: number;
    /** Partial-reload keys, when the page has props the filters cannot affect. */
    only?: string[];
}) {
    const [values, setValues] = useState<T>(filters);
    const [processing, setProcessing] = useState(false);

    const latest = useRef<T>(filters);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    // `only` is nearly always an inline array literal, so its identity changes
    // every render. Depending on the joined string instead keeps `visit` stable.
    const onlyKey = only?.join(',') ?? '';

    const serverKey = JSON.stringify(filters);
    const appliedKey = useRef(serverKey);

    /**
     * Adopt filter state that arrived from the server rather than from this
     * component — a back-button navigation, or a redirect that cleared a filter.
     */
    useEffect(() => {
        if (appliedKey.current === serverKey) {
            return;
        }

        appliedKey.current = serverKey;

        const next = JSON.parse(serverKey) as T;

        latest.current = next;
        setValues(next);
    }, [serverKey]);

    useEffect(
        () => () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    const visit = useCallback(
        (next: T) => {
            const query: Record<string, string> = {};

            for (const [key, value] of Object.entries(next)) {
                if (value !== null && value !== '') {
                    query[key] = value;
                }
            }

            router.get(url, query, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: onlyKey === '' ? undefined : onlyKey.split(','),
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            });
        },
        [url, onlyKey],
    );

    const schedule = useCallback(
        (next: T, immediate: boolean) => {
            if (timer.current) {
                clearTimeout(timer.current);
                timer.current = null;
            }

            if (immediate || debounceMs === 0) {
                visit(next);

                return;
            }

            timer.current = setTimeout(() => visit(next), debounceMs);
        },
        [debounceMs, visit],
    );

    /** Update one filter. Debounced unless `immediate` is set. */
    const set = useCallback(
        (key: keyof T, value: string | null, immediate = false) => {
            const next = { ...latest.current, [key]: value };

            latest.current = next;
            setValues(next);
            schedule(next, immediate);
        },
        [schedule],
    );

    /** Flush any pending debounce now — for an explicit submit or Enter key. */
    const commit = useCallback(() => {
        schedule(latest.current, true);
    }, [schedule]);

    /** Reset every filter and reload. */
    const clear = useCallback(() => {
        const next = Object.fromEntries(
            Object.keys(latest.current).map((key) => [key, null]),
        ) as T;

        latest.current = next;
        setValues(next);
        schedule(next, true);
    }, [schedule]);

    const isFiltered = Object.values(values).some(
        (value) => value !== null && value !== '',
    );

    return { values, set, commit, clear, isFiltered, processing };
}
