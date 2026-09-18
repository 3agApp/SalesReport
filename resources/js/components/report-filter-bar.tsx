import { router } from '@inertiajs/react';
import {
    CalendarDays,
    Check,
    ChevronDown,
    Download,
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

export default function ReportFilterBar({
    organizationSlug,
    filters,
    periods,
    shops,
    statusOptions,
}: Props) {
    const [calendarOpen, setCalendarOpen] = useState(false);

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
        if (!range?.from) {
            return;
        }

        apply({
            period: 'custom',
            from: toDateString(range.from),
            to: toDateString(range.to ?? range.from),
        });

        if (range.to) {
            setCalendarOpen(false);
        }
    };

    const allShopsSelected = filters.shopIds.length === shops.length;

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

            <Popover open={calendarOpen} onOpenChange={setCalendarOpen}>
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
                        defaultMonth={parseDate(filters.from)}
                        selected={{
                            from: parseDate(filters.from),
                            to: parseDate(filters.to),
                        }}
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
