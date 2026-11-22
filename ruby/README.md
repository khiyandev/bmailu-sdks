# Bmailu for Ruby

The official Ruby SDK for the Bmailu Email API. It needs Ruby 2.7 or newer and uses only the standard library
(net/http and json).

## Install

```sh
gem install bmailu
```

## Send an email

```ruby
require "bmailu"
bmailu = Bmailu::Client.new("bmk_...") # or set BMAILU_API_KEY
bmailu.emails.send({ from: "Shop <hello@shop.com>", to: "ann@example.com", subject: "Hello", html: "<p>Hello!</p>" })
```

You pass hashes and get hashes back (string keys). The answer to a send is `{"id" => "..."}`.
Keep the braces around the message: `send` also takes the `idempotency_key:` keyword.

To send safely twice (for example after a timeout), add an idempotency key. The second call returns the same id:

```ruby
bmailu.emails.send(message, idempotency_key: "order-1234")
```

## What is covered

The brief for 0.1.0 asks for emails, events, contacts, broadcasts, templates and webhooks plus a generic call.
This gem goes further: every operation of the API has a method, with the same names as the Python SDK:
`emails` (send, batch, get, list, export, update / reschedule, cancel), `events`, `contacts`, `segments`, `topics`,
`broadcasts` (send, cancel, test, stats), `templates` (publish, versions, render, preview), `webhooks`
(deliveries, replay_delivery, rotate_secret), `suppressions`, `automations` (start, stop, runs), `metrics`, `logs`,
`domains`, `mailboxes`, `aliases`, `sending_domains`, `api_keys`, `me` and `inbox`.

`api_keys` and `sending_domains` are in the spec but need a panel session: an API key gets the error `session_only`.

For anything else there is a generic call:

```ruby
bmailu.request("GET", "/emails", query: { status: "bounced", limit: 10 })
```

## Errors

A failed call raises `Bmailu::Error` with `status` (HTTP status), `code` and `error_message`:

```ruby
begin
  bmailu.emails.send({ ... })
rescue Bmailu::Error => e
  puts e.status, e.code, e.error_message # 403 domain_not_verified ...
end
```

## Verify a webhook

Pass the raw body, the request headers and the endpoint's signing secret. You get the parsed event back,
or a `Bmailu::WebhookVerificationError` if the signature is wrong or the timestamp is more than 5 minutes away.
Rack-style headers (`HTTP_WEBHOOK_ID`) work too.

```ruby
event = Bmailu::Webhook.verify(request.raw_post, request.headers.to_h, "whsec_...")
puts event["type"]
```

## Settings

- `Bmailu::Client.new(api_key = nil, base_url: nil, timeout: 30)`
- `BMAILU_API_KEY` and `BMAILU_BASE_URL` are read from the environment when you leave them out.

## Tests

`./test.sh` starts a local Bmailu API (`../test-server.js`, needs Node) and runs the minitest suite.
Nothing is really sent.

Docs: https://bmailu.com/docs/

License: MIT
