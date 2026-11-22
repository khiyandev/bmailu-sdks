<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Aliases extends Resource
{
    /** Add an alias. POST /domains/{domain_id}/aliases */
    public function create(string|int $domainId, array $params): mixed
    {
        return $this->client->request('POST', "/domains/{$this->seg($domainId)}/aliases", $this->obj($params));
    }

    /** Remove an alias. DELETE /aliases/{alias_id} */
    public function delete(string|int $aliasId): mixed
    {
        return $this->client->request('DELETE', "/aliases/{$this->seg($aliasId)}", null);
    }
}
