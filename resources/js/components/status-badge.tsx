import {
    CircleAlert,
    CircleCheck,
    CircleDashed,
    CircleX,
    type LucideIcon,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { StatusIndicator, StatusTone } from '@/types';

/**
 * Tonal rather than solid, because several of these sit in a table at once and
 * a column of filled red badges reads as an emergency rather than a status.
 */
const toneStyles: Record<StatusTone, string> = {
    positive:
        'border-emerald-600/25 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/25 dark:text-emerald-400',
    warning:
        'border-amber-600/25 bg-amber-500/10 text-amber-700 dark:border-amber-400/25 dark:text-amber-400',
    negative:
        'border-red-600/25 bg-red-500/10 text-red-700 dark:border-red-400/25 dark:text-red-400',
    neutral: 'text-muted-foreground bg-muted/50',
};

const toneIcons: Record<StatusTone, LucideIcon> = {
    positive: CircleCheck,
    warning: CircleAlert,
    negative: CircleX,
    neutral: CircleDashed,
};

/**
 * The same tones as plain text, for places that would be too busy with a
 * second badge in them.
 */
export const statusToneText: Record<StatusTone, string> = {
    positive: 'text-muted-foreground',
    warning: 'text-amber-700 dark:text-amber-400',
    negative: 'text-red-700 dark:text-red-400',
    neutral: 'text-muted-foreground',
};

/**
 * Wraps content in a tooltip carrying the reason and the time of the check,
 * and falls through untouched when there is nothing more to say.
 */
export function StatusTooltip({
    indicator,
    children,
}: {
    indicator: StatusIndicator;
    children: React.ReactNode;
}) {
    if (!indicator.message && !indicator.checkedAtDiff) {
        return children;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span className="inline-flex">{children}</span>
            </TooltipTrigger>
            <TooltipContent className="max-w-xs">
                {indicator.message ? <p>{indicator.message}</p> : null}
                {indicator.checkedAtDiff ? (
                    <p className="opacity-70">
                        Checked {indicator.checkedAtDiff}
                    </p>
                ) : null}
            </TooltipContent>
        </Tooltip>
    );
}

type Props = {
    indicator: StatusIndicator;
    testId?: string;
    busy?: boolean;
    busyLabel?: string;
};

/**
 * The last known answer to a yes-or-no question about a shop, with the reason
 * and the time of the check a hover away.
 */
export default function StatusBadge({
    indicator,
    testId,
    busy = false,
    busyLabel = 'Working…',
}: Props) {
    const Icon = toneIcons[indicator.tone];

    return (
        <StatusTooltip indicator={indicator}>
            <Badge
                variant="outline"
                data-test={testId}
                data-status={indicator.status}
                className={cn('gap-1.5', toneStyles[indicator.tone])}
            >
                {busy ? <Spinner className="size-3" /> : <Icon />}
                {busy ? busyLabel : indicator.statusLabel}
            </Badge>
        </StatusTooltip>
    );
}
