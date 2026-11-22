<?php

declare(strict_types=1);

// End-to-end tests against sdks/test-server.js (started by test.sh, config in BMAILU_TEST_JSON).
// Plain PHP: every test is a closure; a failed check throws; the exit code is the number of failures.

require __DIR__ . '/bootstrap.php';

use Bmailu\Bmailu;
use Bmailu\BmailuError;
use Bmailu\Webhook;
use Bmailu\WebhookVerificationError;

$cfg = json_decode((string) getenv('BMAILU_TEST_JSON'), true, 512, JSON_THROW_ON_ERROR);
const FROM = 'Shop <hello@shop.test>';
const TO = 'delivered@simulator.bmailu.com';

function check(bool $ok, string $what): void
{
    if (!$ok) {
        throw new RuntimeException("check failed: $what");
    }
}

/** @return BmailuError the error the call threw */
function expectError(callable $fn): BmailuError
{
    try {
        $fn();
    } catch (BmailuError $e) {
        return $e;
    }
    throw new RuntimeException('expected a BmailuError');
}

function panelSign(string $secret, string $id, int $ts, string $body): string
{
    $js = 'process.stdout.write(require(process.argv[1]).sign(process.argv[2], process.argv[3], process.argv[4], process.argv[5]))';
    $path = __DIR__ . '/../../../panel/src/webhook-routes.js';
    $cmd = 'node -e ' . escapeshellarg($js) . ' ' . implode(' ', array_map('escapeshellarg', [$path, $secret, $id, (string) $ts, $body]));
    return (string) shell_exec($cmd);
}

$tests = [];

foreach (['curl', 'stream'] as $transport) {
    $b = new Bmailu($cfg['key'], $cfg['api'], ['transport' => $transport]);

    $tests["[$transport] send twice with an idempotency key, get, list, export"] = function () use ($b, $transport) {
        $msg = ['from' => FROM, 'to' => TO, 'subject' => 'Hello', 'html' => '<p>Hello</p>'];
        $a = $b->emails->send($msg, "php-idem-$transport");
        $c = $b->emails->send($msg, "php-idem-$transport");
        check($a['id'] === $c['id'], 'same id');
        $got = $b->emails->get($a['id']);
        check($got['object'] === 'email' && $got['subject'] === 'Hello', 'get');
        check(isset($b->emails->list(['limit' => 5])['data']), 'list');
        check(str_starts_with($b->emails->export(), 'id,'), 'export csv');
    };

    $tests["[$transport] batch of 2"] = function () use ($b) {
        $msg = ['from' => FROM, 'to' => TO, 'subject' => 'Batch', 'text' => 'Hi'];
        check(count($b->emails->batch([$msg, $msg])['data']) === 2, 'two ids');
    };

    $tests["[$transport] contact + event"] = function () use ($b, $transport) {
        $email = "php-$transport@example.com";
        $c = $b->contacts->create(['email' => $email, 'first_name' => 'Ann']);
        check($c['email'] === $email, 'contact');
        $e = $b->events->send(['event' => 'signed_up', 'email' => $email]);
        check($e['object'] === 'event' && $e['contact_id'] === $c['id'], 'event');
    };

    $tests["[$transport] template create, publish, send"] = function () use ($b, $transport) {
        $alias = "welcome-php-$transport";
        $t = $b->templates->create(['name' => "Welcome $transport", 'alias' => $alias, 'subject' => 'Hi {{name}}',
            'html' => '<p>Hi {{name}}</p>', 'variables' => [['key' => 'name', 'fallback_value' => 'there']]]);
        check($b->templates->publish($t['id'])['published_version'] === 1, 'published');
        check(str_contains($b->templates->preview($alias), 'Hi there'), 'preview');
        check(is_array($b->templates->render($alias)), 'render with no params sends {}');
        $r = $b->emails->send(['from' => FROM, 'to' => TO, 'template' => ['id' => $alias, 'variables' => ['name' => 'Ann']]]);
        check($b->emails->get($r['id'])['subject'] === 'Hi Ann', 'subject filled');
    };

    $tests["[$transport] webhooks list"] = function () use ($b) {
        $r = $b->webhooks->list();
        check($r['object'] === 'list' && is_array($r['data']), 'list');
    };

    $tests["[$transport] error: domain_not_verified"] = function () use ($b) {
        $e = expectError(fn () => $b->emails->send(['from' => 'x@nope.test', 'to' => TO, 'subject' => 'x', 'text' => 'x']));
        check($e->errorCode === 'domain_not_verified' && $e->status === 403, 'code + status');
    };

    $tests["[$transport] sending key refused on GET /domains"] = function () use ($cfg, $transport) {
        $s = new Bmailu($cfg['sending_key'], $cfg['api'], ['transport' => $transport]);
        check(expectError(fn () => $s->domains->list())->errorCode === 'restricted_api_key', 'restricted');
    };

    $tests["[$transport] generic request + url-encoded path"] = function () use ($b) {
        check(is_array($b->request('GET', '/me')), 'me');
        check(expectError(fn () => $b->suppressions->delete('nobody@example.com'))->errorCode === 'not_found', '404');
    };
}

