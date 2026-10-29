<?php

namespace Darvis\LaravelApiKeyAlert\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * A key was rejected. Fires on every detection, also within the throttle window, so a host
 * app can count failures; the mail goes out at most once per window.
 */
class ApiKeyFailed
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $context  Already redacted.
     */
    public function __construct(
        public readonly string $service,
        public readonly string $reason,
        public readonly array $context = [],
        public readonly bool $alerted = false,
    ) {}
}
