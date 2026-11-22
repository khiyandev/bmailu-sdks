<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class SendingDomains extends Resource
{
    /** List sending domains. GET /sending-domains */
    public function list(): mixed
    {
        return $this->client->request('GET', "/sending-domains", null);
    }

    /** Add a sending domain. POST /sending-domains */
    public function create(array $params): mixed
    {
        return $this->client->request('POST', "/sending-domains", $this->obj($params));
    }

    /** Get a sending domain. GET /sending-domains/{sending_domain_id} */
    public function get(string|int $sendingDomainId): mixed
    {
        return $this->client->request('GET', "/sending-domains/{$this->seg($sendingDomainId)}", null);
    }

    /** Remove a sending domain. DELETE /sending-domains/{sending_domain_id} */
    public function delete(string|int $sendingDomainId): mixed
    {
        return $this->client->request('DELETE', "/sending-domains/{$this->seg($sendingDomainId)}", null);
    }

    /** Check the TXT and create DKIM keys. POST /sending-domains/{sending_domain_id}/verify */
    public function verify(string|int $sendingDomainId): mixed
    {
        return $this->client->request('POST', "/sending-domains/{$this->seg($sendingDomainId)}/verify", null);
    }

    /** Check DKIM, bounce MX and SPF. POST /sending-domains/{sending_domain_id}/check */
    public function check(string|int $sendingDomainId): mixed
    {
        return $this->client->request('POST', "/sending-domains/{$this->seg($sendingDomainId)}/check", null);
    }
}
