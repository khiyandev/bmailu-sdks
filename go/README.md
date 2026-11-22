# Bmailu for Go

The official Go SDK for the Bmailu Email API. Standard library only (net/http and encoding/json).
Every method takes a `context.Context`.

## Install

```sh
go get github.com/khiyandev/bmailu-sdks/go
```

## Send an email

```go
client := bmailu.NewClient("bmk_...") // "" reads BMAILU_API_KEY
res, err := client.Emails.Send(ctx, &bmailu.SendEmailRequest{From: "Shop <hello@shop.com>", To: []string{"ann@example.com"}, Subject: "Hello", HTML: "<p>Hello!</p>"})
fmt.Println(res.ID, err)
```

To send safely twice (for example after a timeout), add an idempotency key. The second call returns the same id:

```go
client.Emails.Send(ctx, req, bmailu.WithIdempotencyKey("order-1234"))
```

## What is covered

Version 0.1.0 covers the core resources:

- `Emails`: Send, Batch, Get, List, Reschedule, Cancel (typed structs: `SendEmailRequest`, `Email`, `EmailList`)
- `Events`: Send, List
- `Contacts`: List, Create, Get, Update, Delete
- `Broadcasts`: List, Create, Get, Update, Delete, Send, Cancel, Test, Stats
- `Templates`: List, Create, Get, Update, Delete, Publish, Duplicate, Versions, PublishVersion, Render, Preview
- `Webhooks`: List, Create, Get, Update, Delete, RotateSecret, Test, Deliveries, ReplayDelivery

Less common bodies and answers use `bmailu.M` (`map[string]any`).
Everything else in the API (domains, mailboxes, inbox, segments, topics, suppressions, automations, ...)
works through the generic call:

```go
var out bmailu.M
err := client.Do(ctx, "GET", "/automations", nil, &out)
```

Pass a `*string` or `*[]byte` as `out` for CSV, HTML or file answers.

## Errors

API errors are `*bmailu.Error` with `StatusCode`, `Code` and `Message`:

```go
var e *bmailu.Error
if errors.As(err, &e) {
	fmt.Println(e.StatusCode, e.Code, e.Message) // 403 domain_not_verified ...
}
```

## Verify a webhook

Pass the raw body, the request headers and the endpoint's signing secret. `nil` means the webhook is genuine.
A wrong signature, or a timestamp more than 5 minutes away, gives an error that matches `bmailu.ErrWebhookVerification`.

```go
body, _ := io.ReadAll(r.Body)
if err := bmailu.VerifyWebhook(body, r.Header, "whsec_..."); err != nil {
	http.Error(w, "bad signature", http.StatusUnauthorized)
	return
}
```

## Settings

- `bmailu.NewClient(apiKey, bmailu.WithBaseURL(url), bmailu.WithHTTPClient(h))`
- `BMAILU_API_KEY` and `BMAILU_BASE_URL` are read from the environment when you leave them out.

## Tests

`./test.sh` starts a local Bmailu API (`../test-server.js`, needs Node) and runs `go test`.
Plain `go test` works too: it starts the test server itself on port 47131. Nothing is really sent.

Docs: https://bmailu.com/docs/

License: MIT
