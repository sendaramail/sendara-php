<?php

declare(strict_types=1);

namespace Sendara\Resources;

use Sendara\Resource;

final class Lists extends Resource
{
    /**
     * @param array{limit?: int, offset?: int} $params
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(array $params = []): array
    {
        $query = [
            'limit' => $params['limit'] ?? null,
            'offset' => $params['offset'] ?? null,
        ];
        $response = $this->client->request('GET', '/v1/contacts/lists', null, $query) ?? [];
        $lists = $response['lists'] ?? [];

        return is_array($lists) ? array_values($lists) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->client->request('GET', '/v1/contacts/lists/' . rawurlencode($id)) ?? [];
    }

    /**
     * @param array{
     *     name: string,
     *     listType?: string,
     *     segmentRules?: array<string, mixed>
     * } $params
     *
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        $body = self::filterNull([
            'name' => $params['name'] ?? null,
            'list_type' => $params['listType'] ?? null,
            'segment_rules' => $params['segmentRules'] ?? null,
        ]);

        return $this->client->request('POST', '/v1/contacts/lists', $body) ?? [];
    }

    /**
     * @param array{name?: string, segmentRules?: array<string, mixed>} $params
     *
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        $body = self::filterNull([
            'name' => $params['name'] ?? null,
            'segment_rules' => $params['segmentRules'] ?? null,
        ]);

        return $this->client->request('PUT', '/v1/contacts/lists/' . rawurlencode($id), $body) ?? [];
    }

    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/contacts/lists/' . rawurlencode($id));
    }

    /**
     * @return array<string, mixed>
     */
    public function addMember(string $id, string $contactId): array
    {
        return $this->client->request(
            'POST',
            '/v1/contacts/lists/' . rawurlencode($id) . '/members',
            ['contact_id' => $contactId]
        ) ?? [];
    }

    public function removeMember(string $id, string $contactId): void
    {
        $this->client->request(
            'DELETE',
            '/v1/contacts/lists/' . rawurlencode($id) . '/members/' . rawurlencode($contactId)
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function members(string $id): array
    {
        $response = $this->client->request(
            'GET',
            '/v1/contacts/lists/' . rawurlencode($id) . '/members'
        ) ?? [];
        $members = $response['members'] ?? [];

        return is_array($members) ? array_values($members) : [];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private static function filterNull(array $body): array
    {
        return array_filter($body, static fn ($value): bool => $value !== null);
    }
}
