import { Head, Link, router, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    MoreHorizontal,
    Pencil,
    PlugZap,
    Plus,
    Search,
    Store,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import DeleteShopModal from '@/components/delete-shop-modal';
import ShopConnectionBadge from '@/components/shop-connection-badge';
import ShopFormModal from '@/components/shop-form-modal';
import { Badge } from '@/components/ui/badge';
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
import { dashboard } from '@/routes';
import { index as shopsIndex } from '@/routes/shops';
import { test as testShopConnection } from '@/routes/shops/connection';
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
    permissions: OrganizationPermissions;
};

export default function ShopsIndex({
    shops,
    filters,
    platforms,
    permissions,
}: Props) {
    const { currentOrganization } = usePage().props;
    const [search, setSearch] = useState(filters.search);
    const [formOpen, setFormOpen] = useState(false);
    const [editingShop, setEditingShop] = useState<Shop | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [shopToDelete, setShopToDelete] = useState<Shop | null>(null);
    const [testingShopId, setTestingShopId] = useState<number | null>(null);
    const searchTimeout = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => {
        const timeout = searchTimeout;

        return () => clearTimeout(timeout.current);
    }, []);

    if (!currentOrganization) {
        return null;
    }

    const hasFilters = filters.search !== '';
    const canManageShops =
        permissions.canUpdateShop || permissions.canDeleteShop;

    const applySearch = (value: string) => {
        router.get(
            shopsIndex(currentOrganization.slug).url,
            value ? { search: value } : {},
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const handleSearchChange = (value: string) => {
        setSearch(value);
        clearTimeout(searchTimeout.current);
        searchTimeout.current = setTimeout(
            () => applySearch(value.trim()),
            300,
        );
    };

    const clearFilters = () => {
        clearTimeout(searchTimeout.current);
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
                        <p className="text-muted-foreground text-xs font-medium tracking-[0.16em] uppercase">
                            Organization shops
                        </p>
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

                <div className="workspace-table">
                    <div className="flex flex-col gap-2 border-b p-4 sm:flex-row sm:items-center">
                        <div className="relative w-full sm:max-w-xs">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    handleSearchChange(event.target.value)
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
                                    <TableHead className="hidden sm:table-cell">
                                        Platform
                                    </TableHead>
                                    <TableHead>Connection</TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        API key
                                    </TableHead>
                                    <TableHead className="hidden xl:table-cell">
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
                                        <TableCell className="hidden sm:table-cell">
                                            <Badge variant="secondary">
                                                {shop.platformLabel}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <ShopConnectionBadge
                                                connection={shop.connection}
                                                testing={
                                                    testingShopId === shop.id
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden font-mono text-xs lg:table-cell">
                                            {shop.consumerKeyHint}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden xl:table-cell">
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

function PaginationArrow({
    href,
    label,
    icon: Icon,
}: {
    href: string | null;
    label: string;
    icon: LucideIcon;
}) {
    if (!href) {
        return (
            <Button variant="ghost" size="icon" className="size-8" disabled>
                <Icon />
                <span className="sr-only">{label}</span>
            </Button>
        );
    }

    return (
        <Button variant="ghost" size="icon" className="size-8" asChild>
            <Link href={href} preserveScroll preserveState>
                <Icon />
                <span className="sr-only">{label}</span>
            </Link>
        </Button>
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
