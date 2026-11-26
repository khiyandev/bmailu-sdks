"""Bmailu API client. Standard library only (urllib + json)."""
from __future__ import annotations

import json
import os
import urllib.error
import urllib.parse
import urllib.request
from typing import Any, Dict, List, Mapping, Optional, Union

VERSION = "0.1.0"
DEFAULT_BASE_URL = "https://app.bmailu.com/api/v1"

JSON = Dict[str, Any]
Params = Optional[Mapping[str, Any]]


class BmailuError(Exception):
    """An error answer from the API (or a network failure, status 0)."""

    def __init__(self, status: int, code: str, message: str, body: Any = None) -> None:
        super().__init__(f"{code}: {message} (HTTP {status})")
        self.status = status
        self.code = code
        self.message = message
        self.body = body


def _seg(value: Any) -> str:
    """One URL path segment, fully encoded."""
    return urllib.parse.quote(str(value), safe="")


def _query(query: Params) -> str:
    if not query:
        return ""
    items = []
    for k, v in query.items():
        if v is None:
            continue
        if isinstance(v, bool):
            v = "true" if v else "false"
        items.append((k, str(v)))
    return ("?" + urllib.parse.urlencode(items)) if items else ""


class Bmailu:
    """Client for the Bmailu API.

    >>> bmailu = Bmailu("bmk_...")
    >>> bmailu.emails.send({"from": "Shop <hello@shop.com>", "to": "ann@example.com", "subject": "Hi", "html": "<p>Hi</p>"})
    """

    def __init__(self, api_key: Optional[str] = None, base_url: Optional[str] = None, timeout: float = 30.0) -> None:
        self.api_key = api_key or os.environ.get("BMAILU_API_KEY")
        if not self.api_key:
            raise ValueError("Missing API key: pass it to Bmailu() or set BMAILU_API_KEY")
        self.base_url = (base_url or os.environ.get("BMAILU_BASE_URL") or DEFAULT_BASE_URL).rstrip("/")
        self.timeout = timeout
        self.me = Me(self)
        self.domains = Domains(self)
        self.mailboxes = Mailboxes(self)
        self.aliases = Aliases(self)
        self.api_keys = ApiKeys(self)
        self.sending_domains = SendingDomains(self)
        self.emails = Emails(self)
        self.webhooks = Webhooks(self)
        self.suppressions = Suppressions(self)
        self.metrics = Metrics(self)
        self.logs = Logs(self)
        self.inbox = Inbox(self)
        self.templates = Templates(self)
        self.contacts = Contacts(self)
        self.segments = Segments(self)
        self.topics = Topics(self)
        self.broadcasts = Broadcasts(self)
        self.events = Events(self)
        self.automations = Automations(self)

    def request(
        self,
        method: str,
        path: str,
        body: Any = None,
        query: Params = None,
        headers: Optional[Mapping[str, str]] = None,
    ) -> Any:
        """Call any endpoint. Returns parsed JSON, a str for text answers or bytes for files."""
        url = self.base_url + (path if path.startswith("/") else "/" + path) + _query(query)
        data = None if body is None else json.dumps(body).encode("utf-8")
        h = {
            "Authorization": "Bearer " + str(self.api_key),
            "Content-Type": "application/json",
            "Accept": "application/json",
            "User-Agent": "bmailu-python/" + VERSION,
        }
        if headers:
            h.update({k: v for k, v in headers.items() if v is not None})
        req = urllib.request.Request(url, data=data, method=method.upper(), headers=h)
        try:
            with urllib.request.urlopen(req, timeout=self.timeout) as res:
                return self._parse(res.read(), res.headers.get("Content-Type", ""))
        except urllib.error.HTTPError as e:
            raw = e.read()
            parsed = self._parse(raw, e.headers.get("Content-Type", "") if e.headers else "")
            err = parsed.get("error") if isinstance(parsed, dict) else None
            if isinstance(err, dict):
                raise BmailuError(e.code, str(err.get("code", "http_error")), str(err.get("message", "")), parsed) from None
            text = parsed if isinstance(parsed, str) else raw.decode("utf-8", "replace")
            raise BmailuError(e.code, "http_error", text[:500] or e.reason, parsed) from None
        except urllib.error.URLError as e:
            raise BmailuError(0, "connection_error", str(e.reason)) from None

    @staticmethod
    def _parse(raw: bytes, content_type: str) -> Any:
        if not raw:
            return None
        ct = content_type.lower()
        if "json" in ct:
            return json.loads(raw.decode("utf-8"))
        if ct.startswith("text/"):
            return raw.decode("utf-8", "replace")
        return raw


