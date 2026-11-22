<?php

declare(strict_types=1);

namespace Bmailu\Resources;

final class Inbox extends Resource
{
    /** List folders with counts. GET /mailboxes/{mailbox_id}/folders */
    public function listFolders(string|int $mailboxId): mixed
    {
        return $this->client->request('GET', "/mailboxes/{$this->seg($mailboxId)}/folders", null);
    }

    /** Create a folder. POST /mailboxes/{mailbox_id}/folders */
    public function createFolder(string|int $mailboxId, array $params): mixed
    {
        return $this->client->request('POST', "/mailboxes/{$this->seg($mailboxId)}/folders", $this->obj($params));
    }

    /** Delete an empty own folder. DELETE /mailboxes/{mailbox_id}/folders/{folder_id} */
    public function deleteFolder(string|int $mailboxId, string|int $folderId): mixed
    {
        return $this->client->request('DELETE', "/mailboxes/{$this->seg($mailboxId)}/folders/{$this->seg($folderId)}", null);
    }

    /** query: folder, q, from, unread, flagged, label, message_id, threads, limit, position. GET /mailboxes/{mailbox_id}/messages */
    public function listMessages(string|int $mailboxId, array $query = []): mixed
    {
        return $this->client->request('GET', "/mailboxes/{$this->seg($mailboxId)}/messages", null, $query);
    }

    /** Write a new email (or a draft). POST /mailboxes/{mailbox_id}/messages */
    public function createMessage(string|int $mailboxId, array $params): mixed
    {
        return $this->client->request('POST', "/mailboxes/{$this->seg($mailboxId)}/messages", $this->obj($params));
    }

    /** Read a message. GET /mailboxes/{mailbox_id}/messages/{message_id} */
    public function getMessage(string|int $mailboxId, string|int $messageId): mixed
    {
        return $this->client->request('GET', "/mailboxes/{$this->seg($mailboxId)}/messages/{$this->seg($messageId)}", null);
    }

    /** Mark, label or move a message. PATCH /mailboxes/{mailbox_id}/messages/{message_id} */
    public function updateMessage(string|int $mailboxId, string|int $messageId, array $params): mixed
    {
        return $this->client->request('PATCH', "/mailboxes/{$this->seg($mailboxId)}/messages/{$this->seg($messageId)}", $this->obj($params));
    }

    /** Move a message to Deleted Items. DELETE /mailboxes/{mailbox_id}/messages/{message_id} */
    public function trashMessage(string|int $mailboxId, string|int $messageId): mixed
    {
        return $this->client->request('DELETE', "/mailboxes/{$this->seg($mailboxId)}/messages/{$this->seg($messageId)}", null);
    }

    /** Download an attachment (raw bytes). GET /mailboxes/{mailbox_id}/messages/{message_id}/attachments/{blob_id} */
    public function getAttachment(string|int $mailboxId, string|int $messageId, string|int $blobId): mixed
    {
        return $this->client->request('GET', "/mailboxes/{$this->seg($mailboxId)}/messages/{$this->seg($messageId)}/attachments/{$this->seg($blobId)}", null);
    }

    /** Reply in the same thread. POST /mailboxes/{mailbox_id}/messages/{message_id}/reply */
    public function reply(string|int $mailboxId, string|int $messageId, array $params): mixed
    {
        return $this->client->request('POST', "/mailboxes/{$this->seg($mailboxId)}/messages/{$this->seg($messageId)}/reply", $this->obj($params));
    }

    /** Forward with the attachments. POST /mailboxes/{mailbox_id}/messages/{message_id}/forward */
    public function forward(string|int $mailboxId, string|int $messageId, array $params): mixed
    {
        return $this->client->request('POST', "/mailboxes/{$this->seg($mailboxId)}/messages/{$this->seg($messageId)}/forward", $this->obj($params));
    }

    /** Send a draft. POST /mailboxes/{mailbox_id}/messages/{message_id}/send */
    public function sendDraft(string|int $mailboxId, string|int $messageId): mixed
    {
        return $this->client->request('POST', "/mailboxes/{$this->seg($mailboxId)}/messages/{$this->seg($messageId)}/send", null);
    }

    /** Read a whole conversation. GET /mailboxes/{mailbox_id}/threads/{thread_id} */
    public function getThread(string|int $mailboxId, string|int $threadId): mixed
    {
        return $this->client->request('GET', "/mailboxes/{$this->seg($mailboxId)}/threads/{$this->seg($threadId)}", null);
    }
}
