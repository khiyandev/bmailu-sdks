"""End-to-end tests against sdks/test-server.js (started by test.sh, config in BMAILU_TEST_JSON)."""
import base64
import json
import os
import subprocess
import sys
import time
import unittest

sys.path.insert(0, os.path.join(os.path.dirname(__file__), "..", "src"))

from bmailu import Bmailu, BmailuError, WebhookVerificationError, verify_webhook  # noqa: E402

CFG = json.loads(os.environ["BMAILU_TEST_JSON"])
SDKS = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
FROM = "Shop <hello@shop.test>"
TO = "delivered@simulator.bmailu.com"


def panel_sign(secret, msg_id, ts, body):
    js = "process.stdout.write(require(process.argv[1]).sign(process.argv[2], process.argv[3], process.argv[4], process.argv[5]))"
    path = os.path.join(SDKS, "..", "panel", "src", "webhook-routes.js")
    return subprocess.check_output(["node", "-e", js, path, secret, msg_id, str(ts), body], text=True)


class BmailuTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.b = Bmailu(CFG["key"], base_url=CFG["api"])

    def test_send_idempotent_get(self):
        msg = {"from": FROM, "to": TO, "subject": "Hello", "html": "<p>Hello</p>"}
        a = self.b.emails.send(msg, idempotency_key="py-idem-1")
        b = self.b.emails.send(msg, idempotency_key="py-idem-1")
        self.assertEqual(a["id"], b["id"])
        got = self.b.emails.get(a["id"])
        self.assertEqual(got["object"], "email")
        self.assertEqual(got["subject"], "Hello")
        self.assertIn("data", self.b.emails.list({"limit": 5}))
        self.assertTrue(self.b.emails.export().startswith("id,"))

    def test_batch(self):
        msg = {"from": FROM, "to": TO, "subject": "Batch", "text": "Hi"}
        r = self.b.emails.batch([msg, msg])
        self.assertEqual(len(r["data"]), 2)

    def test_contact_and_event(self):
        c = self.b.contacts.create({"email": "py-ann@example.com", "first_name": "Ann"})
        self.assertEqual(c["email"], "py-ann@example.com")
        e = self.b.events.send({"event": "signed_up", "email": "py-ann@example.com"})
        self.assertEqual(e["object"], "event")
        self.assertEqual(e["contact_id"], c["id"])

    def test_template_publish_send(self):
        t = self.b.templates.create({
            "name": "Welcome py", "alias": "welcome-py", "subject": "Hi {{name}}",
            "html": "<p>Hi {{name}}</p>", "variables": [{"key": "name", "fallback_value": "there"}],
        })
        p = self.b.templates.publish(t["id"])
        self.assertEqual(p["published_version"], 1)
        self.assertIn("Hi there", self.b.templates.preview("welcome-py"))
        r = self.b.emails.send({"from": FROM, "to": TO, "template": {"id": "welcome-py", "variables": {"name": "Ann"}}})
        self.assertEqual(self.b.emails.get(r["id"])["subject"], "Hi Ann")

    def test_webhooks_list(self):
        r = self.b.webhooks.list()
        self.assertEqual(r["object"], "list")
        self.assertIsInstance(r["data"], list)

    def test_error_domain_not_verified(self):
        with self.assertRaises(BmailuError) as ctx:
            self.b.emails.send({"from": "x@nope.test", "to": TO, "subject": "x", "text": "x"})
        self.assertEqual(ctx.exception.code, "domain_not_verified")
        self.assertEqual(ctx.exception.status, 403)

    def test_sending_key_restricted(self):
        s = Bmailu(CFG["sending_key"], base_url=CFG["api"])
        with self.assertRaises(BmailuError) as ctx:
            s.domains.list()
        self.assertEqual(ctx.exception.code, "restricted_api_key")

    def test_generic_request(self):
        me = self.b.request("GET", "/me")
        self.assertIsInstance(me, dict)
        with self.assertRaises(BmailuError) as ctx:
            self.b.suppressions.delete("nobody@example.com")
        self.assertEqual(ctx.exception.code, "not_found")

    def test_webhook_verify(self):
        secret = "whsec_" + base64.b64encode(os.urandom(24)).decode()
        body = json.dumps({"type": "email.delivered", "data": {"email_id": "e1"}})
        ts = int(time.time())
        sig = panel_sign(secret, "msg_1", ts, body)
        headers = {"Webhook-Id": "msg_1", "webhook-timestamp": str(ts), "webhook-signature": "v1,bogus " + sig}
        self.assertEqual(verify_webhook(body, headers, secret)["type"], "email.delivered")
        with self.assertRaises(WebhookVerificationError):
            verify_webhook(body.replace("e1", "e2"), headers, secret)
        with self.assertRaises(WebhookVerificationError):
            verify_webhook(body, headers, secret, now=ts + 301)


if __name__ == "__main__":
    unittest.main()


