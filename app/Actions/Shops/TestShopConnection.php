<?php

namespace App\Actions\Shops;

use App\Data\ShopConnectionResult;
use App\Enums\ShopConnectionStatus;
use App\Models\Shop;
use App\Services\Network\UnsafeDestinationException;
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
        } catch (UnsafeDestinationException) {
            // Deliberately not the resolved address: whoever typed the URL
            // does not need this server's reading of their DNS handed back.
            return ShopConnectionResult::for(
                ShopConnectionStatus::Unreachable,
                'This address points to a private or reserved network, so it is not contacted.',
                $this->elapsedMs($startedAt),
            );
        } catch (ConnectionException $exception) {
            return ShopConnectionResult::for(
                ShopConnectionStatus::Unreachable,
                Str::of($exception->getMessage())->limit(120)->toString(),
                $this->elapsedMs($startedAt),
            );
        }

        $status = $this->statusFor($response);

        return ShopConnectionResult::for(
            $status,
            $this->detailFrom($response),
            $this->elapsedMs($startedAt),
            // Asked once and then remembered. A store's currency changes
            // about never, an hourly check should not pay for a second
            // request to hear the same answer, and if one ever did change
            // the orders would carry the new one and the report would say so.
            $status->isHealthy() && $shop->currency === null ? $this->currencyOf($shop) : null,
        );
    }

    /**
     * Ask the store which currency it sells in.
     *
     * Totals in two currencies cannot be added together, so knowing this per
     * shop is what lets the interface say so before a report tries. A store
     * that will not answer simply stays unknown; it is not a failed check.
     */
    private function currencyOf(Shop $shop): ?string
    {
        try {
            // Without _fields the store returns its whole currency list,
            // some twenty kilobytes of it, on every hourly check.
            $response = $this->client->get($shop, 'settings/general/woocommerce_currency', ['_fields' => 'id,value']);
        } catch (ConnectionException|UnsafeDestinationException) {
            return null;
        }

        $currency = $response->successful() ? $response->json('value') : null;

        return is_string($currency) && preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null;
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
