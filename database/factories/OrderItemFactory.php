<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 4);
        $price = fake()->randomFloat(2, 5, 120);

        return [
            'order_id' => Order::factory(),
            'woo_id' => fake()->unique()->numberBetween(1, 999_999),
            'name' => fake()->words(3, true),
            'sku' => fake()->bothify('SKU-####'),
            'product_woo_id' => fake()->numberBetween(1, 2_000),
            'variation_woo_id' => null,
            'quantity' => $quantity,
            'subtotal' => $price * $quantity,
            'subtotal_tax' => 0,
            'total' => $price * $quantity,
            'total_tax' => 0,
        ];
    }
}
