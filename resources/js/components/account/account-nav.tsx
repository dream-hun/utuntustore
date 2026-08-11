import { Link } from '@inertiajs/react';
import { Heart, Home, MapPin, Package, Star } from 'lucide-react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import account from '@/routes/account';

/**
 * Section nav for the customer account.
 *
 * It scrolls horizontally on a phone rather than wrapping onto three lines, since
 * this sits above the content on every account screen.
 */
export function AccountNav() {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    const items = [
        {
            title: 'Overview',
            href: account.index.url(),
            icon: Home,
            exact: true,
        },
        {
            title: 'Orders',
            href: account.orders.index.url(),
            icon: Package,
            exact: false,
        },
        {
            title: 'Addresses',
            href: account.addresses.index.url(),
            icon: MapPin,
            exact: false,
        },
        {
            title: 'Reviews',
            href: account.reviews.index.url(),
            icon: Star,
            exact: false,
        },
        {
            title: 'Wishlist',
            href: account.wishlist.index.url(),
            icon: Heart,
            exact: false,
        },
    ];

    return (
        <nav
            aria-label="Account sections"
            className="-mx-4 overflow-x-auto px-4 pb-1"
        >
            <ul className="flex min-w-max items-center gap-1">
                {items.map((item) => {
                    const active = item.exact
                        ? isCurrentUrl(item.href)
                        : isCurrentOrParentUrl(item.href);

                    return (
                        <li key={item.title}>
                            <Link
                                href={item.href}
                                prefetch
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                    active
                                        ? 'bg-muted text-foreground'
                                        : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                                )}
                            >
                                <item.icon className="size-4" />
                                {item.title}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
