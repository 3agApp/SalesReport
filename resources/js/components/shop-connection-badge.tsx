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
import type { ShopConnection, ShopConnectionTone } from '@/types';

/**
 * Tonal rather than solid, because several of these sit in a table at once and
 * a column of filled red badges reads as an emergency rather than a status.
 */
const toneStyles: Record<ShopConnectionTone, string> = {
    positive:
        'border-emerald-600/25 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/25 dark:text-emerald-400',
    warning:
        'border-amber-600/25 bg-amber-500/10 text-amber-700 dark:border-amber-400/25 dark:text-amber-400',
    negative:
        'border-red-600/25 bg-red-500/10 text-red-700 dark:border-red-400/25 dark:text-red-400',
    neutral: 'text-muted-foreground bg-muted/50',
};

const toneIcons: Record<ShopConnectionTone, LucideIcon> = {
    positive: CircleCheck,
    warning: CircleAlert,
    negative: CircleX,
    neutral: CircleDashed,
};

type Props = {
    connection: ShopConnection;
    testing?: boolean;
};

/**
 * The last known answer to "can we still read this shop's orders?", with the
 * reason and the time of the check a hover away.
 */
export default function ShopConnectionBadge({
    connection,
    testing = false,
}: Props) {
    const Icon = toneIcons[connection.tone];

    const badge = (
        <Badge
            variant="outline"
            data-test="shop-connection"
            data-status={connection.status}
            className={cn('gap-1.5', toneStyles[connection.tone])}
        >
            {testing ? <Spinner className="size-3" /> : <Icon />}
            {testing ? 'Testing…' : connection.statusLabel}
        </Badge>
    );

    if (!connection.message && !connection.checkedAtDiff) {
        return badge;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span className="inline-flex">{badge}</span>
            </TooltipTrigger>
            <TooltipContent className="max-w-xs">
                {connection.message ? <p>{connection.message}</p> : null}
                {connection.checkedAtDiff ? (
                    <p className="opacity-70">
                        Checked {connection.checkedAtDiff}
                    </p>
                ) : null}
            </TooltipContent>
        </Tooltip>
    );
}
