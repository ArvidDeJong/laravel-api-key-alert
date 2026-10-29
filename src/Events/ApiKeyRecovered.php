<?php

namespace Darvis\LaravelApiKeyAlert\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A key that was reported as failed works again. Fires once per outage.
 */
class ApiKeyRecovered
{
    use Dispatchable;

    public function __construct(
        public readonly string $service,
        public readonly ?string $failedSince = null,
    ) {}
}
