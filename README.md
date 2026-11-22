# Bmailu SDKs

Official libraries for the [Bmailu](https://bmailu.com) Email API — send email from your own domain, receive webhooks,
manage contacts, broadcasts, templates and automations.

| Language | Folder | Install |
|---|---|---|
| Node.js + CLI | [node](node) | `npm install bmailu` |
| Python | [python](python) | `pip install bmailu` |
| PHP | [php](php) | `composer require bmailu/bmailu` |
| Ruby | [ruby](ruby) | `gem install bmailu` |
| Go | [go](go) | `go get github.com/khiyandev/bmailu-sdks/go` |
| Java | [java](java) | Maven `com.bmailu:bmailu` |
| .NET | [dotnet](dotnet) | `dotnet add package Bmailu` |
| Rust | [rust](rust) | `cargo add bmailu` |
| Elixir | [elixir](elixir) | `{:bmailu, "~> 0.1"}` |
| n8n | [n8n](n8n) | Community node `n8n-nodes-bmailu` |
| WordPress | [wordpress](wordpress) | Plugin "Bmailu" |

```js
const { Bmailu } = require('bmailu');
const bmailu = new Bmailu(process.env.BMAILU_API_KEY);
await bmailu.emails.send({ from: 'Shop <hello@yourshop.com>', to: 'ann@example.com', subject: 'Hello', html: '<p>Hi!</p>' });
```

- Docs: https://bmailu.com/docs/sdks.html · API reference (OpenAPI): https://bmailu.com/openapi.json
- Every library also checks webhook signatures (Standard Webhooks: `webhook-id`, `webhook-timestamp`, `webhook-signature`).
- Version 0.1.0. MIT licence, except the WordPress plugin (GPLv2 or later, as WordPress requires).
- Found a bug? Open an issue, or write to support@bmailu.com.

The test suites (`test.sh` in each folder) run against a local Bmailu API server that is not part of this repository yet.
