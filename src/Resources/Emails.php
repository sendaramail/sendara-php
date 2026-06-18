<?php

declare(strict_types=1);

namespace Sendara\Resources;

use Sendara\Client;
use Sendara\Resource;

final class Emails extends Resource
{
    /**
     * Send a transactional email.
     *
     * Mirrors the Node SDK field mapping exactly:
     *   from        -> metadata.from_email
     *   to          -> destination.email
     *   subject     -> payload.subject
     *   html        -> payload.body_html
     *   text        -> payload.body_text
     *   templateId  -> template_id
     *   templateVars-> template_vars
     *
     * @param array{
     *     to: string,
     *     from?: string,
     *     subject?: string,
     *     html?: string,
     *     text?: string,
     *     messageType?: string,
     *     templateId?: string,
     *     templateVars?: array<string, mixed>,
     *     metadata?: array<string, mixed>,
     *     storePayload?: bool,
     *     testSend?: bool,
     *     idempotencyKey?: string
     * } $params
     *
     * @return array<string, mixed>
     */
    public function send(array $params): array
    {
        $metadata = $params['metadata'] ?? [];
        if (isset($params['from'])) {
            $metadata['from_email'] = $params['from'];
        }

        $request = [
            'channel' => 'email',
            'idempotency_key' => $params['idempotencyKey'] ?? Client::uuid(),
            'destination' => ['email' => $params['to']],
            'payload' => [
                'subject' => $params['subject'] ?? null,
                'body_html' => $params['html'] ?? null,
                'body_text' => $params['text'] ?? null,
            ],
            'metadata' => $metadata,
        ];

        if (isset($params['messageType'])) {
            $request['message_type'] = $params['messageType'];
        }
        if (isset($params['templateId'])) {
            $request['template_id'] = $params['templateId'];
        }
        if (isset($params['templateVars'])) {
            $request['template_vars'] = $params['templateVars'];
        }
        if (array_key_exists('storePayload', $params)) {
            $request['store_payload'] = $params['storePayload'];
        }
        if (array_key_exists('testSend', $params)) {
            $request['test_send'] = $params['testSend'];
        }

        return $this->sendRaw($request);
    }

    /**
     * Raw send escape hatch — POST /v1/send with a fully-formed request body.
     * Injects an idempotency_key when absent.
     *
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    public function sendRaw(array $request): array
    {
        if (empty($request['idempotency_key'])) {
            $request['idempotency_key'] = Client::uuid();
        }

        return $this->client->request('POST', '/v1/send', $request) ?? [];
    }
}
