import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { navigationFor } from '@/components/nav-items';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';

/**
 * The signed-in sidebar, carrying whichever area menu the current role belongs to.
 *
 * `dashboard()` is a redirect that routes each role to its own landing screen, so the
 * logo works for all three without knowing which one that is.
 */
export function AppSidebar() {
    const { auth } = usePage().props;
    const section = navigationFor(auth?.user?.role);

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain label={section.label} items={section.items} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
