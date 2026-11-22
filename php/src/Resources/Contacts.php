<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Contacts extends Resource
{
    /** query: segment_id, status, q, limit, position. GET /contacts */
    public function list(array $query = []): mixed
    {
        return $this->client->request('GET', "/contacts", null, $query);
    }

    /** Add or update a contact (by email). POST /contacts */
    public function create(array $params): mixed
    {
        return $this->client->request('POST', "/contacts", $this->obj($params));
    }

    /** params: csv, mode, from, consent_statement, segment_id. POST /contacts/import */
    public function importCsv(array $params): mixed
    {
        return $this->client->request('POST', "/contacts/import", $this->obj($params));
    }

    /** Get a contact (id or email). GET /contacts/{contact} */
    public function get(string|int $contact): mixed
    {
        return $this->client->request('GET', "/contacts/{$this->seg($contact)}", null);
    }

    /** Change a contact, (un)subscribe, set topics. PATCH /contacts/{contact} */
    public function update(string|int $contact, array $params): mixed
    {
        return $this->client->request('PATCH', "/contacts/{$this->seg($contact)}", $this->obj($params));
    }

    /** Delete a contact. DELETE /contacts/{contact} */
    public function delete(string|int $contact): mixed
    {
        return $this->client->request('DELETE', "/contacts/{$this->seg($contact)}", null);
    }
}
