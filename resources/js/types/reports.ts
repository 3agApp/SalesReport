export type ReportPeriod =
    | 'today'
    | 'yesterday'
    | 'this_month'
    | 'last_month'
    | 'this_quarter'
    | 'last_quarter'
    | 'this_year'
    | 'last_year'
    | 'last_30_days'
    | 'last_12_months'
    | 'custom';

export type ReportInterval = 'day' | 'week' | 'month';

export type ReportFilters = {
    period: ReportPeriod;
    from: string;
    to: string;
    timezone: string;
    shopIds: number[];
    statuses: string[];
    interval: ReportInterval;
    rangeLabel: string;
};

export type ReportSummary = {
    orderCount: number;
    grossRevenue: number;
    refunded: number;
    netRevenue: number;
    tax: number;
    shipping: number;
    discount: number;
    averageOrderValue: number;
    itemsSold: number;
    currency: string;
};

export type ReportSeriesPoint = {
    date: string;
    label: string;
    orders: number;
    revenue: number;
    averageOrderValue: number;
};

export type ReportShopRow = {
    shopId: number;
    name: string;
    orderCount: number;
    netRevenue: number;
    /** Net revenue per bucket, on the same buckets as the main series. */
    trend: number[];
};

export type ReportComparison = {
    rangeLabel: string;
    summary: ReportSummary;
    series: ReportSeriesPoint[];
    /** Percentage change per figure; null where there was nothing to compare against. */
    deltas: Record<string, number | null>;
    /** True when the earlier period reaches back before the imported history. */
    partial: boolean;
};

export type ReportProductRow = {
    name: string;
    sku: string | null;
    quantity: number;
    revenue: number;
};

export type ReportStatusRow = {
    status: string;
    orderCount: number;
    netRevenue: number;
    counted: boolean;
};

export type ReportOption = { value: string; label: string };

export type ReportShopOption = { id: number; name: string };

/** Which figure the time series is showing. */
export type ReportMeasure = 'revenue' | 'orders' | 'averageOrderValue';

export type OrderStatusSetting = {
    status: string;
    /** What the status would be called if the organization named nothing. */
    suggestedLabel: string;
    label: string | null;
    countsAsRevenue: boolean;
    /** False while nobody has said what this status means. */
    decided: boolean;
    orderCount: number;
};
