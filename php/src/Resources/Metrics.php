<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Metrics extends Resource
{
    /** query: days, domain. GET /metrics */
    public function get(array $query = []): mixed
    {
        return $this->client->request('GET', "/metrics", null, $query);
    }
}
