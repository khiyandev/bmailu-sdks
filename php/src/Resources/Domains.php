<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Domains extends Resource
{
    /** List mailbox domains. GET /domains */
    public function list(): mixed
    {
        return $this->client->request('GET', "/domains", null);
    }

    /** Add a mailbox domain. POST /domains */
    public function create(array $params): mixed
    {
        return $this->client->request('POST', "/domains", $this->obj($params));
    }

    /** Get a domain with DNS records, mailboxes and aliases. GET /domains/{domain_id} */
    public function get(string|int $domainId): mixed
    {
        return $this->client->request('GET', "/domains/{$this->seg($domainId)}", null);
    }

    /** Remove a pending domain. DELETE /domains/{domain_id} */
    public function delete(string|int $domainId): mixed
    {
        return $this->client->request('DELETE', "/domains/{$this->seg($domainId)}", null);
    }

    /** Check the ownership TXT record. POST /domains/{domain_id}/verify */
    public function verify(string|int $domainId): mixed
    {
        return $this->client->request('POST', "/domains/{$this->seg($domainId)}/verify", null);
    }

    /** Check MX, SPF, DKIM and DMARC. POST /domains/{domain_id}/dns-check */
    public function checkDns(string|int $domainId): mixed
    {
        return $this->client->request('POST', "/domains/{$this->seg($domainId)}/dns-check", null);
    }

    /** Catch-all on (mailbox id) or off (null). PUT /domains/{domain_id}/catch-all */
    public function setCatchAll(string|int $domainId, string|int|null $mailboxId): mixed
    {
        return $this->client->request('PUT', "/domains/{$this->seg($domainId)}/catch-all", ['mailbox_id' => $mailboxId]);
    }
}
