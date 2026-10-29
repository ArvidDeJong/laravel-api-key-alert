<?php

namespace Darvis\LaravelApiKeyAlert\Support;

/**
 * A key that is currently reported as rejected.
 */
final class Failure
{
    public function __construct(
        public readonly string $service,
        public readonly string $reason,
        public readonly ?string $since = null,
    ) {}
}
