<?php

namespace App\Services\WooCommerce;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns WooCommerce order payloads into rows in our own tables.
 */
class OrderImporter
{
    /**
     * The order columns that a later sync is allowed to overwrite.
     *
     * A status change or a refund has to land on the row we already hold, so
     * everything that can move is listed here.
     *
     * @var array<string>
     */
    private const array UPDATABLE = [
        'number', 'status', 'currency', 'total', 'total_tax', 'shipping_total',
        'shipping_tax', 'cart_tax', 'discount_total', 'discount_tax',
        'refunded_total', 'customer_woo_id', 'customer_email', 'customer_name',
        'billing_country', 'payment_method_title', 'placed_at', 'paid_at',
        'completed_at', 'woo_updated_at',
    ];

    /**
     * Import a page of orders, inserting the new ones and refreshing the rest.
     *
     * @param  array<int, mixed>  $payloads  raw order payloads, straight from JSON
     * @return int the number of orders written
     */
    public function import(Shop $shop, array $payloads): int
    {
        $payloads = array_values(array_filter($payloads, fn ($payload) => is_array($payload) && isset($payload['id'])));

        if ($payloads === []) {
            return 0;
        }

        $now = now();

        $orderRows = array_map(fn (array $payload) => [
            'shop_id' => $shop->id,
            ...$this->toOrderRow($payload),
        ], $payloads);

        DB::transaction(function () use ($shop, $payloads, $orderRows, $now) {
            Order::upsert($orderRows, ['shop_id', 'woo_id'], self::UPDATABLE);

            $orderIds = Order::query()
                ->where('shop_id', $shop->id)
                ->whereIn('woo_id', array_column($orderRows, 'woo_id'))
                ->pluck('id', 'woo_id');

            // Line items are replaced rather than merged: a line removed from
            // an order in WooCommerce has to disappear here too, and nothing
            // refers to an order item by its local id.
            OrderItem::query()->whereIn('order_id', $orderIds->values())->delete();

            $itemRows = [];

            foreach ($payloads as $payload) {
                $orderId = $orderIds[(int) $payload['id']] ?? null;

                if ($orderId === null) {
                    continue;
                }

                foreach (Arr::get($payload, 'line_items', []) as $lineItem) {
                    if (! is_array($lineItem)) {
                        continue;
                    }

                    $itemRows[] = [
                        'order_id' => $orderId,
                        ...$this->toItemRow($lineItem),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($itemRows, 500) as $chunk) {
                OrderItem::insert($chunk);
            }
        });

        return count($orderRows);
    }

    /**
     * Map a WooCommerce order onto our columns.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function toOrderRow(array $payload): array
    {
        $name = trim(implode(' ', array_filter([
            Arr::get($payload, 'billing.first_name'),
            Arr::get($payload, 'billing.last_name'),
        ])));

        return [
            'woo_id' => (int) $payload['id'],
            'number' => $this->string(Arr::get($payload, 'number')),
            'status' => (string) (Arr::get($payload, 'status') ?: 'pending'),
            'currency' => Str::upper((string) (Arr::get($payload, 'currency') ?: 'XXX')),
            'total' => $this->money(Arr::get($payload, 'total')),
            'total_tax' => $this->money(Arr::get($payload, 'total_tax')),
            'shipping_total' => $this->money(Arr::get($payload, 'shipping_total')),
            'shipping_tax' => $this->money(Arr::get($payload, 'shipping_tax')),
            'cart_tax' => $this->money(Arr::get($payload, 'cart_tax')),
            'discount_total' => $this->money(Arr::get($payload, 'discount_total')),
            'discount_tax' => $this->money(Arr::get($payload, 'discount_tax')),
            'refunded_total' => $this->refundedTotal($payload),
            'customer_woo_id' => ((int) Arr::get($payload, 'customer_id', 0)) ?: null,
            'customer_email' => $this->string(Arr::get($payload, 'billing.email')),
            'customer_name' => $name !== '' ? Str::limit($name, 255, '') : null,
            'billing_country' => $this->string(Arr::get($payload, 'billing.country'), 2),
            'payment_method_title' => $this->string(Arr::get($payload, 'payment_method_title')),
            'placed_at' => $this->gmt(Arr::get($payload, 'date_created_gmt')),
            'paid_at' => $this->gmt(Arr::get($payload, 'date_paid_gmt')),
            'completed_at' => $this->gmt(Arr::get($payload, 'date_completed_gmt')),
            'woo_updated_at' => $this->gmt(Arr::get($payload, 'date_modified_gmt')),
        ];
    }

    /**
     * Map a WooCommerce line item onto our columns.
     *
     * @param  array<string, mixed>  $lineItem
     * @return array<string, mixed>
     */
    private function toItemRow(array $lineItem): array
    {
        return [
            'woo_id' => (int) Arr::get($lineItem, 'id', 0),
            'name' => Str::limit((string) (Arr::get($lineItem, 'name') ?: 'Item'), 255, ''),
            'sku' => $this->string(Arr::get($lineItem, 'sku')),
            'product_woo_id' => ((int) Arr::get($lineItem, 'product_id', 0)) ?: null,
            'variation_woo_id' => ((int) Arr::get($lineItem, 'variation_id', 0)) ?: null,
            'quantity' => (int) Arr::get($lineItem, 'quantity', 0),
            'subtotal' => $this->money(Arr::get($lineItem, 'subtotal')),
            'subtotal_tax' => $this->money(Arr::get($lineItem, 'subtotal_tax')),
            'total' => $this->money(Arr::get($lineItem, 'total')),
            'total_tax' => $this->money(Arr::get($lineItem, 'total_tax')),
        ];
    }

    /**
     * Add up the refunds attached to an order, as a positive amount.
     *
     * WooCommerce reports each refund as a negative string.
     *
     * @param  array<string, mixed>  $payload
     */
    private function refundedTotal(array $payload): string
    {
        $total = 0.0;

        foreach (Arr::get($payload, 'refunds', []) as $refund) {
            $total += abs((float) Arr::get((array) $refund, 'total', 0));
        }

        return number_format($total, 4, '.', '');
    }

    /**
     * Normalise a WooCommerce money string, which may be empty or missing.
     */
    private function money(mixed $value): string
    {
        return number_format((float) (is_numeric($value) ? $value : 0), 4, '.', '');
    }

    /**
     * Normalise an optional string, dropping blanks.
     */
    private function string(mixed $value, ?int $limit = 255): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Str::limit(trim($value), $limit ?? 255, '');
    }

    /**
     * Parse one of WooCommerce's GMT timestamps.
     *
     * The `_gmt` fields carry no offset, so they have to be read as UTC
     * explicitly or they would be taken as the application's timezone.
     */
    private function gmt(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value, 'UTC');
    }
}
