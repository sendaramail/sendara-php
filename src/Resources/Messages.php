<?php

declare(strict_types=1);

namespace Sendara\Resources;

use Generator;
use Sendara\Exception\SendaraException;
use Sendara\MessagePage;
use Sendara\Resource;

final class Messages extends Resource
{
    /**
     * Fetch a single page of messages plus the cursor for the next page.
     *
     * @param array{
     *     channel?: string,
     *     status?: string,
     *     search?: string,
     *     from?: string,
     *     to?: string,
     *     limit?: int,
     *     cursor?: string
     * } $params
     */
    public function page(array $params = []): MessagePage
    {
        $query = [
            'channel' => $params['channel'] ?? null,
            'status' => $params['status'] ?? null,
            'search' => $params['search'] ?? null,
            'from' => $params['from'] ?? null,
            'to' => $params['to'] ?? null,
            'limit' => $params['limit'] ?? null,
            'cursor' => $params['cursor'] ?? null,
        ];

        $response = $this->client->request('GET', '/v1/messages', null, $query) ?? [];

        return MessagePage::fromArray($response);
    }

    /**
     * Iterate every matching message, auto-paginating through next_cursor.
     *
     * @param array<string, mixed> $params
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function list(array $params = []): Generator
    {
        $cursor = $params['cursor'] ?? null;
        while (true) {
            $page = $this->page([...$params, 'cursor' => $cursor]);
            foreach ($page->messages as $message) {
                yield $message;
            }
            if ($page->nextCursor === null) {
                return;
            }
            $cursor = $page->nextCursor;
        }
    }

    /**
     * Fetch a single message with its full event timeline, by id or by the
     * idempotency key supplied on the original send.
     *
     * @param array{id?: string, idempotency_key?: string} $params
     *
     * @return array<string, mixed>
     */
    public function get(array $params): array
    {
        $idempotencyKey = $params['idempotency_key'] ?? null;
        if (is_string($idempotencyKey) && $idempotencyKey !== '') {
            return $this->client->request('GET', '/v1/messages', null, ['idempotency_key' => $idempotencyKey]) ?? [];
        }

        $id = $params['id'] ?? null;
        if (is_string($id) && $id !== '') {
            return $this->client->request('GET', '/v1/messages/' . rawurlencode($id)) ?? [];
        }

        throw new SendaraException('messages->get requires id or idempotency_key');
    }
}
