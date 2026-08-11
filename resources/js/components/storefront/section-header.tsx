import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { cn } from '@/lib/utils';

interface SectionHeaderProps {
    title: string;
    description?: string;
    viewAllHref?: string;
    viewAllLabel?: string;
    className?: string;
    as?: 'h1' | 'h2' | 'h3';
    /** id for aria-labelledby on the parent section */
    id?: string;
}

/**
 * Consistent section heading with an optional "View all →" link.
 * Used in all home page sections and list pages to keep vertical rhythm
 * identical across the storefront.
 */
export function SectionHeader({
    title,
    description,
    viewAllHref,
    viewAllLabel = 'View all',
    className,
    as: Tag = 'h2',
    id,
}: SectionHeaderProps) {
    return (
        <div className={cn('flex items-end justify-between gap-4', className)}>
            <div className="min-w-0">
                <Tag
                    id={id}
                    className="text-2xl font-semibold tracking-[-0.035em] text-foreground sm:text-3xl"
                >
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
                    className="inline-flex shrink-0 items-center gap-1 rounded-full bg-primary/8 px-3 py-1.5 text-sm font-semibold text-primary transition-colors hover:bg-primary/14 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    {viewAllLabel}
                    <ArrowRight className="size-3.5" aria-hidden="true" />
                </Link>
            ) : null}
        </div>
    );
}
