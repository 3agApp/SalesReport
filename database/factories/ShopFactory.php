<?php

namespace Database\Factories;

use App\Enums\ShopConnectionStatus;
use App\Enums\ShopPlatform;
use App\Models\Organization;
use App\Models\Shop;
use DateTimeInterface;
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

    /**
     * Indicate that the shop's credentials were last seen working.
     */
    public function connected(): static
    {
        return $this->state(fn () => [
            'connection_status' => ShopConnectionStatus::Connected,
            'connection_message' => ShopConnectionStatus::Connected->description(),
            'connection_checked_at' => now(),
            'connection_response_time_ms' => fake()->numberBetween(80, 900),
        ]);
    }

    /**
     * Indicate that the shop's last connection check failed.
     */
    public function failing(ShopConnectionStatus $status = ShopConnectionStatus::InvalidCredentials): static
    {
        return $this->state(fn () => [
            'connection_status' => $status,
            'connection_message' => $status->description(),
            'connection_checked_at' => now(),
            'connection_response_time_ms' => fake()->numberBetween(80, 900),
        ]);
    }

    /**
     * Indicate that the shop was last checked at the given time.
     */
    public function checkedAt(?DateTimeInterface $checkedAt): static
    {
        return $this->state(fn () => ['connection_checked_at' => $checkedAt]);
    }
}
