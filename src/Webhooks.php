<?php

declare(strict_types=1);

namespace Sendara;

use Sendara\Exception\WebhookVerificationException;

final class Webhooks
{
    public const SIGNATURE_HEADER = 'Sendara-Signature';
    public const TIMESTAMP_HEADER = 'Sendara-Timestamp';
    public const EVENT_ID_HEADER = 'Sendara-Event-Id';
    public const EVENT_TYPE_HEADER = 'Sendara-Event-Type';

    /**
     * Verify an inbound Sendara webhook and return its decoded JSON payload.
     *
     * Recomputes HMAC-SHA256(secret, "<timestamp>.<rawBody>") (hex-encoded) and
     * compares it in constant time against the Sendara-Signature header. The
     * $rawBody MUST be the exact bytes received — never a re-serialized object —
     * or the signature will not match.
     *
     * @param array<string, mixed> $headers Case-insensitive header map.
     *
     * @return array<string, mixed>
     *
     * @throws WebhookVerificationException
     */
    public static function verify(
        string $rawBody,
        array $headers,
        string $secret,
        int $toleranceSeconds = 300
    ): array {
        if ($secret === '') {
            throw new WebhookVerificationException('A signing secret is required');
        }

        $signature = self::header($headers, self::SIGNATURE_HEADER);
        $timestamp = self::header($headers, self::TIMESTAMP_HEADER);

        if ($signature === null || $signature === '') {
            throw new WebhookVerificationException('Missing ' . self::SIGNATURE_HEADER . ' header');
        }
        if ($timestamp === null || $timestamp === '') {
            throw new WebhookVerificationException('Missing ' . self::TIMESTAMP_HEADER . ' header');
        }

        if ($toleranceSeconds > 0) {
            if (!ctype_digit(ltrim($timestamp, '-')) || !is_numeric($timestamp)) {
                throw new WebhookVerificationException('Invalid timestamp header');
            }
            $skew = abs(time() - (int) $timestamp);
            if ($skew > $toleranceSeconds) {
                throw new WebhookVerificationException('Timestamp outside tolerance window');
            }
        }

        $expected = self::sign($secret, $timestamp, $rawBody);
        if (!hash_equals($expected, $signature)) {
            throw new WebhookVerificationException('Signature mismatch');
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded)) {
            throw new WebhookVerificationException('Body is not valid JSON');
        }

        return $decoded;
    }

    public static function sign(string $secret, string $timestamp, string $rawBody): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    }

    /**
     * @param array<string, mixed> $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        if (array_key_exists($name, $headers)) {
            return self::scalarHeader($headers[$name]);
        }

        $lower = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $lower) {
                return self::scalarHeader($value);
            }
        }

        return null;
    }

    private static function scalarHeader(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return $value === null ? null : (string) $value;
    }
}
