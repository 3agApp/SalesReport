<?php

namespace Database\Factories;

use App\Enums\ShopPlatform;
use App\Models\Organization;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
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
            'name' => fake()->unique()->company(),
            'url' => 'https://'.fake()->unique()->domainName(),
            'platform' => ShopPlatform::WooCommerce,
            'consumer_key' => 'ck_'.fake()->regexify('[a-f0-9]{40}'),
            'consumer_secret' => 'cs_'.fake()->regexify('[a-f0-9]{40}'),
        ];
    }
}
