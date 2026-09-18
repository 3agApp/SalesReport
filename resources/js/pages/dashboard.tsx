import { Head, Link, usePage } from '@inertiajs/react';
import { ExternalLink, Store } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { edit as editOrganization } from '@/routes/organizations';
import { index as shopsIndex } from '@/routes/shops';
import ShopConnectionBadge from '@/components/shop-connection-badge';
import { cn } from '@/lib/utils';
import type { OrganizationPermissions, ShopConnection } from '@/types';

type RecentShop = {
    id: number;
    name: string;
    url: string;
    host: string;
    platformLabel: string;
    updatedAtDiff: string | null;
    connection: ShopConnection;
};

type Props = {
    stats: {
        shops: number;
        members: number;
        shopsNeedingAttention: number;
    };
    recentShops: RecentShop[];
    permissions: OrganizationPermissions;
};

type Stat = {
    label: string;
    value: number;
    href: string;
    testId: string;
    alert?: boolean;
};

/**
 * One number and where to go to act on it. Every tile is a link, because a
 * count you cannot click through to is a dead end.
 */
function StatTile({ stat }: { stat: Stat }) {
    return (
        <Link
            href={stat.href}
            data-test={stat.testId}
            className={cn(
                'workspace-panel hover:border-primary/40 flex flex-col gap-2 px-6 py-5 transition-colors',
                stat.alert && 'border-red-600/30 dark:border-red-400/30',
            )}
        >
            <span className="text-muted-foreground text-xs font-medium tracking-[0.16em] uppercase">
                {stat.label}
            </span>
            <span
                className={cn(
                    'text-3xl font-semibold tabular-nums',
                    stat.alert && 'text-red-700 dark:text-red-400',
                )}
            >
                {stat.value.toLocaleString()}
            </span>
        </Link>
    );
}

export default function Dashboard({ stats, recentShops, permissions }: Props) {
    const { currentOrganization } = usePage().props;

    if (!currentOrganization) {
        return null;
    }

    const organizationSlug = currentOrganization.slug;

    const tiles: Stat[] = [
        {
            label: 'Connected shops',
            value: stats.shops,
            href: shopsIndex(organizationSlug).url,
            testId: 'dashboard-shops',
        },
        {
            label: 'Members',
            value: stats.members,
            href: editOrganization(organizationSlug).url,
            testId: 'dashboard-members',
        },
        {
            label: 'Needs attention',
            value: stats.shopsNeedingAttention,
            href: shopsIndex(organizationSlug).url,
            testId: 'dashboard-shop-issues',
            alert: stats.shopsNeedingAttention > 0,
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <div className="workspace-page">
                <div className="page-heading">
                    <p className="text-muted-foreground text-xs font-medium tracking-[0.16em] uppercase">
                        {currentOrganization.name}
                    </p>
                    <h1 className="page-title">Dashboard</h1>
                    <p className="text-muted-foreground text-sm">
                        Where {currentOrganization.name} stands today.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {tiles.map((stat) => (
                        <StatTile key={stat.label} stat={stat} />
                    ))}
                </div>

                {recentShops.length > 0 ? (
                    <div className="workspace-panel">
                        <div className="flex flex-wrap items-center justify-between gap-2 border-b px-6 py-4">
                            <h2 className="font-medium">
                                Recently updated shops
                            </h2>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={shopsIndex(organizationSlug)}>
                                    All shops
                                </Link>
                            </Button>
                        </div>

                        <ul className="divide-y">
                            {recentShops.map((shop) => (
                                <li
                                    key={shop.id}
                                    data-test="recent-shop"
                                    className="flex items-center gap-3 px-6 py-4"
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
                                    <ShopConnectionBadge
                                        connection={shop.connection}
                                    />
                                    <div className="text-muted-foreground hidden text-xs sm:block">
                                        {shop.updatedAtDiff}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : (
                    <div className="workspace-panel flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                        <div className="bg-muted flex size-12 items-center justify-center rounded-full">
                            <Store className="text-muted-foreground size-6" />
                        </div>
                        <div className="space-y-1">
                            <h2 className="font-medium">No shops yet</h2>
                            <p className="text-muted-foreground max-w-md text-sm leading-relaxed">
                                {permissions.canCreateShop
                                    ? 'Connect your first WooCommerce store to start reporting on sales.'
                                    : 'Shops connected to this organization will show up here.'}
                            </p>
                        </div>
                        {permissions.canCreateShop ? (
                            <Button size="sm" asChild>
                                <Link href={shopsIndex(organizationSlug)}>
                                    Add a shop
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                )}
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
