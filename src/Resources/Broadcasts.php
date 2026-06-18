<?php

declare(strict_types=1);

namespace Sendara\Resources;

use Sendara\Resource;

final class Broadcasts extends Resource
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
        $response = $this->client->request('GET', '/v1/broadcasts', null, $query) ?? [];
        $broadcasts = $response['broadcasts'] ?? [];

        return is_array($broadcasts) ? array_values($broadcasts) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->client->request('GET', '/v1/broadcasts/' . rawurlencode($id)) ?? [];
    }

    /**
     * @param array{
     *     name?: string,
     *     fromEmail: string,
     *     subject?: string,
     *     bodyHtml?: string,
     *     bodyText?: string,
     *     templateId?: string,
     *     messageType?: string,
     *     audienceListId?: string,
     *     recipients?: array<int, array<string, mixed>>,
     *     scheduledAt?: string,
     *     sendNow?: bool
     * } $params
     *
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->client->request('POST', '/v1/broadcasts', self::body($params)) ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function send(string $id): array
    {
        return $this->client->request('POST', '/v1/broadcasts/' . rawurlencode($id) . '/send') ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function cancel(string $id): array
    {
        return $this->client->request('POST', '/v1/broadcasts/' . rawurlencode($id) . '/cancel') ?? [];
    }

    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/broadcasts/' . rawurlencode($id));
    }

    /**
     * POST /v1/send/bulk — create and immediately fan out a broadcast.
     *
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function bulkSend(array $params): array
    {
        return $this->client->request('POST', '/v1/send/bulk', self::body($params)) ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private static function body(array $params): array
    {
        return [
            'name' => $params['name'] ?? null,
            'from_email' => $params['fromEmail'] ?? null,
            'subject' => $params['subject'] ?? null,
            'body_html' => $params['bodyHtml'] ?? null,
            'body_text' => $params['bodyText'] ?? null,
            'template_id' => $params['templateId'] ?? null,
            'message_type' => $params['messageType'] ?? null,
            'audience_list_id' => $params['audienceListId'] ?? null,
            'recipients' => $params['recipients'] ?? null,
            'scheduled_at' => $params['scheduledAt'] ?? null,
            'send_now' => $params['sendNow'] ?? null,
        ];
    }
}
