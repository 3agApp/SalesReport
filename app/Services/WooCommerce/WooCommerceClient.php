<?php

namespace App\Services\WooCommerce;

use App\Models\Shop;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The single place where a shop's WooCommerce credentials touch the wire.
 */
class WooCommerceClient
{
    /**
     * The WooCommerce REST API namespace this application talks to.
     */
    public const string NAMESPACE = 'wc/v3';

    /**
     * The request timeout in seconds, when it differs from the configured one.
     */
    private ?int $timeout = null;

    /**
     * Get a copy of the client that waits longer for a response.
     *
     * Pulling a page of a hundred orders is far heavier than asking for one
     * order id, so the sync needs more room than a connection test does.
     */
    public function withTimeout(int $seconds): self
    {
        $client = clone $this;
        $client->timeout = $seconds;

        return $client;
    }

    /**
     * Send a read request to a shop.
     *
     * Credentials go in the Authorization header first. Some hosts (typically
     * Apache running PHP as CGI) strip that header before it reaches
     * WordPress, which WooCommerce documents as a reason to fall back to
     * passing the key and secret as query parameters, so a 401 is retried that
     * way before it is believed.
     *
     * @param  array<string, mixed>  $query
     */
    public function get(Shop $shop, string $path, array $query = []): Response
    {
        $response = $this->request()
            ->withBasicAuth($shop->consumer_key, $shop->consumer_secret)
            ->get($this->endpoint($shop, $path), $query);

        if ($response->status() !== 401) {
            return $response;
        }

        return $this->request()->get($this->endpoint($shop, $path), [
            ...$query,
            'consumer_key' => $shop->consumer_key,
            'consumer_secret' => $shop->consumer_secret,
        ]);
    }

    /**
     * Build the absolute URL for a WooCommerce REST API endpoint.
     */
    public function endpoint(Shop $shop, string $path): string
    {
        return Str::of($shop->url)
            ->rtrim('/')
            ->append('/wp-json/'.self::NAMESPACE.'/')
            ->append(ltrim($path, '/'))
            ->toString();
    }

    /**
     * Build a request configured for talking to a shop.
     *
     * The default timeout is deliberately short: a manual connection test runs
     * inside a web request, so a hanging shop must never hang the page.
     */
    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withUserAgent(config('app.name').'/1.0')
            ->connectTimeout((int) config('services.woocommerce.connect_timeout'))
            ->timeout($this->timeout ?? (int) config('services.woocommerce.timeout'));
    }
}
