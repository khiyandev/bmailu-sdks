//! Rust SDK for the Bmailu Email API (blocking).
//!
//! ```no_run
//! use serde_json::json;
//! let bmailu = bmailu::Bmailu::new("bmk_your_api_key");
//! let email = bmailu.emails().send(&json!({"from": "Shop <hello@your-domain.com>", "to": "ann@example.com", "subject": "Hello", "html": "<p>It works.</p>"}))?;
//! println!("{}", email["id"]);
//! # Ok::<(), bmailu::BmailuError>(())
//! ```
//!
//! Bodies and answers are [`serde_json::Value`]. Every call returns `Result<Value, BmailuError>`.

use serde_json::Value;
use std::fmt;
use std::time::Duration;

pub mod webhook;

pub use webhook::verify_webhook;

/// SDK version.
pub const VERSION: &str = "0.1.0";
/// Default API address.
pub const DEFAULT_BASE_URL: &str = "https://app.bmailu.com/api/v1";

/// An error from the Bmailu API (`{"error":{"code","message"}}`) or from the network.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct BmailuError {
    /// HTTP status, or 0 when the request did not reach the API.
    pub status: u16,
    /// Machine-readable code, e.g. `not_found`, `validation_error`, `domain_not_verified`.
    pub code: String,
    /// Plain-words explanation.
    pub message: String,
}

impl BmailuError {
    pub(crate) fn new(status: u16, code: &str, message: impl Into<String>) -> Self {
        BmailuError { status, code: code.to_string(), message: message.into() }
    }
}

impl fmt::Display for BmailuError {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        write!(f, "bmailu error {} ({}): {}", self.status, self.code, self.message)
    }
}

impl std::error::Error for BmailuError {}

/// Shorthand for results of this crate.
pub type Result<T> = std::result::Result<T, BmailuError>;

/// Bmailu API client.
#[derive(Clone)]
pub struct Bmailu {
    api_key: String,
    base_url: String,
    agent: ureq::Agent,
}

impl fmt::Debug for Bmailu {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        f.debug_struct("Bmailu").field("base_url", &self.base_url).finish_non_exhaustive()
    }
}

impl Bmailu {
    /// A client with this API key. The base URL comes from `BMAILU_BASE_URL` or the default.
    pub fn new(api_key: impl Into<String>) -> Self {
        let base = std::env::var("BMAILU_BASE_URL").ok().filter(|s| !s.is_empty()).unwrap_or_else(|| DEFAULT_BASE_URL.to_string());
        Bmailu {
            api_key: api_key.into(),
            base_url: base.trim_end_matches('/').to_string(),
            agent: ureq::AgentBuilder::new().timeout_connect(Duration::from_secs(30)).timeout(Duration::from_secs(60)).build(),
        }
    }

    /// A client with the key from `BMAILU_API_KEY`.
    pub fn from_env() -> Result<Self> {
        match std::env::var("BMAILU_API_KEY") {
            Ok(k) if !k.is_empty() => Ok(Bmailu::new(k)),
            _ => Err(BmailuError::new(0, "missing_api_key", "Set BMAILU_API_KEY or use Bmailu::new(key)")),
        }
    }

    /// Use another API address, e.g. a local test server.
    pub fn with_base_url(mut self, base_url: impl Into<String>) -> Self {
        self.base_url = base_url.into().trim_end_matches('/').to_string();
        self
    }

    /// Any API call: method (`"GET"`, `"POST"`, ...), path under /api/v1 (e.g. `"/domains"`), optional body and query.
    pub fn request(&self, method: &str, path: &str, body: Option<&Value>, query: &[(&str, &str)]) -> Result<Value> {
        self.request_with_headers(method, path, body, query, &[])
    }

