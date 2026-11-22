<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Webhooks extends Resource
{
    /** List webhook endpoints. GET /webhooks */
    public function list(): mixed
    {
        return $this->client->request('GET', "/webhooks", null);
    }

    /** Add a webhook endpoint. POST /webhooks */
    public function create(array $params): mixed
    {
        return $this->client->request('POST', "/webhooks", $this->obj($params));
    }

    /** Get a webhook (with its signing secret). GET /webhooks/{webhook_id} */
    public function get(string|int $webhookId): mixed
    {
        return $this->client->request('GET', "/webhooks/{$this->seg($webhookId)}", null);
    }

    /** Change a webhook. PATCH /webhooks/{webhook_id} */
    public function update(string|int $webhookId, array $params): mixed
    {
        return $this->client->request('PATCH', "/webhooks/{$this->seg($webhookId)}", $this->obj($params));
    }

    /** Delete a webhook. DELETE /webhooks/{webhook_id} */
    public function delete(string|int $webhookId): mixed
    {
        return $this->client->request('DELETE', "/webhooks/{$this->seg($webhookId)}", null);
    }

    /** New signing secret. POST /webhooks/{webhook_id}/rotate-secret */
    public function rotateSecret(string|int $webhookId): mixed
    {
        return $this->client->request('POST', "/webhooks/{$this->seg($webhookId)}/rotate-secret", null);
    }

    /** Send a test email.delivered event. POST /webhooks/{webhook_id}/test */
    public function test(string|int $webhookId): mixed
    {
        return $this->client->request('POST', "/webhooks/{$this->seg($webhookId)}/test", null);
    }

    /** Latest 100 deliveries. GET /webhooks/{webhook_id}/deliveries */
    public function deliveries(string|int $webhookId): mixed
    {
        return $this->client->request('GET', "/webhooks/{$this->seg($webhookId)}/deliveries", null);
    }

    /** Deliver an event again. POST /webhook-deliveries/{delivery_id}/replay */
    public function replayDelivery(string|int $deliveryId): mixed
    {
        return $this->client->request('POST', "/webhook-deliveries/{$this->seg($deliveryId)}/replay", null);
    }
}
