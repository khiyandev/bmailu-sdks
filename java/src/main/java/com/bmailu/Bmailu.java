package com.bmailu;

import java.io.IOException;
import java.net.URI;
import java.net.URLEncoder;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.time.Duration;
import java.util.Collections;
import java.util.List;
import java.util.Map;

/**
 * Bmailu API client.
 *
 * <pre>
 * Bmailu bmailu = new Bmailu("bmk_...");
 * Map&lt;String, Object&gt; email = bmailu.emails().send(Map.of("from", "Shop &lt;hello@shop.example&gt;",
 *     "to", "ann@example.com", "subject", "Hi", "html", "&lt;p&gt;Hello&lt;/p&gt;"));
 * </pre>
 *
 * Bodies are {@code Map}/{@code List} values; answers are parsed JSON ({@code Map<String,Object>}).
 * Every method throws {@link BmailuException} on an API or network error.
 */
public class Bmailu {
    public static final String VERSION = "0.1.0";
    public static final String DEFAULT_BASE_URL = "https://app.bmailu.com/api/v1";

    private final String apiKey;
    private final String baseUrl;
    private final HttpClient http;

    private final Emails emails = new Emails();
    private final Events events = new Events();
    private final Contacts contacts = new Contacts();
    private final Broadcasts broadcasts = new Broadcasts();
    private final Templates templates = new Templates();
    private final Webhooks webhooks = new Webhooks();

    /** Uses the BMAILU_API_KEY and BMAILU_BASE_URL environment variables. */
    public Bmailu() { this(null, null); }

    public Bmailu(String apiKey) { this(apiKey, null); }

    public Bmailu(String apiKey, String baseUrl) {
        String key = apiKey != null ? apiKey : System.getenv("BMAILU_API_KEY");
        if (key == null || key.isEmpty()) throw new IllegalArgumentException("Missing API key: pass it or set BMAILU_API_KEY");
        String base = baseUrl != null ? baseUrl : System.getenv("BMAILU_BASE_URL");
        if (base == null || base.isEmpty()) base = DEFAULT_BASE_URL;
        this.apiKey = key;
        this.baseUrl = base.replaceAll("/+$", "");
        this.http = HttpClient.newBuilder().connectTimeout(Duration.ofSeconds(30)).build();
    }

    public Emails emails() { return emails; }
    public Events events() { return events; }
    public Contacts contacts() { return contacts; }
    public Broadcasts broadcasts() { return broadcasts; }
    public Templates templates() { return templates; }
    public Webhooks webhooks() { return webhooks; }

    /** Any API call: method, path under /api/v1 (e.g. "/domains"), optional JSON body and query. */
    public Object request(String method, String path, Object body, Map<String, ?> query) {
        return request(method, path, body, query, Collections.emptyMap());
    }

    public Object request(String method, String path, Object body, Map<String, ?> query, Map<String, String> headers) {
        StringBuilder url = new StringBuilder(baseUrl).append(path.startsWith("/") ? path : "/" + path);
        if (query != null && !query.isEmpty()) {
            char sep = url.indexOf("?") >= 0 ? '&' : '?';
            for (Map.Entry<String, ?> e : query.entrySet()) {
                if (e.getValue() == null) continue;
                url.append(sep).append(enc(e.getKey())).append('=').append(enc(String.valueOf(e.getValue())));
                sep = '&';
            }
        }
        HttpRequest.BodyPublisher pub = body == null ? HttpRequest.BodyPublishers.noBody()
                : HttpRequest.BodyPublishers.ofString(Json.write(body), StandardCharsets.UTF_8);
        HttpRequest.Builder rb = HttpRequest.newBuilder(URI.create(url.toString()))
                .timeout(Duration.ofSeconds(60))
                .method(method.toUpperCase(), pub)
                .header("Authorization", "Bearer " + apiKey)
                .header("Content-Type", "application/json")
                .header("Accept", "application/json")
                .header("User-Agent", "bmailu-java/" + VERSION);
        if (headers != null) headers.forEach((k, v) -> { if (v != null) rb.header(k, v); });
        HttpResponse<String> res;
        try {
            res = http.send(rb.build(), HttpResponse.BodyHandlers.ofString(StandardCharsets.UTF_8));
        } catch (IOException e) {
            throw new BmailuException(0, "network_error", "Could not reach Bmailu: " + e.getMessage(), e);
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
            throw new BmailuException(0, "network_error", "Interrupted", e);
        }
        String text = res.body();
        Object data = null;
        if (text != null && !text.isEmpty()) {
            try { data = Json.parse(text); } catch (IllegalArgumentException notJson) { data = text; }
        }
        if (res.statusCode() >= 400) {
            String code = "http_" + res.statusCode();
            String message = text == null || text.isEmpty() ? "HTTP " + res.statusCode() : text;
            if (data instanceof Map && ((Map<?, ?>) data).get("error") instanceof Map) {
                Map<?, ?> err = (Map<?, ?>) ((Map<?, ?>) data).get("error");
                if (err.get("code") != null) code = String.valueOf(err.get("code"));
                if (err.get("message") != null) message = String.valueOf(err.get("message"));
            }
            throw new BmailuException(res.statusCode(), code, message);
        }
        return data;
    }

