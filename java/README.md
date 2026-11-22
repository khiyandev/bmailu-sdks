# Bmailu Java SDK

Send email with the Bmailu Email API from Java 11 or newer. No dependencies.

Docs: https://bmailu.com/docs/

## Install

Maven:

```xml
<dependency>
  <groupId>com.bmailu</groupId>
  <artifactId>bmailu</artifactId>
  <version>0.1.0</version>
</dependency>
```

## Send an email

```java
Bmailu bmailu = new Bmailu("bmk_your_api_key");
Map<String, Object> email = bmailu.emails().send(Map.of("from", "Shop <hello@your-domain.com>",
    "to", "ann@example.com", "subject", "Hello", "html", "<p>It works.</p>"));
System.out.println(email.get("id"));
```

`new Bmailu()` reads the key from `BMAILU_API_KEY`. `BMAILU_BASE_URL` changes the API address
(default `https://app.bmailu.com/api/v1`).

Bodies are `Map` and `List` values. Answers are parsed JSON as `Map<String, Object>`.

To send at most once, pass an idempotency key:

```java
bmailu.emails().send(email, "order-1234-receipt");
```

## What is covered in 0.1.0

- `emails()`: `send`, `batch`, `get`, `list`, `update` (reschedule), `cancel`
- `events()`: `send`, `list`
- `contacts()`: `create`, `list`, `get`, `update`, `delete`, `importCsv`
- `broadcasts()`: `create`, `list`, `get`, `update`, `delete`, `send`, `cancel`, `test`, `stats`
- `templates()`: `create`, `list`, `get`, `update`, `delete`, `publish`, `duplicate`, `versions`, `publishVersion`, `render`
- `webhooks()`: `create`, `list`, `get`, `update`, `delete`, `rotateSecret`, `test`, `deliveries`, `replay`

Everything else (domains, mailboxes, inbox, segments, topics, suppressions, automations) works through
`request`:

```java
Object domains = bmailu.request("GET", "/domains", null, null);
```

## Errors

Every call throws `BmailuException` on failure. It has `getStatus()` (HTTP status), `getCode()`
(for example `domain_not_verified`) and `getMessage()`.

## Check a webhook

```java
// rawBody: the request body exactly as received. headers: the request headers.
Map<String, Object> event = Webhook.verify(System.getenv("BMAILU_WEBHOOK_SECRET"), rawBody, headers);
```

It throws `BmailuException` with code `invalid_signature` when the signature does not match or the
timestamp is more than 5 minutes away.

## Tests

`./test.sh` starts the local test API (`../test-server.js`, needs Node) and runs `mvn test` in the
`maven:3-eclipse-temurin-21` Docker image.

License: MIT.
