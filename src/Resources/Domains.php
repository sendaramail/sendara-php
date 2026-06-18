<?php

declare(strict_types=1);

namespace Sendara\Resources;

use Sendara\Resource;

final class Domains extends Resource
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $response = $this->client->request('GET', '/v1/domains') ?? [];
        $domains = $response['domains'] ?? [];

        return is_array($domains) ? array_values($domains) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $domain): array
    {
        return $this->client->request('POST', '/v1/domains', ['domain' => $domain]) ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $domain): array
    {
        return $this->client->request('GET', '/v1/domains/' . rawurlencode($domain)) ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $domain): array
    {
        return $this->client->request('POST', '/v1/domains/' . rawurlencode($domain) . '/verify') ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getBimi(string $domain): array
    {
        return $this->client->request('GET', '/v1/domains/' . rawurlencode($domain) . '/bimi') ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function setBimi(string $domain, string $logoUrl): array
    {
        return $this->client->request(
            'PUT',
            '/v1/domains/' . rawurlencode($domain) . '/bimi',
            ['logo_url' => $logoUrl]
        ) ?? [];
    }

    /**
     * Upload a BIMI SVG logo (raw SVG bytes, <=1 MiB) as a base64 data payload.
     *
     * @return array<string, mixed>
     */
    public function uploadBimiLogo(string $domain, string $svg, string $filename = 'logo.svg'): array
    {
        return $this->client->request(
            'POST',
            '/v1/domains/' . rawurlencode($domain) . '/bimi/logo',
            [
                'filename' => $filename,
                'content_type' => 'image/svg+xml',
                'data' => base64_encode($svg),
            ]
        ) ?? [];
    }
}
