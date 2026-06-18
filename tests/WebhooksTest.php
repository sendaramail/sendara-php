<?php

declare(strict_types=1);

namespace Sendara\Tests;

use PHPUnit\Framework\TestCase;
use Sendara\Exception\WebhookVerificationException;
use Sendara\Webhooks;

final class WebhooksTest extends TestCase
{
    private const KNOWN_SECRET = 'whsec_test_secret';
    private const KNOWN_TIMESTAMP = '1718000000';
    private const KNOWN_BODY = '{"event_id":"evt_known","event_type":"message.delivered"}';
    private const KNOWN_SIGNATURE = 'af01640927ce547da73a0209db60b99e5f063d9351656af958be484f15c0f6ab';

    public function testSignProducesKnownVector(): void
    {
        $signature = Webhooks::sign(self::KNOWN_SECRET, self::KNOWN_TIMESTAMP, self::KNOWN_BODY);

        self::assertSame(self::KNOWN_SIGNATURE, $signature);
    }

    public function testVerifyAcceptsKnownVectorAndReturnsPayload(): void
    {
        $payload = Webhooks::verify(
            self::KNOWN_BODY,
            [
                Webhooks::SIGNATURE_HEADER => self::KNOWN_SIGNATURE,
                Webhooks::TIMESTAMP_HEADER => self::KNOWN_TIMESTAMP,
            ],
            self::KNOWN_SECRET,
            0
        );

        self::assertSame('evt_known', $payload['event_id']);
        self::assertSame('message.delivered', $payload['event_type']);
    }

    public function testVerifyRoundTripWithFreshTimestamp(): void
    {
        $timestamp = (string) time();
        $body = '{"event_id":"evt_rt","event_type":"message.opened"}';
        $signature = Webhooks::sign(self::KNOWN_SECRET, $timestamp, $body);

        $payload = Webhooks::verify($body, [
            Webhooks::SIGNATURE_HEADER => $signature,
            Webhooks::TIMESTAMP_HEADER => $timestamp,
        ], self::KNOWN_SECRET);

        self::assertSame('evt_rt', $payload['event_id']);
    }

    public function testVerifyIsCaseInsensitiveForHeaderNames(): void
    {
        $payload = Webhooks::verify(
            self::KNOWN_BODY,
            [
                'sendara-signature' => self::KNOWN_SIGNATURE,
                'SENDARA-TIMESTAMP' => self::KNOWN_TIMESTAMP,
            ],
            self::KNOWN_SECRET,
            0
        );

        self::assertSame('evt_known', $payload['event_id']);
    }

    public function testVerifyRejectsTamperedSignature(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Signature mismatch');

        Webhooks::verify(
            self::KNOWN_BODY,
            [
                Webhooks::SIGNATURE_HEADER => str_repeat('0', 64),
                Webhooks::TIMESTAMP_HEADER => self::KNOWN_TIMESTAMP,
            ],
            self::KNOWN_SECRET,
            0
        );
    }

    public function testVerifyRejectsTamperedBody(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Signature mismatch');

        Webhooks::verify(
            '{"event_id":"evt_known","event_type":"message.bounced"}',
            [
                Webhooks::SIGNATURE_HEADER => self::KNOWN_SIGNATURE,
                Webhooks::TIMESTAMP_HEADER => self::KNOWN_TIMESTAMP,
            ],
            self::KNOWN_SECRET,
            0
        );
    }

    public function testVerifyRejectsExpiredTimestamp(): void
    {
        $timestamp = (string) (time() - 301);
        $signature = Webhooks::sign(self::KNOWN_SECRET, $timestamp, self::KNOWN_BODY);

        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Timestamp outside tolerance window');

        Webhooks::verify(self::KNOWN_BODY, [
            Webhooks::SIGNATURE_HEADER => $signature,
            Webhooks::TIMESTAMP_HEADER => $timestamp,
        ], self::KNOWN_SECRET, 300);
    }

    public function testVerifyRejectsFutureTimestampBeyondTolerance(): void
    {
        $timestamp = (string) (time() + 301);
        $signature = Webhooks::sign(self::KNOWN_SECRET, $timestamp, self::KNOWN_BODY);

        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Timestamp outside tolerance window');

        Webhooks::verify(self::KNOWN_BODY, [
            Webhooks::SIGNATURE_HEADER => $signature,
            Webhooks::TIMESTAMP_HEADER => $timestamp,
        ], self::KNOWN_SECRET, 300);
    }

    public function testVerifyRejectsMissingSignatureHeader(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Missing ' . Webhooks::SIGNATURE_HEADER . ' header');

        Webhooks::verify(self::KNOWN_BODY, [
            Webhooks::TIMESTAMP_HEADER => self::KNOWN_TIMESTAMP,
        ], self::KNOWN_SECRET, 0);
    }

    public function testVerifyRejectsMissingTimestampHeader(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Missing ' . Webhooks::TIMESTAMP_HEADER . ' header');

        Webhooks::verify(self::KNOWN_BODY, [
            Webhooks::SIGNATURE_HEADER => self::KNOWN_SIGNATURE,
        ], self::KNOWN_SECRET, 0);
    }

    public function testVerifyRejectsEmptySecret(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('A signing secret is required');

        Webhooks::verify(self::KNOWN_BODY, [
            Webhooks::SIGNATURE_HEADER => self::KNOWN_SIGNATURE,
            Webhooks::TIMESTAMP_HEADER => self::KNOWN_TIMESTAMP,
        ], '', 0);
    }

    public function testVerifyRejectsNonNumericTimestamp(): void
    {
        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Invalid timestamp header');

        Webhooks::verify(self::KNOWN_BODY, [
            Webhooks::SIGNATURE_HEADER => self::KNOWN_SIGNATURE,
            Webhooks::TIMESTAMP_HEADER => 'not-a-number',
        ], self::KNOWN_SECRET, 300);
    }

    public function testVerifyRejectsNonJsonBody(): void
    {
        $body = 'this is not json';
        $timestamp = (string) time();
        $signature = Webhooks::sign(self::KNOWN_SECRET, $timestamp, $body);

        $this->expectException(WebhookVerificationException::class);
        $this->expectExceptionMessage('Body is not valid JSON');

        Webhooks::verify($body, [
            Webhooks::SIGNATURE_HEADER => $signature,
            Webhooks::TIMESTAMP_HEADER => $timestamp,
        ], self::KNOWN_SECRET);
    }
}
