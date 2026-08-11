import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

interface CategoryPillProps {
    name: string;
    href: string;
    isActive?: boolean;
    count?: number;
    className?: string;
}

/**
 * Pill-style category chip used in the horizontal scrollable nav and category
 * filter areas. The active state mirrors the primary button styling so it's
 * clear which filter is applied.
 */
export function CategoryPill({
    name,
    href,
    isActive = false,
    count,
    className,
}: CategoryPillProps) {
    return (
        <Link
            href={href}
            aria-current={isActive ? 'page' : undefined}
            className={cn(
                'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-4 py-1.5 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                isActive
                    ? 'border-primary bg-primary text-primary-foreground hover:bg-primary/90'
                    : 'border-border bg-background text-foreground hover:bg-accent hover:text-accent-foreground',
                className,
            )}
        >
            {name}
            {count !== undefined ? (
                <span
                    className={cn(
                        'rounded-full px-1.5 py-0.5 text-xs tabular-nums',
                        isActive
                            ? 'bg-primary-foreground/20 text-primary-foreground'
                            : 'bg-muted text-muted-foreground',
                    )}
                    aria-label={`${count} products`}
                >
                    {count.toLocaleString()}
                </span>
            ) : null}
        </Link>
    );
}
