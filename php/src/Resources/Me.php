<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Me extends Resource
{
    /** Who the key belongs to. GET /me */
    public function get(): mixed
    {
        return $this->client->request('GET', "/me", null);
    }

    /** Set the recovery email. PATCH /me */
    public function update(array $params): mixed
    {
        return $this->client->request('PATCH', "/me", $this->obj($params));
    }
}
