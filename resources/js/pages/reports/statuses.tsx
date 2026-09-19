import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatNumber } from '@/lib/format';
import { index as reportsIndex } from '@/routes/reports';
import { update as updateStatuses } from '@/routes/reports/statuses';
import type { OrderStatusSetting } from '@/types';

type Props = {
    statuses: OrderStatusSetting[];
    defaultStatuses: string[];
    anyDecided: boolean;
    permissions: { canUpdate: boolean };
};

export default function OrderStatuses({
    statuses: initialStatuses,
    defaultStatuses,
    anyDecided,
    permissions,
}: Props) {
    const { currentOrganization } = usePage().props;
    const [rows, setRows] = useState(initialStatuses);
    const [saving, setSaving] = useState(false);

    if (!currentOrganization) {
        return null;
    }

    const update = (status: string, changes: Partial<OrderStatusSetting>) => {
        setRows((current) =>
            current.map((row) =>
                row.status === status ? { ...row, ...changes } : row,
            ),
        );
    };

    const save = () => {
        setSaving(true);

        router.patch(
            updateStatuses(currentOrganization.slug).url,
            {
                statuses: rows.map((row) => ({
                    status: row.status,
                    label: row.label ?? '',
                    countsAsRevenue: row.countsAsRevenue,
                })),
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    };

    const undecided = rows.filter((row) => !row.decided && row.orderCount > 0);

    return (
        <>
            <Head title="Order statuses" />
            <div className="workspace-page">
                <div className="page-heading">
                    <p className="text-muted-foreground text-xs font-medium tracking-[0.16em] uppercase">
                        {currentOrganization.name}
                    </p>
                    <h1 className="page-title">Order statuses</h1>
                    <p className="text-muted-foreground max-w-2xl text-sm">
                        Every status your shops have actually used. Each shop
                        decides its own, so give them names that mean something
                        to you and say which ones are money you have taken.
                    </p>
                </div>

                <div>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={reportsIndex(currentOrganization.slug)}>
                            <ArrowLeft /> Back to reports
                        </Link>
                    </Button>
                </div>

                {!anyDecided ? (
                    <div className="workspace-panel flex items-start gap-3 px-6 py-4 text-sm">
                        <p className="text-muted-foreground">
                            Nothing has been decided yet, so reports currently
                            count{' '}
                            <span className="text-foreground font-medium">
                                {defaultStatuses
                                    .map((status) => status.replace(/-/g, ' '))
                                    .join(' and ')}
                            </span>{' '}
                            and nothing else.
                        </p>
                    </div>
                ) : null}

                {undecided.length > 0 ? (
                    <div className="workspace-panel flex items-start gap-3 border-amber-600/30 px-6 py-4 text-sm dark:border-amber-400/30">
                        <TriangleAlert className="mt-0.5 size-4 shrink-0 text-amber-700 dark:text-amber-400" />
                        <p className="text-muted-foreground">
                            {undecided.length === 1
                                ? 'One status has orders in it but has not been decided on yet.'
                                : `${undecided.length} statuses have orders in them but have not been decided on yet.`}{' '}
                            Until you say otherwise their orders are shown but
                            never counted as revenue.
                        </p>
                    </div>
                ) : null}

                <div className="workspace-panel">
                    <Table data-test="order-statuses">
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="pl-6">
                                    WooCommerce status
                                </TableHead>
                                <TableHead>What you call it</TableHead>
                                <TableHead className="text-right">
                                    Orders
                                </TableHead>
                                <TableHead className="w-44 pr-6 text-center">
                                    Counts as revenue
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.length === 0 ? (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={4}
                                        className="text-muted-foreground py-10 text-center text-sm"
                                    >
                                        No orders have been imported yet, so
                                        there are no statuses to describe.
                                    </TableCell>
                                </TableRow>
                            ) : null}
                            {rows.map((row) => (
                                <TableRow key={row.status}>
                                    <TableCell className="pl-6">
                                        <span className="inline-flex items-center gap-2">
                                            <code className="text-xs">
                                                {row.status}
                                            </code>
                                            {row.decided ? null : (
                                                <Badge
                                                    variant="outline"
                                                    className="text-muted-foreground"
                                                >
                                                    Not decided
                                                </Badge>
                                            )}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <Input
                                            value={row.label ?? ''}
                                            placeholder={row.suggestedLabel}
                                            disabled={!permissions.canUpdate}
                                            maxLength={60}
                                            className="h-8 max-w-56"
                                            data-test={`status-label-${row.status}`}
                                            onChange={(event) =>
                                                update(row.status, {
                                                    label: event.target.value,
                                                })
                                            }
                                        />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground text-right tabular-nums">
                                        {formatNumber(row.orderCount)}
                                    </TableCell>
                                    <TableCell className="pr-6 text-center">
                                        <Checkbox
                                            checked={row.countsAsRevenue}
                                            disabled={!permissions.canUpdate}
                                            data-test={`status-revenue-${row.status}`}
                                            onCheckedChange={(checked) =>
                                                update(row.status, {
                                                    countsAsRevenue:
                                                        checked === true,
                                                })
                                            }
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                {permissions.canUpdate && rows.length > 0 ? (
                    <div className="flex justify-end">
                        <Button
                            onClick={save}
                            disabled={saving}
                            data-test="save-order-statuses"
                        >
                            {saving ? 'Saving…' : 'Save statuses'}
                        </Button>
                    </div>
                ) : null}
            </div>
        </>
    );
}