class _Resource:
    def __init__(self, client: Bmailu) -> None:
        self._c = client

    def _r(self, method: str, path: str, body: Any = None, query: Params = None, headers: Optional[Mapping[str, str]] = None) -> Any:
        return self._c.request(method, path, body, query, headers)


class Me(_Resource):
    def get(self) -> JSON:
        return self._r("GET", "/me")

    def update(self, params: Mapping[str, Any]) -> JSON:
        return self._r("PATCH", "/me", params)


class Domains(_Resource):
    """Mailbox domains."""

    def list(self) -> JSON:
        return self._r("GET", "/domains")

    def create(self, params: Mapping[str, Any]) -> JSON:
        return self._r("POST", "/domains", params)

    def get(self, domain_id: Union[int, str]) -> JSON:
        return self._r("GET", f"/domains/{_seg(domain_id)}")

    def delete(self, domain_id: Union[int, str]) -> JSON:
        return self._r("DELETE", f"/domains/{_seg(domain_id)}")

    def verify(self, domain_id: Union[int, str]) -> JSON:
        return self._r("POST", f"/domains/{_seg(domain_id)}/verify")

    def check_dns(self, domain_id: Union[int, str]) -> JSON:
        return self._r("POST", f"/domains/{_seg(domain_id)}/dns-check")

    def set_catch_all(self, domain_id: Union[int, str], mailbox_id: Optional[Union[int, str]]) -> JSON:
        """Catch-all on (a mailbox id) or off (None)."""
        return self._r("PUT", f"/domains/{_seg(domain_id)}/catch-all", {"mailbox_id": mailbox_id})


class Mailboxes(_Resource):
    def create(self, domain_id: Union[int, str], params: Mapping[str, Any]) -> JSON:
        return self._r("POST", f"/domains/{_seg(domain_id)}/mailboxes", params)

    def delete(self, mailbox_id: Union[int, str]) -> JSON:
        return self._r("DELETE", f"/mailboxes/{_seg(mailbox_id)}")

    def restore(self, mailbox_id: Union[int, str]) -> JSON:
        return self._r("POST", f"/mailboxes/{_seg(mailbox_id)}/restore")

    def reset_password(self, mailbox_id: Union[int, str]) -> JSON:
        return self._r("POST", f"/mailboxes/{_seg(mailbox_id)}/password")

    def set_forward(self, mailbox_id: Union[int, str], to: Optional[str]) -> JSON:
        """Forward copies to an address, or stop with None."""
        return self._r("PUT", f"/mailboxes/{_seg(mailbox_id)}/forward", {"to": to})

    def send_test_email(self, mailbox_id: Union[int, str]) -> JSON:
        return self._r("POST", f"/mailboxes/{_seg(mailbox_id)}/test-email")


class Aliases(_Resource):
    def create(self, domain_id: Union[int, str], params: Mapping[str, Any]) -> JSON:
        return self._r("POST", f"/domains/{_seg(domain_id)}/aliases", params)

    def delete(self, alias_id: Union[int, str]) -> JSON:
        return self._r("DELETE", f"/aliases/{_seg(alias_id)}")


class ApiKeys(_Resource):
    def list(self) -> JSON:
        return self._r("GET", "/api-keys")

    def create(self, params: Mapping[str, Any]) -> JSON:
        return self._r("POST", "/api-keys", params)

    def revoke(self, key_id: Union[int, str]) -> JSON:
        return self._r("DELETE", f"/api-keys/{_seg(key_id)}")


class SendingDomains(_Resource):
    def list(self) -> JSON:
        return self._r("GET", "/sending-domains")

    def create(self, params: Mapping[str, Any]) -> JSON:
        return self._r("POST", "/sending-domains", params)

    def get(self, sending_domain_id: Union[int, str]) -> JSON:
        return self._r("GET", f"/sending-domains/{_seg(sending_domain_id)}")

    def delete(self, sending_domain_id: Union[int, str]) -> JSON:
        return self._r("DELETE", f"/sending-domains/{_seg(sending_domain_id)}")

    def verify(self, sending_domain_id: Union[int, str]) -> JSON:
        return self._r("POST", f"/sending-domains/{_seg(sending_domain_id)}/verify")

    def check(self, sending_domain_id: Union[int, str]) -> JSON:
        return self._r("POST", f"/sending-domains/{_seg(sending_domain_id)}/check")

    def update(self, sending_domain_id: Union[int, str], params: Mapping[str, Any]) -> JSON:
        """Change settings, e.g. {"tls": "enforced"}."""
        return self._r("PATCH", f"/sending-domains/{_seg(sending_domain_id)}", params)

    def insights(self, sending_domain_id: Union[int, str]) -> JSON:
        """Deliverability insights: DNS, DMARC, alignment, 7-day bounce and complaint rates."""
        return self._r("GET", f"/sending-domains/{_seg(sending_domain_id)}/insights")


