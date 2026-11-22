<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Events extends Resource
{
    /** params: event, email or contact_id, first_name, data. POST /events */
    public function send(array $params): mixed
    {
        return $this->client->request('POST', "/events", $this->obj($params));
    }

    /** query: contact. GET /events */
    public function list(array $query = []): mixed
    {
        return $this->client->request('GET', "/events", null, $query);
    }
}
