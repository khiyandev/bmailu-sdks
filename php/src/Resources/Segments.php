<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Segments extends Resource
{
    /** List segments. GET /segments */
    public function list(): mixed
    {
        return $this->client->request('GET', "/segments", null);
    }

    /** Create a segment. POST /segments */
    public function create(array $params): mixed
    {
        return $this->client->request('POST', "/segments", $this->obj($params));
    }

    /** Get a segment. GET /segments/{segment_id} */
    public function get(string|int $segmentId): mixed
    {
        return $this->client->request('GET', "/segments/{$this->seg($segmentId)}", null);
    }

    /** Rename a segment. PATCH /segments/{segment_id} */
    public function update(string|int $segmentId, array $params): mixed
    {
        return $this->client->request('PATCH', "/segments/{$this->seg($segmentId)}", $this->obj($params));
    }

    /** Delete a segment. DELETE /segments/{segment_id} */
    public function delete(string|int $segmentId): mixed
    {
        return $this->client->request('DELETE', "/segments/{$this->seg($segmentId)}", null);
    }

    /** Add contacts (ids or emails). POST /segments/{segment_id}/contacts */
    public function addContacts(string|int $segmentId, array $contacts): mixed
    {
        return $this->client->request('POST', "/segments/{$this->seg($segmentId)}/contacts", ['contacts' => $contacts]);
    }

    /** Remove a contact. DELETE /segments/{segment_id}/contacts/{contact} */
    public function removeContact(string|int $segmentId, string|int $contact): mixed
    {
        return $this->client->request('DELETE', "/segments/{$this->seg($segmentId)}/contacts/{$this->seg($contact)}", null);
    }
}
