<?php

namespace Tests;

use App\Services\Net\HostResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test should depend on what the machine running it can resolve,
        // nor wait on a name server for a .test domain that never answers.
        // A test that cares about resolution swaps this for its own answer.
        $this->resolveHostsTo([]);
    }

    /**
     * Answer every hostname lookup with the given addresses.
     *
     * @param  array<int, string>  $addresses
     */
    protected function resolveHostsTo(array $addresses): void
    {
        $this->swap(HostResolver::class, new class($addresses) extends HostResolver
        {
            /**
             * @param  array<int, string>  $addresses
             */
            public function __construct(private array $addresses) {}

            /**
             * @return array<int, string>
             */
            public function addressesFor(string $host): array
            {
                return $this->addresses;
            }
        });
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
