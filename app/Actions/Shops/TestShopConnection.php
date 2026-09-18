<?php

namespace App\Actions\Shops;

use App\Data\ShopConnectionResult;
use App\Enums\ShopConnectionStatus;
use App\Models\Shop;
use App\Services\WooCommerce\WooCommerceClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

class TestShopConnection
{
    public function __construct(private WooCommerceClient $client) {}

    /**
     * Check whether a shop's stored credentials can read its orders, and
     * record the outcome on the shop.
     */
    public function handle(Shop $shop): ShopConnectionResult
    {
        $result = $this->probe($shop);

        $shop->recordConnectionResult($result);

        return $result;
    }

    /**
     * Ask the shop for a single order id.
     *
     * Orders are the cheapest probe that proves everything this application
     * depends on at once: the shop is reachable, the credentials authenticate,
     * and the key is allowed to read orders.
     */
    private function probe(Shop $shop): ShopConnectionResult
    {
        if (! Str::startsWith($shop->url, 'https://')) {
            return ShopConnectionResult::for(ShopConnectionStatus::RequiresHttps);
        }

        $query = ['per_page' => 1, '_fields' => 'id'];
        $startedAt = hrtime(true);

        try {
            $response = $this->client->get($shop, 'orders', $query);

            // A 401 can mean the credentials are wrong, or that the host
            // stripped the Authorization header before WordPress saw it.
            if ($response->status() === 401) {
                $response = $this->client->getWithQueryAuth($shop, 'orders', $query);
            }
        } catch (ConnectionException $exception) {
            return ShopConnectionResult::for(
                ShopConnectionStatus::Unreachable,
                Str::of($exception->getMessage())->limit(120)->toString(),
                $this->elapsedMs($startedAt),
            );
        }

        return ShopConnectionResult::for(
            $this->statusFor($response),
            $this->detailFrom($response),
            $this->elapsedMs($startedAt),
        );
    }

    /**
     * Map a WooCommerce response onto a connection status.
     */
    private function statusFor(Response $response): ShopConnectionStatus
    {
        if ($response->successful()) {
            return ShopConnectionStatus::Connected;
        }

        return match ($response->status()) {
            401 => ShopConnectionStatus::InvalidCredentials,
            403 => ShopConnectionStatus::InsufficientPermissions,
            404 => ShopConnectionStatus::NotFound,
            default => ShopConnectionStatus::Failed,
        };
    }

    /**
     * Pull the reason out of a WooCommerce error response, so the interface
     * can explain a failure without asking the shop again.
     */
    private function detailFrom(Response $response): ?string
    {
        if ($response->successful()) {
            return null;
        }

        $message = $response->json('message');

        return is_string($message) && $message !== ''
            ? Str::of($message)->stripTags()->squish()->limit(120)->toString()
            : 'HTTP '.$response->status();
    }

    /**
     * Get the milliseconds elapsed since the given high-resolution timestamp.
     */
    private function elapsedMs(float|int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
