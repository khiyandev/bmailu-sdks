<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Automations extends Resource
{
    /** List automations. GET /automations */
    public function list(): mixed
    {
        return $this->client->request('GET', "/automations", null);
    }

    /** Create an automation (draft). POST /automations */
    public function create(array $params): mixed
    {
        return $this->client->request('POST', "/automations", $this->obj($params));
    }

    /** Get an automation. GET /automations/{automation_id} */
    public function get(string|int $automationId): mixed
    {
        return $this->client->request('GET', "/automations/{$this->seg($automationId)}", null);
    }

    /** Change a stopped or draft automation. PATCH /automations/{automation_id} */
    public function update(string|int $automationId, array $params): mixed
    {
        return $this->client->request('PATCH', "/automations/{$this->seg($automationId)}", $this->obj($params));
    }

    /** Delete a stopped automation. DELETE /automations/{automation_id} */
    public function delete(string|int $automationId): mixed
    {
        return $this->client->request('DELETE', "/automations/{$this->seg($automationId)}", null);
    }

    /** Start it. POST /automations/{automation_id}/start */
    public function start(string|int $automationId): mixed
    {
        return $this->client->request('POST', "/automations/{$this->seg($automationId)}/start", null);
    }

    /** Stop it and every run. POST /automations/{automation_id}/stop */
    public function stop(string|int $automationId): mixed
    {
        return $this->client->request('POST', "/automations/{$this->seg($automationId)}/stop", null);
    }

    /** Copy it as a draft. POST /automations/{automation_id}/duplicate */
    public function duplicate(string|int $automationId): mixed
    {
        return $this->client->request('POST', "/automations/{$this->seg($automationId)}/duplicate", null);
    }

    /** query: status. GET /automations/{automation_id}/runs */
    public function runs(string|int $automationId, array $query = []): mixed
    {
        return $this->client->request('GET', "/automations/{$this->seg($automationId)}/runs", null, $query);
    }

    /** One run with its step log. GET /automations/{automation_id}/runs/{run_id} */
    public function getRun(string|int $automationId, string|int $runId): mixed
    {
        return $this->client->request('GET', "/automations/{$this->seg($automationId)}/runs/{$this->seg($runId)}", null);
    }
}