$tests['webhook verify (panel sign) + tampered body + old timestamp'] = function () {
    $secret = 'whsec_' . base64_encode(random_bytes(24));
    $body = json_encode(['type' => 'email.delivered', 'data' => ['email_id' => 'e1']]);
    $ts = time();
    $sig = panelSign($secret, 'msg_1', $ts, $body);
    check(str_starts_with($sig, 'v1,'), 'node sign');
    $headers = ['Webhook-Id' => 'msg_1', 'webhook-timestamp' => (string) $ts, 'webhook-signature' => "v1,bogus $sig"];
    check(Webhook::verify($body, $headers, $secret)['type'] === 'email.delivered', 'valid');
    foreach ([fn () => Webhook::verify(str_replace('e1', 'e2', $body), $headers, $secret),
              fn () => Webhook::verify($body, $headers, $secret, 300, $ts + 301)] as $bad) {
        try {
            $bad();
            throw new RuntimeException('expected WebhookVerificationError');
        } catch (WebhookVerificationError) {
        }
    }
};

$tests['every spec operation has a method'] = function () use ($cfg) {
    $spec = json_decode((string) file_get_contents($cfg['api'] . '/openapi.json'), true);
    $want = [];
    foreach ($spec['paths'] as $p => $ops) {
        foreach (array_keys($ops) as $m) {
            if (in_array($m, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                $want[] = strtoupper($m) . ' ' . preg_replace('/\{[^}]+\}/', 'X', $p);
            }
        }
    }
    $seen = [];
    $b = new Bmailu('bmk_test', 'http://localhost:1', ['handler' => function ($method, $url) use (&$seen) {
        $seen[] = $method . ' ' . preg_replace('#\?.*$#', '', substr($url, strlen('http://localhost:1')));
        return [200, 'application/json', '{}'];
    }]);
    foreach ((new ReflectionClass($b))->getProperties() as $prop) {
        $res = $prop->getValue($b);
        if (!$res instanceof \Bmailu\Resources\Resource) {
            continue;
        }
        foreach ((new ReflectionClass($res))->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->isConstructor()) {
                continue;
            }
            $args = [];
            foreach ($m->getParameters() as $p) {
                if ($p->isOptional()) {
                    break;
                }
                $t = (string) $p->getType();
                $args[] = $t === 'array' ? [] : (str_contains($t, 'null') ? null : 'X');
            }
            $m->invokeArgs($res, $args);
        }
    }
    $seen = array_unique($seen);
    $missing = array_diff($want, $seen);
    $extra = array_diff($seen, $want);
    check($missing === [], 'missing: ' . implode(', ', $missing));
    check($extra === [], 'not in spec: ' . implode(', ', $extra));
    echo '    (' . count($want) . " operations)\n";
};

$failed = 0;
foreach ($tests as $name => $fn) {
    try {
        $fn();
        echo "ok   $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL $name: " . get_class($e) . ': ' . $e->getMessage() . "\n";
    }
}
echo $failed === 0 ? 'All ' . count($tests) . " tests passed\n" : "$failed of " . count($tests) . " tests failed\n";
exit($failed === 0 ? 0 : 1);
