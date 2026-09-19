import { Deferred, Head, usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import ReportChart from '@/components/report-chart';
import ReportDelta from '@/components/report-delta';
import ReportSparkline from '@/components/report-sparkline';
import ReportFilterBar from '@/components/report-filter-bar';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatMoney, formatNumber, MIXED_CURRENCY } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import type {
    ReportComparison,
    ReportFilters,
    ReportMeasure,
    ReportOption,
    ReportProductRow,
    ReportSeriesPoint,
    ReportShopOption,
    ReportShopRow,
    ReportStatusRow,
    ReportSummary,
} from '@/types';

type Props = {
    filters: ReportFilters;
    periods: ReportOption[];
    shops: ReportShopOption[];
    statusOptions: ReportOption[];
    summary: ReportSummary;
    series?: ReportSeriesPoint[];
    byShop?: ReportShopRow[];
    topProducts?: ReportProductRow[];
    byStatus?: ReportStatusRow[];
    comparison?: ReportComparison;
};

const measures: { value: ReportMeasure; label: string }[] = [
    { value: 'revenue', label: 'Revenue' },
    { value: 'orders', label: 'Orders' },
    { value: 'averageOrderValue', label: 'Avg order' },
];

/**
 * A headline figure. The first one is the number the page exists to answer,
 * so it is set larger than the rest rather than being one tile among equals.
 */
function Figure({
    label,
    value,
    hint,
    delta,
    hero = false,
}: {
    label: string;
    value: string;
    hint?: string;
    delta?: React.ReactNode;
    hero?: boolean;
}) {
    return (
        <div
            className={cn(
                'workspace-panel flex min-w-0 flex-col gap-1 px-5 py-5',
                // The headline figure gets the room it needs: a bookkeeper
                // wants the exact number, not a truncated one.
                hero && 'lg:col-span-2',
            )}
        >
            <span className="text-muted-foreground text-xs font-medium tracking-[0.16em] uppercase">
                {label}
            </span>
            <span
                className={cn(
                    'font-semibold tracking-tight tabular-nums',
                    // Sized to fit the tile: a bookkeeper's figure must never
                    // be cut off, and these run to thousands with a currency
                    // prefix and grouping marks.
                    hero ? 'text-3xl sm:text-4xl' : 'text-xl 2xl:text-2xl',
                )}
            >
                {value}
            </span>
            {delta ?? null}
            {hint ? (
                <span className="text-muted-foreground text-xs">{hint}</span>
            ) : null}
        </div>
    );
}

function PanelHeading({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children?: React.ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-2 border-b px-6 py-4">
            <div className="min-w-0">
                <h2 className="font-medium">{title}</h2>
                {description ? (
                    <p className="text-muted-foreground text-xs">
                        {description}
                    </p>
                ) : null}
            </div>
            {children}
        </div>
    );
}

function LoadingRows({ rows = 5 }: { rows?: number }) {
    return (
        <div className="space-y-3 px-6 py-5">
            {Array.from({ length: rows }).map((_, index) => (
                <Skeleton key={index} className="h-6 w-full" />
            ))}
        </div>
    );
}

