<?php

declare(strict_types=1);

namespace Sendara\Resources;

use Generator;
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
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->client->request('GET', '/v1/messages/' . rawurlencode($id)) ?? [];
    }
}
