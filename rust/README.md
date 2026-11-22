# Bmailu Rust SDK

Send email with the Bmailu Email API from Rust. A small blocking client on `ureq`, `serde` and `serde_json`.

Docs: https://bmailu.com/docs/

## Install

```sh
cargo add bmailu serde_json
```

## Send an email

```rust
let bmailu = bmailu::Bmailu::new("bmk_your_api_key");
let email = bmailu.emails().send(&serde_json::json!({"from": "Shop <hello@your-domain.com>", "to": "ann@example.com", "subject": "Hello", "html": "<p>It works.</p>"}))?;
println!("{}", email["id"]);
```

`Bmailu::from_env()` reads the key from `BMAILU_API_KEY`. `BMAILU_BASE_URL` (or `.with_base_url(...)`) changes the
API address (default `https://app.bmailu.com/api/v1`).

Bodies and answers are `serde_json::Value`. Every call returns `Result<Value, BmailuError>`.

To send at most once, pass an idempotency key:

```rust
bmailu.emails().send_with_idempotency_key(&email, "order-1234-receipt")?;
```

## What is covered in 0.1.0

- `emails()`: `send`, `send_with_idempotency_key`, `batch`, `batch_with_idempotency_key`, `get`, `list`, `update` (reschedule), `cancel`
- `events()`: `send`, `list`
- `contacts()`: `create`, `list`, `get`, `update`, `delete`, `import`
- `broadcasts()`: `create`, `list`, `get`, `update`, `delete`, `send`, `cancel`, `test`, `stats`
- `templates()`: `create`, `list`, `get`, `update`, `delete`, `publish`, `duplicate`, `versions`, `publish_version`, `render`
- `webhooks()`: `create`, `list`, `get`, `update`, `delete`, `rotate_secret`, `test`, `deliveries`, `replay`

Everything else (domains, mailboxes, inbox, segments, topics, suppressions, automations) works through `request`:

```rust
let domains = bmailu.request("GET", "/domains", None, &[])?;
```

## Errors

`BmailuError` has `status` (HTTP status, 0 if the API was not reached), `code` (for example
`domain_not_verified`) and `message`.

## Check a webhook

```rust
// raw_body: the request body exactly as received. headers: (name, value) pairs.
let event = bmailu::verify_webhook(&secret, &raw_body, [
    ("webhook-id", id), ("webhook-timestamp", ts), ("webhook-signature", sig),
])?;
```

It returns an error with code `invalid_signature` when the signature does not match or the timestamp is more
than 5 minutes away.

## Tests

`./test.sh` starts the local test API (`../test-server.js`, needs Node) and runs `cargo test` in the `rust:1`
Docker image.

License: MIT.