    /// Like [`Bmailu::request`] with extra headers.
    pub fn request_with_headers(&self, method: &str, path: &str, body: Option<&Value>, query: &[(&str, &str)], headers: &[(&str, &str)]) -> Result<Value> {
        let path = if path.starts_with('/') { path.to_string() } else { format!("/{path}") };
        let mut req = self
            .agent
            .request(&method.to_uppercase(), &format!("{}{}", self.base_url, path))
            .set("Authorization", &format!("Bearer {}", self.api_key))
            .set("Content-Type", "application/json")
            .set("Accept", "application/json")
            .set("User-Agent", &format!("bmailu-rust/{VERSION}"));
        for (k, v) in query {
            req = req.query(k, v);
        }
        for (k, v) in headers {
            req = req.set(k, v);
        }
        let result = match body {
            Some(b) => req.send_string(&b.to_string()),
            None => req.call(),
        };
        match result {
            Ok(res) => parse_body(res).map(|(v, _)| v),
            Err(ureq::Error::Status(status, res)) => {
                let (v, text) = parse_body(res)?;
                let err = v.get("error");
                let code = err.and_then(|e| e.get("code")).and_then(Value::as_str).map(str::to_string).unwrap_or_else(|| format!("http_{status}"));
                let message = err.and_then(|e| e.get("message")).and_then(Value::as_str).map(str::to_string)
                    .unwrap_or_else(|| if text.is_empty() { format!("HTTP {status}") } else { text });
                Err(BmailuError { status, code, message })
            }
            Err(e) => Err(BmailuError::new(0, "network_error", format!("Could not reach Bmailu: {e}"))),
        }
    }

    pub fn emails(&self) -> Emails<'_> { Emails(self) }
    pub fn events(&self) -> Events<'_> { Events(self) }
    pub fn contacts(&self) -> Contacts<'_> { Contacts(self) }
    pub fn broadcasts(&self) -> Broadcasts<'_> { Broadcasts(self) }
    pub fn templates(&self) -> Templates<'_> { Templates(self) }
    pub fn webhooks(&self) -> Webhooks<'_> { Webhooks(self) }
}

fn parse_body(res: ureq::Response) -> Result<(Value, String)> {
    let text = res.into_string().map_err(|e| BmailuError::new(0, "network_error", format!("Could not read the answer: {e}")))?;
    if text.is_empty() {
        return Ok((Value::Null, text));
    }
    let v = serde_json::from_str(&text).unwrap_or_else(|_| Value::String(text.clone()));
    Ok((v, text))
}

/// Percent-encodes one path segment (ids, emails, aliases).
fn seg(s: &str) -> String {
    let mut out = String::with_capacity(s.len());
    for b in s.bytes() {
        if b.is_ascii_alphanumeric() || matches!(b, b'-' | b'_' | b'.' | b'~') {
            out.push(b as char);
        } else {
            out.push_str(&format!("%{b:02X}"));
        }
    }
    out
}

/// Emails: send (with optional idempotency key), batch, get, list, update (reschedule), cancel.
pub struct Emails<'a>(&'a Bmailu);

impl Emails<'_> {
    /// POST /emails.
    pub fn send(&self, email: &Value) -> Result<Value> { self.0.request("POST", "/emails", Some(email), &[]) }
    /// POST /emails with `Idempotency-Key`: the same key within 24 hours returns the first email instead of sending again.
    pub fn send_with_idempotency_key(&self, email: &Value, key: &str) -> Result<Value> {
        self.0.request_with_headers("POST", "/emails", Some(email), &[], &[("Idempotency-Key", key)])
    }
    /// POST /emails/batch with a JSON array of up to 100 emails.
    pub fn batch(&self, emails: &Value) -> Result<Value> { self.0.request("POST", "/emails/batch", Some(emails), &[]) }
    /// Batch with `Idempotency-Key`.
    pub fn batch_with_idempotency_key(&self, emails: &Value, key: &str) -> Result<Value> {
        self.0.request_with_headers("POST", "/emails/batch", Some(emails), &[], &[("Idempotency-Key", key)])
    }
    pub fn get(&self, id: &str) -> Result<Value> { self.0.request("GET", &format!("/emails/{}", seg(id)), None, &[]) }
    /// Query: limit, before, status, source, key, domain, q, days.
    pub fn list(&self, query: &[(&str, &str)]) -> Result<Value> { self.0.request("GET", "/emails", None, query) }
    /// Reschedule: `{"scheduled_at": "in 10 minutes"}`.
    pub fn update(&self, id: &str, changes: &Value) -> Result<Value> { self.0.request("PATCH", &format!("/emails/{}", seg(id)), Some(changes), &[]) }
    pub fn cancel(&self, id: &str) -> Result<Value> { self.0.request("POST", &format!("/emails/{}/cancel", seg(id)), None, &[]) }
}

