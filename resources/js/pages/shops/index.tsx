import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    MoreHorizontal,
    Pencil,
    PlugZap,
    Plus,
    RefreshCw,
    Search,
    Store,
    Trash2,
    TriangleAlert,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import DeleteShopModal from '@/components/delete-shop-modal';
import ShopFormModal from '@/components/shop-form-modal';
import StatusBadge, {
    statusToneText,
    StatusTooltip,
} from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import PaginationArrow from '@/components/pagination-arrow';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as shopsIndex } from '@/routes/shops';
import { test as testShopConnection } from '@/routes/shops/connection';
import { store as syncShopOrders } from '@/routes/shops/sync';
import type {
    OrganizationPermissions,
    Paginated,
    Shop,
    ShopFilters,
    ShopPlatformOption,
} from '@/types';

type Props = {
    shops: Paginated<Shop>;
    filters: ShopFilters;
    platforms: ShopPlatformOption[];
    /** The currencies these shops sell in; more than one is a problem. */
    currencies: string[];
    permissions: OrganizationPermissions;
};

export default function ShopsIndex({
    shops,
    filters,
    platforms,
    currencies,
    permissions,
}: Props) {
    const { currentOrganization } = usePage().props;
    const [search, setSearch] = useState(filters.search);
    const [formOpen, setFormOpen] = useState(false);
    const [editingShop, setEditingShop] = useState<Shop | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [shopToDelete, setShopToDelete] = useState<Shop | null>(null);
    const [testingShopId, setTestingShopId] = useState<number | null>(null);
    const debouncedSearch = useDebouncedValue(search);

    const applySearch = (value: string) => {
        if (!currentOrganization) {
            return;
        }

        router.get(
            shopsIndex(currentOrganization.slug).url,
            value ? { search: value } : {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    /**
     * The server value is the source of truth, so a visit only fires when the
     * settled input disagrees with it. That skips the pointless request on
     * mount and converges rather than looping once our own response lands.
     */
    useEffect(() => {
        const term = debouncedSearch.trim();

        if (term === filters.search) {
            return;
        }

        applySearch(term);
    }, [debouncedSearch, filters.search]);

    if (!currentOrganization) {
        return null;
    }

    const hasFilters = filters.search !== '';
    const canManageShops =
        permissions.canUpdateShop || permissions.canDeleteShop;

    const clearFilters = () => {
        setSearch('');
        applySearch('');
    };

    const openCreateForm = () => {
        setEditingShop(null);
        setFormOpen(true);
    };

    const openEditForm = (shop: Shop) => {
        setEditingShop(shop);
        setFormOpen(true);
    };

    const syncOrders = (shop: Shop) => {
        router.post(
            syncShopOrders([currentOrganization.slug, shop.id]).url,
            {},
            { preserveScroll: true, preserveState: true },
        );
    };

    const testConnection = (shop: Shop) => {
        router.post(
            testShopConnection([currentOrganization.slug, shop.id]).url,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setTestingShopId(shop.id),
                onFinish: () => setTestingShopId(null),
            },
        );
    };

    const confirmDelete = (shop: Shop) => {
        setShopToDelete(shop);
        setDeleteOpen(true);
    };

    return (
        <>
            <Head title="Shops" />

            <div className="workspace-page">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="page-heading">
                        <h1 className="page-title">Shops</h1>
                        <p className="text-muted-foreground text-sm">
                            The WooCommerce stores {currentOrganization.name}{' '}
                            reports on.
                        </p>
                    </div>

                    {permissions.canCreateShop ? (
                        <Button
                            data-test="add-shop-button"
                            onClick={openCreateForm}
                        >
                            <Plus /> Add shop
                        </Button>
                    ) : null}
                </div>

                {currencies.length > 1 ? (
                    <div className="workspace-panel flex items-start gap-3 border-amber-600/30 px-6 py-4 text-sm dark:border-amber-400/30">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-400" />
                        <div className="space-y-1">
                            <p className="text-foreground font-medium">
                                These shops sell in {currencies.join(' and ')}.
                            </p>
                            <p className="text-muted-foreground">
                                A report cannot total them together, so it will
                                refuse to until the selection is narrowed to one
                                currency. Keep an organization to shops sharing
                                a currency, or split them into separate
                                organizations.
                            </p>
                        </div>
                    </div>
                ) : null}

                <div className="workspace-table">
                    <div className="flex flex-col gap-2 border-b p-4 sm:flex-row sm:items-center">
                        <div className="relative w-full sm:max-w-xs">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search by name or URL"
                                aria-label="Search shops"
                                data-test="shop-search"
                                className="pl-9"
                            />
                        </div>

                        {hasFilters ? (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={clearFilters}
                            >
                                <X /> Clear
                            </Button>
                        ) : null}
                    </div>

                    {shops.data.length > 0 ? (
                        <Table>
                            <TableHeader>
                                <TableRow className="hover:bg-transparent">
                                    <TableHead className="pl-6">Shop</TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Platform
                                    </TableHead>
                                    <TableHead>Connection</TableHead>
                                    <TableHead className="hidden sm:table-cell">
                                        Orders
                                    </TableHead>
                                    <TableHead className="hidden xl:table-cell">
                                        API key
                                    </TableHead>
                                    <TableHead className="hidden 2xl:table-cell">
                                        Updated
                                    </TableHead>
                                    {canManageShops ? (
                                        <TableHead className="w-12 pr-6">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </TableHead>
                                    ) : null}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {shops.data.map((shop) => (
                                    <TableRow
                                        key={shop.id}
                                        data-test="shop-row"
                                    >
                                        <TableCell className="pl-6">
                                            <div className="flex items-center gap-3">
                                                <div className="bg-muted text-muted-foreground flex size-9 shrink-0 items-center justify-center rounded-md">
                                                    <Store className="size-4" />
                                                </div>
                                                <div className="min-w-0">
                                                    <div className="max-w-36 truncate font-medium sm:max-w-64">
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
                                            </div>
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            <Badge variant="secondary">
                                                {shop.platformLabel}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                indicator={shop.connection}
                                                testId="shop-connection"
                                                busy={testingShopId === shop.id}
                                                busyLabel="Testing…"
                                            />
                                        </TableCell>
                                        <TableCell
                                            className="hidden sm:table-cell"
                                            data-test="shop-orders"
                                        >
                                            <div className="font-medium tabular-nums">
                                                {shop.sync.orderCount.toLocaleString()}
                                            </div>
                                            <StatusTooltip
                                                indicator={shop.sync}
                                            >
                                                <span
                                                    data-test="shop-sync"
                                                    data-status={
                                                        shop.sync.status
                                                    }
                                                    className={cn(
                                                        'text-xs',
                                                        statusToneText[
                                                            shop.sync.tone
                                                        ],
                                                    )}
                                                >
                                                    {shop.sync.status ===
                                                    'synced'
                                                        ? `synced ${shop.sync.checkedAtDiff ?? ''}`.trim()
                                                        : shop.sync.statusLabel}
                                                </span>
                                            </StatusTooltip>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden font-mono text-xs xl:table-cell">
                                            {shop.consumerKeyHint}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden 2xl:table-cell">
                                            {shop.updatedAtDiff}
                                        </TableCell>
                                        {canManageShops ? (
                                            <TableCell className="pr-6 text-right">
                                                <DropdownMenu modal={false}>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="size-8"
                                                            data-test="shop-actions"
                                                        >
                                                            <MoreHorizontal />
                                                            <span className="sr-only">
                                                                Actions for{' '}
                                                                {shop.name}
                                                            </span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent
                                                        align="end"
                                                        className="w-40"
                                                    >
                                                        {permissions.canUpdateShop ? (
                                                            <>
                                                                <DropdownMenuItem
                                                                    data-test="shop-sync-orders"
                                                                    onSelect={() =>
                                                                        syncOrders(
                                                                            shop,
                                                                        )
                                                                    }
                                                                >
                                                                    <RefreshCw />{' '}
                                                                    Sync orders
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem
                                                                    data-test="shop-test-connection"
                                                                    disabled={
                                                                        testingShopId ===
                                                                        shop.id
                                                                    }
                                                                    onSelect={() =>
                                                                        testConnection(
                                                                            shop,
                                                                        )
                                                                    }
                                                                >
                                                                    <PlugZap />{' '}
                                                                    Test
                                                                    connection
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem
                                                                    data-test="shop-edit"
                                                                    onSelect={() =>
                                                                        openEditForm(
                                                                            shop,
                                                                        )
                                                                    }
                                                                >
                                                                    <Pencil />{' '}
                                                                    Edit
                                                                </DropdownMenuItem>
                                                            </>
                                                        ) : null}
                                                        {permissions.canUpdateShop &&
                                                        permissions.canDeleteShop ? (
                                                            <DropdownMenuSeparator />
                                                        ) : null}
                                                        {permissions.canDeleteShop ? (
                                                            <DropdownMenuItem
                                                                variant="destructive"
                                                                data-test="shop-delete"
                                                                onSelect={() =>
                                                                    confirmDelete(
                                                                        shop,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 />{' '}
                                                                Remove
                                                            </DropdownMenuItem>
                                                        ) : null}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        ) : null}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <div className="flex flex-col items-center justify-center gap-3 px-6 py-16 text-center">
                            <div className="bg-muted flex size-12 items-center justify-center rounded-full">
                                <Store className="text-muted-foreground size-6" />
                            </div>
                            {hasFilters ? (
                                <>
                                    <div className="space-y-1">
                                        <h2 className="font-medium">
                                            No shops match your search
                                        </h2>
                                        <p className="text-muted-foreground text-sm">
                                            Try a different name or URL.
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={clearFilters}
                                    >
                                        Clear search
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <div className="space-y-1">
                                        <h2 className="font-medium">
                                            No shops yet
                                        </h2>
                                        <p className="text-muted-foreground text-sm">
                                            Connect a WooCommerce store to start
                                            reporting on{' '}
                                            {currentOrganization.name} sales.
                                        </p>
                                    </div>
                                    {permissions.canCreateShop ? (
                                        <Button
                                            size="sm"
                                            onClick={openCreateForm}
                                        >
                                            <Plus /> Add shop
                                        </Button>
                                    ) : null}
                                </>
                            )}
                        </div>
                    )}

                    {shops.total > 0 ? (
                        <div className="flex flex-col items-center justify-between gap-3 border-t px-4 py-3 text-sm sm:flex-row">
                            <p className="text-muted-foreground">
                                Showing{' '}
                                <span className="text-foreground font-medium">
                                    {shops.from}–{shops.to}
                                </span>{' '}
                                of{' '}
                                <span className="text-foreground font-medium">
                                    {shops.total}
                                </span>{' '}
                                shops
                            </p>

                            {shops.last_page > 1 ? (
                                <nav
                                    className="flex items-center gap-1"
                                    aria-label="Pagination"
                                >
                                    <PaginationArrow
                                        href={shops.prev_page_url}
                                        label="Previous page"
                                        icon={ChevronLeft}
                                        test="pagination-previous"
                                    />
                                    {shops.links
                                        .slice(1, -1)
                                        .map((link, index) =>
                                            link.url ? (
                                                <Button
                                                    key={`${link.label}-${index}`}
                                                    variant={
                                                        link.active
                                                            ? 'outline'
                                                            : 'ghost'
                                                    }
                                                    size="sm"
                                                    className="min-w-8 px-2 tabular-nums"
                                                    asChild
                                                >
                                                    <Link
                                                        href={link.url}
                                                        preserveScroll
                                                        preserveState
                                                        aria-current={
                                                            link.active
                                                                ? 'page'
                                                                : undefined
                                                        }
                                                    >
                                                        {link.label}
                                                    </Link>
                                                </Button>
                                            ) : (
                                                <span
                                                    key={`${link.label}-${index}`}
                                                    className="text-muted-foreground px-2"
                                                >
                                                    {link.label}
                                                </span>
                                            ),
                                        )}
                                    <PaginationArrow
                                        href={shops.next_page_url}
                                        label="Next page"
                                        icon={ChevronRight}
                                        test="pagination-next"
                                    />
                                </nav>
                            ) : null}
                        </div>
                    ) : null}
                </div>
            </div>

            <ShopFormModal
                organization={currentOrganization}
                shop={editingShop}
                platforms={platforms}
                open={formOpen}
                onOpenChange={setFormOpen}
            />

            <DeleteShopModal
                organization={currentOrganization}
                shop={shopToDelete}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

ShopsIndex.layout = (props: {
    currentOrganization?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentOrganization
                ? dashboard(props.currentOrganization.slug)
                : '/',
        },
        {
            title: 'Shops',
            href: props.currentOrganization
                ? shopsIndex(props.currentOrganization.slug)
                : '/',
        },
    ],
});
