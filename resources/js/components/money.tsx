import { cn } from '@/lib/utils';
import { formatMoney } from '@/lib/format';

/**
 * Renders a whole-franc amount.
 *
 * Always use this rather than formatting inline, so a future second currency only
 * has to be handled in one place.
 */
export function Money({
    amount,
    currency = 'RWF',
    className,
}: {
    amount: number;
    currency?: string;
    className?: string;
}) {
    return (
        <span className={cn('tabular-nums', className)}>
            {formatMoney(amount, currency)}
        </span>
    );
}