/// Custom events for automations.
pub struct Events<'a>(&'a Bmailu);

impl Events<'_> {
    /// `{"event": "signup", "email": "ann@example.com", "data": {...}}`.
    pub fn send(&self, event: &Value) -> Result<Value> { self.0.request("POST", "/events", Some(event), &[]) }
    /// Query: contact (id or email).
    pub fn list(&self, query: &[(&str, &str)]) -> Result<Value> { self.0.request("GET", "/events", None, query) }
}

/// Contacts; the id may also be the contact's email address.
pub struct Contacts<'a>(&'a Bmailu);

impl Contacts<'_> {
    /// Creates or updates by email.
    pub fn create(&self, contact: &Value) -> Result<Value> { self.0.request("POST", "/contacts", Some(contact), &[]) }
    /// Query: segment_id, status, q, limit, position.
    pub fn list(&self, query: &[(&str, &str)]) -> Result<Value> { self.0.request("GET", "/contacts", None, query) }
    pub fn get(&self, id_or_email: &str) -> Result<Value> { self.0.request("GET", &format!("/contacts/{}", seg(id_or_email)), None, &[]) }
    pub fn update(&self, id_or_email: &str, changes: &Value) -> Result<Value> { self.0.request("PATCH", &format!("/contacts/{}", seg(id_or_email)), Some(changes), &[]) }
    pub fn delete(&self, id_or_email: &str) -> Result<Value> { self.0.request("DELETE", &format!("/contacts/{}", seg(id_or_email)), None, &[]) }
    /// `{"csv": "...", "mode": "confirm" | "attested", ...}`.
    pub fn import(&self, options: &Value) -> Result<Value> { self.0.request("POST", "/contacts/import", Some(options), &[]) }
}

/// Broadcasts (each one is approved by Bmailu before it goes out).
pub struct Broadcasts<'a>(&'a Bmailu);

impl Broadcasts<'_> {
    pub fn create(&self, broadcast: &Value) -> Result<Value> { self.0.request("POST", "/broadcasts", Some(broadcast), &[]) }
    pub fn list(&self) -> Result<Value> { self.0.request("GET", "/broadcasts", None, &[]) }
    pub fn get(&self, id: &str) -> Result<Value> { self.0.request("GET", &format!("/broadcasts/{}", seg(id)), None, &[]) }
    pub fn update(&self, id: &str, changes: &Value) -> Result<Value> { self.0.request("PATCH", &format!("/broadcasts/{}", seg(id)), Some(changes), &[]) }
    pub fn delete(&self, id: &str) -> Result<Value> { self.0.request("DELETE", &format!("/broadcasts/{}", seg(id)), None, &[]) }
    /// Options: `Some(&json!({"scheduled_at": "in 2 hours"}))`.
    pub fn send(&self, id: &str, options: Option<&Value>) -> Result<Value> { self.0.request("POST", &format!("/broadcasts/{}/send", seg(id)), options, &[]) }
    pub fn cancel(&self, id: &str) -> Result<Value> { self.0.request("POST", &format!("/broadcasts/{}/cancel", seg(id)), None, &[]) }
    pub fn test(&self, id: &str, to: &str) -> Result<Value> {
        self.0.request("POST", &format!("/broadcasts/{}/test", seg(id)), Some(&serde_json::json!({ "to": to })), &[])
    }
    pub fn stats(&self, id: &str) -> Result<Value> { self.0.request("GET", &format!("/broadcasts/{}/stats", seg(id)), None, &[]) }
}

