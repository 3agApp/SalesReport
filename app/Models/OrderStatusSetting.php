<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\OrderStatusSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What an organization has decided one WooCommerce order status means.
 *
 * Statuses are discovered from the orders themselves rather than assumed,
 * because a store can register its own and routinely does. A status with no
 * row here has simply not been decided on yet, and until it is, the orders
 * sitting in it are shown but not counted.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $status
 * @property string|null $label
 * @property bool $counts_as_revenue
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 */
#[Fillable(['status', 'label', 'counts_as_revenue'])]
class OrderStatusSetting extends Model
{
    /** @use HasFactory<OrderStatusSettingFactory> */
    use HasFactory;

    /**
     * Get the organization the decision belongs to.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the name to show for the status.
     */
    public function displayLabel(): string
    {
        return $this->label !== null && $this->label !== ''
            ? $this->label
            : Order::statusLabel($this->status);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'counts_as_revenue' => 'boolean',
        ];
    }
}
