import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

interface SectionHeaderProps {
    title: string;
    /** Small tracked label above the title, e.g. "Fresh in". */
    eyebrow?: string;
    description?: string;
    viewAllHref?: string;
    viewAllLabel?: string;
    className?: string;
    as?: 'h1' | 'h2' | 'h3';
    /** id for aria-labelledby on the parent section */
    id?: string;
}

/**
 * Consistent section heading with an optional eyebrow and "See all" link.
 * Used in all home page sections and list pages to keep vertical rhythm
 * identical across the storefront.
 */
export function SectionHeader({
    title,
    eyebrow,
    description,
    viewAllHref,
    viewAllLabel = 'See all',
    className,
    as: Tag = 'h2',
    id,
}: SectionHeaderProps) {
    return (
        <div
            className={cn(
                'grid grid-cols-[minmax(0,1fr)_auto] items-end gap-4',
                className,
            )}
        >
            <div className="min-w-0">
                {eyebrow ? (
                    <span className="eyebrow text-primary">{eyebrow}</span>
                ) : null}

                <Tag id={id} className="mt-1 text-2xl sm:text-3xl">
                    {title}
                </Tag>

                {description ? (
                    <p className="mt-1.5 text-sm leading-6 text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>

            {viewAllHref ? (
                <Link
                    href={viewAllHref}
                    className="shrink-0 rounded text-sm font-semibold text-primary transition-opacity hover:opacity-80 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {viewAllLabel}
                </Link>
            ) : null}
        </div>
    );
}
