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
 * Pill-style category chip used in category filter areas. The active state
 * fills with the storefront primary so it's clear which filter is applied;
 * the rest sit on a hairline border and warm to aqua on hover.
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
                'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-4 py-1.5 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                isActive
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'border-border bg-card text-foreground hover:border-primary hover:bg-aqua-soft',
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
