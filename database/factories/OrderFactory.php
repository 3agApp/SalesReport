<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 10, 500);
        $tax = round($total * 0.077, 2);

        return [
            'shop_id' => Shop::factory(),
            'woo_id' => fake()->unique()->numberBetween(1, 999_999),
            'number' => fn (array $attributes) => (string) $attributes['woo_id'],
            'status' => fake()->randomElement(Order::REVENUE_STATUSES),
            'currency' => 'CHF',
            'total' => $total,
            'total_tax' => $tax,
            'shipping_total' => fake()->randomElement([0, 4.9, 7.9]),
            'shipping_tax' => 0,
            'cart_tax' => $tax,
            'discount_total' => 0,
            'discount_tax' => 0,
            'refunded_total' => 0,
            'customer_woo_id' => fake()->numberBetween(1, 5_000),
            'customer_email' => fake()->safeEmail(),
            'customer_name' => fake()->name(),
            'billing_country' => 'CH',
            'payment_method_title' => fake()->randomElement(['Credit Card', 'Invoice', 'TWINT']),
            'placed_at' => fake()->dateTimeBetween('-1 year'),
            'paid_at' => fake()->dateTimeBetween('-1 year'),
            'completed_at' => null,
            'woo_updated_at' => fake()->dateTimeBetween('-1 month'),
        ];
    }

    /**
     * Indicate that the order does not count towards revenue.
     */
    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'cancelled', 'paid_at' => null]);
    }

    /**
     * Indicate that the order was placed at a given time.
     */
    public function placedAt(mixed $placedAt): static
    {
        return $this->state(fn () => ['placed_at' => $placedAt]);
    }
}
