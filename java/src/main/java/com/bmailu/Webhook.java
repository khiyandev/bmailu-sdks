package com.bmailu;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.util.Base64;
import java.util.Map;
import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;

/**
 * Checks the signature of a Bmailu webhook delivery (Standard Webhooks / Svix scheme).
 *
 * <pre>
 * Map&lt;String, Object&gt; event = Webhook.verify(secret, rawBody, headers);
 * </pre>
 */
public final class Webhook {
    /** Deliveries with a timestamp further than this from now are refused. */
    public static final long TOLERANCE_SECONDS = 5 * 60;

    private Webhook() {}

    /** Header names are matched without regard to case. Returns the parsed body, or throws BmailuException. */
    @SuppressWarnings("unchecked")
    public static Map<String, Object> verify(String secret, String payload, Map<String, String> headers) {
        String id = null, ts = null, sig = null;
        for (Map.Entry<String, String> e : headers.entrySet()) {
            String k = e.getKey() == null ? "" : e.getKey().toLowerCase();
            if (k.equals("webhook-id")) id = e.getValue();
            else if (k.equals("webhook-timestamp")) ts = e.getValue();
            else if (k.equals("webhook-signature")) sig = e.getValue();
        }
        verify(secret, payload, id, ts, sig);
        Object body = Json.parse(payload);
        return body instanceof Map ? (Map<String, Object>) body : Map.of("data", body);
    }

    /** Throws BmailuException (code invalid_signature) unless one of the signatures matches. */
    public static void verify(String secret, String payload, String id, String timestamp, String signature) {
        if (secret == null || payload == null || id == null || timestamp == null || signature == null) throw fail("Missing webhook headers");
        long ts;
        try { ts = Long.parseLong(timestamp.trim()); } catch (NumberFormatException e) { throw fail("Bad webhook-timestamp"); }
        long now = System.currentTimeMillis() / 1000;
        if (Math.abs(now - ts) > TOLERANCE_SECONDS) throw fail("Webhook timestamp is too old or too new");
        byte[] key;
        try {
            key = Base64.getDecoder().decode(secret.startsWith("whsec_") ? secret.substring(6) : secret);
        } catch (IllegalArgumentException e) {
            throw fail("Bad webhook secret");
        }
        byte[] expected;
        try {
            Mac mac = Mac.getInstance("HmacSHA256");
            mac.init(new SecretKeySpec(key, "HmacSHA256"));
            expected = mac.doFinal((id + "." + timestamp + "." + payload).getBytes(StandardCharsets.UTF_8));
        } catch (Exception e) {
            throw fail("Could not compute HMAC: " + e.getMessage());
        }
        for (String part : signature.trim().split(" +")) {
            int comma = part.indexOf(',');
            if (comma < 0 || !part.substring(0, comma).equals("v1")) continue;
            byte[] given;
            try { given = Base64.getDecoder().decode(part.substring(comma + 1)); } catch (IllegalArgumentException e) { continue; }
            if (MessageDigest.isEqual(expected, given)) return;
        }
        throw fail("No matching webhook signature");
    }

    private static BmailuException fail(String message) {
        return new BmailuException(400, "invalid_signature", message);
    }
}
