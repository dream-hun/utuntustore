import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

interface ProductCardSkeletonProps {
    className?: string;
}

/**
 * Animated placeholder for a product card while data loads.
 * Matches the exact layout of ProductCard so the skeleton-to-content
 * transition is visually seamless.
 */
export function ProductCardSkeleton({ className }: ProductCardSkeletonProps) {
    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border border-border',
                className,
            )}
            aria-hidden="true"
        >
            <Skeleton className="aspect-square w-full rounded-none" />
            <div className="space-y-2 p-3 sm:p-4">
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-3 w-3/4" />
                <Skeleton className="mt-1 h-4 w-1/2" />
            </div>
        </div>
    );
}

/**
 * Grid of skeleton cards for the full product listing state.
 */
export function ProductGridSkeleton({
    count = 8,
    className,
}: {
    count?: number;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 lg:grid-cols-4',
                className,
            )}
            aria-busy="true"
            aria-label="Loading products…"
        >
            {Array.from({ length: count }).map((_, index) => (
                <ProductCardSkeleton key={index} />
            ))}
        </div>
    );
}
