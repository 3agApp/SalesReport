<?php

namespace Database\Factories;

use App\Models\OrderStatusSetting;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderStatusSetting>
 */
class OrderStatusSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'status' => fake()->unique()->slug(2),
            'label' => null,
            'counts_as_revenue' => false,
        ];
    }
}
