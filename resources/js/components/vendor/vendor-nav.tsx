import { Link, usePage } from '@inertiajs/react';
import {
    Boxes,
    CreditCard,
    LayoutDashboard,
    Package,
    Receipt,
    Store,
    Truck,
} from 'lucide-react';
import { cn } from '@/lib/utils';

const items = [
    { href: '/vendor', label: 'Dashboard', icon: LayoutDashboard },
    { href: '/vendor/orders', label: 'Orders', icon: Receipt },
    { href: '/vendor/products', label: 'Products', icon: Package },
    { href: '/vendor/inventory', label: 'Inventory', icon: Boxes },
    { href: '/vendor/delivery', label: 'Delivery areas', icon: Truck },
    { href: '/vendor/sales', label: 'Sales', icon: Receipt },
    { href: '/vendor/shop', label: 'Shop profile', icon: Store },
    { href: '/vendor/subscription', label: 'Subscription', icon: CreditCard },
];

/**
 * Vendor area navigation.
 *
 * Every entry stays available when a subscription lapses — an expired vendor loses
 * selling rights, not access to their own orders, history and settings.
 */
export function VendorNav() {
    const url = usePage().url;

    return (
        <nav className="flex gap-1 overflow-x-auto pb-2">
            {items.map((item) => {
                const active =
                    item.href === '/vendor'
                        ? url === '/vendor'
                        : url.startsWith(item.href);

                return (
                    <Link
                        key={item.href}
                        href={item.href}
                        className={cn(
                            'flex shrink-0 items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors',
                            active
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                        )}
                    >
                        <item.icon className="size-4" />
                        {item.label}
                    </Link>
                );
            })}
        </nav>
    );
}
