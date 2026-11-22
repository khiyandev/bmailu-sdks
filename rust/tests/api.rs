//! Runs against the shared local API (sdks/test-server.js), started by test.sh.

use bmailu::{verify_webhook, webhook::verify_signature, Bmailu};
use serde_json::json;

const FROM: &str = "Shop <hello@shop.test>";
const TO: &str = "delivered@simulator.bmailu.com";

fn env(name: &str) -> String {
    std::env::var(name).unwrap_or_else(|_| panic!("{name} is not set; run test.sh"))
}

fn client() -> Bmailu {
    Bmailu::new(env("BMAILU_TEST_KEY")).with_base_url(env("BMAILU_TEST_URL"))
}

#[test]
fn send_get_and_idempotency() {
    let c = client();
    let key = format!("rust-{}", std::process::id());
    let email = json!({"from": FROM, "to": TO, "subject": "Idempotent", "html": "<p>Hello from Rust</p>"});
    let a = c.emails().send_with_idempotency_key(&email, &key).unwrap();
    let b = c.emails().send_with_idempotency_key(&email, &key).unwrap();
    let id = a["id"].as_str().unwrap();
    assert_eq!(a["id"], b["id"]);
    assert_eq!(c.emails().get(id).unwrap()["subject"], "Idempotent");
    let list = c.emails().list(&[("limit", "5")]).unwrap();
    assert_eq!(list["object"], "list");
    assert!(!list["data"].as_array().unwrap().is_empty());
    assert!(c.emails().send(&json!({"from": FROM, "to": TO, "subject": "Plain", "text": "x"})).unwrap()["id"].is_string());
}

#[test]
fn batch_of_two() {
    let r = client().emails().batch(&json!([
        {"from": FROM, "to": TO, "subject": "Batch 1", "text": "one"},
        {"from": FROM, "to": [TO], "subject": "Batch 2", "text": "two"}
    ])).unwrap();
    assert_eq!(r["data"].as_array().unwrap().len(), 2);
    assert!(r["data"][0]["id"].is_string());
}

#[test]
fn schedule_and_cancel() {
    let c = client();
    // Test addresses are sent at once, so use a real-looking one (the test server stubs delivery).
    let e = c.emails().send(&json!({"from": FROM, "to": "scheduled.rust@example.com", "subject": "Later", "text": "x", "scheduled_at": "in 1 hour"})).unwrap();
    let id = e["id"].as_str().unwrap();
    c.emails().update(id, &json!({"scheduled_at": "in 2 hours"})).unwrap();
    c.emails().cancel(id).unwrap();
    assert_eq!(c.emails().get(id).unwrap()["last_event"], "canceled");
}

#[test]
fn contact_and_event() {
    let c = client();
    let addr = "ann.rust@example.com";
    let contact = c.contacts().create(&json!({"email": addr, "first_name": "Ann"})).unwrap();
    assert_eq!(contact["object"], "contact");
    assert_eq!(c.contacts().get(addr).unwrap()["email"], addr);
    let ev = c.events().send(&json!({"event": "signup", "email": addr, "data": {"plan": "free"}})).unwrap();
    assert_eq!(ev["contact_id"], contact["id"]);
}

#[test]
fn template_publish_and_send() {
    let c = client();
    let t = c.templates().create(&json!({
        "name": "Rust welcome", "alias": "rust-welcome", "subject": "Hi {{NAME}}", "html": "<p>Hi {{NAME}}</p>",
        "variables": [{"key": "NAME", "type": "string", "fallback_value": "there"}]
    })).unwrap();
    assert_eq!(t["status"], "draft");
    assert_eq!(c.templates().publish("rust-welcome").unwrap()["status"], "published");
    let sent = c.emails().send(&json!({"from": FROM, "to": TO, "template": {"id": "rust-welcome", "variables": {"NAME": "Bo"}}})).unwrap();
    assert_eq!(c.emails().get(sent["id"].as_str().unwrap()).unwrap()["subject"], "Hi Bo");
}

#[test]
fn broadcast_draft() {
    let c = client();
    let b = c.broadcasts().create(&json!({"name": "Rust broadcast"})).unwrap();
    assert_eq!(b["status"], "draft");
    let id = b["id"].as_str().unwrap();
    assert_eq!(c.broadcasts().get(id).unwrap()["object"], "broadcast");
    c.broadcasts().delete(id).unwrap();
}

#[test]
fn list_webhooks_and_generic_request() {
    let c = client();
    assert_eq!(c.webhooks().list().unwrap()["object"], "list");
    assert!(c.request("GET", "/me", None, &[]).unwrap().is_object());
}

#[test]
fn error_bad_from_domain() {
    let e = client().emails().send(&json!({"from": "Shop <hello@not-mine.test>", "to": TO, "subject": "x", "text": "x"})).unwrap_err();
    assert_eq!(e.code, "domain_not_verified");
    assert!(e.status >= 400);
}

#[test]
fn sending_key_is_restricted() {
    let sending = Bmailu::new(env("BMAILU_TEST_SENDING_KEY")).with_base_url(env("BMAILU_TEST_URL"));
    let e = sending.request("GET", "/domains", None, &[]).unwrap_err();
    assert_eq!(e.code, "restricted_api_key");
    assert_eq!(e.status, 403);
}

#[test]
fn webhook_verification() {
    let (secret, body, id, ts, sig) = (env("WH_SECRET"), env("WH_BODY"), env("WH_ID"), env("WH_TS"), env("WH_SIG"));
    let headers = [("Webhook-Id", id.as_str()), ("webhook-timestamp", ts.as_str()), ("webhook-signature", sig.as_str())];
    let ev = verify_webhook(&secret, &body, headers).unwrap();
    assert_eq!(ev["type"], "email.delivered");
    let e = verify_webhook(&secret, &body.replace("e1", "e2"), headers).unwrap_err();
    assert_eq!(e.code, "invalid_signature");
    assert!(verify_signature(&secret, &body, &id, "1000000000", &sig).is_err());
    assert!(verify_webhook("whsec_AQID", &body, headers).is_err());
}
