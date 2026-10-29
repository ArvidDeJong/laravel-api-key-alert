<?php

namespace Darvis\LaravelApiKeyAlert\Support;

final class CheckResult
{
    public function __construct(
        public readonly string $service,
        public readonly CheckStatus $status,
        public readonly ?string $detail = null,
    ) {}
}
