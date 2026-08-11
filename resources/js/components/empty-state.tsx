import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * The blank slate shown when a list has nothing in it.
 *
 * An empty state should always say what to do next, not just that there is nothing
 * here — a new vendor's empty product list is the moment they most need direction.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: {
    icon?: LucideIcon;
    title: string;
    description?: string;
    action?: React.ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center rounded-xl border border-dashed px-6 py-16 text-center',
                className,
            )}
        >
            {Icon ? (
                <div className="mb-4 flex size-12 items-center justify-center rounded-full bg-muted">
                    <Icon className="size-6 text-muted-foreground" />
                </div>
            ) : null}

            <h3 className="text-base font-semibold">{title}</h3>

            {description ? (
                <p className="mt-1 max-w-sm text-sm text-muted-foreground">
                    {description}
                </p>
            ) : null}

            {action ? <div className="mt-6">{action}</div> : null}
        </div>
    );
}
