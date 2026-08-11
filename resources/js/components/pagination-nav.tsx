import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/marketplace';

/**
 * Pagination controls for a Laravel length-aware paginator.
 *
 * Uses Inertia `Link` with `preserveScroll` so paging a table does not throw the
 * reader back to the top of the page.
 */
export function PaginationNav<T>({
    paginator,
    className,
}: {
    paginator: Paginated<T>;
    className?: string;
}) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <nav
            className={cn(
                'flex items-center justify-between gap-4 pt-4',
                className,
            )}
            aria-label="Pagination"
        >
            <p className="text-sm text-muted-foreground">
                Showing{' '}
                <span className="font-medium">{paginator.from ?? 0}</span>
                {' – '}
                <span className="font-medium">{paginator.to ?? 0}</span> of{' '}
                <span className="font-medium">{paginator.total}</span>
            </p>

            <div className="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    asChild={Boolean(paginator.prev_page_url)}
                    disabled={!paginator.prev_page_url}
                >
                    {paginator.prev_page_url ? (
                        <Link
                            href={paginator.prev_page_url}
                            preserveScroll
                            preserveState
                        >
                            <ChevronLeft className="size-4" />
                            Previous
                        </Link>
                    ) : (
                        <span>
                            <ChevronLeft className="size-4" />
                            Previous
                        </span>
                    )}
                </Button>

                <span className="text-sm text-muted-foreground">
                    Page {paginator.current_page} of {paginator.last_page}
                </span>

                <Button
                    variant="outline"
                    size="sm"
                    asChild={Boolean(paginator.next_page_url)}
                    disabled={!paginator.next_page_url}
                >
                    {paginator.next_page_url ? (
                        <Link
                            href={paginator.next_page_url}
                            preserveScroll
                            preserveState
                        >
                            Next
                            <ChevronRight className="size-4" />
                        </Link>
                    ) : (
                        <span>
                            Next
                            <ChevronRight className="size-4" />
                        </span>
                    )}
                </Button>
            </div>
        </nav>
    );
}
