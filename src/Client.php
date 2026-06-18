<?php

declare(strict_types=1);

namespace Sendara;

use Sendara\Exception\ApiException;
use Sendara\Exception\SendaraException;
use Sendara\Resources\ApiKeys;
use Sendara\Resources\Billing;
use Sendara\Resources\Broadcasts;
use Sendara\Resources\Contacts;
use Sendara\Resources\Domains;
use Sendara\Resources\Emails;
use Sendara\Resources\Lists;
use Sendara\Resources\Messages;
use Sendara\Resources\Suppressions;
use Sendara\Resources\Templates;
use Sendara\Resources\Usage;

/**
 * Sendara API client.
 *
 * @property-read Emails       $emails
 * @property-read Broadcasts   $broadcasts
 * @property-read Messages     $messages
 * @property-read Contacts     $contacts
 * @property-read Lists        $lists
 * @property-read Domains      $domains
 * @property-read Templates    $templates
 * @property-read Suppressions $suppressions
 * @property-read Usage        $usage
 * @property-read ApiKeys      $apiKeys
 * @property-read Billing      $billing
 */
final class Client
{
    public const DEFAULT_BASE_URL = 'https://api.sendara.dev';
    public const DEFAULT_TIMEOUT = 30;
    public const DEFAULT_MAX_RETRIES = 2;

    private const RETRY_BASE_DELAY_MS = 500;
    private const RETRY_MAX_DELAY_MS = 8000;
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH'];
    private const RETRIABLE_METHODS = ['GET', 'HEAD', 'PUT', 'DELETE'];

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly int $timeout;
    private readonly int $maxRetries;

    /** @var callable */
    private $transport;

    /** @var array<string, Resource> */
    private array $resources = [];

