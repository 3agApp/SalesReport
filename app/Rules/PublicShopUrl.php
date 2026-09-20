<?php

namespace App\Rules;

use App\Services\Network\PublicHostGuard;
use App\Services\Network\UnsafeDestinationException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Keeps a shop URL pointed at somebody's shop rather than at our own network.
 *
 * Everything stored here is fetched by the server itself, with credentials
 * attached, and the outcome is reported back in the interface. Without this a
 * member could aim a "shop" at a cloud metadata endpoint or an internal
 * service and read the difference between a refused port and an open one.
 *
 * This is the early feedback, not the protection. DNS can answer differently
 * after the form is saved, so the client checks and pins every request too.
 */
class PublicShopUrl implements ValidationRule
{
    public function __construct(private PublicHostGuard $guard) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $host = is_string($value) ? parse_url($value, PHP_URL_HOST) : null;

        // Not a URL at all. The url rule alongside this one says so already,
        // and repeating it would only put the same complaint on screen twice.
        if (! is_string($host) || $host === '') {
            return;
        }

        if (config('services.woocommerce.allow_private_hosts')) {
            return;
        }

        // An IPv6 literal arrives wrapped in brackets.
        $host = trim($host, '[]');

        // A WooCommerce store lives at a domain name. A bare address, or a
        // single-label name such as "localhost" or an intranet machine, is
        // only ever a way to point this server somewhere on its own network.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || ! str_contains($host, '.')) {
            $fail(__('Enter the shop\'s public web address, such as https://example.com.'));

            return;
        }

        try {
            // A host that does not resolve passes here. It may simply be down
            // while someone corrects a typo elsewhere on the form, and the
            // client refuses to connect to it either way.
            $this->guard->publicAddresses($host);
        } catch (UnsafeDestinationException) {
            $fail(__('That address resolves to a private network and cannot be reached as a shop.'));
        }
    }
}