    @SuppressWarnings("unchecked")
    private Map<String, Object> call(String method, String path, Object body, Map<String, ?> query, Map<String, String> headers) {
        Object r = request(method, path, body, query, headers);
        if (r == null) return new java.util.LinkedHashMap<>();
        if (r instanceof Map) return (Map<String, Object>) r;
        java.util.Map<String, Object> wrap = new java.util.LinkedHashMap<>();
        wrap.put("data", r);
        return wrap;
    }

    private Map<String, Object> call(String method, String path, Object body) {
        return call(method, path, body, null, null);
    }

    private static String enc(String s) {
        return URLEncoder.encode(s, StandardCharsets.UTF_8).replace("+", "%20");
    }

    private static Map<String, String> idem(String key) {
        return key == null ? null : Map.of("Idempotency-Key", key);
    }

    /** POST /emails and friends. */
    public final class Emails {
        public Map<String, Object> send(Map<String, ?> email) { return send(email, null); }
        /** The same idempotency key within 24 hours returns the first email instead of sending again. */
        public Map<String, Object> send(Map<String, ?> email, String idempotencyKey) { return call("POST", "/emails", email, null, idem(idempotencyKey)); }
        public Map<String, Object> batch(List<? extends Map<String, ?>> emails) { return batch(emails, null); }
        public Map<String, Object> batch(List<? extends Map<String, ?>> emails, String idempotencyKey) { return call("POST", "/emails/batch", emails, null, idem(idempotencyKey)); }
        public Map<String, Object> get(String id) { return call("GET", "/emails/" + enc(id), null); }
        public Map<String, Object> list() { return list(null); }
        /** Query: limit, before, status, source, key, domain, q, days. */
        public Map<String, Object> list(Map<String, ?> query) { return call("GET", "/emails", null, query, null); }
        /** Reschedule: {"scheduled_at": "in 10 minutes"}. */
        public Map<String, Object> update(String id, Map<String, ?> changes) { return call("PATCH", "/emails/" + enc(id), changes); }
        public Map<String, Object> cancel(String id) { return call("POST", "/emails/" + enc(id) + "/cancel", null); }
    }

    /** Custom events for automations. */
    public final class Events {
        /** {"event": "signup", "email": "ann@example.com", "data": {...}}. */
        public Map<String, Object> send(Map<String, ?> event) { return call("POST", "/events", event); }
        public Map<String, Object> list(Map<String, ?> query) { return call("GET", "/events", null, query, null); }
    }

    /** Contacts; the id may also be the contact's email address. */
    public final class Contacts {
        /** Creates or updates by email. */
        public Map<String, Object> create(Map<String, ?> contact) { return call("POST", "/contacts", contact); }
        public Map<String, Object> list() { return list(null); }
        /** Query: segment_id, status, q, limit, position. */
        public Map<String, Object> list(Map<String, ?> query) { return call("GET", "/contacts", null, query, null); }
        public Map<String, Object> get(String idOrEmail) { return call("GET", "/contacts/" + enc(idOrEmail), null); }
        public Map<String, Object> update(String idOrEmail, Map<String, ?> changes) { return call("PATCH", "/contacts/" + enc(idOrEmail), changes); }
        public Map<String, Object> delete(String idOrEmail) { return call("DELETE", "/contacts/" + enc(idOrEmail), null); }
        /** {"csv": "...", "mode": "confirm"|"attested", ...}. */
        public Map<String, Object> importCsv(Map<String, ?> options) { return call("POST", "/contacts/import", options); }
    }

