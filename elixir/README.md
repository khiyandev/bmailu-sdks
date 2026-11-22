# Bmailu Elixir SDK

Send email with the Bmailu Email API from Elixir 1.14 or newer. No dependencies: HTTP is OTP's `:httpc`.

Docs: https://bmailu.com/docs/

## Install

```elixir
def deps do
  [{:bmailu, "~> 0.1.0"}]
end
```

JSON uses Elixir's built-in `JSON` module (Elixir 1.18+), or OTP 27's `:json`. On older versions add
`{:jason, "~> 1.4"}` to your deps and Bmailu uses it.

## Send an email

```elixir
client = Bmailu.new("bmk_your_api_key")
{:ok, email} = Bmailu.Emails.send(client, %{from: "Shop <hello@your-domain.com>", to: "ann@example.com", subject: "Hello", html: "<p>It works.</p>"})
IO.puts(email["id"])
```

`Bmailu.new()` reads the key from `BMAILU_API_KEY`. `BMAILU_BASE_URL` (or `base_url:`) changes the API address
(default `https://app.bmailu.com/api/v1`).

Every call returns `{:ok, map}` or `{:error, %Bmailu.Error{}}`. Bodies are maps; answers have string keys.

To send at most once, pass an idempotency key:

```elixir
Bmailu.Emails.send(client, email, idempotency_key: "order-1234-receipt")
```

## What is covered in 0.1.0

- `Bmailu.Emails`: `send`, `batch`, `get`, `list`, `update` (reschedule), `cancel`
- `Bmailu.Events`: `send`, `list`
- `Bmailu.Contacts`: `create`, `list`, `get`, `update`, `delete`, `import`
- `Bmailu.Broadcasts`: `create`, `list`, `get`, `update`, `delete`, `send`, `cancel`, `test`, `stats`
- `Bmailu.Templates`: `create`, `list`, `get`, `update`, `delete`, `publish`, `duplicate`, `versions`, `publish_version`, `render`
- `Bmailu.Webhooks`: `create`, `list`, `get`, `update`, `delete`, `rotate_secret`, `test`, `deliveries`, `replay`

Everything else (domains, mailboxes, inbox, segments, topics, suppressions, automations) works through
`Bmailu.request/5`:

```elixir
{:ok, domains} = Bmailu.request(client, :get, "/domains")
```

## Errors

`%Bmailu.Error{}` has `status` (HTTP status, 0 if the API was not reached), `code` (for example
`domain_not_verified`) and `message`.

## Check a webhook

```elixir
# raw_body: the request body exactly as received. Headers: a map or a list of {name, value}.
{:ok, event} = Bmailu.Webhook.verify(System.fetch_env!("BMAILU_WEBHOOK_SECRET"), raw_body, conn.req_headers)
```

It returns `{:error, %Bmailu.Error{code: "invalid_signature"}}` when the signature does not match or the
timestamp is more than 5 minutes away.

## Tests

`./test.sh` starts the local test API (`../test-server.js`, needs Node) and runs `mix test`.

License: MIT.
