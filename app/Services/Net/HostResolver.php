<?php

namespace App\Services\Net;

/**
 * Looks up the addresses a hostname currently points at.
 *
 * Wrapped in a class of its own so that a test can answer for a name without
 * the suite depending on what the machine running it can resolve.
 */
class HostResolver
{
    /**
     * Get the addresses the hostname resolves to, or none if it does not.
     *
     * @return array<int, string>
     */
    public function addressesFor(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));
    }
}
