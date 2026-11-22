"""Verify Bmailu webhooks (Standard Webhooks / Svix scheme)."""
from __future__ import annotations

import base64
import binascii
import hashlib
import hmac
import json
import time
from typing import Any, Mapping, Optional, Union


class WebhookVerificationError(Exception):
    """The webhook signature or timestamp is not valid."""


def _header(headers: Mapping[str, Any], name: str) -> str:
    for k, v in headers.items():
        if k.lower() == name:
            return str(v)
    raise WebhookVerificationError(f"Missing {name} header")


def verify_webhook(
    payload: Union[str, bytes],
    headers: Mapping[str, Any],
    secret: str,
    tolerance: int = 300,
    now: Optional[float] = None,
) -> Any:
    """Check the signature of a webhook and return the parsed JSON body.

    payload: the raw request body, exactly as received.
    headers: the request headers (webhook-id, webhook-timestamp, webhook-signature).
    secret: the endpoint's signing secret (whsec_...).
    Raises WebhookVerificationError when anything is wrong.
    """
    body = payload.decode("utf-8") if isinstance(payload, bytes) else payload
    msg_id = _header(headers, "webhook-id")
    ts = _header(headers, "webhook-timestamp")
    sigs = _header(headers, "webhook-signature")
    try:
        ts_int = int(ts)
    except ValueError:
        raise WebhookVerificationError("Bad webhook-timestamp") from None
    current = time.time() if now is None else now
    if abs(current - ts_int) > tolerance:
        raise WebhookVerificationError("Timestamp is more than 5 minutes away")
    raw_secret = secret[6:] if secret.startswith("whsec_") else secret
    try:
        key = base64.b64decode(raw_secret)
    except (binascii.Error, ValueError):
        raise WebhookVerificationError("Bad secret") from None
    expected = base64.b64encode(
        hmac.new(key, f"{msg_id}.{ts}.{body}".encode("utf-8"), hashlib.sha256).digest()
    ).decode("ascii")
    ok = False
    for part in sigs.split(" "):
        version, _, sig = part.partition(",")
        if version == "v1" and hmac.compare_digest(sig.encode("ascii", "replace"), expected.encode("ascii")):
            ok = True
    if not ok:
        raise WebhookVerificationError("No matching signature")
    return json.loads(body) if body else None
