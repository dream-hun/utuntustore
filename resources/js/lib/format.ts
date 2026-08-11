/**
 * Formatting helpers shared across every area of the marketplace.
 */

/**
 * Format a whole-franc amount for display, e.g. 320000 -> "320,000 FRW".
 *
 * Amounts arrive from the backend as whole Rwandan Francs. RWF has no minor unit
 * in daily use, so there is nothing to divide by 100 — doing so would silently
 * show every price at one hundredth of its value.
 */
export function formatMoney(amount: number, currency = 'RWF'): string {
    const suffix = currency === 'RWF' ? 'FRW' : currency;

    return `${new Intl.NumberFormat('en-RW').format(amount)} ${suffix}`;
}

/**
 * Format a date for display in the marketplace's locale.
 */
export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

/**
 * A human phrase for how long until (or since) a date, used for subscription expiry.
 */
export function formatRelativeDays(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const target = new Date(value).getTime();
    const days = Math.round((target - Date.now()) / 86_400_000);

    if (days === 0) {
        return 'today';
    }

    if (days > 0) {
        return `in ${days} day${days === 1 ? '' : 's'}`;
    }

    const past = Math.abs(days);

    return `${past} day${past === 1 ? '' : 's'} ago`;
}

/**
 * Build a delivery estimate label from a vendor's coverage row.
 */
export function formatDeliveryEstimate(
    min: number | null,
    max: number | null,
): string | null {
    if (min === null || max === null) {
        return null;
    }

    if (min === max) {
        return `${min} day${min === 1 ? '' : 's'}`;
    }

    return `${min}–${max} days`;
}
