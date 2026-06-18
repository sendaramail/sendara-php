<?php

declare(strict_types=1);

namespace Sendara\Resources;

use Sendara\Resource;

final class Suppressions extends Resource
{
    /**
     * @param array{channel?: string} $params
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(array $params = []): array
    {
        $query = ['channel' => $params['channel'] ?? null];
        $response = $this->client->request('GET', '/v1/suppressions', null, $query) ?? [];
        $suppressions = $response['suppressions'] ?? [];

        return is_array($suppressions) ? array_values($suppressions) : [];
    }

    /**
     * @param array{channel: string, recipient: string, reason?: string} $params
     *
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        $body = [
            'channel' => $params['channel'] ?? null,
            'recipient' => $params['recipient'] ?? null,
        ];
        if (($params['reason'] ?? null) !== null) {
            $body['reason'] = $params['reason'];
        }

        return $this->client->request('POST', '/v1/suppressions', $body) ?? [];
    }

    public function delete(string $channel, string $recipient): void
    {
        $this->client->request(
            'DELETE',
            '/v1/suppressions',
            null,
            ['channel' => $channel, 'recipient' => $recipient]
        );
    }
}
