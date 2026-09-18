import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ArrowRight, ExternalLink, Store, Users } from 'lucide-react';
import { useState } from 'react';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as shopsIndex } from '@/routes/shops';
import type { DashboardInvitation, OrganizationPermissions } from '@/types';

type RecentShop = {
    id: number;
    name: string;
    url: string;
    host: string;
    platformLabel: string;
    updatedAtDiff: string | null;
};

type Props = {
    stats: {
        shops: number;
        members: number;
    };
    recentShops: RecentShop[];
    permissions: OrganizationPermissions;
    pendingInvitations?: DashboardInvitation[];
};

type StatCard = {
    label: string;
    value: string;
    hint: string;
    icon: LucideIcon;
};

export default function Dashboard({
    stats,
    recentShops,
    permissions,
    pendingInvitations = [],
}: Props) {
    const { currentOrganization } = usePage().props;
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    if (!currentOrganization) {
        return null;
    }

    const statCards: StatCard[] = [
        {
            label: 'Connected shops',
            value: stats.shops.toLocaleString(),
            hint: 'WooCommerce stores reporting in',
            icon: Store,
        },
        {
            label: 'Members',
            value: stats.members.toLocaleString(),
            hint: `People in ${currentOrganization.name}`,
            icon: Users,
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="space-y-0.5">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Dashboard
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        An overview of {currentOrganization.name}.
                    </p>
                </div>

                <div className="grid auto-rows-min gap-4 sm:grid-cols-2">
                    {statCards.map((card) => (
                        <div
                            key={card.label}
                            data-test="stat-card"
                            className="border-sidebar-border/70 dark:border-sidebar-border rounded-xl border p-4"
                        >
                            <div className="text-muted-foreground flex items-center justify-between text-sm">
                                <span>{card.label}</span>
                                <card.icon className="size-4" />
                            </div>
                            <div className="mt-2 text-2xl font-semibold tracking-tight tabular-nums">
                                {card.value}
                            </div>
                            <p className="text-muted-foreground mt-1 text-xs">
                                {card.hint}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="border-sidebar-border/70 dark:border-sidebar-border flex flex-col rounded-xl border">
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b p-4">
                        <h2 className="font-medium">Recently updated shops</h2>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={shopsIndex(currentOrganization.slug)}>
                                All shops <ArrowRight />
                            </Link>
                        </Button>
                    </div>

                    {recentShops.length > 0 ? (
                        <ul className="divide-y">
                            {recentShops.map((shop) => (
                                <li
                                    key={shop.id}
                                    data-test="recent-shop"
                                    className="flex items-center gap-3 px-4 py-3"
                                >
                                    <div className="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-md">
                                        <Store className="size-4" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="truncate font-medium">
                                            {shop.name}
                                        </div>
                                        <a
                                            href={shop.url}
                                            target="_blank"
                                            rel="noreferrer noopener"
                                            className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-xs"
                                        >
                                            {shop.host}
                                            <ExternalLink className="size-3" />
                                        </a>
                                    </div>
                                    <div className="text-muted-foreground hidden text-xs sm:block">
                                        {shop.updatedAtDiff}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div className="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                            <div className="bg-muted flex size-12 items-center justify-center rounded-full">
                                <Store className="text-muted-foreground size-6" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="font-medium">No shops yet</h3>
                                <p className="text-muted-foreground text-sm">
                                    Connect a WooCommerce store to start
                                    reporting on {currentOrganization.name}{' '}
                                    sales.
                                </p>
                            </div>
                            {permissions.canCreateShop ? (
                                <Button size="sm" asChild>
                                    <Link
                                        href={shopsIndex(
                                            currentOrganization.slug,
                                        )}
                                    >
                                        Add a shop
                                    </Link>
                                </Button>
                            ) : null}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (props: {
    currentOrganization?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentOrganization
                ? dashboard(props.currentOrganization.slug)
                : '/',
        },
    ],
});
