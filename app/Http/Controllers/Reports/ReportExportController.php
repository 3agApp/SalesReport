<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportFilterRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Organization;
use App\Models\Shop;
use App\Services\Reports\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    /**
     * Download the filtered orders as a CSV.
     *
     * The rows are streamed and read in chunks, so exporting years of history
     * costs the same memory as exporting a day of it.
     */
    public function orders(ReportFilterRequest $request, Organization $currentOrganization): StreamedResponse
    {
        $filters = $request->filters();
        $report = new SalesReport($filters);
        $shopNames = $this->shopNames($currentOrganization);

        return $this->stream($this->filename($currentOrganization, 'orders', $filters->rangeLabel()), [
            'Shop', 'Order', 'Status', 'Placed at', 'Currency',
            'Total', 'Tax', 'Shipping', 'Discount', 'Refunded', 'Refunded tax', 'Net', 'Net tax',
            'Customer', 'Email', 'Country', 'Payment method',
        ], function () use ($report, $shopNames, $filters) {
            foreach ($report->ordersForExport() as $order) {
                /** @var Order $order */
                yield [
                    $shopNames[$order->shop_id] ?? '',
                    $order->number,
                    $order->status,
                    $this->localTime($order->placed_at, $filters->timezone),
                    $order->currency,
                    $order->total,
                    $order->total_tax,
                    $order->shipping_total,
                    $order->discount_total,
                    $order->refunded_total,
                    $order->refunded_tax,
                    number_format((float) $order->total - (float) $order->refunded_total, 4, '.', ''),
                    number_format((float) $order->total_tax - (float) $order->refunded_tax, 4, '.', ''),
                    $order->customer_name,
                    $order->customer_email,
                    $order->billing_country,
                    $order->payment_method_title,
                ];
            }
        });
    }

    /**
     * Download the filtered line items as a CSV, one row per product sold.
     */
    public function items(ReportFilterRequest $request, Organization $currentOrganization): StreamedResponse
    {
        $filters = $request->filters();
        $report = new SalesReport($filters);
        $shopNames = $this->shopNames($currentOrganization);

        return $this->stream($this->filename($currentOrganization, 'line-items', $filters->rangeLabel()), [
            'Shop', 'Order', 'Order status', 'Placed at', 'Currency',
            'SKU', 'Product', 'Quantity', 'Subtotal', 'Total', 'Tax',
        ], function () use ($report, $shopNames, $filters) {
            foreach ($report->itemsForExport() as $item) {
                /** @var OrderItem $item */
                yield [
                    $shopNames[$item->getAttribute('shop_id')] ?? '',
                    $item->getAttribute('order_number'),
                    $item->getAttribute('order_status'),
                    $this->localTime($item->getAttribute('placed_at'), $filters->timezone),
                    $item->getAttribute('currency'),
                    $item->sku,
                    $item->name,
                    $item->quantity,
                    $item->subtotal,
                    $item->total,
                    $item->total_tax,
                ];
            }
        });
    }

    /**
     * Stream rows out as a CSV download.
     *
     * @param  array<string>  $headings
     * @param  callable(): iterable<array<int, mixed>>  $rows
     */
    private function stream(string $filename, array $headings, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            // Excel opens a UTF-8 CSV as the local codepage unless it sees a
            // byte order mark, which mangles every accented product name.
            fwrite($handle, "\u{FEFF}");
            fputcsv($handle, $headings);

            foreach ($rows() as $row) {
                fputcsv($handle, array_map($this->defuse(...), $row));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Stop a spreadsheet treating a value as a formula.
     *
     * Customer and product names arrive from somebody else's shop, and Excel
     * runs a cell beginning with = + - or @ the moment the file is opened. A
     * leading apostrophe makes it text again. Numbers are left alone, or
     * every negative figure in the file would turn into a string.
     */
    private function defuse(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return Str::startsWith($value, ['=', '+', '-', '@', "\t", "\r"]) ? "'".$value : $value;
    }

    /**
     * Get the shop names for the organization, keyed by id.
     *
     * @return array<int, string>
     */
    private function shopNames(Organization $organization): array
    {
        return $organization->shops()->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int $id) => [$id => $name])
            ->all();
    }

    /**
     * Render a stored UTC timestamp in the organization's timezone.
     */
    private function localTime(mixed $value, string $timezone): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return CarbonImmutable::parse($value, 'UTC')->setTimezone($timezone)->format('Y-m-d H:i:s');
    }

    /**
     * Build a filename that says what is in the file.
     */
    private function filename(Organization $organization, string $kind, string $range): string
    {
        return Str::slug($organization->name.' '.$kind.' '.$range).'.csv';
    }
}
