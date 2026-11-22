<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Emails extends Resource
{
    /** Send an email. Returns {id}. POST /emails */
    public function send(array $params, ?string $idempotencyKey = null): mixed
    {
        return $this->client->request('POST', "/emails", $this->obj($params), [], $this->idem($idempotencyKey));
    }

    /** Send up to 100 emails. Returns {data: [{id}]}. POST /emails/batch */
    public function batch(array $emails, ?string $idempotencyKey = null): mixed
    {
        return $this->client->request('POST', "/emails/batch", array_values($emails), [], $this->idem($idempotencyKey));
    }

    /** Get an email with per-recipient status and events. GET /emails/{email_id} */
    public function get(string|int $emailId): mixed
    {
        return $this->client->request('GET', "/emails/{$this->seg($emailId)}", null);
    }

    /** query: limit, before, status, source, key, domain, q, days. GET /emails */
    public function list(array $query = []): mixed
    {
        return $this->client->request('GET', "/emails", null, $query);
    }

    /** CSV text of the filtered list. GET /emails/export */
    public function export(array $query = []): mixed
    {
        return $this->client->request('GET', "/emails/export", null, $query);
    }

    /** Reschedule: {scheduled_at}. PATCH /emails/{email_id} */
    public function update(string|int $emailId, array $params): mixed
    {
        return $this->client->request('PATCH', "/emails/{$this->seg($emailId)}", $this->obj($params));
    }

    /** Reschedule a scheduled email. PATCH /emails/{email_id} */
    public function reschedule(string|int $emailId, string $scheduledAt): mixed
    {
        return $this->client->request('PATCH', "/emails/{$this->seg($emailId)}", ['scheduled_at' => $scheduledAt]);
    }

    /** Cancel a scheduled email. POST /emails/{email_id}/cancel */
    public function cancel(string|int $emailId): mixed
    {
        return $this->client->request('POST', "/emails/{$this->seg($emailId)}/cancel", null);
    }
}