def _idem(key: Optional[str]) -> Optional[Dict[str, str]]:
    return {"Idempotency-Key": key} if key else None


class Emails(_Resource):
    def send(self, params: Mapping[str, Any], idempotency_key: Optional[str] = None) -> JSON:
        """Send one email. Returns {"id": ...}."""
        return self._r("POST", "/emails", params, headers=_idem(idempotency_key))

    def batch(self, emails: List[Mapping[str, Any]], idempotency_key: Optional[str] = None) -> JSON:
        """Send up to 100 emails. Returns {"data": [{"id": ...}, ...]}."""
        return self._r("POST", "/emails/batch", list(emails), headers=_idem(idempotency_key))

    def get(self, email_id: str) -> JSON:
        return self._r("GET", f"/emails/{_seg(email_id)}")

    def list(self, query: Params = None) -> JSON:
        """query: limit, before, status, source, key, domain, q, days."""
        return self._r("GET", "/emails", query=query)

    def export(self, query: Params = None) -> str:
        """CSV text of the filtered list (up to 10,000 rows)."""
        return self._r("GET", "/emails/export", query=query)

    def update(self, email_id: str, params: Mapping[str, Any]) -> JSON:
        """Reschedule: {"scheduled_at": ...}."""
        return self._r("PATCH", f"/emails/{_seg(email_id)}", params)

    def reschedule(self, email_id: str, scheduled_at: str) -> JSON:
        return self.update(email_id, {"scheduled_at": scheduled_at})

    def cancel(self, email_id: str) -> JSON:
        return self._r("POST", f"/emails/{_seg(email_id)}/cancel")


class Webhooks(_Resource):
    def list(self) -> JSON:
        return self._r("GET", "/webhooks")

    def create(self, params: Mapping[str, Any]) -> JSON:
        return self._r("POST", "/webhooks", params)

    def get(self, webhook_id: Union[int, str]) -> JSON:
        return self._r("GET", f"/webhooks/{_seg(webhook_id)}")

    def update(self, webhook_id: Union[int, str], params: Mapping[str, Any]) -> JSON:
        return self._r("PATCH", f"/webhooks/{_seg(webhook_id)}", params)

    def delete(self, webhook_id: Union[int, str]) -> JSON:
        return self._r("DELETE", f"/webhooks/{_seg(webhook_id)}")

    def rotate_secret(self, webhook_id: Union[int, str]) -> JSON:
        return self._r("POST", f"/webhooks/{_seg(webhook_id)}/rotate-secret")

    def test(self, webhook_id: Union[int, str]) -> JSON:
        return self._r("POST", f"/webhooks/{_seg(webhook_id)}/test")

    def deliveries(self, webhook_id: Union[int, str]) -> JSON:
        return self._r("GET", f"/webhooks/{_seg(webhook_id)}/deliveries")

    def replay_delivery(self, delivery_id: Union[int, str]) -> JSON:
        return self._r("POST", f"/webhook-deliveries/{_seg(delivery_id)}/replay")


class Suppressions(_Resource):
    def list(self, query: Params = None) -> JSON:
        """query: email."""
        return self._r("GET", "/suppressions", query=query)

    def create(self, params: Mapping[str, Any]) -> JSON:
        return self._r("POST", "/suppressions", params)

    def delete(self, email: str) -> JSON:
        return self._r("DELETE", f"/suppressions/{_seg(email)}")


class Metrics(_Resource):
    def get(self, query: Params = None) -> JSON:
        """query: days, domain."""
        return self._r("GET", "/metrics", query=query)


class Logs(_Resource):
    def list(self, query: Params = None) -> JSON:
        """query: source, status, key, limit."""
        return self._r("GET", "/logs", query=query)


