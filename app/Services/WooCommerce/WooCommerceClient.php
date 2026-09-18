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
     * Send a read request authenticated with HTTP Basic auth.
     *
     * @param  array<string, mixed>  $query
     */
    public function get(Shop $shop, string $path, array $query = []): Response
    {
        return $this->request()
            ->withBasicAuth($shop->consumer_key, $shop->consumer_secret)
            ->get($this->endpoint($shop, $path), $query);
    }

    /**
     * Send a read request with the credentials in the query string.
     *
     * Some hosts (typically Apache running PHP as CGI) strip the Authorization
     * header before it reaches WordPress, which WooCommerce documents as a
     * reason to fall back to passing the key and secret as query parameters.
     *
     * @param  array<string, mixed>  $query
     */
    public function getWithQueryAuth(Shop $shop, string $path, array $query = []): Response
    {
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
     * Timeouts are deliberately short: a manual connection test runs inside a
     * web request, so a hanging shop must never hang the page.
     */
    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withUserAgent(config('app.name').'/1.0')
            ->connectTimeout((int) config('services.woocommerce.connect_timeout'))
            ->timeout((int) config('services.woocommerce.timeout'));
    }
}
