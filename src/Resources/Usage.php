<?php

declare(strict_types=1);

namespace Sendara\Resources;

use Sendara\Resource;

final class Usage extends Resource
{
    /**
     * GET /v1/usage — current usage summary, optionally scoped to a billing period.
     *
     * @return array<string, mixed>
     */
    public function get(?string $period = null): array
    {
        return $this->client->request('GET', '/v1/usage', null, ['period' => $period]) ?? [];
    }
}
