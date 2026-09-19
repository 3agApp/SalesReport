import { Link, router } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    ChevronDown,
    Download,
    SlidersHorizontal,
    Store,
} from 'lucide-react';
import { useState } from 'react';
import type { DateRange } from 'react-day-picker';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index as reportsIndex } from '@/routes/reports';
import { index as manageStatuses } from '@/routes/reports/statuses';
import {
    items as exportItems,
    orders as exportOrders,
} from '@/routes/reports/export';
import type {
    ReportFilters,
    ReportOption,
    ReportPeriod,
    ReportShopOption,
} from '@/types';

type Props = {
    organizationSlug: string;
    filters: ReportFilters;
    periods: ReportOption[];
    shops: ReportShopOption[];
    statusOptions: ReportOption[];
};

/** Read a yyyy-mm-dd string as a local date, free of timezone drift. */
function parseDate(value: string): Date | undefined {
    const [year, month, day] = value.split('-').map(Number);

    return year && month && day ? new Date(year, month - 1, day) : undefined;
}

function toDateString(date: Date): string {
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

/**
 * The first of the two months the calendar opens on.
 *
 * It follows the end of the range rather than the start, so a twelve month
 * report does not open a year in the past and leave the bookkeeper paging
 * forward before they can pick anything.
 */
function openingMonth(from?: Date, to?: Date): Date | undefined {
    if (!to) {
        return from;
    }

    const penultimate = new Date(to.getFullYear(), to.getMonth() - 1, 1);

    return from && from > penultimate ? from : penultimate;
}

export default function ReportFilterBar({
    organizationSlug,
    filters,
    periods,
    shops,
    statusOptions,
}: Props) {
    const [calendarOpen, setCalendarOpen] = useState(false);
    // The range being drawn, while only one of its ends has been clicked.
    // Until both exist there is nothing to report on, so nothing is sent.
    const [draftRange, setDraftRange] = useState<DateRange | undefined>();

    const query = {
        period: filters.period,
        from: filters.from,
        to: filters.to,
        shops: filters.shopIds,
        statuses: filters.statuses,
    };

    const apply = (changes: Partial<typeof query>) => {
        router.get(
            reportsIndex(organizationSlug).url,
            { ...query, ...changes },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const toggleShop = (id: number) => {
        const next = filters.shopIds.includes(id)
            ? filters.shopIds.filter((shopId) => shopId !== id)
            : [...filters.shopIds, id];

        // Clearing the last shop would report on nothing; fall back to all.
        apply({ shops: next.length > 0 ? next : shops.map((shop) => shop.id) });
    };

    const toggleStatus = (status: string) => {
        const next = filters.statuses.includes(status)
            ? filters.statuses.filter((value) => value !== status)
            : [...filters.statuses, status];

        apply({ statuses: next });
    };

    const selectRange = (range: DateRange | undefined) => {
        setDraftRange(range);

        // A half-drawn range would otherwise be reported on as a single day,
        // and the picker would close before the second end was ever clicked.
        if (!range?.from || !range.to) {
            return;
        }

        setDraftRange(undefined);
        setCalendarOpen(false);

        apply({
            period: 'custom',
            from: toDateString(range.from),
            to: toDateString(range.to),
        });
    };

    const allShopsSelected = filters.shopIds.length === shops.length;
    const appliedFrom = parseDate(filters.from);
    const appliedTo = parseDate(filters.to);

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Select
                value={filters.period}
                onValueChange={(period) =>
                    apply({ period: period as ReportPeriod })
                }
            >
                <SelectTrigger className="w-44" data-test="report-period">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {periods.map((period) => (
                        <SelectItem key={period.value} value={period.value}>
                            {period.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>

            <Popover
                open={calendarOpen}
                onOpenChange={(open) => {
                    setCalendarOpen(open);

                    // An abandoned half-drawn range must not survive into the
                    // next time the picker is opened.
                    if (!open) {
                        setDraftRange(undefined);
                    }
                }}
            >
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        className="font-normal"
                        data-test="report-range"
                    >
                        <CalendarDays />
                        {filters.rangeLabel}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                    <Calendar
                        mode="range"
                        numberOfMonths={2}
                        // Without this, a click on a day with a range already
                        // applied only drags that range's end along: the start
                        // stays wherever the last report left it, and no
                        // amount of clicking can move it.
                        resetOnSelect
                        defaultMonth={openingMonth(appliedFrom, appliedTo)}
                        selected={
                            draftRange ?? { from: appliedFrom, to: appliedTo }
                        }
                        onSelect={selectRange}
                        autoFocus
                    />
                </PopoverContent>
            </Popover>

            {shops.length > 1 ? (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="outline"
                            className="font-normal"
                            data-test="report-shops"
                        >
                            <Store />
                            {allShopsSelected
                                ? 'All shops'
                                : `${filters.shopIds.length} of ${shops.length} shops`}
                            <ChevronDown className="text-muted-foreground" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-56">
                        <DropdownMenuLabel>Shops</DropdownMenuLabel>
                        {shops.map((shop) => (
                            <DropdownMenuCheckboxItem
                                key={shop.id}
                                checked={filters.shopIds.includes(shop.id)}
                                onCheckedChange={() => toggleShop(shop.id)}
                                onSelect={(event) => event.preventDefault()}
                            >
                                {shop.name}
                            </DropdownMenuCheckboxItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onSelect={() =>
                                apply({ shops: shops.map((shop) => shop.id) })
                            }
                        >
                            <Check /> Select all
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ) : null}

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        className="font-normal"
                        data-test="report-statuses"
                    >
                        {filters.statuses.length} status
                        {filters.statuses.length === 1 ? '' : 'es'}
                        <ChevronDown className="text-muted-foreground" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" className="w-56">
                    <DropdownMenuLabel>Counted as revenue</DropdownMenuLabel>
                    {statusOptions.length === 0 ? (
                        <p className="text-muted-foreground px-2 py-1.5 text-xs">
                            No orders imported yet.
                        </p>
                    ) : null}
                    {statusOptions.map((status) => (
                        <DropdownMenuCheckboxItem
                            key={status.value}
                            checked={filters.statuses.includes(status.value)}
                            onCheckedChange={() => toggleStatus(status.value)}
                            onSelect={(event) => event.preventDefault()}
                        >
                            {status.label}
                        </DropdownMenuCheckboxItem>
                    ))}
                    <DropdownMenuSeparator />
                    {/* Each shop names its own statuses, so what a slug like
                        "partial-complete" means to the books is a question
                        only the organization can answer. */}
                    <DropdownMenuItem asChild>
                        <Link href={manageStatuses(organizationSlug)}>
                            <SlidersHorizontal /> Manage statuses
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <div className="ml-auto">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" data-test="report-export">
                            <Download /> Export
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-56">
                        <DropdownMenuLabel>
                            Download this range
                        </DropdownMenuLabel>
                        <DropdownMenuItem asChild>
                            <a
                                href={exportOrders.url(organizationSlug, {
                                    query,
                                })}
                                data-test="report-export-orders"
                            >
                                Orders (CSV)
                            </a>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <a
                                href={exportItems.url(organizationSlug, {
                                    query,
                                })}
                                data-test="report-export-items"
                            >
                                Line items (CSV)
                            </a>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    );
}
