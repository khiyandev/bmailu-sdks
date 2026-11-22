<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Mailboxes extends Resource
{
    /** Create a mailbox (password shown once). POST /domains/{domain_id}/mailboxes */
    public function create(string|int $domainId, array $params): mixed
    {
        return $this->client->request('POST', "/domains/{$this->seg($domainId)}/mailboxes", $this->obj($params));
    }

    /** Delete a mailbox (restorable for 14 days). DELETE /mailboxes/{mailbox_id} */
    public function delete(string|int $mailboxId): mixed
    {
        return $this->client->request('DELETE', "/mailboxes/{$this->seg($mailboxId)}", null);
    }

    /** Restore a deleted mailbox. POST /mailboxes/{mailbox_id}/restore */
    public function restore(string|int $mailboxId): mixed
    {
        return $this->client->request('POST', "/mailboxes/{$this->seg($mailboxId)}/restore", null);
    }

    /** Set a new random password. POST /mailboxes/{mailbox_id}/password */
    public function resetPassword(string|int $mailboxId): mixed
    {
        return $this->client->request('POST', "/mailboxes/{$this->seg($mailboxId)}/password", null);
    }

    /** Forward copies (or stop with null). PUT /mailboxes/{mailbox_id}/forward */
    public function setForward(string|int $mailboxId, ?string $to): mixed
    {
        return $this->client->request('PUT', "/mailboxes/{$this->seg($mailboxId)}/forward", ['to' => $to]);
    }

    /** Send a test email to the mailbox. POST /mailboxes/{mailbox_id}/test-email */
    public function sendTestEmail(string|int $mailboxId): mixed
    {
        return $this->client->request('POST', "/mailboxes/{$this->seg($mailboxId)}/test-email", null);
    }
}