export default function ReportsIndex({
    filters,
    periods,
    shops,
    statusOptions,
    summary,
    series,
    byShop,
    topProducts,
    byStatus,
    comparison,
}: Props) {
    const { currentOrganization } = usePage().props;
    const [measure, setMeasure] = useState<ReportMeasure>('revenue');

    if (!currentOrganization) {
        return null;
    }

    const money = (value: number) => formatMoney(value, summary.currency);

    // The comparison arrives after the first paint, so the tiles render
    // without deltas and gain them a moment later rather than blocking.
    const deltaFor = (key: string, invert = false) =>
        comparison ? (
            <ReportDelta
                value={comparison.deltas[key] ?? null}
                against={comparison.rangeLabel}
                invert={invert}
            />
        ) : undefined;

    return (
        <>
            <Head title="Reports" />
            <div className="workspace-page">
                <div className="page-heading">
                    <p className="text-muted-foreground text-xs font-medium tracking-[0.16em] uppercase">
                        {currentOrganization.name}
                    </p>
                    <h1 className="page-title">Reports</h1>
                    <p className="text-muted-foreground text-sm">
                        {filters.rangeLabel} · times shown in{' '}
                        {filters.timezone.replace('_', ' ')}
                    </p>
                </div>

                <ReportFilterBar
                    organizationSlug={currentOrganization.slug}
                    filters={filters}
                    periods={periods}
                    shops={shops}
                    statusOptions={statusOptions}
                />

                {comparison?.partial ? (
                    <div className="workspace-panel flex items-start gap-3 border-amber-600/30 px-6 py-4 text-sm dark:border-amber-400/30">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-400" />
                        <p className="text-muted-foreground">
                            {comparison.rangeLabel} reaches back further than
                            the orders imported so far, so the changes below may
                            reflect when the sync started rather than how the
                            shops traded.
                        </p>
                    </div>
                ) : null}

                {summary.currency === MIXED_CURRENCY ? (
                    <div className="workspace-panel flex items-start gap-3 border-amber-600/30 px-6 py-4 text-sm dark:border-amber-400/30">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-400" />
                        <p className="text-muted-foreground">
                            These shops sell in more than one currency, so the
                            totals below are unconverted sums. Filter to one
                            shop, or to shops sharing a currency, for a figure
                            you can book.
                        </p>
                    </div>
                ) : null}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Figure
                        label="Net revenue"
                        value={money(summary.netRevenue)}
                        delta={deltaFor('netRevenue')}
                        hint={`${money(summary.grossRevenue)} gross, less ${money(summary.refunded)} refunded`}
                        hero
                    />
                    <Figure
                        label="Orders"
                        value={formatNumber(summary.orderCount)}
                        delta={deltaFor('orderCount')}
                        hint={`${formatNumber(summary.itemsSold)} items sold`}
                    />
                    <Figure
                        label="Average order"
                        value={money(summary.averageOrderValue)}
                        delta={deltaFor('averageOrderValue')}
                    />
                    <Figure
                        label="Tax"
                        value={money(summary.tax)}
                        hint={`${money(summary.shipping)} shipping, ${money(summary.discount)} discounts`}
                    />
                </div>

                <div className="workspace-panel">
                    <PanelHeading
                        title="Over time"
                        description={
                            comparison
                                ? `Grouped by ${filters.interval}, against ${comparison.rangeLabel}`
                                : `Grouped by ${filters.interval}`
                        }
                    >
                        <ToggleGroup
                            type="single"
                            size="sm"
                            variant="outline"
                            value={measure}
                            onValueChange={(value) =>
                                value && setMeasure(value as ReportMeasure)
                            }
                            data-test="report-measure"
                        >
                            {measures.map((option) => (
                                <ToggleGroupItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                    </PanelHeading>

                    <div className="px-2 py-5 sm:px-4">
                        <Deferred
                            data="series"
                            fallback={
                                <Skeleton className="mx-4 h-[280px] animate-pulse" />
                            }
                        >
                            <ReportChart
                                series={series ?? []}
                                measure={measure}
                                currency={summary.currency}
                                previous={comparison?.series}
                                previousLabel={comparison?.rangeLabel}
                            />
                        </Deferred>
                    </div>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <div className="workspace-panel">
                        <PanelHeading
                            title="By shop"
                            description="Net revenue in this range"
                        />
                        <Deferred data="byShop" fallback={<LoadingRows />}>
                            <ShopTable rows={byShop ?? []} money={money} />
                        </Deferred>
                    </div>

                    <div className="workspace-panel">
                        <PanelHeading
                            title="By status"
                            description="Including the statuses this report is not counting"
                        />
                        <Deferred data="byStatus" fallback={<LoadingRows />}>
                            <StatusTable
                                rows={byStatus ?? []}
                                statusOptions={statusOptions}
                                money={money}
                            />
                        </Deferred>
                    </div>
                </div>

                <div className="workspace-panel">
                    <PanelHeading
                        title="Top products"
                        description="By revenue, grouped by SKU"
                    />
                    <Deferred data="topProducts" fallback={<LoadingRows />}>
                        <ProductTable rows={topProducts ?? []} money={money} />
                    </Deferred>
                </div>
            </div>
        </>
    );
}

/**
 * A bar drawn behind a table row, so magnitude is readable at a glance
 * without a separate chart competing with the numbers.
 */
function MagnitudeCell({
    value,
    max,
    children,
}: {
    value: number;
    max: number;
    children: React.ReactNode;
}) {
    const width = max > 0 ? Math.max(0, (value / max) * 100) : 0;

    return (
        <div className="relative">
            <div
                aria-hidden
                className="absolute inset-y-0 left-0 rounded-sm"
                style={{
                    width: `${width}%`,
                    backgroundColor: 'var(--viz-series-fill)',
                    opacity: 0.35,
                }}
            />
            <div className="relative px-2 py-1">{children}</div>
        </div>
    );
}

function EmptyRow({ children }: { children: React.ReactNode }) {
    return (
        <p className="text-muted-foreground px-6 py-10 text-center text-sm">
            {children}
        </p>
    );
}

function ShopTable({
    rows,
    money,
}: {
    rows: ReportShopRow[];
    money: (value: number) => string;
}) {
    if (rows.length === 0) {
        return <EmptyRow>No orders from any shop in this range.</EmptyRow>;
    }

    const max = Math.max(...rows.map((row) => row.netRevenue));

    return (
        <Table data-test="report-by-shop">
            <TableHeader>
                <TableRow className="hover:bg-transparent">
                    <TableHead className="pl-6">Shop</TableHead>
                    <TableHead className="text-right">Orders</TableHead>
                    <TableHead className="hidden sm:table-cell">
                        Trend
                    </TableHead>
                    <TableHead className="w-2/5 pr-6">Net revenue</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.shopId}>
                        <TableCell className="pl-6 font-medium">
                            {row.name}
                        </TableCell>
                        <TableCell className="text-muted-foreground text-right tabular-nums">
                            {formatNumber(row.orderCount)}
                        </TableCell>
                        <TableCell className="hidden sm:table-cell">
                            <ReportSparkline
                                values={row.trend}
                                label={`${row.name} over the reported range`}
                            />
                        </TableCell>
                        <TableCell className="pr-6">
                            <MagnitudeCell value={row.netRevenue} max={max}>
                                <span className="font-medium whitespace-nowrap tabular-nums">
                                    {money(row.netRevenue)}
                                </span>
                            </MagnitudeCell>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

/**
 * Name a status the way the filter does.
 *
 * A store's own status has whatever name the store gave it, so the two lists
 * have to agree or the same status reads as two different things.
 */
function statusLabel(status: string, options: ReportOption[]): string {
    return (
        options.find((option) => option.value === status)?.label ??
        status.replace(/-/g, ' ').replace(/^./, (first) => first.toUpperCase())
    );
}

function StatusTable({
    rows,
    statusOptions,
    money,
}: {
    rows: ReportStatusRow[];
    statusOptions: ReportOption[];
    money: (value: number) => string;
}) {
    if (rows.length === 0) {
        return <EmptyRow>No orders in this range.</EmptyRow>;
    }

    return (
        <Table data-test="report-by-status">
            <TableHeader>
                <TableRow className="hover:bg-transparent">
                    <TableHead className="pl-6">Status</TableHead>
                    <TableHead className="text-right">Orders</TableHead>
                    <TableHead className="pr-6 text-right">
                        Net revenue
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.status}>
                        <TableCell className="pl-6">
                            <span className="inline-flex items-center gap-2">
                                {statusLabel(row.status, statusOptions)}
                                {row.counted ? null : (
                                    <Badge
                                        variant="outline"
                                        className="text-muted-foreground"
                                    >
                                        Excluded
                                    </Badge>
                                )}
                            </span>
                        </TableCell>
                        <TableCell className="text-muted-foreground text-right tabular-nums">
                            {formatNumber(row.orderCount)}
                        </TableCell>
                        <TableCell
                            className={cn(
                                'pr-6 text-right whitespace-nowrap tabular-nums',
                                row.counted
                                    ? 'font-medium'
                                    : 'text-muted-foreground',
                            )}
                        >
                            {money(row.netRevenue)}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function ProductTable({
    rows,
    money,
}: {
    rows: ReportProductRow[];
    money: (value: number) => string;
}) {
    if (rows.length === 0) {
        return <EmptyRow>No products sold in this range.</EmptyRow>;
    }

    const max = Math.max(...rows.map((row) => row.revenue));

    return (
        <Table data-test="report-top-products">
            <TableHeader>
                <TableRow className="hover:bg-transparent">
                    <TableHead className="pl-6">Product</TableHead>
                    <TableHead className="hidden sm:table-cell">SKU</TableHead>
                    <TableHead className="text-right">Sold</TableHead>
                    <TableHead className="w-1/3 pr-6">Revenue</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.sku ?? row.name}>
                        <TableCell className="max-w-64 truncate pl-6 font-medium">
                            {row.name}
                        </TableCell>
                        <TableCell className="text-muted-foreground hidden font-mono text-xs sm:table-cell">
                            {row.sku ?? '—'}
                        </TableCell>
                        <TableCell className="text-muted-foreground text-right tabular-nums">
                            {formatNumber(row.quantity)}
                        </TableCell>
                        <TableCell className="pr-6">
                            <MagnitudeCell value={row.revenue} max={max}>
                                <span className="font-medium tabular-nums">
                                    {money(row.revenue)}
                                </span>
                            </MagnitudeCell>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

ReportsIndex.layout = (props: {
    currentOrganization?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentOrganization
                ? dashboard(props.currentOrganization.slug)
                : '#',
        },
        { title: 'Reports', href: '#' },
    ],
});
