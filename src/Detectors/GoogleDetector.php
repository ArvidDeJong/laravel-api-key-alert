<?php

namespace Darvis\LaravelApiKeyAlert\Detectors;

/**
 * Google Maps web services answer a rejected key with HTTP 200 and a status in the body,
 * so a status code check alone never sees it. The Cloud APIs use 401 and 403 as usual.
 */
class GoogleDetector extends StatusDetector
{
    /**
     * @var array<int, string>
     */
    protected array $deniedStatuses = ['REQUEST_DENIED', 'OVER_DAILY_LIMIT'];

    public function detect(int $status, string $body): ?string
    {
        $reason = parent::detect($status, $body);

        if ($reason !== null || $status !== 200) {
            return $reason;
        }

        // Most responses are fine; only decode the ones that mention a denied status.
        if (! $this->mentionsDeniedStatus($body)) {
            return null;
        }

        $json = json_decode($body, true);
        $bodyStatus = is_array($json) ? ($json['status'] ?? null) : null;

        if (! in_array($bodyStatus, $this->deniedStatuses, true)) {
            return null;
        }

        return $this->messageFrom($body) ?? $bodyStatus;
    }

    private function mentionsDeniedStatus(string $body): bool
    {
        foreach ($this->deniedStatuses as $deniedStatus) {
            if (str_contains($body, $deniedStatus)) {
                return true;
            }
        }

        return false;
    }
}