class Inbox(_Resource):
    """Read and write mail in a mailbox (JMAP behind the scenes)."""

    def _m(self, mailbox_id: Union[int, str]) -> str:
        return f"/mailboxes/{_seg(mailbox_id)}"

    def list_folders(self, mailbox_id: Union[int, str]) -> JSON:
        return self._r("GET", self._m(mailbox_id) + "/folders")

    def create_folder(self, mailbox_id: Union[int, str], params: Mapping[str, Any]) -> JSON:
        return self._r("POST", self._m(mailbox_id) + "/folders", params)

    def delete_folder(self, mailbox_id: Union[int, str], folder_id: str) -> JSON:
        return self._r("DELETE", self._m(mailbox_id) + f"/folders/{_seg(folder_id)}")

    def list_messages(self, mailbox_id: Union[int, str], query: Params = None) -> JSON:
        """query: folder, q, from, unread, flagged, label, message_id, threads, limit, position."""
        return self._r("GET", self._m(mailbox_id) + "/messages", query=query)

    def create_message(self, mailbox_id: Union[int, str], params: Mapping[str, Any]) -> JSON:
        return self._r("POST", self._m(mailbox_id) + "/messages", params)

    def get_message(self, mailbox_id: Union[int, str], message_id: str) -> JSON:
        return self._r("GET", self._m(mailbox_id) + f"/messages/{_seg(message_id)}")

    def update_message(self, mailbox_id: Union[int, str], message_id: str, params: Mapping[str, Any]) -> JSON:
        return self._r("PATCH", self._m(mailbox_id) + f"/messages/{_seg(message_id)}", params)

    def trash_message(self, mailbox_id: Union[int, str], message_id: str) -> JSON:
        return self._r("DELETE", self._m(mailbox_id) + f"/messages/{_seg(message_id)}")

    def get_attachment(self, mailbox_id: Union[int, str], message_id: str, blob_id: str) -> bytes:
        return self._r("GET", self._m(mailbox_id) + f"/messages/{_seg(message_id)}/attachments/{_seg(blob_id)}")

    def reply(self, mailbox_id: Union[int, str], message_id: str, params: Mapping[str, Any]) -> JSON:
        return self._r("POST", self._m(mailbox_id) + f"/messages/{_seg(message_id)}/reply", params)

    def forward(self, mailbox_id: Union[int, str], message_id: str, params: Mapping[str, Any]) -> JSON:
        return self._r("POST", self._m(mailbox_id) + f"/messages/{_seg(message_id)}/forward", params)

    def send_draft(self, mailbox_id: Union[int, str], message_id: str) -> JSON:
        return self._r("POST", self._m(mailbox_id) + f"/messages/{_seg(message_id)}/send")

    def get_thread(self, mailbox_id: Union[int, str], thread_id: str) -> JSON:
        return self._r("GET", self._m(mailbox_id) + f"/threads/{_seg(thread_id)}")


class Templates(_Resource):
    """Templates by id or alias."""

    def list(self) -> JSON:
        return self._r("GET", "/templates")

    def create(self, params: Mapping[str, Any]) -> JSON:
        return self._r("POST", "/templates", params)

    def get(self, template: str) -> JSON:
        return self._r("GET", f"/templates/{_seg(template)}")

    def update(self, template: str, params: Mapping[str, Any]) -> JSON:
        return self._r("PATCH", f"/templates/{_seg(template)}", params)

    def delete(self, template: str) -> JSON:
        return self._r("DELETE", f"/templates/{_seg(template)}")

    def publish(self, template: str) -> JSON:
        return self._r("POST", f"/templates/{_seg(template)}/publish")

    def duplicate(self, template: str) -> JSON:
        return self._r("POST", f"/templates/{_seg(template)}/duplicate")

    def versions(self, template: str) -> JSON:
        return self._r("GET", f"/templates/{_seg(template)}/versions")

    def publish_version(self, template: str, version: Union[int, str]) -> JSON:
        return self._r("POST", f"/templates/{_seg(template)}/versions/{_seg(version)}/publish")

    def render(self, template: str, params: Params = None) -> JSON:
        """params: variables, version."""
        return self._r("POST", f"/templates/{_seg(template)}/render", dict(params or {}))

    def preview(self, template: str) -> str:
        """HTML of the draft with fallback values."""
        return self._r("GET", f"/templates/{_seg(template)}/preview.html")


class Contacts(_Resource):
    """Contacts by id or email."""

    def list(self, query: Params = None) -> JSON:
        """query: segment_id, status, q, limit, position."""
        return self._r("GET", "/contacts", query=query)

    def create(self, params: Mapping[str, Any]) -> JSON:
        """Add or update by email."""
        return self._r("POST", "/contacts", params)

    upsert = create

    def import_csv(self, params: Mapping[str, Any]) -> JSON:
        """params: csv (text), mode, from, consent_statement, segment_id."""
        return self._r("POST", "/contacts/import", params)

    def get(self, contact: str) -> JSON:
        return self._r("GET", f"/contacts/{_seg(contact)}")

    def update(self, contact: str, params: Mapping[str, Any]) -> JSON:
        return self._r("PATCH", f"/contacts/{_seg(contact)}", params)

    def delete(self, contact: str) -> JSON:
        return self._r("DELETE", f"/contacts/{_seg(contact)}")


