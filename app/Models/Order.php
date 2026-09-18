<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single order pulled from a shop, as it stood at the last sync.
 *
 * @property int $id
 * @property int $shop_id
 * @property int $woo_id
 * @property string|null $number
 * @property string $status
 * @property string $currency
 * @property string $total
 * @property string $total_tax
 * @property string $shipping_total
 * @property string $shipping_tax
 * @property string $cart_tax
 * @property string $discount_total
 * @property string $discount_tax
 * @property string $refunded_total
 * @property int|null $customer_woo_id
 * @property string|null $customer_email
 * @property string|null $customer_name
 * @property string|null $billing_country
 * @property string|null $payment_method_title
 * @property CarbonImmutable|null $placed_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $woo_updated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Shop $shop
 * @property-read Collection<int, OrderItem> $items
 */
#[Fillable([
    'woo_id', 'number', 'status', 'currency',
    'total', 'total_tax', 'shipping_total', 'shipping_tax', 'cart_tax',
    'discount_total', 'discount_tax', 'refunded_total',
    'customer_woo_id', 'customer_email', 'customer_name', 'billing_country',
    'payment_method_title', 'placed_at', 'paid_at', 'completed_at', 'woo_updated_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * The WooCommerce statuses that represent money the shop actually kept.
     *
     * Cancelled, failed and pending orders are excluded: a bookkeeper's
     * revenue figure should not count a sale that never completed.
     *
     * @var array<string>
     */
    public const array REVENUE_STATUSES = ['processing', 'completed', 'on-hold', 'refunded'];

    /**
     * Get the shop the order belongs to.
     *
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the line items on the order.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Scope the query to orders that count towards revenue.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeCountingTowardsRevenue(Builder $query): void
    {
        $query->whereIn('status', self::REVENUE_STATUSES);
    }

    /**
     * Scope the query to orders placed within a period.
     *
     * @param  Builder<Order>  $query
     */
    public function scopePlacedBetween(Builder $query, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $query->whereBetween('placed_at', [$from, $to]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'decimal:4',
            'total_tax' => 'decimal:4',
            'shipping_total' => 'decimal:4',
            'shipping_tax' => 'decimal:4',
            'cart_tax' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'discount_tax' => 'decimal:4',
            'refunded_total' => 'decimal:4',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'woo_updated_at' => 'datetime',
        ];
    }
}
