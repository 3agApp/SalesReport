import { Link, usePage } from '@inertiajs/react';
import { ChartColumn, LayoutGrid, Mail, Settings2, Store } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { OrganizationSwitcher } from '@/components/organization-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, onboarding } from '@/routes';
import { index as invitationsIndex } from '@/routes/invitations';
import { edit as editOrganization } from '@/routes/organizations';
import { index as reportsIndex } from '@/routes/reports';
import { index as shopsIndex } from '@/routes/shops';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { currentOrganization, pendingInvitationsCount } = usePage().props;
    const dashboardUrl = currentOrganization
        ? dashboard(currentOrganization.slug)
        : onboarding();

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
    ];

    if (currentOrganization) {
        mainNavItems.push(
            {
                title: 'Reports',
                href: reportsIndex(currentOrganization.slug),
                icon: ChartColumn,
            },
            {
                title: 'Shops',
                href: shopsIndex(currentOrganization.slug),
                icon: Store,
            },
            {
                title: 'Organization settings',
                href: editOrganization(currentOrganization.slug),
                icon: Settings2,
            },
        );
    }

    if (pendingInvitationsCount > 0) {
        mainNavItems.push({
            title: 'Invitations',
            href: invitationsIndex(),
            icon: Mail,
            badge: pendingInvitationsCount,
        });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <OrganizationSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
