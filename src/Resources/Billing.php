<?php

declare(strict_types=1);

namespace Sendara\Resources;

use Sendara\Resource;

final class Billing extends Resource
{
    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->client->request('GET', '/v1/billing') ?? [];
    }

    /**
     * Start a checkout session and return the hosted checkout URL.
     */
    public function checkout(?string $plan = null): string
    {
        $body = $plan !== null ? ['plan' => $plan] : [];
        $response = $this->client->request('POST', '/v1/billing/checkout', $body) ?? [];

        return (string) ($response['url'] ?? '');
    }

    /**
     * Open the billing portal and return its URL.
     */
    public function portal(): string
    {
        $response = $this->client->request('POST', '/v1/billing/portal') ?? [];

        return (string) ($response['url'] ?? '');
    }
}
