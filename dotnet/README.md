# Bmailu .NET SDK

Send email with the Bmailu Email API from C# and .NET (net8.0 and netstandard2.0).
It uses only System.Net.Http and System.Text.Json.

Docs: https://bmailu.com/docs/

## Install

```sh
dotnet add package Bmailu
```

## Send an email

```csharp
var bmailu = new Bmailu.BmailuClient("bmk_your_api_key");
var email = await bmailu.Emails.SendAsync(new { from = "Shop <hello@your-domain.com>", to = "ann@example.com", subject = "Hello", html = "<p>It works.</p>" });
Console.WriteLine(email.GetProperty("id").GetString());
```

`new BmailuClient()` reads the key from `BMAILU_API_KEY`. `BMAILU_BASE_URL` changes the API address
(default `https://app.bmailu.com/api/v1`). You can pass your own `HttpClient` as the third argument.

Bodies are any object System.Text.Json can write: anonymous objects, dictionaries or your own classes.
Field names are sent as written, so use the API's names (`reply_to`, `scheduled_at`). Answers are `JsonElement`.

To send at most once, pass an idempotency key:

```csharp
await bmailu.Emails.SendAsync(email, idempotencyKey: "order-1234-receipt");
```

## What is covered in 0.1.0

- `Emails`: `SendAsync`, `BatchAsync`, `GetAsync`, `ListAsync`, `UpdateAsync` (reschedule), `CancelAsync`
- `Events`: `SendAsync`, `ListAsync`
- `Contacts`: `CreateAsync`, `ListAsync`, `GetAsync`, `UpdateAsync`, `DeleteAsync`, `ImportAsync`
- `Broadcasts`: `CreateAsync`, `ListAsync`, `GetAsync`, `UpdateAsync`, `DeleteAsync`, `SendAsync`, `CancelAsync`, `TestAsync`, `StatsAsync`
- `Templates`: `CreateAsync`, `ListAsync`, `GetAsync`, `UpdateAsync`, `DeleteAsync`, `PublishAsync`, `DuplicateAsync`, `VersionsAsync`, `PublishVersionAsync`, `RenderAsync`
- `Webhooks`: `CreateAsync`, `ListAsync`, `GetAsync`, `UpdateAsync`, `DeleteAsync`, `RotateSecretAsync`, `TestAsync`, `DeliveriesAsync`, `ReplayAsync`

Everything else (domains, mailboxes, inbox, segments, topics, suppressions, automations) works through
`RequestAsync`:

```csharp
var domains = await bmailu.RequestAsync("GET", "/domains");
```

## Errors

Every call throws `BmailuException` on failure. It has `StatusCode`, `Code` (for example
`domain_not_verified`) and `Message`.

## Check a webhook

```csharp
// rawBody: the request body exactly as received. headers: the request headers as a dictionary.
JsonElement evt = Bmailu.Webhook.Verify(Environment.GetEnvironmentVariable("BMAILU_WEBHOOK_SECRET"), rawBody, headers);
```

It throws `BmailuException` with code `invalid_signature` when the signature does not match or the
timestamp is more than 5 minutes away.

## Tests

`./test.sh` starts the local test API (`../test-server.js`, needs Node) and runs `dotnet test` in the
`mcr.microsoft.com/dotnet/sdk:8.0` Docker image.

License: MIT.
