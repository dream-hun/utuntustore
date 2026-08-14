import { Star } from 'lucide-react';
import { cn } from '@/lib/utils';

interface RatingStarsProps {
    rating: number;
    count?: number;
    size?: 'sm' | 'md' | 'lg';
    showCount?: boolean;
    className?: string;
}

const sizeMap = {
    sm: 'size-3',
    md: 'size-4',
    lg: 'size-5',
};

/**
 * Accessible star rating display for the storefront.
 *
 * Renders 5 filled/unfilled stars with an aria-label for screen readers.
 * Count is optional — pass it to render "(123)" next to the stars.
 */
export function RatingStars({
    rating,
    count,
    size = 'md',
    showCount = true,
    className,
}: RatingStarsProps) {
    const rounded = Math.round(rating * 2) / 2; // nearest 0.5

    return (
        <span
            className={cn('inline-flex items-center gap-1', className)}
            aria-label={`${rating.toFixed(1)} out of 5 stars${count !== undefined ? `, ${count} review${count === 1 ? '' : 's'}` : ''}`}
        >
            <span className="flex items-center gap-0.5" aria-hidden="true">
                {[1, 2, 3, 4, 5].map((value) => (
                    <Star
                        key={value}
                        className={cn(
                            sizeMap[size],
                            value <= rounded
                                ? 'fill-gold text-gold'
                                : 'fill-muted text-muted-foreground/30',
                        )}
                    />
                ))}
            </span>

            {showCount && count !== undefined ? (
                <span className="text-xs text-muted-foreground tabular-nums">
                    ({count.toLocaleString()})
                </span>
            ) : null}
        </span>
    );
}
