import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

/**
 * One headline number on an admin screen.
 *
 * `StatCardSkeleton` is the fallback every deferred stat uses, so the dashboard keeps
 * its shape while the aggregates land instead of jumping as each one arrives.
 */
export function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    emphasis = false,
    className,
}: {
    label: string;
    value: React.ReactNode;
    hint?: React.ReactNode;
    icon?: LucideIcon;
    emphasis?: boolean;
    className?: string;
}) {
    return (
        <Card className={cn(emphasis && 'border-primary/40', className)}>
            <CardContent className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {label}
                    </p>
                    <p
                        className={cn(
                            'mt-2 truncate text-2xl font-semibold tabular-nums',
                            emphasis && 'text-primary',
                        )}
                    >
                        {value}
                    </p>
                    {hint ? (
                        <p className="mt-1 text-xs text-muted-foreground">
                            {hint}
                        </p>
                    ) : null}
                </div>

                {Icon ? (
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted">
                        <Icon className="size-4 text-muted-foreground" />
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}

export function StatCardSkeleton() {
    return (
        <Card>
            <CardContent className="flex items-start justify-between gap-3">
                <div className="w-full">
                    <Skeleton className="h-3 w-24" />
                    <Skeleton className="mt-3 h-7 w-28" />
                    <Skeleton className="mt-2 h-3 w-20" />
                </div>
                <Skeleton className="size-9 rounded-lg" />
            </CardContent>
        </Card>
    );
}

/**
 * Fallback for a deferred list panel.
 */
export function ListSkeleton({ rows = 4 }: { rows?: number }) {
    return (
        <div className="flex flex-col gap-3">
            {Array.from({ length: rows }, (_, index) => (
                <div
                    key={index}
                    className="flex items-center justify-between gap-3"
                >
                    <div className="w-full">
                        <Skeleton className="h-4 w-40" />
                        <Skeleton className="mt-2 h-3 w-24" />
                    </div>
                    <Skeleton className="h-6 w-20 shrink-0 rounded-full" />
                </div>
            ))}
        </div>
    );
}