    /** Broadcasts (each one is approved by Bmailu before it goes out). */
    public final class Broadcasts {
        public Map<String, Object> create(Map<String, ?> broadcast) { return call("POST", "/broadcasts", broadcast); }
        public Map<String, Object> list() { return call("GET", "/broadcasts", null); }
        public Map<String, Object> get(String id) { return call("GET", "/broadcasts/" + enc(id), null); }
        public Map<String, Object> update(String id, Map<String, ?> changes) { return call("PATCH", "/broadcasts/" + enc(id), changes); }
        public Map<String, Object> delete(String id) { return call("DELETE", "/broadcasts/" + enc(id), null); }
        public Map<String, Object> send(String id) { return send(id, null); }
        /** Options: {"scheduled_at": "in 2 hours"}. */
        public Map<String, Object> send(String id, Map<String, ?> options) { return call("POST", "/broadcasts/" + enc(id) + "/send", options); }
        public Map<String, Object> cancel(String id) { return call("POST", "/broadcasts/" + enc(id) + "/cancel", null); }
        public Map<String, Object> test(String id, String to) { return call("POST", "/broadcasts/" + enc(id) + "/test", Map.of("to", to)); }
        public Map<String, Object> stats(String id) { return call("GET", "/broadcasts/" + enc(id) + "/stats", null); }
    }

    /** Templates; the id may also be the alias. */
    public final class Templates {
        public Map<String, Object> create(Map<String, ?> template) { return call("POST", "/templates", template); }
        public Map<String, Object> list() { return call("GET", "/templates", null); }
        public Map<String, Object> get(String idOrAlias) { return call("GET", "/templates/" + enc(idOrAlias), null); }
        public Map<String, Object> update(String idOrAlias, Map<String, ?> changes) { return call("PATCH", "/templates/" + enc(idOrAlias), changes); }
        public Map<String, Object> delete(String idOrAlias) { return call("DELETE", "/templates/" + enc(idOrAlias), null); }
        public Map<String, Object> publish(String idOrAlias) { return call("POST", "/templates/" + enc(idOrAlias) + "/publish", null); }
        public Map<String, Object> duplicate(String idOrAlias) { return call("POST", "/templates/" + enc(idOrAlias) + "/duplicate", null); }
        public Map<String, Object> versions(String idOrAlias) { return call("GET", "/templates/" + enc(idOrAlias) + "/versions", null); }
        public Map<String, Object> publishVersion(String idOrAlias, int version) { return call("POST", "/templates/" + enc(idOrAlias) + "/versions/" + version + "/publish", null); }
        /** Options: {"variables": {...}, "version": 2}. */
        public Map<String, Object> render(String idOrAlias, Map<String, ?> options) { return call("POST", "/templates/" + enc(idOrAlias) + "/render", options); }
    }

    /** Webhook endpoints. To check a delivery's signature use {@link Webhook#verify}. */
    public final class Webhooks {
        /** {"url": "https://...", "events": ["email.delivered"]}; the answer holds signing_secret. */
        public Map<String, Object> create(Map<String, ?> webhook) { return call("POST", "/webhooks", webhook); }
        public Map<String, Object> list() { return call("GET", "/webhooks", null); }
        public Map<String, Object> get(String id) { return call("GET", "/webhooks/" + enc(id), null); }
        public Map<String, Object> update(String id, Map<String, ?> changes) { return call("PATCH", "/webhooks/" + enc(id), changes); }
        public Map<String, Object> delete(String id) { return call("DELETE", "/webhooks/" + enc(id), null); }
        public Map<String, Object> rotateSecret(String id) { return call("POST", "/webhooks/" + enc(id) + "/rotate-secret", null); }
        public Map<String, Object> test(String id) { return call("POST", "/webhooks/" + enc(id) + "/test", null); }
        public Map<String, Object> deliveries(String id) { return call("GET", "/webhooks/" + enc(id) + "/deliveries", null); }
        public Map<String, Object> replay(String deliveryId) { return call("POST", "/webhook-deliveries/" + enc(deliveryId) + "/replay", null); }
    }
}
