<?php

namespace App\Services\WooCommerce;

use App\Models\Shop;
use App\Services\Network\PublicHostGuard;
use App\Services\Network\UnsafeDestinationException;
use Illuminate\Http\Client\ConnectionException;
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
        // Resolved once for the pair: the fallback must go to the same
        // addresses the first attempt was checked against, and asking twice
        // would give a rebinding host a second chance to answer differently.
        $destination = $this->pinnedDestination($shop);

        $response = $this->request($destination)
            ->withBasicAuth($shop->consumer_key, $shop->consumer_secret)
            ->get($this->endpoint($shop, $path), $query);

        if ($response->status() !== 401) {
            return $response;
        }

        return $this->request($destination)->get($this->endpoint($shop, $path), [
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
     *
     * @param  array<string, mixed>  $destination
     */
    private function request(array $destination = []): PendingRequest
    {
        return Http::acceptJson()
            ->withUserAgent(config('app.name').'/1.0')
            ->withOptions($destination)
            ->connectTimeout((int) config('services.woocommerce.connect_timeout'))
            ->timeout($this->timeout ?? (int) config('services.woocommerce.timeout'));
    }

    /**
     * Check where the shop's host points now, and nail the request to it.
     *
     * The rule on the form is early feedback, nothing more: the name is
     * looked up again there and then, and whoever controls it can answer
     * publicly while the form is saved and privately once the sync runs.
     * Checking here closes that gap, and pinning the answer closes the one
     * that is left, where curl looks the name up a third time and gets a
     * different reply than the one just approved.
     *
     * @return array<string, mixed>
     *
     * @throws UnsafeDestinationException
     * @throws ConnectionException
     */
    private function pinnedDestination(Shop $shop): array
    {
        if (config('services.woocommerce.allow_private_hosts')) {
            return [];
        }

        $host = trim((string) parse_url($shop->url, PHP_URL_HOST), '[]');

        $addresses = app(PublicHostGuard::class)->publicAddresses($host);

        if ($addresses === []) {
            throw new ConnectionException("The shop host {$host} could not be resolved.");
        }

        // A proxy from HTTP_PROXY or HTTPS_PROXY would make the connection to
        // the proxy instead and resolve the shop on the far side, where the
        // pin does not reach. An empty proxy is Guzzle's final "no proxy".
        $direct = ['proxy' => ''];

        // An IP literal is never looked up, so there is nothing to pin.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $direct;
        }

        $port = parse_url($shop->url, PHP_URL_PORT)
            ?? (strtolower((string) parse_url($shop->url, PHP_URL_SCHEME)) === 'http' ? 80 : 443);

        $pinned = implode(',', array_map(
            fn (string $address) => str_contains($address, ':') ? "[{$address}]" : $address,
            $addresses,
        ));

        return [...$direct, 'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinned}"]]];
    }
}
