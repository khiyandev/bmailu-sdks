# n8n-nodes-bmailu

An [n8n](https://n8n.io) community node for [Bmailu](https://bmailu.com): send email, add contacts, send events
and send broadcasts from your workflows.

Docs: https://bmailu.com/docs/

## Install

In n8n (self-hosted):

1. Go to **Settings → Community Nodes**.
2. Click **Install**.
3. Type `n8n-nodes-bmailu` and agree to the notice.
4. Click **Install**. The **Bmailu** node now shows up in the node list.

Without the UI (for example in Docker), install it in your n8n custom folder:

```sh
cd ~/.n8n/nodes
npm install n8n-nodes-bmailu
```

Then restart n8n.

## Credentials

1. In the Bmailu panel, open **Developers** and create an API key (`bmk_…`).
2. In n8n, add a **Bmailu API** credential and paste the key.
3. Leave **Base URL** as `https://app.bmailu.com/api/v1`.

n8n checks the key with `GET /emails?limit=1` when you save it.
A sending-only key can send emails and events. Use a full-access key for contacts and broadcasts.

## Operations

| Resource | Operation | API call |
|---|---|---|
| Email | Send | `POST /emails` (cc, bcc, reply-to, tags, scheduling, templates, idempotency key) |
| Email | Get | `GET /emails/{id}` |
| Contact | Create or Update | `POST /contacts` |
| Contact | Delete | `DELETE /contacts/{id or email}` |
| Event | Send | `POST /events` (starts automations) |
| Broadcast | Create | `POST /broadcasts` (a draft) |
| Broadcast | Send | `POST /broadcasts/{id}/send` (now or scheduled) |

The From address must be on a verified sending domain. Errors show the Bmailu error code, for example
`domain_not_verified`. Turn on **Continue On Fail** to get the error as output instead.

For anything else, use n8n's **HTTP Request** node with the **Bmailu API** credential.

## License

MIT
