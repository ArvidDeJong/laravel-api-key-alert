<?php

namespace Darvis\LaravelApiKeyAlert\Detectors;

/**
 * Recognises a rejected API key in an HTTP response.
 */
interface Detector
{
    /**
     * The reason the key was rejected, or null when the response does not show a rejected key.
     *
     * Runs on every response to a host of the service, so return early on the common case
     * and only decode the body when the status or a cheap string check calls for it.
     */
    public function detect(int $status, string $body): ?string;
}
