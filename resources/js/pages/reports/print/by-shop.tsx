import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatMoney, formatNumber } from '@/lib/format';
import { index as reportsIndex, shops as shopsReport } from '@/routes/reports';
import { shops as exportShops } from '@/routes/reports/export';
import type {
    ReportFilters,
    ReportTotalsByShop,
    ReportShopTotalsRow,
    ShopReportFigure,
} from '@/types';

type Props = {
    organizationName: string;
    filters: ReportFilters;
    figures: ShopReportFigure[];
    countedStatuses: string[];
    report: ReportTotalsByShop;
    generatedAt: string;
};

const columns: {
    figure: ShopReportFigure;
    label: string;
    value: (row: Pick<ReportShopTotalsRow, 'netRevenue' | 'tax'>) => number;
}[] = [
    { figure: 'revenue', label: 'Net revenue', value: (row) => row.netRevenue },
    { figure: 'tax', label: 'VAT', value: (row) => row.tax },
];

/** Name the sheet after what is on it. */
function titleFor(figures: ShopReportFigure[]): string {
    if (figures.length > 1) {
        return 'Revenue and VAT by shop';
    }

    return figures[0] === 'tax' ? 'VAT by shop' : 'Revenue by shop';
}

/**
 * Each shop's revenue, its VAT or both, as a sheet of paper a bookkeeper
 * works the VAT return out from.
 *
 * The sheet keeps its own light colours whatever theme the app is in, since
 * it exists to be printed or saved as a PDF and handed to a bookkeeper.
 */
export default function ShopReport({
    organizationName,
    filters,
    figures,
    countedStatuses,
    report,
    generatedAt,
}: Props) {
    const { currentOrganization } = usePage().props;
    const organizationSlug = currentOrganization?.slug ?? '';
    const query = {
        period: filters.period,
        from: filters.from,
        to: filters.to,
        shops: filters.shopIds,
        statuses: filters.statuses,
    };
    const shown = columns.filter((column) => figures.includes(column.figure));
    const title = titleFor(figures);

    const showFigures = (next: string[]) => {
        // A sheet with no figures on it would be a list of shop names.
        if (next.length === 0) {
            return;
        }

        router.get(
            shopsReport.url(organizationSlug, {
                query: { ...query, figures: next },
            }),
            {},
            { preserveScroll: true, replace: true },
        );
    };

    return (
        <>
            <Head title={title} />
            <div className="bg-muted/40 min-h-svh px-4 py-6 print:bg-white print:p-0">
                <div className="mx-auto mb-4 flex max-w-3xl flex-wrap items-center gap-2 print:hidden">
                    <Button variant="ghost" asChild>
                        <Link href={reportsIndex(organizationSlug, { query })}>
                            <ArrowLeft /> Back to reports
                        </Link>
                    </Button>
                    <div className="ml-auto flex flex-wrap gap-2">
                        <ToggleGroup
                            type="multiple"
                            variant="outline"
                            value={figures}
                            onValueChange={showFigures}
                            aria-label="Figures to show"
                            data-test="report-figures"
                        >
                            {columns.map((column) => (
                                <ToggleGroupItem
                                    key={column.figure}
                                    value={column.figure}
                                    className="px-3"
                                >
                                    {column.label}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                        <Button variant="outline" asChild>
                            <a
                                href={exportShops.url(organizationSlug, {
                                    query: { ...query, figures },
                                })}
                            >
                                <Download /> CSV
                            </a>
                        </Button>
                        <Button
                            onClick={() => window.print()}
                            data-test="print-report"
                        >
                            <Printer /> Print or save as PDF
                        </Button>
                    </div>
                </div>

                <article className="mx-auto max-w-3xl rounded-lg border bg-white px-8 py-10 text-neutral-900 shadow-sm sm:px-12 print:max-w-none print:rounded-none print:border-0 print:p-0 print:shadow-none">
                    <header className="border-b border-neutral-300 pb-4">
                        <p className="text-sm text-neutral-500">
                            {organizationName}
                        </p>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        <dl className="mt-3 grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 text-sm">
                            <dt className="text-neutral-500">Period</dt>
                            <dd className="font-medium">
                                {filters.rangeLabel}
                            </dd>
                            <dt className="text-neutral-500">Order statuses</dt>
                            <dd>{countedStatuses.join(', ')}</dd>
                            <dt className="text-neutral-500">Timezone</dt>
                            <dd>{filters.timezone.replace('_', ' ')}</dd>
                        </dl>
                    </header>

                    <table
                        className="mt-6 w-full text-sm"
                        data-test="revenue-by-shop"
                    >
                        <thead>
                            <tr className="border-b border-neutral-300 text-left text-neutral-500">
                                <th className="py-2 font-medium">Shop</th>
                                <th className="py-2 text-right font-medium">
                                    Orders
                                </th>
                                {shown.map((column) => (
                                    <th
                                        key={column.figure}
                                        className="py-2 text-right font-medium"
                                    >
                                        {column.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {report.rows.map((row) => (
                                <tr
                                    key={`${row.shopId}-${row.currency}`}
                                    className="break-inside-avoid border-b border-neutral-200"
                                >
                                    <td className="py-2">{row.name}</td>
                                    <td className="py-2 text-right text-neutral-500 tabular-nums">
                                        {formatNumber(row.orderCount)}
                                    </td>
                                    {shown.map((column) => (
                                        <td
                                            key={column.figure}
                                            className="py-2 text-right whitespace-nowrap tabular-nums"
                                        >
                                            {formatMoney(
                                                column.value(row),
                                                row.currency,
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                        {report.totals ? (
                            <tfoot>
                                <tr className="border-t-2 border-neutral-900 font-semibold">
                                    <td className="pt-3">Total</td>
                                    <td className="pt-3 text-right tabular-nums">
                                        {formatNumber(report.totals.orderCount)}
                                    </td>
                                    {shown.map((column) => (
                                        <td
                                            key={column.figure}
                                            className="pt-3 text-right whitespace-nowrap tabular-nums"
                                        >
                                            {formatMoney(
                                                column.value(report.totals!),
                                                report.currency ?? '',
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            </tfoot>
                        ) : null}
                    </table>

                    {report.totals ? null : (
                        <p className="mt-4 text-sm text-neutral-600">
                            These shops sold in more than one currency, so there
                            is no total. Adding two currencies together needs an
                            exchange rate.
                        </p>
                    )}

                    <footer className="mt-10 space-y-1 text-xs text-neutral-500">
                        {figures.includes('revenue') ? (
                            <p>
                                Net revenue is what customers paid for orders
                                placed in the period, VAT and shipping included,
                                less refunds.
                            </p>
                        ) : null}
                        {figures.includes('tax') ? (
                            <p>
                                VAT is the VAT charged on orders placed in the
                                period, less the VAT refunded on them.
                            </p>
                        ) : null}
                        <p>Generated {generatedAt}.</p>
                    </footer>
                </article>
            </div>
        </>
    );
}
