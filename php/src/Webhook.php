<?php

declare(strict_types=1);

namespace Bmailu;

/** Verify Bmailu webhooks (Standard Webhooks / Svix scheme). */
final class Webhook
{
    /**
     * Check the signature of a webhook and return the decoded JSON body (associative array).
     *
     * @param string $payload the raw request body, exactly as received
     * @param array<string, string|string[]> $headers request headers (any case)
     * @param string $secret the endpoint's signing secret (whsec_...)
     * @throws WebhookVerificationError
     */
    public static function verify(string $payload, array $headers, string $secret, int $tolerance = 300, ?int $now = null): mixed
    {
        $h = [];
        foreach ($headers as $k => $v) {
            $h[strtolower((string) $k)] = is_array($v) ? (string) reset($v) : (string) $v;
        }
        foreach (['webhook-id', 'webhook-timestamp', 'webhook-signature'] as $name) {
            if (!isset($h[$name]) || $h[$name] === '') {
                throw new WebhookVerificationError("Missing $name header");
            }
        }
        $ts = $h['webhook-timestamp'];
        if (!ctype_digit($ts)) {
            throw new WebhookVerificationError('Bad webhook-timestamp');
        }
        if (abs(($now ?? time()) - (int) $ts) > $tolerance) {
            throw new WebhookVerificationError('Timestamp is more than 5 minutes away');
        }
        $raw = str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret;
        $key = base64_decode($raw, true);
        if ($key === false) {
            throw new WebhookVerificationError('Bad secret');
        }
        $expected = base64_encode(hash_hmac('sha256', $h['webhook-id'] . '.' . $ts . '.' . $payload, $key, true));
        $ok = false;
        foreach (explode(' ', $h['webhook-signature']) as $part) {
            [$version, $sig] = array_pad(explode(',', $part, 2), 2, '');
            if ($version === 'v1' && hash_equals($expected, $sig)) {
                $ok = true;
            }
        }
        if (!$ok) {
            throw new WebhookVerificationError('No matching signature');
        }
        return $payload === '' ? null : json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    }
}
