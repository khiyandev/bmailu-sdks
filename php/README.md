# Bmailu for PHP

The official PHP SDK for the Bmailu Email API. It needs PHP 8.1 or newer and has no dependencies.
It uses curl when the extension is loaded, and PHP streams when it is not.

## Install

```sh
composer require bmailu/bmailu
```

## Send an email

```php
$bmailu = new \Bmailu\Bmailu('bmk_...'); // or set BMAILU_API_KEY
$bmailu->emails->send(['from' => 'Shop <hello@shop.com>', 'to' => 'ann@example.com', 'subject' => 'Hello', 'html' => '<p>Hello!</p>']);
```

You pass plain arrays and get associative arrays back. The answer to a send is `['id' => '...']`.

To send safely twice (for example after a timeout), add an idempotency key. The second call returns the same id:

```php
$bmailu->emails->send($message, 'order-1234');
```

## What is covered

Every operation of the API has a method:
`emails` (send, batch, get, list, export, update / reschedule, cancel), `events`, `contacts`, `segments`, `topics`,
`broadcasts` (send, cancel, test, stats), `templates` (publish, versions, render, preview), `webhooks`
(deliveries, replayDelivery, rotateSecret), `suppressions`, `automations` (start, stop, runs), `metrics`, `logs`,
`domains`, `mailboxes`, `aliases`, `sendingDomains`, `apiKeys`, `me` and `inbox`
(folders, messages, reply, forward, drafts, threads, attachments).

`apiKeys` and `sendingDomains` are in the spec but need a panel session: an API key gets the error `session_only`.

For anything else there is a generic call:

```php
$bmailu->request('GET', '/emails', null, ['status' => 'bounced', 'limit' => 10]);
```

An empty PHP array is sent as a JSON list. Where the API wants an empty object inside a body, use `new \stdClass()`.

## Errors

A failed call throws `Bmailu\BmailuError` with `status` (HTTP status), `errorCode` and the message:

```php
try {
    $bmailu->emails->send([...]);
} catch (\Bmailu\BmailuError $e) {
    echo $e->status, ' ', $e->errorCode, ' ', $e->getMessage(); // 403 domain_not_verified ...
}
```

## Verify a webhook

Pass the raw body, the request headers and the endpoint's signing secret. You get the decoded event back,
or a `WebhookVerificationError` if the signature is wrong or the timestamp is more than 5 minutes away.

```php
$event = \Bmailu\Webhook::verify(file_get_contents('php://input'), getallheaders(), 'whsec_...');
echo $event['type'];
```

## Settings

- `new Bmailu($apiKey = null, $baseUrl = null, ['timeout' => 30, 'transport' => 'curl'|'stream'])`
- `BMAILU_API_KEY` and `BMAILU_BASE_URL` are read from the environment when you leave them out.

## Tests

`./test.sh` starts a local Bmailu API (`../test-server.js`, needs Node) and runs `tests/run.php`
with both transports. Nothing is really sent.

Docs: https://bmailu.com/docs/

License: MIT
