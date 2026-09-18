<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single product line on an order.
 *
 * @property int $id
 * @property int $order_id
 * @property int $woo_id
 * @property string $name
 * @property string|null $sku
 * @property int|null $product_woo_id
 * @property int|null $variation_woo_id
 * @property int $quantity
 * @property string $subtotal
 * @property string $subtotal_tax
 * @property string $total
 * @property string $total_tax
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Order $order
 */
#[Fillable([
    'woo_id', 'name', 'sku', 'product_woo_id', 'variation_woo_id',
    'quantity', 'subtotal', 'subtotal_tax', 'total', 'total_tax',
])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    /**
     * Get the order the line belongs to.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'subtotal' => 'decimal:4',
            'subtotal_tax' => 'decimal:4',
            'total' => 'decimal:4',
            'total_tax' => 'decimal:4',
        ];
    }
}
