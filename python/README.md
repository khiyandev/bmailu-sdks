# Bmailu for Python

The official Python SDK for the Bmailu Email API. It has no dependencies (standard library only) and works on Python 3.8 and newer.

## Install

```sh
pip install bmailu
```

## Send an email

```python
from bmailu import Bmailu

bmailu = Bmailu("bmk_...")  # or set BMAILU_API_KEY
bmailu.emails.send({"from": "Shop <hello@shop.com>", "to": "ann@example.com", "subject": "Hello", "html": "<p>Hello!</p>"})
```

You pass plain dicts and get plain dicts back. The answer to a send is `{"id": "..."}`.

To send safely twice (for example after a timeout), add an idempotency key. The second call returns the same id:

```python
bmailu.emails.send(message, idempotency_key="order-1234")
```

## What is covered

Every operation of the API has a method:
`emails` (send, batch, get, list, export, update / reschedule, cancel), `events`, `contacts`, `segments`, `topics`,
`broadcasts` (send, cancel, test, stats), `templates` (publish, versions, render, preview), `webhooks`
(deliveries, replay_delivery, rotate_secret), `suppressions`, `automations` (start, stop, runs), `metrics`, `logs`,
`domains`, `mailboxes`, `aliases`, `sending_domains`, `api_keys`, `me` and `inbox`
(folders, messages, reply, forward, drafts, threads, attachments).

`api_keys` and `sending_domains` are in the spec but need a panel session: an API key gets the error `session_only`.

For anything else there is a generic call:

```python
bmailu.request("GET", "/emails", query={"status": "bounced", "limit": 10})
```

## Errors

A failed call raises `BmailuError` with `status` (HTTP status), `code` and `message`:

```python
from bmailu import BmailuError

try:
    bmailu.emails.send({...})
except BmailuError as e:
    print(e.status, e.code, e.message)  # 403 domain_not_verified ...
```

## Verify a webhook

Pass the raw body, the request headers and the endpoint's signing secret. You get the parsed event back,
or a `WebhookVerificationError` if the signature is wrong or the timestamp is more than 5 minutes away.

```python
from bmailu import verify_webhook, WebhookVerificationError

event = verify_webhook(request.body, request.headers, "whsec_...")
print(event["type"])
```

## Settings

- `Bmailu(api_key, base_url=None, timeout=30)`
- `BMAILU_API_KEY` and `BMAILU_BASE_URL` are read from the environment when you leave them out.

## Tests

`./test.sh` starts a local Bmailu API (`../test-server.js`, needs Node) and runs the tests. Nothing is really sent.

Docs: https://bmailu.com/docs/

License: MIT