class Segments(_Resource):
    def list(self) -> JSON:
        return self._r("GET", "/segments")

    def create(self, params: Mapping[str, Any]) -> JSON:
        return self._r("POST", "/segments", params)

    def get(self, segment_id: str) -> JSON:
        return self._r("GET", f"/segments/{_seg(segment_id)}")

    def update(self, segment_id: str, params: Mapping[str, Any]) -> JSON:
        return self._r("PATCH", f"/segments/{_seg(segment_id)}", params)

    def delete(self, segment_id: str) -> JSON:
        return self._r("DELETE", f"/segments/{_seg(segment_id)}")

    def add_contacts(self, segment_id: str, contacts: List[str]) -> JSON:
        """contacts: ids or emails."""
        return self._r("POST", f"/segments/{_seg(segment_id)}/contacts", {"contacts": list(contacts)})

    def remove_contact(self, segment_id: str, contact: str) -> JSON:
        return self._r("DELETE", f"/segments/{_seg(segment_id)}/contacts/{_seg(contact)}")


class Topics(_Resource):
    def list(self) -> JSON:
        return self._r("GET", "/topics")

    def create(self, params: Mapping[str, Any]) -> JSON:
        return self._r("POST", "/topics", params)

    def delete(self, topic_id: str) -> JSON:
        return self._r("DELETE", f"/topics/{_seg(topic_id)}")


class Broadcasts(_Resource):
    def list(self) -> JSON:
        return self._r("GET", "/broadcasts")

    def create(self, params: Mapping[str, Any]) -> JSON:
        return self._r("POST", "/broadcasts", params)

    def get(self, broadcast_id: str) -> JSON:
        return self._r("GET", f"/broadcasts/{_seg(broadcast_id)}")

    def update(self, broadcast_id: str, params: Mapping[str, Any]) -> JSON:
        return self._r("PATCH", f"/broadcasts/{_seg(broadcast_id)}", params)

    def delete(self, broadcast_id: str) -> JSON:
        return self._r("DELETE", f"/broadcasts/{_seg(broadcast_id)}")

    def send(self, broadcast_id: str, params: Params = None) -> JSON:
        """params: scheduled_at (optional)."""
        return self._r("POST", f"/broadcasts/{_seg(broadcast_id)}/send", dict(params or {}))

    def cancel(self, broadcast_id: str) -> JSON:
        return self._r("POST", f"/broadcasts/{_seg(broadcast_id)}/cancel")

    def test(self, broadcast_id: str, to: str) -> JSON:
        return self._r("POST", f"/broadcasts/{_seg(broadcast_id)}/test", {"to": to})

    def stats(self, broadcast_id: str) -> JSON:
        return self._r("GET", f"/broadcasts/{_seg(broadcast_id)}/stats")


class Events(_Resource):
    def send(self, params: Mapping[str, Any]) -> JSON:
        """params: event (required), email or contact_id, first_name, data."""
        return self._r("POST", "/events", params)

    def list(self, query: Params = None) -> JSON:
        """query: contact."""
        return self._r("GET", "/events", query=query)


class Automations(_Resource):
    def list(self) -> JSON:
        return self._r("GET", "/automations")

    def create(self, params: Mapping[str, Any]) -> JSON:
        return self._r("POST", "/automations", params)

    def get(self, automation_id: str) -> JSON:
        return self._r("GET", f"/automations/{_seg(automation_id)}")

    def update(self, automation_id: str, params: Mapping[str, Any]) -> JSON:
        return self._r("PATCH", f"/automations/{_seg(automation_id)}", params)

    def delete(self, automation_id: str) -> JSON:
        return self._r("DELETE", f"/automations/{_seg(automation_id)}")

    def start(self, automation_id: str) -> JSON:
        return self._r("POST", f"/automations/{_seg(automation_id)}/start")

    def stop(self, automation_id: str) -> JSON:
        return self._r("POST", f"/automations/{_seg(automation_id)}/stop")

    def duplicate(self, automation_id: str) -> JSON:
        return self._r("POST", f"/automations/{_seg(automation_id)}/duplicate")

    def runs(self, automation_id: str, query: Params = None) -> JSON:
        """query: status."""
        return self._r("GET", f"/automations/{_seg(automation_id)}/runs", query=query)

    def get_run(self, automation_id: str, run_id: str) -> JSON:
        return self._r("GET", f"/automations/{_seg(automation_id)}/runs/{_seg(run_id)}")
