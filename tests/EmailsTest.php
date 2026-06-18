<?php

declare(strict_types=1);

namespace Sendara\Tests;

use PHPUnit\Framework\TestCase;
use Sendara\Client;

final class EmailsTest extends TestCase
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    private array $calls = [];

    /**
     * @param list<array{status:int,headers?:array<string,string>,body?:string}> $responses
     */
    private function stubTransport(array $responses): callable
    {
        $index = 0;

        return function (string $method, string $url, array $headers, ?string $body) use (&$index, $responses): array {
            $this->calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
            $response = $responses[$index] ?? $responses[count($responses) - 1];
            $index++;

            return [
                'status' => $response['status'],
                'headers' => $response['headers'] ?? [],
                'body' => $response['body'] ?? '',
            ];
        };
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function sendAndDecode(array $params): array
    {
        $client = new Client('sk_test', [
            'transport' => $this->stubTransport([
                ['status' => 200, 'body' => '{"id":"msg_1","status":"queued","channel":"email"}'],
            ]),
        ]);

        $client->emails()->send($params);

        self::assertCount(1, $this->calls);

        return json_decode((string) $this->calls[0]['body'], true);
    }

    public function testSendPostsToSendEndpoint(): void
    {
        $this->sendAndDecode([
            'from' => 'hi@acme.dev',
            'to' => 'user@example.com',
            'subject' => 'Welcome',
            'html' => '<b>hi</b>',
        ]);

        self::assertSame('POST', $this->calls[0]['method']);
        self::assertStringEndsWith('/v1/send', $this->calls[0]['url']);
    }

    public function testSendMapsCoreFieldsToDestinationPayloadAndMetadata(): void
    {
        $sent = $this->sendAndDecode([
            'from' => 'hi@acme.dev',
            'to' => 'user@example.com',
            'subject' => 'Welcome',
            'html' => '<b>hi</b>',
            'text' => 'hi',
        ]);

        self::assertSame('email', $sent['channel']);
        self::assertSame('user@example.com', $sent['destination']['email']);
        self::assertSame('Welcome', $sent['payload']['subject']);
        self::assertSame('<b>hi</b>', $sent['payload']['body_html']);
        self::assertSame('hi', $sent['payload']['body_text']);
        self::assertSame('hi@acme.dev', $sent['metadata']['from_email']);
    }

    public function testSendReturnsDecodedResponse(): void
    {
        $client = new Client('sk_test', [
            'transport' => $this->stubTransport([
                ['status' => 200, 'body' => '{"id":"msg_1","status":"queued","channel":"email"}'],
            ]),
        ]);

        $result = $client->emails()->send([
            'to' => 'user@example.com',
            'subject' => 'Welcome',
            'html' => '<b>hi</b>',
        ]);

        self::assertSame('msg_1', $result['id']);
        self::assertSame('queued', $result['status']);
    }

    public function testSendDefaultsOptionalPayloadFieldsToNull(): void
    {
        $sent = $this->sendAndDecode([
            'to' => 'user@example.com',
            'subject' => 'Only subject',
        ]);

        self::assertArrayHasKey('body_html', $sent['payload']);
        self::assertArrayHasKey('body_text', $sent['payload']);
        self::assertNull($sent['payload']['body_html']);
        self::assertNull($sent['payload']['body_text']);
    }

    public function testSendOmitsFromEmailWhenFromAbsent(): void
    {
        $sent = $this->sendAndDecode([
            'to' => 'user@example.com',
            'subject' => 'No from',
            'html' => '<b>hi</b>',
        ]);

        self::assertArrayNotHasKey('from_email', $sent['metadata']);
    }

    public function testSendMergesProvidedMetadataWithFromEmail(): void
    {
        $sent = $this->sendAndDecode([
            'from' => 'hi@acme.dev',
            'to' => 'user@example.com',
            'subject' => 'Welcome',
            'html' => '<b>hi</b>',
            'metadata' => ['campaign' => 'launch', 'tier' => 'pro'],
        ]);

        self::assertSame('launch', $sent['metadata']['campaign']);
        self::assertSame('pro', $sent['metadata']['tier']);
        self::assertSame('hi@acme.dev', $sent['metadata']['from_email']);
    }

    public function testSendMapsTemplateFields(): void
    {
        $sent = $this->sendAndDecode([
            'to' => 'user@example.com',
            'templateId' => 'tpl_1',
            'templateVars' => ['name' => 'Ada'],
        ]);

        self::assertSame('tpl_1', $sent['template_id']);
        self::assertSame(['name' => 'Ada'], $sent['template_vars']);
    }

    public function testSendMapsMessageTypeAndBooleanFlags(): void
    {
        $sent = $this->sendAndDecode([
            'to' => 'user@example.com',
            'subject' => 'Welcome',
            'html' => '<b>hi</b>',
            'messageType' => 'transactional',
            'storePayload' => false,
            'testSend' => true,
        ]);

        self::assertSame('transactional', $sent['message_type']);
        self::assertFalse($sent['store_payload']);
        self::assertTrue($sent['test_send']);
    }

    public function testSendUsesProvidedIdempotencyKeyInBody(): void
    {
        $sent = $this->sendAndDecode([
            'to' => 'user@example.com',
            'subject' => 'Welcome',
            'html' => '<b>hi</b>',
            'idempotencyKey' => 'idem-123',
        ]);

        self::assertSame('idem-123', $sent['idempotency_key']);
    }

    public function testSendGeneratesIdempotencyKeyWhenAbsent(): void
    {
        $sent = $this->sendAndDecode([
            'to' => 'user@example.com',
            'subject' => 'Welcome',
            'html' => '<b>hi</b>',
        ]);

        self::assertArrayHasKey('idempotency_key', $sent);
        self::assertNotSame('', $sent['idempotency_key']);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $sent['idempotency_key']
        );
    }

    public function testSendRawPostsRequestVerbatimAndInjectsIdempotencyKey(): void
    {
        $client = new Client('sk_test', [
            'transport' => $this->stubTransport([
                ['status' => 200, 'body' => '{"id":"msg_raw"}'],
            ]),
        ]);

        $result = $client->emails()->sendRaw([
            'channel' => 'email',
            'destination' => ['email' => 'raw@example.com'],
            'payload' => ['subject' => 'Raw'],
        ]);

        self::assertSame('msg_raw', $result['id']);

        $sent = json_decode((string) $this->calls[0]['body'], true);
        self::assertSame('raw@example.com', $sent['destination']['email']);
        self::assertArrayHasKey('idempotency_key', $sent);
        self::assertNotSame('', $sent['idempotency_key']);
    }
}
