<?php

declare(strict_types=1);

namespace Sendara\Resources;

use Sendara\Resource;

final class Templates extends Resource
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $response = $this->client->request('GET', '/v1/templates') ?? [];
        $templates = $response['templates'] ?? [];

        return is_array($templates) ? array_values($templates) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->client->request('GET', '/v1/templates/' . rawurlencode($id)) ?? [];
    }

    /**
     * @param array{
     *     name?: string,
     *     channel?: string,
     *     subject?: string,
     *     bodyText?: string,
     *     bodyHtml?: string,
     *     bodyJson?: array<string, mixed>,
     *     variables?: array<int, string>
     * } $params
     *
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->client->request('POST', '/v1/templates', [
            'name' => $params['name'] ?? null,
            'channel' => $params['channel'] ?? null,
            'subject' => $params['subject'] ?? null,
            'body_text' => $params['bodyText'] ?? null,
            'body_html' => $params['bodyHtml'] ?? null,
            'body_json' => $params['bodyJson'] ?? null,
            'variables' => $params['variables'] ?? null,
        ]) ?? [];
    }

    /**
     * @param array{
     *     name?: string,
     *     subject?: string,
     *     bodyText?: string,
     *     bodyHtml?: string,
     *     bodyJson?: array<string, mixed>,
     *     variables?: array<int, string>,
     *     isActive?: bool
     * } $params
     *
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        return $this->client->request('PUT', '/v1/templates/' . rawurlencode($id), [
            'name' => $params['name'] ?? null,
            'subject' => $params['subject'] ?? null,
            'body_text' => $params['bodyText'] ?? null,
            'body_html' => $params['bodyHtml'] ?? null,
            'body_json' => $params['bodyJson'] ?? null,
            'variables' => $params['variables'] ?? null,
            'is_active' => $params['isActive'] ?? null,
        ]) ?? [];
    }

    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/templates/' . rawurlencode($id));
    }

    /**
     * @param array<string, mixed> $vars
     *
     * @return array<string, mixed>
     */
    public function render(string $id, array $vars = []): array
    {
        return $this->client->request(
            'POST',
            '/v1/templates/' . rawurlencode($id) . '/render',
            ['vars' => $vars]
        ) ?? [];
    }
}
