import {
    Area,
    AreaChart,
    CartesianGrid,
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
export default function ReportChart({ series, measure, currency }: Props) {
    const isMoney = measure !== 'orders';

    const formatValue = (value: number) =>
        isMoney ? formatMoney(value, currency) : formatNumber(value);

    const formatTick = (value: number) => formatNumber(Math.round(value));

    // With many buckets, labelling every one turns the axis into a smear.
    const tickInterval = Math.max(0, Math.ceil(series.length / 8) - 1);

    return (
        <ResponsiveContainer width="100%" height={280}>
            <AreaChart
                data={series}
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
                            .payload as unknown as ReportSeriesPoint;

                        return (
                            <div className="bg-popover text-popover-foreground rounded-md border px-3 py-2 text-xs shadow-md">
                                <p className="font-medium">{label}</p>
                                <p className="text-muted-foreground mt-1">
                                    {measureLabels[measure]}:{' '}
                                    <span className="text-foreground font-medium tabular-nums">
                                        {formatValue(point[measure])}
                                    </span>
                                </p>
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
            </AreaChart>
        </ResponsiveContainer>
    );
}
