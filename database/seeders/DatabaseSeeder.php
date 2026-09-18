<?php

namespace Database\Seeders;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        [$organizationName, $shops] = $this->localShops();

        $organization = Organization::factory()->create(['name' => $organizationName]);
        $organization->members()->attach($user, ['role' => OrganizationRole::Owner->value]);

        foreach ($shops as $shop) {
            $organization->shops()->create([
                'name' => $shop['name'],
                'url' => Shop::normalizeUrl($shop['url']),
                'consumer_key' => $shop['consumer_key'],
                'consumer_secret' => $shop['consumer_secret'],
            ]);
        }
    }

    /**
     * Read the shops to seed from the developer's local, untracked file,
     * falling back to the committed example when it is not there.
     *
     * Real WooCommerce credentials live only in database/seeders/data/shops.json,
     * which is git-ignored, so they never reach the repository.
     *
     * @return array{0: string, 1: array<int, array{name: string, url: string, consumer_key: string, consumer_secret: string}>}
     */
    private function localShops(): array
    {
        $path = database_path('seeders/data/shops.json');

        if (! file_exists($path)) {
            $path = database_path('seeders/data/shops.example.json');

            $this->command->warn('No database/seeders/data/shops.json found; seeding the example shop instead.');
        }

        /** @var array{organization: string, shops: array<int, array{name: string, url: string, consumer_key: string, consumer_secret: string}>} $data */
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return [$data['organization'], $data['shops']];
    }
}
