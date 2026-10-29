<?php

namespace Darvis\LaravelApiKeyAlert\Detectors;

/**
 * The default detector: HTTP 401 and 403 mean the key was rejected.
 */
class StatusDetector implements Detector
{
    /**
     * @var array<int, int>
     */
    protected array $statuses = [401, 403];

    public function detect(int $status, string $body): ?string
    {
        if (! in_array($status, $this->statuses, true)) {
            return null;
        }

        return $this->messageFrom($body) ?? "HTTP {$status}";
    }

    /**
     * The error message of a JSON error body, in the shapes the common APIs use.
     */
    protected function messageFrom(string $body): ?string
    {
        $json = json_decode($body, true);

        if (! is_array($json)) {
            return null;
        }

        $candidates = [
            data_get($json, 'error.message'),
            data_get($json, 'error_message'),
            data_get($json, 'error_description'),
            data_get($json, 'detail'),
            data_get($json, 'message'),
            data_get($json, 'error'),
            data_get($json, 'title'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return mb_strimwidth(trim($candidate), 0, 500, '…');
            }
        }

        return null;
    }
}
