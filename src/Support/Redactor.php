<?php

namespace Darvis\LaravelApiKeyAlert\Support;

/**
 * Strips secrets from text before it reaches a mail, a log line or an event.
 *
 * An alert about a key travels by mail, often to more than one person, so the key itself
 * must never be in it. The provider's own message stays readable: that is the useful part.
 */
final class Redactor
{
    private const REPLACEMENT = '[redacted]';

    /**
     * Query parameters that carry a secret.
     *
     * @var array<int, string>
     */
    private const SECRET_PARAMETERS = [
        'access_token', 'api_key', 'apikey', 'client_secret', 'key', 'password', 'secret', 'signature', 'token',
    ];

    /**
     * Key formats of the common providers, found anywhere in a text.
     *
     * @var array<int, string>
     */
    private const KEY_PATTERNS = [
        '/\bAIza[0-9A-Za-z_\-]{35}\b/',           // Google
        '/\bsk-[A-Za-z0-9_\-]{16,}\b/',           // OpenAI, Anthropic
        '/\b(?:live|test)_[A-Za-z0-9]{20,}\b/',   // Mollie
        '/\b(?:Bearer|AccessKey)\s+[A-Za-z0-9._\-~+\/=]{8,}/i',
    ];

    public static function text(string $text): string
    {
        $parameters = implode('|', array_map(fn (string $name): string => preg_quote($name, '/'), self::SECRET_PARAMETERS));

        $text = (string) preg_replace('/([?&](?:'.$parameters.')=)[^&#\s"\']+/i', '$1'.self::REPLACEMENT, $text);

        return (string) preg_replace(self::KEY_PATTERNS, self::REPLACEMENT, $text);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function context(array $context): array
    {
        foreach ($context as $name => $value) {
            if (is_string($value)) {
                $context[$name] = self::text($value);
            } elseif (is_array($value)) {
                $context[$name] = self::context($value);
            } elseif (! is_scalar($value) && $value !== null) {
                unset($context[$name]);
            }
        }

        return $context;
    }
}
