import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { formatRelativeDays } from '@/lib/format';

/**
 * Warns a vendor whose selling rights are lapsing or have lapsed.
 *
 * Deliberately reassuring about the catalog: expiry hides a shop, it never deletes
 * anything, and a vendor who thinks their products are gone is a vendor who does not
 * come back.
 */
export function SubscriptionBanner() {
    const vendor = usePage().props.auth?.vendor;

    if (!vendor || vendor.subscription_status === 'active') {
        return null;
    }

    const expired = !vendor.can_sell;

    return (
        <Alert
            className={
                expired
                    ? 'border-destructive/30 bg-destructive/5'
                    : 'border-amber-500/30 bg-amber-500/5'
            }
        >
            <AlertTriangle
                className={
                    expired
                        ? 'size-4 text-destructive'
                        : 'size-4 text-amber-600'
                }
            />
            <AlertDescription className="flex flex-wrap items-center justify-between gap-3">
                <span>
                    {expired
                        ? 'Your subscription has expired, so your shop is hidden from the storefront. Your products and history are safe — paying again restores everything.'
                        : `Your subscription ends ${formatRelativeDays(vendor.subscription_ends_at)}. Renew to keep selling.`}
                </span>
                <Button
                    asChild
                    size="sm"
                    variant={expired ? 'destructive' : 'outline'}
                >
                    <Link href="/vendor/subscription">View subscription</Link>
                </Button>
            </AlertDescription>
        </Alert>
    );
}
