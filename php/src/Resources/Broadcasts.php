<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Broadcasts extends Resource
{
    /** List broadcasts. GET /broadcasts */
    public function list(): mixed
    {
        return $this->client->request('GET', "/broadcasts", null);
    }

    /** Create a draft broadcast. POST /broadcasts */
    public function create(array $params): mixed
    {
        return $this->client->request('POST', "/broadcasts", $this->obj($params));
    }

    /** Get a broadcast. GET /broadcasts/{broadcast_id} */
    public function get(string|int $broadcastId): mixed
    {
        return $this->client->request('GET', "/broadcasts/{$this->seg($broadcastId)}", null);
    }

    /** Change a draft or rejected broadcast. PATCH /broadcasts/{broadcast_id} */
    public function update(string|int $broadcastId, array $params): mixed
    {
        return $this->client->request('PATCH', "/broadcasts/{$this->seg($broadcastId)}", $this->obj($params));
    }

    /** Delete a broadcast. DELETE /broadcasts/{broadcast_id} */
    public function delete(string|int $broadcastId): mixed
    {
        return $this->client->request('DELETE', "/broadcasts/{$this->seg($broadcastId)}", null);
    }

    /** Send or schedule. params: scheduled_at. POST /broadcasts/{broadcast_id}/send */
    public function send(string|int $broadcastId, array $params = []): mixed
    {
        return $this->client->request('POST', "/broadcasts/{$this->seg($broadcastId)}/send", $this->obj($params));
    }

    /** Cancel (back to draft). POST /broadcasts/{broadcast_id}/cancel */
    public function cancel(string|int $broadcastId): mixed
    {
        return $this->client->request('POST', "/broadcasts/{$this->seg($broadcastId)}/cancel", null);
    }

    /** Send one test copy. POST /broadcasts/{broadcast_id}/test */
    public function test(string|int $broadcastId, string $to): mixed
    {
        return $this->client->request('POST', "/broadcasts/{$this->seg($broadcastId)}/test", ['to' => $to]);
    }

    /** Delivery, open, click and unsubscribe counts. GET /broadcasts/{broadcast_id}/stats */
    public function stats(string|int $broadcastId): mixed
    {
        return $this->client->request('GET', "/broadcasts/{$this->seg($broadcastId)}/stats", null);
    }
}
