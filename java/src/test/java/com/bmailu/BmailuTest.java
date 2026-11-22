package com.bmailu;

import static org.junit.jupiter.api.Assertions.*;

import java.util.List;
import java.util.Map;
import java.util.UUID;
import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.Test;

/** Runs against the shared local API (sdks/test-server.js), started by test.sh. */
class BmailuTest {
    static final String FROM = "Shop <hello@shop.test>";
    static final String TO = "delivered@simulator.bmailu.com";
    static Bmailu bmailu;

    @BeforeAll
    static void setUp() {
        bmailu = new Bmailu(env("BMAILU_TEST_KEY"), env("BMAILU_TEST_URL"));
    }

    static String env(String name) {
        String v = System.getenv(name);
        assertNotNull(v, name + " is not set; run test.sh");
        return v;
    }

    static Map<String, Object> email(String subject) {
        return Map.of("from", FROM, "to", TO, "subject", subject, "html", "<p>Hello from Java</p>");
    }

    @Test
    void sendGetAndIdempotency() {
        String key = "java-" + UUID.randomUUID();
        Map<String, Object> a = bmailu.emails().send(email("Idempotent"), key);
        Map<String, Object> b = bmailu.emails().send(email("Idempotent"), key);
        String id = (String) a.get("id");
        assertNotNull(id);
        assertEquals(id, b.get("id"));
        Map<String, Object> got = bmailu.emails().get(id);
        assertEquals(id, got.get("id"));
        assertEquals("Idempotent", got.get("subject"));
        Map<String, Object> list = bmailu.emails().list(Map.of("limit", 5));
        assertEquals("list", list.get("object"));
        assertFalse(((List<?>) list.get("data")).isEmpty());
    }

    @Test
    void batchOfTwo() {
        Map<String, Object> r = bmailu.emails().batch(List.of(email("Batch 1"), email("Batch 2")));
        List<?> data = (List<?>) r.get("data");
        assertEquals(2, data.size());
        assertNotNull(((Map<?, ?>) data.get(0)).get("id"));
    }

    @Test
    void scheduleAndCancel() {
        Map<String, Object> m = new java.util.HashMap<>(email("Later"));
        m.put("to", "scheduled.java@example.com"); // test addresses are sent at once, so use a stubbed real one
        m.put("scheduled_at", "in 1 hour");
        String id = (String) bmailu.emails().send(m).get("id");
        bmailu.emails().update(id, Map.of("scheduled_at", "in 2 hours"));
        Map<String, Object> c = bmailu.emails().cancel(id);
        assertNotNull(c);
        assertEquals("canceled", bmailu.emails().get(id).get("last_event"));
    }

    @Test
    void contactAndEvent() {
        String addr = "ann.java@example.com";
        Map<String, Object> c = bmailu.contacts().create(Map.of("email", addr, "first_name", "Ann"));
        assertEquals("contact", c.get("object"));
        assertEquals(addr, bmailu.contacts().get(addr).get("email"));
        Map<String, Object> e = bmailu.events().send(Map.of("event", "signup", "email", addr, "data", Map.of("plan", "free")));
        assertEquals("event", e.get("object"));
        assertEquals(c.get("id"), e.get("contact_id"));
    }

    @Test
    void templatePublishAndSend() {
        String alias = "java-welcome";
        Map<String, Object> t = bmailu.templates().create(Map.of("name", "Java welcome", "alias", alias,
                "subject", "Hi {{NAME}}", "html", "<p>Hi {{NAME}}</p>",
                "variables", List.of(Map.of("key", "NAME", "type", "string", "fallback_value", "there"))));
        assertEquals("draft", t.get("status"));
        Map<String, Object> p = bmailu.templates().publish(alias);
        assertEquals("published", p.get("status"));
        Map<String, Object> sent = bmailu.emails().send(Map.of("from", FROM, "to", TO,
                "template", Map.of("id", alias, "variables", Map.of("NAME", "Bo"))));
        assertEquals("Hi Bo", bmailu.emails().get((String) sent.get("id")).get("subject"));
    }

    @Test
    void broadcastDraft() {
        Map<String, Object> b = bmailu.broadcasts().create(Map.of("name", "Java broadcast"));
        assertEquals("draft", b.get("status"));
        assertEquals("broadcast", bmailu.broadcasts().get((String) b.get("id")).get("object"));
        bmailu.broadcasts().delete((String) b.get("id"));
    }

    @Test
    void listWebhooksAndGenericRequest() {
        Map<String, Object> w = bmailu.webhooks().list();
        assertEquals("list", w.get("object"));
        Object me = bmailu.request("GET", "/me", null, null);
        assertTrue(me instanceof Map);
    }

    @Test
    void errorBadFromDomain() {
        BmailuException e = assertThrows(BmailuException.class, () -> bmailu.emails().send(
                Map.of("from", "Shop <hello@not-mine.test>", "to", TO, "subject", "x", "text", "x")));
        assertEquals("domain_not_verified", e.getCode());
        assertTrue(e.getStatus() >= 400);
        assertFalse(e.getMessage().isEmpty());
    }

    @Test
    void sendingKeyIsRestricted() {
        Bmailu sending = new Bmailu(env("BMAILU_TEST_SENDING_KEY"), env("BMAILU_TEST_URL"));
        BmailuException e = assertThrows(BmailuException.class, () -> sending.request("GET", "/domains", null, null));
        assertEquals("restricted_api_key", e.getCode());
        assertEquals(403, e.getStatus());
    }

    @Test
    void webhookVerification() {
        String secret = env("WH_SECRET"), body = env("WH_BODY");
        Map<String, String> headers = Map.of("Webhook-Id", env("WH_ID"), "webhook-timestamp", env("WH_TS"), "webhook-signature", env("WH_SIG"));
        Map<String, Object> event = Webhook.verify(secret, body, headers);
        assertEquals("email.delivered", event.get("type"));
        BmailuException tampered = assertThrows(BmailuException.class, () -> Webhook.verify(secret, body.replace("e1", "e2"), headers));
        assertEquals("invalid_signature", tampered.getCode());
        assertThrows(BmailuException.class, () -> Webhook.verify(secret, body, env("WH_ID"), "1000000000", env("WH_SIG")));
        assertThrows(BmailuException.class, () -> Webhook.verify("whsec_" + java.util.Base64.getEncoder().encodeToString("other".getBytes()), body, headers));
    }

    @Test
    void jsonRoundTrip() {
        String s = Json.write(Map.of("a", List.of(1, 2.5, "x\"\n", true), "b", Map.of()));
        @SuppressWarnings("unchecked")
        Map<String, Object> back = (Map<String, Object>) Json.parse(s);
        assertEquals(List.of(1L, 2.5, "x\"\n", true), back.get("a"));
        assertEquals("\u00e9", Json.parse("\"\\u00e9\""));
    }
}
