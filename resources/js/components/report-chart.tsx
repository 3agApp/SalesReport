import {
    Area,
    AreaChart,
    CartesianGrid,
    Legend,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { formatMoney, formatNumber } from '@/lib/format';
import type { ReportMeasure, ReportSeriesPoint } from '@/types';

type Props = {
    series: ReportSeriesPoint[];
    measure: ReportMeasure;
    currency: string;
    /** The same measure over the period before, aligned bucket for bucket. */
    previous?: ReportSeriesPoint[];
    previousLabel?: string;
};

const measureLabels: Record<ReportMeasure, string> = {
    revenue: 'Net revenue',
    orders: 'Orders',
    averageOrderValue: 'Average order value',
};

/**
 * One measure at a time, on one axis.
 *
 * Revenue and order counts share no scale, so putting both on the same plot
 * would need a second y-axis and invite the reader to compare two lines that
 * are not comparable. Switching the measure keeps a single honest axis.
 */
export default function ReportChart({
    series,
    measure,
    currency,
    previous,
    previousLabel,
}: Props) {
    const isMoney = measure !== 'orders';
    const comparing = Boolean(previous?.length);

    // The two periods rarely have the same number of buckets (a 31 day month
    // against a 30 day one), so they line up by position, not by date.
    const data = series.map((point, index) => ({
        ...point,
        previous: previous?.[index]?.[measure] ?? null,
        previousLabel: previous?.[index]?.label ?? null,
    }));

    const formatValue = (value: number) =>
        isMoney ? formatMoney(value, currency) : formatNumber(value);

    const formatTick = (value: number) => formatNumber(Math.round(value));

    // With many buckets, labelling every one turns the axis into a smear.
    const tickInterval = Math.max(0, Math.ceil(series.length / 8) - 1);

    return (
        <ResponsiveContainer width="100%" height={280}>
            <AreaChart
                data={data}
                margin={{ top: 8, right: 8, bottom: 0, left: 0 }}
            >
                <defs>
                    <linearGradient id="reportFill" x1="0" y1="0" x2="0" y2="1">
                        <stop
                            offset="0%"
                            stopColor="var(--viz-series-fill)"
                            stopOpacity={0.45}
                        />
                        <stop
                            offset="100%"
                            stopColor="var(--viz-series-fill)"
                            stopOpacity={0.02}
                        />
                    </linearGradient>
                </defs>

                {/* Recessive grid: horizontal only, so it reads as a scale
                    rather than as graph paper. */}
                <CartesianGrid
                    vertical={false}
                    stroke="var(--border)"
                    strokeDasharray="3 3"
                />
                <XAxis
                    dataKey="label"
                    interval={tickInterval}
                    tickLine={false}
                    axisLine={false}
                    tickMargin={10}
                    minTickGap={8}
                    stroke="var(--muted-foreground)"
                    style={{ fontSize: 12 }}
                />
                <YAxis
                    width={64}
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                    stroke="var(--muted-foreground)"
                    style={{ fontSize: 12 }}
                    tickFormatter={formatTick}
                />
                <Tooltip
                    cursor={{ stroke: 'var(--muted-foreground)' }}
                    content={({ active, payload, label }) => {
                        if (!active || !payload?.length) {
                            return null;
                        }

                        const point = payload[0]
                            .payload as ReportSeriesPoint & {
                            previous: number | null;
                            previousLabel: string | null;
                        };

                        return (
                            <div className="bg-popover text-popover-foreground rounded-md border px-3 py-2 text-xs shadow-md">
                                <p className="font-medium">{label}</p>
                                <p className="text-muted-foreground mt-1">
                                    {measureLabels[measure]}:{' '}
                                    <span className="text-foreground font-medium tabular-nums">
                                        {formatValue(point[measure])}
                                    </span>
                                </p>
                                {comparing && point.previous !== null ? (
                                    <p className="text-muted-foreground">
                                        {point.previousLabel ?? 'Previous'}:{' '}
                                        <span className="font-medium tabular-nums">
                                            {formatValue(point.previous)}
                                        </span>
                                    </p>
                                ) : null}
                                {measure !== 'orders' ? (
                                    <p className="text-muted-foreground">
                                        Orders:{' '}
                                        <span className="text-foreground font-medium tabular-nums">
                                            {formatNumber(point.orders)}
                                        </span>
                                    </p>
                                ) : null}
                            </div>
                        );
                    }}
                />
                {/* The earlier period is context, not a rival series: it is
                    drawn in the de-emphasis grey and dashed, so the current
                    period stays the subject of the chart. */}
                {comparing ? (
                    <Area
                        type="monotone"
                        dataKey="previous"
                        name={previousLabel ?? 'Previous period'}
                        stroke="var(--muted-foreground)"
                        strokeWidth={1.5}
                        strokeDasharray="4 3"
                        fill="none"
                        dot={false}
                        activeDot={false}
                        connectNulls
                    />
                ) : null}
                <Area
                    type="monotone"
                    dataKey={measure}
                    name={measureLabels[measure]}
                    stroke="var(--viz-series)"
                    strokeWidth={2}
                    fill="url(#reportFill)"
                    dot={false}
                    activeDot={{ r: 4, strokeWidth: 2, stroke: 'var(--card)' }}
                />
                {/* Two series always carry a legend, so identity never rests
                    on colour alone. */}
                {comparing ? (
                    <Legend
                        verticalAlign="top"
                        align="right"
                        height={28}
                        iconType="plainline"
                        wrapperStyle={{ fontSize: 12 }}
                    />
                ) : null}
            </AreaChart>
        </ResponsiveContainer>
    );
}
