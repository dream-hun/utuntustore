import { Link } from '@inertiajs/react';
import {
    FolderTree,
    LayoutDashboard,
    ReceiptText,
    ShoppingBag,
    SlidersHorizontal,
    Store,
    Users,
} from 'lucide-react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import admin from '@/routes/admin';
import type { NavItem } from '@/types';

/**
 * The admin area's own sidebar group.
 *
 * Subscriptions sits high in the list on purpose: it is the platform's only source of
 * income, so it is the screen an operator returns to most.
 */
const adminNavItems: NavItem[] = [
    { title: 'Overview', href: admin.dashboard(), icon: LayoutDashboard },
    { title: 'Vendors', href: admin.vendors.index(), icon: Store },
    {
        title: 'Subscriptions',
        href: admin.subscriptions.index(),
        icon: ReceiptText,
    },
    { title: 'Categories', href: admin.categories.index(), icon: FolderTree },
    { title: 'Orders', href: admin.orders.index(), icon: ShoppingBag },
    { title: 'Customers', href: admin.customers.index(), icon: Users },
    { title: 'Settings', href: admin.settings.edit(), icon: SlidersHorizontal },
];

export function AdminNav() {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Administration</SidebarGroupLabel>
            <SidebarMenu>
                {adminNavItems.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentUrl(item.href)}
                            tooltip={{ children: item.title }}
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
