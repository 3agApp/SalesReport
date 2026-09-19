<?php

namespace Database\Factories;

use App\Enums\ShopSyncStatus;
use App\Models\Shop;
use App\Models\ShopSyncState;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopSyncState>
 */
class ShopSyncStateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'status' => ShopSyncStatus::Pending,
        ];
    }

    /**
     * Indicate that the historical import has finished.
     */
    public function backfilled(): static
    {
        return $this->state(fn () => [
            'status' => ShopSyncStatus::Synced,
            'backfill_cursor' => now()->subYear(),
            'backfill_completed_at' => now()->subDay(),
            'last_synced_at' => now()->subDay(),
            'last_finished_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate when the last run finished.
     */
    public function finishedAt(?DateTimeInterface $finishedAt): static
    {
        return $this->state(fn () => ['last_finished_at' => $finishedAt]);
    }

    /**
     * Indicate that the last run failed.
     */
    public function failing(string $error = 'The shop returned HTTP 500.'): static
    {
        return $this->state(fn () => [
            'status' => ShopSyncStatus::Failed,
            'last_error' => $error,
            'last_finished_at' => now(),
        ]);
    }
}
