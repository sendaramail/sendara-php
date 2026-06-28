<?php

declare(strict_types=1);

namespace Sendara\Tests;

use PHPUnit\Framework\TestCase;
use Sendara\Client;

final class MessagesTest extends TestCase
{
    /** @var list<array{method:string,url:string}> */
    private array $calls = [];

    /**
     * @param list<array{status:int,body?:string}> $responses
     */
    private function stubTransport(array $responses): callable
    {
        $index = 0;

        return function (string $method, string $url, array $headers, ?string $body) use (&$index, $responses): array {
            $this->calls[] = ['method' => $method, 'url' => $url];
            $response = $responses[$index] ?? $responses[count($responses) - 1];
            $index++;

            return [
                'status' => $response['status'],
                'headers' => [],
                'body' => $response['body'] ?? '',
            ];
        };
    }

    private function client(callable $transport): Client
    {
        return new Client('sk_test', ['transport' => $transport]);
    }

    public function testListForwardsSearchAndStatus(): void
    {
        $client = $this->client($this->stubTransport([
            ['status' => 200, 'body' => '{"messages":[{"id":"m1"}],"next_cursor":null}'],
        ]));

        $page = $client->messages->page(['search' => 'welcome', 'status' => 'delivered']);

        self::assertSame('GET', $this->calls[0]['method']);
        self::assertStringContainsString('search=welcome', $this->calls[0]['url']);
        self::assertStringContainsString('status=delivered', $this->calls[0]['url']);
        self::assertSame('m1', $page->messages[0]['id']);
    }

    public function testGetByIdHitsPathEndpoint(): void
    {
        $client = $this->client($this->stubTransport([
            ['status' => 200, 'body' => '{"id":"m1","status":"delivered","events":[]}'],
        ]));

        $message = $client->messages->get(['id' => 'm1']);

        self::assertSame('GET', $this->calls[0]['method']);
        self::assertStringEndsWith('/v1/messages/m1', $this->calls[0]['url']);
        self::assertSame('m1', $message['id']);
    }

    public function testGetByIdempotencyKeyUsesQuery(): void
    {
        $client = $this->client($this->stubTransport([
            ['status' => 200, 'body' => '{"id":"m1","idempotency_key":"k1","events":[]}'],
        ]));

        $message = $client->messages->get(['idempotency_key' => 'k1']);

        self::assertSame('GET', $this->calls[0]['method']);
        self::assertStringContainsString('/v1/messages?', $this->calls[0]['url']);
        self::assertStringContainsString('idempotency_key=k1', $this->calls[0]['url']);
        self::assertSame('m1', $message['id']);
    }

    public function testGetRequiresIdOrIdempotencyKey(): void
    {
        $client = $this->client($this->stubTransport([
            ['status' => 200, 'body' => '{}'],
        ]));

        $this->expectException(\Sendara\Exception\SendaraException::class);
        $client->messages->get([]);
    }
}
