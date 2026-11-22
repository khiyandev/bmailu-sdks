<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Logs extends Resource
{
    /** query: source, status, key, limit. GET /logs */
    public function list(array $query = []): mixed
    {
        return $this->client->request('GET', "/logs", null, $query);
    }
}
