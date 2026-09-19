import { ArrowDownRight, ArrowRight, ArrowUpRight } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = {
    /** Percentage change, or null when there was nothing to compare against. */
    value: number | null;
    /** What the change is measured against, for the title attribute. */
    against: string;
    /** Set when a fall is the good outcome, as with refunds. */
    invert?: boolean;
};

/**
 * The change in a figure against the period before it.
 *
 * Colour alone never carries the meaning: the arrow and the sign say the same
 * thing, so the badge still reads under colour blindness or in print.
 */
export default function ReportDelta({ value, against, invert = false }: Props) {
    if (value === null) {
        return (
            <span className="text-muted-foreground text-xs">
                no {against} figure
            </span>
        );
    }

    const flat = Math.abs(value) < 0.05;
    const good = invert ? value < 0 : value > 0;
    const Icon = flat ? ArrowRight : value > 0 ? ArrowUpRight : ArrowDownRight;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 text-xs font-medium',
                flat && 'text-muted-foreground',
                !flat && good && 'text-emerald-700 dark:text-emerald-400',
                !flat && !good && 'text-red-700 dark:text-red-400',
            )}
            title={`Compared with ${against}`}
        >
            <Icon className="size-3" />
            {flat ? 'no change' : `${value > 0 ? '+' : ''}${value.toFixed(1)}%`}
        </span>
    );
}
