import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type {
    OrderStatus,
    ProductStatus,
    SubscriptionStatus,
    VendorStatus,
} from '@/types/marketplace';

type Tone = 'neutral' | 'progress' | 'success' | 'warning' | 'danger';

const toneClasses: Record<Tone, string> = {
    neutral: 'bg-muted text-muted-foreground border-transparent',
    progress:
        'bg-blue-500/10 text-blue-700 border-blue-500/20 dark:text-blue-300',
    success:
        'bg-emerald-500/10 text-emerald-700 border-emerald-500/20 dark:text-emerald-300',
    warning:
        'bg-amber-500/10 text-amber-700 border-amber-500/20 dark:text-amber-300',
    danger: 'bg-red-500/10 text-red-700 border-red-500/20 dark:text-red-300',
};

const orderTones: Record<OrderStatus, Tone> = {
    pending: 'neutral',
    confirmed: 'progress',
    processing: 'progress',
    shipped: 'progress',
    delivered: 'success',
    cancelled: 'danger',
};

const orderLabels: Record<OrderStatus, string> = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    processing: 'Processing',
    shipped: 'Shipped',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
};

const vendorTones: Record<VendorStatus, Tone> = {
    pending: 'warning',
    approved: 'success',
    rejected: 'danger',
    suspended: 'danger',
};

const vendorLabels: Record<VendorStatus, string> = {
    pending: 'Pending review',
    approved: 'Approved',
    rejected: 'Rejected',
    suspended: 'Suspended',
};

const subscriptionTones: Record<SubscriptionStatus, Tone> = {
    none: 'neutral',
    active: 'success',
    grace: 'warning',
    expired: 'danger',
};

const subscriptionLabels: Record<SubscriptionStatus, string> = {
    none: 'No subscription',
    active: 'Active',
    grace: 'Grace period',
    expired: 'Expired',
};

const productTones: Record<ProductStatus, Tone> = {
    draft: 'neutral',
    published: 'success',
    archived: 'neutral',
};

const productLabels: Record<ProductStatus, string> = {
    draft: 'Draft',
    published: 'Published',
    archived: 'Archived',
};

function StatusPill({
    tone,
    label,
    className,
}: {
    tone: Tone;
    label: string;
    className?: string;
}) {
    return (
        <Badge
            variant="outline"
            className={cn(toneClasses[tone], 'font-medium', className)}
        >
            {label}
        </Badge>
    );
}

export function OrderStatusBadge({
    status,
    className,
}: {
    status: OrderStatus;
    className?: string;
}) {
    return (
        <StatusPill
            tone={orderTones[status]}
            label={orderLabels[status]}
            className={className}
        />
    );
}

export function VendorStatusBadge({
    status,
    className,
}: {
    status: VendorStatus;
    className?: string;
}) {
    return (
        <StatusPill
            tone={vendorTones[status]}
            label={vendorLabels[status]}
            className={className}
        />
    );
}

/**
 * A vendor in grace has lapsed but can still sell, which is why it reads as a
 * warning rather than a failure.
 */
export function SubscriptionStatusBadge({
    status,
    className,
}: {
    status: SubscriptionStatus;
    className?: string;
}) {
    return (
        <StatusPill
            tone={subscriptionTones[status]}
            label={subscriptionLabels[status]}
            className={className}
        />
    );
}

export function ProductStatusBadge({
    status,
    className,
}: {
    status: ProductStatus;
    className?: string;
}) {
    return (
        <StatusPill
            tone={productTones[status]}
            label={productLabels[status]}
            className={className}
        />
    );
}
