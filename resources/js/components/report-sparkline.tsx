type Props = {
    values: number[];
    label: string;
};

/**
 * A shop's shape over the reported range, at row height.
 *
 * Small multiples rather than one chart with a line per shop: with six shops
 * and long names, separate sparklines stay readable where crossing lines and
 * a six-colour legend would not.
 */
export default function ReportSparkline({ values, label }: Props) {
    if (values.length < 2) {
        return <div className="h-6 w-24" aria-hidden />;
    }

    const width = 96;
    const height = 24;
    const max = Math.max(...values);
    const min = Math.min(...values, 0);
    const span = max - min || 1;

    const points = values.map((value, index) => {
        const x = (index / (values.length - 1)) * width;
        const y = height - ((value - min) / span) * height;

        return [x, y] as const;
    });

    const line = points
        .map(
            ([x, y], index) =>
                `${index === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`,
        )
        .join(' ');

    const area = `${line} L${width},${height} L0,${height} Z`;
    const [lastX, lastY] = points[points.length - 1];

    return (
        <svg
            width={width}
            height={height}
            viewBox={`0 0 ${width} ${height}`}
            role="img"
            aria-label={label}
            className="overflow-visible"
        >
            <path d={area} fill="var(--viz-series-fill)" opacity={0.25} />
            <path
                d={line}
                fill="none"
                stroke="var(--viz-series)"
                strokeWidth={1.5}
                strokeLinejoin="round"
                strokeLinecap="round"
            />
            {/* The end of the line is where the eye goes, so mark it. */}
            <circle
                cx={lastX}
                cy={lastY}
                r={2}
                fill="var(--viz-series)"
                stroke="var(--card)"
                strokeWidth={1}
            />
        </svg>
    );
}