    /**
     * @param array{
     *     baseUrl?: string,
     *     timeout?: int,
     *     maxRetries?: int,
     *     transport?: callable
     * } $options
     */
    public function __construct(string $apiKey, array $options = [])
    {
        if ($apiKey === '') {
            throw new SendaraException('An API key is required');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim((string) ($options['baseUrl'] ?? self::DEFAULT_BASE_URL), '/');
        $this->timeout = (int) ($options['timeout'] ?? self::DEFAULT_TIMEOUT);
        $this->maxRetries = max(0, (int) ($options['maxRetries'] ?? self::DEFAULT_MAX_RETRIES));

        $transport = $options['transport'] ?? null;
        if ($transport !== null && !is_callable($transport)) {
            throw new SendaraException('transport must be callable');
        }
        $this->transport = $transport ?? self::curlTransport();
    }

    /**
     * Execute an API request.
     *
     * Adds Bearer auth, JSON-encodes the body, injects an Idempotency-Key for
     * write methods, retries on 429/5xx/network errors with exponential backoff,
     * and decodes the {error:{code,message}} envelope into an ApiException.
     *
     * @param array<string, mixed>|null  $body
     * @param array<string, mixed>        $query
     *
     * @return array<string, mixed>|null
     *
     * @throws ApiException
     * @throws SendaraException
     */
    public function request(string $method, string $path, ?array $body = null, array $query = []): ?array
    {
        $method = strtoupper($method);
        $url = $this->baseUrl . $path . self::buildQuery($query);

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ];

        $rawBody = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $encoded = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                throw new SendaraException('Failed to encode request body: ' . json_last_error_msg());
            }
            $rawBody = $encoded;
        }

        if (in_array($method, self::WRITE_METHODS, true)) {
            $headers['Idempotency-Key'] = self::uuid();
        }

        $idempotent = in_array($method, self::RETRIABLE_METHODS, true);
        $maxAttempts = $idempotent ? $this->maxRetries + 1 : 1;

        $lastException = null;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                $response = ($this->transport)($method, $url, $headers, $rawBody, $this->timeout);
            } catch (SendaraException $exception) {
                $lastException = $exception;
                if ($attempt < $maxAttempts - 1) {
                    self::sleepMs(self::backoffDelay($attempt, null));
                    continue;
                }
                throw $exception;
            }

            $status = (int) ($response['status'] ?? 0);
            $responseHeaders = is_array($response['headers'] ?? null) ? $response['headers'] : [];
            $responseBody = (string) ($response['body'] ?? '');

            if ($status >= 200 && $status < 300) {
                if ($status === 204 || $responseBody === '') {
                    return null;
                }
                $decoded = json_decode($responseBody, true);

                return is_array($decoded) ? $decoded : null;
            }

            $error = self::errorFromResponse($status, $responseBody, $responseHeaders);
            if ($idempotent && $attempt < $maxAttempts - 1 && self::isRetriableStatus($status)) {
                $lastException = $error;
                self::sleepMs(self::backoffDelay($attempt, self::retryAfterMs($responseHeaders)));
                continue;
            }

            throw $error;
        }

        throw $lastException ?? new SendaraException('Request failed after retries');
    }

    public function emails(): Emails
    {
        return $this->resource(Emails::class);
    }

    public function broadcasts(): Broadcasts
    {
        return $this->resource(Broadcasts::class);
    }

    public function messages(): Messages
    {
        return $this->resource(Messages::class);
    }

    public function contacts(): Contacts
    {
        return $this->resource(Contacts::class);
    }

    public function lists(): Lists
    {
        return $this->resource(Lists::class);
    }

    public function domains(): Domains
    {
        return $this->resource(Domains::class);
    }

    public function templates(): Templates
    {
        return $this->resource(Templates::class);
    }

    public function suppressions(): Suppressions
    {
        return $this->resource(Suppressions::class);
    }

    public function usage(): Usage
    {
        return $this->resource(Usage::class);
    }

    public function apiKeys(): ApiKeys
    {
        return $this->resource(ApiKeys::class);
    }

    public function billing(): Billing
    {
        return $this->resource(Billing::class);
    }

    public function __get(string $name): Resource
    {
        return match ($name) {
            'emails' => $this->emails(),
            'broadcasts' => $this->broadcasts(),
            'messages' => $this->messages(),
            'contacts' => $this->contacts(),
            'lists' => $this->lists(),
            'domains' => $this->domains(),
            'templates' => $this->templates(),
            'suppressions' => $this->suppressions(),
            'usage' => $this->usage(),
            'apiKeys' => $this->apiKeys(),
            'billing' => $this->billing(),
            default => throw new SendaraException("Unknown resource: {$name}"),
        };
    }

    /**
     * @template T of Resource
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function resource(string $class): Resource
    {
        if (!isset($this->resources[$class])) {
            $this->resources[$class] = new $class($this);
        }

        /** @var T */
        return $this->resources[$class];
    }

    /**
     * @param array<string, mixed> $query
     */
    private static function buildQuery(array $query): string
    {
        $pairs = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $pairs[$key] = $value;
        }
        if ($pairs === []) {
            return '';
        }

        return '?' . http_build_query($pairs, '', '&', PHP_QUERY_RFC3986);
    }

    private static function isRetriableStatus(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    /**
     * @param array<string, mixed> $headers
     */
    private static function retryAfterMs(array $headers): ?int
    {
        $value = self::headerValue($headers, 'retry-after');
        if ($value === null) {
            return null;
        }
        if (ctype_digit($value)) {
            return ((int) $value) * 1000;
        }
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return max(0, ($timestamp - time()) * 1000);
        }

        return null;
    }

    private static function backoffDelay(int $attempt, ?int $retryAfterMs): int
    {
        if ($retryAfterMs !== null && $retryAfterMs >= 0) {
            return $retryAfterMs;
        }
        $exp = min(self::RETRY_MAX_DELAY_MS, self::RETRY_BASE_DELAY_MS * (2 ** $attempt));
        $half = intdiv($exp, 2);

        return $half + random_int(0, $half);
    }

    private static function sleepMs(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }

    /**
     * @param array<string, mixed> $headers
     */
    private static function errorFromResponse(int $status, string $body, array $headers): ApiException
    {
        $code = 'error';
        $message = $body !== '' ? $body : ('HTTP ' . $status);

        $decoded = $body !== '' ? json_decode($body, true) : null;
        if (is_array($decoded)) {
            $envelope = $decoded['error'] ?? null;
            if (is_array($envelope)) {
                $code = (string) ($envelope['code'] ?? $code);
                $message = (string) ($envelope['message'] ?? $message);
            } elseif (isset($decoded['message'])) {
                $code = (string) ($decoded['code'] ?? $code);
                $message = (string) $decoded['message'];
            }
        }

        $requestId = self::headerValue($headers, 'x-request-id');
        $retryAfterHeader = self::headerValue($headers, 'retry-after');
        $retryAfter = $retryAfterHeader !== null && ctype_digit($retryAfterHeader)
            ? (int) $retryAfterHeader
            : null;

        return ApiException::fromResponse($status, $code, $message, $requestId, $retryAfter);
    }

    /**
     * @param array<string, mixed> $headers
     */
    private static function headerValue(array $headers, string $name): ?string
    {
        $lower = strtolower($name);
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $lower) {
                if (is_array($value)) {
                    $value = $value[0] ?? null;
                }

                return $value === null ? null : (string) $value;
            }
        }

        return null;
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Default cURL transport. Returns ['status'=>int,'headers'=>array,'body'=>string].
     * Throws SendaraException on network failure so the retry loop can act.
     */
    private static function curlTransport(): callable
    {
        return static function (
            string $method,
            string $url,
            array $headers,
            ?string $body,
            int $timeout
        ): array {
            $handle = curl_init();
            if ($handle === false) {
                throw new SendaraException('Failed to initialize cURL');
            }

            $headerLines = [];
            foreach ($headers as $name => $value) {
                $headerLines[] = $name . ': ' . $value;
            }

            $responseHeaders = [];
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headerLines,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $responseHeaders[trim($parts[0])] = trim($parts[1]);
                    }

                    return strlen($line);
                },
            ];

            if ($body !== null) {
                $options[CURLOPT_POSTFIELDS] = $body;
            }
            if ($method === 'HEAD') {
                $options[CURLOPT_NOBODY] = true;
            }

            curl_setopt_array($handle, $options);

            $responseBody = curl_exec($handle);
            if ($responseBody === false) {
                $errno = curl_errno($handle);
                $error = curl_error($handle);
                curl_close($handle);
                throw new SendaraException("Network request failed ({$errno}): {$error}");
            }

            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);

            return [
                'status' => $status,
                'headers' => $responseHeaders,
                'body' => (string) $responseBody,
            ];
        };
    }
}
