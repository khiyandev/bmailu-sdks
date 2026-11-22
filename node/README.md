# bmailu

The official Node.js SDK and command line tool for the [Bmailu](https://bmailu.com) Email API.
Node 18 or newer. No dependencies.

Full docs: https://bmailu.com/docs/

## Install

```sh
npm install bmailu
```

## Send an email

```js
const { Bmailu } = require('bmailu'); // or: import { Bmailu } from 'bmailu';
const bmailu = new Bmailu(process.env.BMAILU_API_KEY);
await bmailu.emails.send({ from: 'Shop <hello@shop.com>', to: 'ann@example.com', subject: 'Hi', html: '<p>Hi</p>' });
```

The From address must be on a verified sending domain in your account.

Safe retries: pass an idempotency key. The same key and body within 24 hours gives the first answer back.

```js
await bmailu.emails.send(params, { idempotencyKey: 'order-1234' });
```

## Options

```js
new Bmailu(apiKey, {
  baseUrl: 'https://app.bmailu.com/api/v1', // default; or env BMAILU_BASE_URL
  timeoutMs: 30000,                         // per request
  fetch: myFetch,                           // default: global fetch
});
```

If you leave out the key, the SDK reads `BMAILU_API_KEY`.

## Errors

Every method returns the parsed JSON answer. A failed call throws a `BmailuError`:

```js
const { BmailuError } = require('bmailu');
try {
  await bmailu.emails.send({ from: 'x@not-mine.com', to: 'ann@example.com', subject: 'Hi', text: 'Hi' });
} catch (e) {
  if (e instanceof BmailuError) console.log(e.status, e.code, e.message); // 403 domain_not_verified ...
}
```

Network errors and timeouts have `status` 0 and code `network_error` or `timeout`.

## Resources

Every operation of the API with an API key is covered:

| Resource | Methods |
|---|---|
| `emails` | `send`, `batch`, `list`, `get`, `update`, `reschedule`, `cancel`, `export` (CSV text) |
| `batch` | `send` (same as `emails.batch`) |
| `events` | `send`, `list` |
| `contacts` | `create` / `upsert`, `list`, `get`, `update`, `delete`, `import` |
| `segments` | `list`, `create`, `get`, `update`, `delete`, `addContacts`, `removeContact` |
| `topics` | `list`, `create`, `delete` |
| `broadcasts` | `list`, `create`, `get`, `update`, `delete`, `send`, `cancel`, `test`, `stats` |
| `templates` | `list`, `create`, `get`, `update`, `delete`, `publish`, `duplicate`, `versions`, `publishVersion`, `render`, `preview` (HTML text) |
| `webhooks` | `list`, `create`, `get`, `update`, `delete`, `rotateSecret`, `test`, `deliveries`, `replay`, `verify` |
| `suppressions` | `list`, `create`, `delete` |
| `automations` | `list`, `create`, `get`, `update`, `delete`, `start`, `stop`, `duplicate`, `runs`, `getRun` |
| `domains` | `list`, `create`, `get`, `delete`, `verify`, `dnsCheck`, `setCatchAll`, `createMailbox`, `createAlias` |
| `mailboxes` | `create`, `delete`, `restore`, `resetPassword`, `setForward`, `sendTestEmail` |
| `aliases` | `create`, `delete` |
| `inbox(mailboxId)` | `messages.list/create/get/update/trash/attachment/reply/forward/send`, `threads.get`, `folders.list/create/delete` |
| `metrics` | `get` |
| `logs` | `list` |
| `me` | `get`, `update` |

API keys and sending domains are managed in the panel only (the API refuses API keys there).
For anything else use the generic call:

```js
await bmailu.request('GET', '/emails', undefined, { limit: 5 });
```

## Verify a webhook

Bmailu signs webhooks the Standard Webhooks way. Pass the raw request body, the headers and the endpoint's
signing secret (`whsec_…`). It throws a `BmailuError` with code `invalid_signature` if the signature does not match
or the timestamp is more than 5 minutes away.

```js
const { Bmailu } = require('bmailu');

app.post('/webhooks/bmailu', express.raw({ type: 'application/json' }), (req, res) => {
  try {
    Bmailu.webhooks.verify(req.body, req.headers, process.env.BMAILU_WEBHOOK_SECRET);
  } catch {
    return res.status(400).end();
  }
  const event = JSON.parse(req.body);
  // event.type is e.g. "email.delivered"
  res.status(200).end();
});
```

## Command line

The package includes a `bmailu` command.

```sh
npx bmailu login                       # paste your API key; saved in ~/.config/bmailu/config.json (mode 0600)
bmailu whoami
bmailu send --from 'Shop <hello@shop.com>' --to ann@example.com --subject Hi --text 'Hello'
bmailu send --from hello@shop.com --to ann@example.com --subject Hi --html-file mail.html --tag kind=welcome --scheduled-at 'in 1 hour'
bmailu emails list
bmailu emails get <id>
bmailu emails cancel <id>
bmailu domains list
bmailu events send signed_up --email ann@example.com --data '{"plan":"pro"}'
bmailu contacts add ann@example.com --first-name Ann
bmailu api GET /metrics?days=7         # any endpoint
bmailu logout
```

- The key comes from `--api-key`, then `BMAILU_API_KEY`, then the saved file.
- The base URL comes from `--base-url`, then `BMAILU_BASE_URL`, then the saved file.
- `--json` prints the raw JSON answer. `--help` works on every command.
- Exit codes: 0 ok, 1 API error (code and message on stderr), 2 wrong usage.

## License

MIT