class CoverageTest(unittest.TestCase):
    """Every operation in the served OpenAPI spec has an SDK method."""

    def test_every_operation(self):
        import urllib.request
        import re
        spec = json.load(urllib.request.urlopen(CFG["api"] + "/openapi.json"))
        want = {(m.upper(), re.sub(r"\{[^}]+\}", "X", p)) for p, ops in spec["paths"].items() for m in ops
                if m in ("get", "post", "put", "patch", "delete")}
        seen = set()
        b = Bmailu("bmk_test", base_url="http://localhost:1")
        b.request = lambda method, path, body=None, query=None, headers=None: seen.add((method, path))
        X, P = "X", {}
        calls = [
            lambda: b.me.get(), lambda: b.me.update(P),
            lambda: b.domains.list(), lambda: b.domains.create(P), lambda: b.domains.get(X), lambda: b.domains.delete(X),
            lambda: b.domains.verify(X), lambda: b.domains.check_dns(X), lambda: b.domains.set_catch_all(X, None),
            lambda: b.mailboxes.create(X, P), lambda: b.mailboxes.delete(X), lambda: b.mailboxes.restore(X),
            lambda: b.mailboxes.reset_password(X), lambda: b.mailboxes.set_forward(X, None), lambda: b.mailboxes.send_test_email(X),
            lambda: b.aliases.create(X, P), lambda: b.aliases.delete(X),
            lambda: b.api_keys.list(), lambda: b.api_keys.create(P), lambda: b.api_keys.revoke(X),
            lambda: b.sending_domains.list(), lambda: b.sending_domains.create(P), lambda: b.sending_domains.get(X),
            lambda: b.sending_domains.delete(X), lambda: b.sending_domains.verify(X), lambda: b.sending_domains.check(X),
            lambda: b.emails.send(P), lambda: b.emails.list(), lambda: b.emails.batch([]), lambda: b.emails.export(),
            lambda: b.emails.get(X), lambda: b.emails.update(X, P), lambda: b.emails.cancel(X),
            lambda: b.webhooks.list(), lambda: b.webhooks.create(P), lambda: b.webhooks.get(X), lambda: b.webhooks.update(X, P),
            lambda: b.webhooks.delete(X), lambda: b.webhooks.rotate_secret(X), lambda: b.webhooks.test(X),
            lambda: b.webhooks.deliveries(X), lambda: b.webhooks.replay_delivery(X),
            lambda: b.suppressions.list(), lambda: b.suppressions.create(P), lambda: b.suppressions.delete(X),
            lambda: b.metrics.get(), lambda: b.logs.list(),
            lambda: b.inbox.list_folders(X), lambda: b.inbox.create_folder(X, P), lambda: b.inbox.delete_folder(X, X),
            lambda: b.inbox.list_messages(X), lambda: b.inbox.create_message(X, P), lambda: b.inbox.get_message(X, X),
            lambda: b.inbox.update_message(X, X, P), lambda: b.inbox.trash_message(X, X), lambda: b.inbox.get_attachment(X, X, X),
            lambda: b.inbox.reply(X, X, P), lambda: b.inbox.forward(X, X, P), lambda: b.inbox.send_draft(X, X),
            lambda: b.inbox.get_thread(X, X),
            lambda: b.templates.list(), lambda: b.templates.create(P), lambda: b.templates.get(X), lambda: b.templates.update(X, P),
            lambda: b.templates.delete(X), lambda: b.templates.publish(X), lambda: b.templates.duplicate(X),
            lambda: b.templates.versions(X), lambda: b.templates.publish_version(X, X), lambda: b.templates.render(X),
            lambda: b.templates.preview(X),
            lambda: b.contacts.list(), lambda: b.contacts.create(P), lambda: b.contacts.import_csv(P), lambda: b.contacts.get(X),
            lambda: b.contacts.update(X, P), lambda: b.contacts.delete(X),
            lambda: b.segments.list(), lambda: b.segments.create(P), lambda: b.segments.get(X), lambda: b.segments.update(X, P),
            lambda: b.segments.delete(X), lambda: b.segments.add_contacts(X, []), lambda: b.segments.remove_contact(X, X),
            lambda: b.topics.list(), lambda: b.topics.create(P), lambda: b.topics.delete(X),
            lambda: b.broadcasts.list(), lambda: b.broadcasts.create(P), lambda: b.broadcasts.get(X),
            lambda: b.broadcasts.update(X, P), lambda: b.broadcasts.delete(X), lambda: b.broadcasts.send(X),
            lambda: b.broadcasts.cancel(X), lambda: b.broadcasts.test(X, "a@b.c"), lambda: b.broadcasts.stats(X),
            lambda: b.events.send(P), lambda: b.events.list(),
            lambda: b.automations.list(), lambda: b.automations.create(P), lambda: b.automations.get(X),
            lambda: b.automations.update(X, P), lambda: b.automations.delete(X), lambda: b.automations.start(X),
            lambda: b.automations.stop(X), lambda: b.automations.duplicate(X), lambda: b.automations.runs(X),
            lambda: b.automations.get_run(X, X),
        ]
        for c in calls:
            c()
        self.assertEqual(sorted(want - seen), [], "spec operations without an SDK method")
        self.assertEqual(sorted(seen - want), [], "SDK methods not in the spec")
