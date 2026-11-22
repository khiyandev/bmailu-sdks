<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Suppressions extends Resource
{
    /** query: email. GET /suppressions */
    public function list(array $query = []): mixed
    {
        return $this->client->request('GET', "/suppressions", null, $query);
    }

    /** Suppress an address. POST /suppressions */
    public function create(array $params): mixed
    {
        return $this->client->request('POST', "/suppressions", $this->obj($params));
    }

    /** Remove an address. DELETE /suppressions/{email} */
    public function delete(string|int $email): mixed
    {
        return $this->client->request('DELETE', "/suppressions/{$this->seg($email)}", null);
    }
}