/// Templates; the id may also be the alias.
pub struct Templates<'a>(&'a Bmailu);

impl Templates<'_> {
    pub fn create(&self, template: &Value) -> Result<Value> { self.0.request("POST", "/templates", Some(template), &[]) }
    pub fn list(&self) -> Result<Value> { self.0.request("GET", "/templates", None, &[]) }
    pub fn get(&self, id: &str) -> Result<Value> { self.0.request("GET", &format!("/templates/{}", seg(id)), None, &[]) }
    pub fn update(&self, id: &str, changes: &Value) -> Result<Value> { self.0.request("PATCH", &format!("/templates/{}", seg(id)), Some(changes), &[]) }
    pub fn delete(&self, id: &str) -> Result<Value> { self.0.request("DELETE", &format!("/templates/{}", seg(id)), None, &[]) }
    pub fn publish(&self, id: &str) -> Result<Value> { self.0.request("POST", &format!("/templates/{}/publish", seg(id)), None, &[]) }
    pub fn duplicate(&self, id: &str) -> Result<Value> { self.0.request("POST", &format!("/templates/{}/duplicate", seg(id)), None, &[]) }
    pub fn versions(&self, id: &str) -> Result<Value> { self.0.request("GET", &format!("/templates/{}/versions", seg(id)), None, &[]) }
    pub fn publish_version(&self, id: &str, version: u32) -> Result<Value> {
        self.0.request("POST", &format!("/templates/{}/versions/{version}/publish", seg(id)), None, &[])
    }
    /// Options: `Some(&json!({"variables": {...}, "version": 2}))`.
    pub fn render(&self, id: &str, options: Option<&Value>) -> Result<Value> { self.0.request("POST", &format!("/templates/{}/render", seg(id)), options, &[]) }
}

/// Webhook endpoints. To check a delivery's signature use [`verify_webhook`].
pub struct Webhooks<'a>(&'a Bmailu);

impl Webhooks<'_> {
    /// `{"url": "https://...", "events": ["email.delivered"]}`; the answer holds `signing_secret`.
    pub fn create(&self, webhook: &Value) -> Result<Value> { self.0.request("POST", "/webhooks", Some(webhook), &[]) }
    pub fn list(&self) -> Result<Value> { self.0.request("GET", "/webhooks", None, &[]) }
    pub fn get(&self, id: &str) -> Result<Value> { self.0.request("GET", &format!("/webhooks/{}", seg(id)), None, &[]) }
    pub fn update(&self, id: &str, changes: &Value) -> Result<Value> { self.0.request("PATCH", &format!("/webhooks/{}", seg(id)), Some(changes), &[]) }
    pub fn delete(&self, id: &str) -> Result<Value> { self.0.request("DELETE", &format!("/webhooks/{}", seg(id)), None, &[]) }
    pub fn rotate_secret(&self, id: &str) -> Result<Value> { self.0.request("POST", &format!("/webhooks/{}/rotate-secret", seg(id)), None, &[]) }
    pub fn test(&self, id: &str) -> Result<Value> { self.0.request("POST", &format!("/webhooks/{}/test", seg(id)), None, &[]) }
    pub fn deliveries(&self, id: &str) -> Result<Value> { self.0.request("GET", &format!("/webhooks/{}/deliveries", seg(id)), None, &[]) }
    pub fn replay(&self, delivery_id: &str) -> Result<Value> { self.0.request("POST", &format!("/webhook-deliveries/{}/replay", seg(delivery_id)), None, &[]) }
}

#[cfg(test)]
mod tests {
    #[test]
    fn seg_encodes() {
        assert_eq!(super::seg("a b@c.d/e"), "a%20b%40c.d%2Fe");
    }
}
