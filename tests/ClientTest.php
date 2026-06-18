<?php

declare(strict_types=1);

namespace Sendara\Tests;

use PHPUnit\Framework\TestCase;
use Sendara\Client;
use Sendara\Exception\ApiException;
use Sendara\Exception\SendaraException;

final class ClientTest extends TestCase
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string,timeout:?int}> */
    private array $calls = [];

    /**
     * @param list<array{status:int,headers?:array<string,string>,body?:string}> $responses
     */
    private function stubTransport(array $responses): callable
    {
        $index = 0;

        return function (
            string $method,
            string $url,
            array $headers,
            ?string $body,
            int $timeout = 0
        ) use (&$index, $responses): array {
            $this->calls[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $headers,
                'body' => $body,
                'timeout' => $timeout,
            ];
            $response = $responses[$index] ?? $responses[count($responses) - 1];
            $index++;

            return [
                'status' => $response['status'],
                'headers' => $response['headers'] ?? [],
                'body' => $response['body'] ?? '',
            ];
        };
    }

    public function testConstructorRejectsEmptyApiKey(): void
    {
        $this->expectException(SendaraException::class);
        $this->expectExceptionMessage('An API key is required');

        new Client('');
    }

    public function testConstructorRejectsNonCallableTransport(): void
    {
        $this->expectException(SendaraException::class);
        $this->expectExceptionMessage('transport must be callable');

        new Client('sk_test', ['transport' => 'not-callable']);
    }

    public function testRequestSendsMethodPathAuthAndAcceptHeaders(): void
    {
        $client = new Client('sk_secret_123', [
            'transport' => $this->stubTransport([
                ['status' => 200, 'body' => '{"ok":true}'],
            ]),
        ]);

        $result = $client->request('GET', '/v1/messages/msg_1');

        self::assertSame(['ok' => true], $result);
        self::assertCount(1, $this->calls);

        $call = $this->calls[0];
        self::assertSame('GET', $call['method']);
        self::assertStringEndsWith('/v1/messages/msg_1', $call['url']);
        self::assertStringStartsWith('https://api.sendara.dev', $call['url']);
        self::assertSame('Bearer sk_secret_123', $call['headers']['Authorization']);
        self::assertSame('application/json', $call['headers']['Accept']);
        self::assertNull($call['body']);
        self::assertArrayNotHasKey('Idempotency-Key', $call['headers']);
        self::assertArrayNotHasKey('Content-Type', $call['headers']);
    }

    public function testRequestHonorsCustomBaseUrlAndTimeout(): void
    {
        $client = new Client('sk_test', [
            'baseUrl' => 'https://api.example.test/',
            'timeout' => 7,
            'transport' => $this->stubTransport([
                ['status' => 200, 'body' => '{}'],
            ]),
        ]);

        $client->request('GET', '/v1/ping');

        $call = $this->calls[0];
        self::assertSame('https://api.example.test/v1/ping', $call['url']);
        self::assertSame(7, $call['timeout']);
    }

    public function testWriteRequestSetsJsonBodyContentTypeAndIdempotencyKey(): void
    {
        $client = new Client('sk_test', [
            'transport' => $this->stubTransport([
                ['status' => 201, 'body' => '{"id":"x"}'],
            ]),
        ]);

        $client->request('POST', '/v1/things', ['name' => 'Ada', 'nested' => ['a' => 1]]);

        $call = $this->calls[0];
        self::assertSame('POST', $call['method']);
        self::assertSame('application/json', $call['headers']['Content-Type']);
        self::assertArrayHasKey('Idempotency-Key', $call['headers']);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $call['headers']['Idempotency-Key']
        );

        $sent = json_decode((string) $call['body'], true);
        self::assertSame('Ada', $sent['name']);
        self::assertSame(['a' => 1], $sent['nested']);
    }

    public function testEachWriteRequestGetsUniqueIdempotencyKey(): void
    {
        $client = new Client('sk_test', [
            'transport' => $this->stubTransport([
                ['status' => 200, 'body' => '{}'],
            ]),
        ]);

        $client->request('POST', '/v1/things', ['n' => 1]);
        $client->request('POST', '/v1/things', ['n' => 2]);

        self::assertNotSame(
            $this->calls[0]['headers']['Idempotency-Key'],
            $this->calls[1]['headers']['Idempotency-Key']
        );
    }

    public function testQueryParametersAreAppendedAndEmptyValuesSkipped(): void
    {
        $client = new Client('sk_test', [
            'transport' => $this->stubTransport([
                ['status' => 200, 'body' => '{}'],
            ]),
        ]);

        $client->request('GET', '/v1/messages', null, [
            'channel' => 'email',
            'limit' => 25,
            'status' => null,
            'cursor' => '',
            'active' => true,
        ]);

        $url = $this->calls[0]['url'];
        self::assertStringContainsString('channel=email', $url);
        self::assertStringContainsString('limit=25', $url);
        self::assertStringContainsString('active=true', $url);
        self::assertStringNotContainsString('status=', $url);
        self::assertStringNotContainsString('cursor=', $url);
    }

    public function testErrorEnvelopeThrowsApiExceptionWithCodeMessageStatus(): void
    {
        $client = new Client('sk_test', [
            'transport' => $this->stubTransport([
                [
                    'status' => 422,
                    'headers' => ['X-Request-Id' => 'req_abc'],
                    'body' => '{"error":{"code":"invalid_request","message":"bad to"}}',
                ],
            ]),
        ]);

        try {
            $client->request('POST', '/v1/send', ['to' => 'x']);
            self::fail('expected ApiException');
        } catch (ApiException $exception) {
            self::assertSame(422, $exception->getStatus());
            self::assertSame('invalid_request', $exception->getErrorCode());
            self::assertSame('bad to', $exception->getMessage());
            self::assertSame('req_abc', $exception->getRequestId());
        }
    }

    public function testFlatCodeMessageStatusBodyThrowsApiException(): void
    {
        $client = new Client('sk_test', [
            'transport' => $this->stubTransport([
                [
                    'status' => 404,
                    'body' => '{"code":"not_found","message":"no such message","status":404}',
                ],
            ]),
        ]);

        try {
            $client->request('GET', '/v1/messages/nope');
            self::fail('expected ApiException');
        } catch (ApiException $exception) {
            self::assertSame(404, $exception->getStatus());
            self::assertSame('not_found', $exception->getErrorCode());
            self::assertSame('no such message', $exception->getMessage());
        }
    }

    public function testRetriesOn429ThenSucceeds(): void
    {
        $client = new Client('sk_test', [
            'maxRetries' => 2,
            'transport' => $this->stubTransport([
                [
                    'status' => 429,
                    'headers' => ['Retry-After' => '0'],
                    'body' => '{"error":{"code":"rate_limited","message":"slow down"}}',
                ],
                ['status' => 200, 'body' => '{"ok":true}'],
            ]),
        ]);

        $result = $client->request('GET', '/v1/messages');

        self::assertSame(['ok' => true], $result);
        self::assertCount(2, $this->calls);
    }

    public function testRetriesOn5xxThenSucceeds(): void
    {
        $client = new Client('sk_test', [
            'maxRetries' => 2,
            'transport' => $this->stubTransport([
                [
                    'status' => 503,
                    'headers' => ['Retry-After' => '0'],
                    'body' => '{"error":{"code":"unavailable","message":"down"}}',
                ],
                ['status' => 200, 'body' => '{"ok":true}'],
            ]),
        ]);

        $result = $client->request('GET', '/v1/messages');

        self::assertSame(['ok' => true], $result);
        self::assertCount(2, $this->calls);
    }

    public function testGivesUpAfterMaxRetriesAndThrowsLastError(): void
    {
        $client = new Client('sk_test', [
            'maxRetries' => 1,
            'transport' => $this->stubTransport([
                [
                    'status' => 429,
                    'headers' => ['Retry-After' => '0'],
                    'body' => '{"error":{"code":"rate_limited","message":"slow down"}}',
                ],
            ]),
        ]);

        try {
            $client->request('GET', '/v1/messages');
            self::fail('expected ApiException');
        } catch (ApiException $exception) {
            self::assertSame(429, $exception->getStatus());
            self::assertSame('rate_limited', $exception->getErrorCode());
        }

        self::assertCount(2, $this->calls);
    }

    public function testNonIdempotentPostIsNotRetried(): void
    {
        $client = new Client('sk_test', [
            'maxRetries' => 3,
            'transport' => $this->stubTransport([
                [
                    'status' => 503,
                    'headers' => ['Retry-After' => '0'],
                    'body' => '{"error":{"code":"unavailable","message":"down"}}',
                ],
            ]),
        ]);

        try {
            $client->request('POST', '/v1/send', ['to' => 'x']);
            self::fail('expected ApiException');
        } catch (ApiException $exception) {
            self::assertSame(503, $exception->getStatus());
        }

        self::assertCount(1, $this->calls);
    }

    public function testNoContentResponseReturnsNull(): void
    {
        $client = new Client('sk_test', [
            'transport' => $this->stubTransport([
                ['status' => 204],
            ]),
        ]);

        self::assertNull($client->request('DELETE', '/v1/things/1'));
    }

    public function testNetworkFailureRetriesThenThrowsSendaraException(): void
    {
        $attempts = 0;
        $transport = function () use (&$attempts): array {
            $attempts++;
            throw new SendaraException('Network request failed (7): connection refused');
        };

        $client = new Client('sk_test', [
            'maxRetries' => 2,
            'transport' => $transport,
        ]);

        try {
            $client->request('GET', '/v1/messages');
            self::fail('expected SendaraException');
        } catch (SendaraException $exception) {
            self::assertStringContainsString('Network request failed', $exception->getMessage());
        }

        self::assertSame(3, $attempts);
    }

    public function testLazyResourceGettersAreMemoized(): void
    {
        $client = new Client('sk_test', [
            'transport' => $this->stubTransport([['status' => 204]]),
        ]);

        self::assertSame($client->emails(), $client->emails());
        self::assertSame($client->emails(), $client->emails);
        self::assertSame($client->domains(), $client->domains);
    }

    public function testUnknownResourceThrows(): void
    {
        $client = new Client('sk_test', [
            'transport' => $this->stubTransport([['status' => 204]]),
        ]);

        $this->expectException(SendaraException::class);
        $this->expectExceptionMessage('Unknown resource: nope');

        $client->__get('nope');
    }
}
