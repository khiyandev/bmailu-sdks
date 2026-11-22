<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Templates extends Resource
{
    /** List templates. GET /templates */
    public function list(): mixed
    {
        return $this->client->request('GET', "/templates", null);
    }

    /** Create a template. POST /templates */
    public function create(array $params): mixed
    {
        return $this->client->request('POST', "/templates", $this->obj($params));
    }

    /** Get a template (id or alias). GET /templates/{template} */
    public function get(string|int $template): mixed
    {
        return $this->client->request('GET', "/templates/{$this->seg($template)}", null);
    }

    /** Change the draft. PATCH /templates/{template} */
    public function update(string|int $template, array $params): mixed
    {
        return $this->client->request('PATCH', "/templates/{$this->seg($template)}", $this->obj($params));
    }

    /** Delete a template and its versions. DELETE /templates/{template} */
    public function delete(string|int $template): mixed
    {
        return $this->client->request('DELETE', "/templates/{$this->seg($template)}", null);
    }

    /** Publish the draft as a new version. POST /templates/{template}/publish */
    public function publish(string|int $template): mixed
    {
        return $this->client->request('POST', "/templates/{$this->seg($template)}/publish", null);
    }

    /** Copy a template. POST /templates/{template}/duplicate */
    public function duplicate(string|int $template): mixed
    {
        return $this->client->request('POST', "/templates/{$this->seg($template)}/duplicate", null);
    }

    /** List published versions. GET /templates/{template}/versions */
    public function versions(string|int $template): mixed
    {
        return $this->client->request('GET', "/templates/{$this->seg($template)}/versions", null);
    }

    /** Make an earlier version the published one. POST /templates/{template}/versions/{version}/publish */
    public function publishVersion(string|int $template, string|int $version): mixed
    {
        return $this->client->request('POST', "/templates/{$this->seg($template)}/versions/{$this->seg($version)}/publish", null);
    }

    /** params: variables, version. POST /templates/{template}/render */
    public function render(string|int $template, array $params = []): mixed
    {
        return $this->client->request('POST', "/templates/{$this->seg($template)}/render", $this->obj($params));
    }

    /** HTML preview with fallback values. GET /templates/{template}/preview.html */
    public function preview(string|int $template): mixed
    {
        return $this->client->request('GET', "/templates/{$this->seg($template)}/preview.html", null);
    }
}
