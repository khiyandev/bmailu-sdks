//! Checks the signature of a Bmailu webhook delivery (Standard Webhooks / Svix scheme).

use base64::{engine::general_purpose::STANDARD, Engine as _};
use hmac::{Hmac, Mac};
use sha2::Sha256;
use crate::{BmailuError, Result};
use serde_json::Value;
use std::time::{SystemTime, UNIX_EPOCH};

/// Deliveries with a timestamp further than this (seconds) from now are refused.
pub const TOLERANCE_SECONDS: i64 = 5 * 60;

fn fail(message: &str) -> BmailuError {
    BmailuError::new(400, "invalid_signature", message)
}

/// Checks `payload` (the raw body) against the `webhook-id`, `webhook-timestamp` and `webhook-signature` headers.
/// `headers` is any list of (name, value) pairs; names are matched without regard to case.
/// Returns the parsed body, or a [`BmailuError`] with code `invalid_signature`.
pub fn verify_webhook<'a, I>(secret: &str, payload: &str, headers: I) -> Result<Value>
where
    I: IntoIterator<Item = (&'a str, &'a str)>,
{
    let (mut id, mut ts, mut sig) = (None, None, None);
    for (k, v) in headers {
        match k.to_ascii_lowercase().as_str() {
            "webhook-id" => id = Some(v),
            "webhook-timestamp" => ts = Some(v),
            "webhook-signature" => sig = Some(v),
            _ => {}
        }
    }
    match (id, ts, sig) {
        (Some(i), Some(t), Some(s)) => verify_signature(secret, payload, i, t, s)?,
        _ => return Err(fail("Missing webhook headers")),
    }
    serde_json::from_str(payload).map_err(|_| fail("Webhook body is not JSON"))
}

/// Checks one delivery given the three header values. Ok(()) when one of the signatures matches.
pub fn verify_signature(secret: &str, payload: &str, id: &str, timestamp: &str, signature: &str) -> Result<()> {
    let ts: i64 = timestamp.trim().parse().map_err(|_| fail("Bad webhook-timestamp"))?;
    let now = SystemTime::now().duration_since(UNIX_EPOCH).map(|d| d.as_secs() as i64).unwrap_or(0);
    if (now - ts).abs() > TOLERANCE_SECONDS {
        return Err(fail("Webhook timestamp is too old or too new"));
    }
    let key = STANDARD.decode(secret.strip_prefix("whsec_").unwrap_or(secret)).map_err(|_| fail("Bad webhook secret"))?;
    let mut mac = Hmac::<Sha256>::new_from_slice(&key).map_err(|_| fail("Bad webhook secret"))?;
    mac.update(format!("{id}.{timestamp}.{payload}").as_bytes());
    for part in signature.split_whitespace() {
        if let Some(b64) = part.strip_prefix("v1,") {
            if let Ok(given) = STANDARD.decode(b64) {
                // verify_slice compares in constant time.
                if mac.clone().verify_slice(&given).is_ok() {
                    return Ok(());
                }
            }
        }
    }
    Err(fail("No matching webhook signature"))
}
