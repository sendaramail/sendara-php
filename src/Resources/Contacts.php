<?php

declare(strict_types=1);

namespace Sendara\Resources;

use Sendara\Client;
use Sendara\Resource;

final class Contacts extends Resource
{
    private readonly Lists $listsResource;

    public function __construct(Client $client)
    {
        parent::__construct($client);
        $this->listsResource = new Lists($client);
    }

    /**
     * Access the contact-list sub-resource (mirrors Node's `contacts.lists`).
     */
    public function lists(): Lists
    {
        return $this->listsResource;
    }

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
        $response = $this->client->request('GET', '/v1/contacts', null, $query) ?? [];
        $contacts = $response['contacts'] ?? [];

        return is_array($contacts) ? array_values($contacts) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->client->request('GET', '/v1/contacts/' . rawurlencode($id)) ?? [];
    }

    /**
     * @param array{
     *     email?: string|null,
     *     phoneNumber?: string|null,
     *     deviceToken?: string|null,
     *     firstName?: string,
     *     lastName?: string,
     *     attributes?: array<string, mixed>,
     *     tags?: array<int, string>,
     *     emailConsent?: string,
     *     smsConsent?: string,
     *     pushConsent?: string,
     *     voiceConsent?: string
     * } $params
     *
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->client->request('POST', '/v1/contacts', self::body($params)) ?? [];
    }

    /**
     * @param array{
     *     email?: string|null,
     *     phoneNumber?: string|null,
     *     deviceToken?: string|null,
     *     firstName?: string,
     *     lastName?: string,
     *     attributes?: array<string, mixed>,
     *     tags?: array<int, string>,
     *     emailConsent?: string,
     *     smsConsent?: string,
     *     pushConsent?: string,
     *     voiceConsent?: string
     * } $params
     *
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        return $this->client->request('PUT', '/v1/contacts/' . rawurlencode($id), self::body($params)) ?? [];
    }

    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/contacts/' . rawurlencode($id));
    }

    /**
     * The lists a contact belongs to.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listsFor(string $id): array
    {
        $response = $this->client->request('GET', '/v1/contacts/' . rawurlencode($id) . '/lists') ?? [];
        $lists = $response['lists'] ?? [];

        return is_array($lists) ? array_values($lists) : [];
    }

    /**
     * A contact's recent activity timeline — outbound sends/events and inbound
     * emails — newest first.
     *
     * @param array{limit?: int} $params
     *
     * @return array<int, array<string, mixed>>
     */
    public function activity(string $id, array $params = []): array
    {
        $query = ['limit' => $params['limit'] ?? null];
        $response = $this->client->request(
            'GET',
            '/v1/contacts/' . rawurlencode($id) . '/activity',
            null,
            $query
        ) ?? [];
        $events = $response['events'] ?? [];

        return is_array($events) ? array_values($events) : [];
    }

    /**
     * Bulk-import contacts from a CSV/JSON file already staged in object storage.
     *
     * @param array{s3Key: string, format: string} $params
     *
     * @return array<string, mixed>
     */
    public function import(array $params): array
    {
        $body = [
            's3_key' => $params['s3Key'] ?? null,
            'format' => $params['format'] ?? null,
        ];

        return $this->client->request('POST', '/v1/contacts/import', $body) ?? [];
    }

    /**
     * Import already-mapped rows directly in the request body — no file upload.
     * Set `dryRun` to validate without writing, and optionally add every imported
     * contact to a list with `listId`.
     *
     * @param array{
     *     rows: array<int, array<string, mixed>>,
     *     listId?: string,
     *     dryRun?: bool
     * } $params
     *
     * @return array<string, mixed>
     */
    public function importInline(array $params): array
    {
        $body = self::filterNull([
            'rows' => $params['rows'] ?? [],
            'list_id' => $params['listId'] ?? null,
            'dry_run' => $params['dryRun'] ?? null,
        ]);

        return $this->client->request('POST', '/v1/contacts/import/inline', $body) ?? [];
    }

    /**
     * Apply one action to a set of contacts. Ids not owned by the account are
     * silently skipped. Returns the number of contacts affected.
     *
     * @param array{
     *     ids: array<int, string>,
     *     action: string,
     *     tag?: string,
     *     listId?: string,
     *     channel?: string,
     *     consent?: string
     * } $params
     */
    public function bulk(array $params): int
    {
        $body = self::filterNull([
            'ids' => $params['ids'] ?? [],
            'action' => $params['action'] ?? null,
            'tag' => $params['tag'] ?? null,
            'list_id' => $params['listId'] ?? null,
            'channel' => $params['channel'] ?? null,
            'consent' => $params['consent'] ?? null,
        ]);

        $response = $this->client->request('POST', '/v1/contacts/bulk', $body) ?? [];

        return (int) ($response['affected'] ?? 0);
    }

    /**
     * Count the contacts that match a set of segment rules, without creating a
     * list. Returns the matching contact count.
     *
     * @param array<string, mixed> $segmentRules
     */
    public function segmentsPreview(array $segmentRules): int
    {
        $response = $this->client->request(
            'POST',
            '/v1/contacts/segments/preview',
            ['segment_rules' => $segmentRules]
        ) ?? [];

        return (int) ($response['count'] ?? 0);
    }

    /**
     * The current contact count against the marketing plan's audience limit.
     * A `limit` of 0 means unlimited.
     *
     * @return array<string, mixed>
     */
    public function usage(): array
    {
        return $this->client->request('GET', '/v1/contacts/usage') ?? [];
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private static function body(array $params): array
    {
        return self::filterNull([
            'email' => $params['email'] ?? null,
            'phone_number' => $params['phoneNumber'] ?? null,
            'device_token' => $params['deviceToken'] ?? null,
            'first_name' => $params['firstName'] ?? null,
            'last_name' => $params['lastName'] ?? null,
            'attributes' => $params['attributes'] ?? null,
            'tags' => $params['tags'] ?? null,
            'email_consent' => $params['emailConsent'] ?? null,
            'sms_consent' => $params['smsConsent'] ?? null,
            'push_consent' => $params['pushConsent'] ?? null,
            'voice_consent' => $params['voiceConsent'] ?? null,
        ]);
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
