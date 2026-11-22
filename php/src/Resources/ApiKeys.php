<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class ApiKeys extends Resource
{
    /** List API keys. GET /api-keys */
    public function list(): mixed
    {
        return $this->client->request('GET', "/api-keys", null);
    }

    /** Create an API key (shown once). POST /api-keys */
    public function create(array $params): mixed
    {
        return $this->client->request('POST', "/api-keys", $this->obj($params));
    }

    /** Revoke an API key. DELETE /api-keys/{key_id} */
    public function revoke(string|int $keyId): mixed
    {
        return $this->client->request('DELETE', "/api-keys/{$this->seg($keyId)}", null);
    }
}
