import {
    Boxes,
    CreditCard,
    FolderTree,
    Heart,
    Home,
    LayoutDashboard,
    MapPin,
    Package,
    Receipt,
    ReceiptText,
    ShoppingBag,
    SlidersHorizontal,
    Star,
    Store,
    Truck,
    Users,
} from 'lucide-react';
import account from '@/routes/account';
import admin from '@/routes/admin';
import vendor from '@/routes/vendor';
import type { UserRole } from '@/types/marketplace';
import type { NavItem } from '@/types/navigation';

/**
 * The signed-in navigation for each area, rendered in the sidebar.
 *
 * There is one menu on screen, not two: these used to be duplicated as a horizontal
 * bar inside every page body as well. `exact` marks the entries whose href is a
 * prefix of every sibling — without it an area's landing page stays highlighted on
 * all of its children.
 */
export type NavSection = {
    label: string;
    items: (NavItem & { exact?: boolean })[];
};

/**
 * Subscriptions sits high in the list on purpose: it is the platform's only source of
 * income, so it is the screen an operator returns to most.
 */
const administration: NavSection = {
    label: 'Administration',
    items: [
        {
            title: 'Overview',
            href: admin.dashboard.url(),
            icon: LayoutDashboard,
            exact: true,
        },
        { title: 'Vendors', href: admin.vendors.index.url(), icon: Store },
        {
            title: 'Subscriptions',
            href: admin.subscriptions.index.url(),
            icon: ReceiptText,
        },
        {
            title: 'Categories',
            href: admin.categories.index.url(),
            icon: FolderTree,
        },
        { title: 'Orders', href: admin.orders.index.url(), icon: ShoppingBag },
        { title: 'Customers', href: admin.customers.index.url(), icon: Users },
        {
            title: 'Settings',
            href: admin.settings.edit.url(),
            icon: SlidersHorizontal,
        },
    ],
};

/**
 * Every entry stays available when a subscription lapses — an expired vendor loses
 * selling rights, not access to their own orders, history and settings.
 */
const shop: NavSection = {
    label: 'Shop',
    items: [
        {
            title: 'Dashboard',
            href: vendor.dashboard.url(),
            icon: LayoutDashboard,
            exact: true,
        },
        { title: 'Orders', href: vendor.orders.index.url(), icon: Receipt },
        { title: 'Products', href: vendor.products.index.url(), icon: Package },
        { title: 'Inventory', href: vendor.inventory.index.url(), icon: Boxes },
        {
            title: 'Delivery areas',
            href: vendor.delivery.index.url(),
            icon: Truck,
        },
        { title: 'Sales', href: vendor.sales.index.url(), icon: Receipt },
        { title: 'Shop profile', href: vendor.shop.edit.url(), icon: Store },
        {
            title: 'Subscription',
            href: vendor.subscription.index.url(),
            icon: CreditCard,
        },
    ],
};

const myAccount: NavSection = {
    label: 'My account',
    items: [
        {
            title: 'Overview',
            href: account.index.url(),
            icon: Home,
            exact: true,
        },
        { title: 'Orders', href: account.orders.index.url(), icon: Package },
        {
            title: 'Addresses',
            href: account.addresses.index.url(),
            icon: MapPin,
        },
        { title: 'Reviews', href: account.reviews.index.url(), icon: Star },
        { title: 'Wishlist', href: account.wishlist.index.url(), icon: Heart },
    ],
};

export function navigationFor(role: UserRole | undefined): NavSection {
    if (role === 'admin') {
        return administration;
    }

    if (role === 'vendor') {
        return shop;
    }

    return myAccount;
}
