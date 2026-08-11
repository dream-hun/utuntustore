import { Star } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * A read-only 1–5 star rating.
 */
export function RatingStars({
    rating,
    className,
}: {
    rating: number;
    className?: string;
}) {
    return (
        <span
            className={cn('flex items-center gap-0.5', className)}
            aria-label={`${rating} out of 5`}
        >
            {[1, 2, 3, 4, 5].map((value) => (
                <Star
                    key={value}
                    aria-hidden
                    className={cn(
                        'size-4',
                        value <= rating
                            ? 'fill-amber-400 text-amber-400'
                            : 'text-muted-foreground/40',
                    )}
                />
            ))}
        </span>
    );
}

/**
 * The star picker used inside the review modal.
 *
 * Rendered as real radio inputs so it is reachable by keyboard and readable by a
 * screen reader, with the stars drawn on top.
 */
export function RatingInput({
    value,
    onChange,
    disabled = false,
}: {
    value: number;
    onChange: (rating: number) => void;
    disabled?: boolean;
}) {
    return (
        <div
            role="radiogroup"
            aria-label="Rating"
            className="flex items-center gap-1"
        >
            {[1, 2, 3, 4, 5].map((rating) => (
                <button
                    key={rating}
                    type="button"
                    role="radio"
                    aria-checked={value === rating}
                    aria-label={`${rating} star${rating === 1 ? '' : 's'}`}
                    disabled={disabled}
                    onClick={() => onChange(rating)}
                    className="rounded p-0.5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                >
                    <Star
                        className={cn(
                            'size-7 transition-colors',
                            rating <= value
                                ? 'fill-amber-400 text-amber-400'
                                : 'text-muted-foreground/40 hover:text-amber-400',
                        )}
                    />
                </button>
            ))}
        </div>
    );
}
