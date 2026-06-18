<?php

declare(strict_types=1);

namespace Sendara\Resources;

use Sendara\Resource;

final class ApiKeys extends Resource
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $response = $this->client->request('GET', '/v1/keys') ?? [];
        $keys = $response['keys'] ?? [];

        return is_array($keys) ? array_values($keys) : [];
    }

    /**
     * @param array{scope?: string, testMode?: bool} $params
     *
     * @return array<string, mixed>
     */
    public function create(array $params = []): array
    {
        return $this->client->request('POST', '/v1/keys', [
            'scope' => $params['scope'] ?? null,
            'test_mode' => $params['testMode'] ?? null,
        ]) ?? [];
    }

    /**
     * Rotate a key and return its new plaintext secret.
     */
    public function rotate(string $id): string
    {
        $response = $this->client->request('POST', '/v1/keys/' . rawurlencode($id) . '/rotate') ?? [];

        return (string) ($response['key'] ?? '');
    }

    public function revoke(string $id): void
    {
        $this->client->request('DELETE', '/v1/keys/' . rawurlencode($id));
    }
}
