'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const { Bmailu, BmailuError, verifyWebhook } = require('..');
const { startServer, panelSign } = require('./helper');

const FROM = 'Shop <hello@shop.test>';
let srv, bmailu;

test.before(async () => {
  srv = await startServer();
  bmailu = new Bmailu(srv.key, { baseUrl: srv.api });
});
test.after(() => srv && srv.stop());

test('send an email, idempotency key twice gives the same id, then get it', async () => {
  const r = await bmailu.emails.send({ from: FROM, to: 'delivered@simulator.bmailu.com', subject: 'Hi', html: '<p>Hi</p>' });
  assert.match(r.id, /^[0-9a-f-]{36}$/);
  const key = 'idem-' + crypto.randomUUID();
  const p = { from: FROM, to: 'ann@example.com', subject: 'Once', text: 'Only once' };
  const a = await bmailu.emails.send(p, { idempotencyKey: key });
  const b = await bmailu.emails.send(p, { idempotencyKey: key });
  assert.equal(a.id, b.id);
  const got = await bmailu.emails.get(r.id);
  assert.equal(got.id, r.id);
  assert.equal(got.subject, 'Hi');
  const list = await bmailu.emails.list({ limit: 10 });
  assert.equal(list.object, 'list');
  assert.ok(list.data.some((e) => e.id === r.id));
});

test('batch of 2 (both spellings)', async () => {
  const emails = [
    { from: FROM, to: 'delivered@simulator.bmailu.com', subject: 'One', text: '1' },
    { from: FROM, to: 'bob@example.com', subject: 'Two', text: '2' },
  ];
  const r = await bmailu.batch.send(emails);
  assert.equal(r.data.length, 2);
  const r2 = await bmailu.emails.batch(emails);
  assert.equal(r2.data.length, 2);
});

test('schedule, reschedule and cancel', async () => {
  const r = await bmailu.emails.send({ from: FROM, to: 'ann@example.com', subject: 'Later', text: 'x', scheduled_at: 'in 1 day' });
  const u = await bmailu.emails.reschedule(r.id, 'in 2 days');
  assert.equal(u.id, r.id);
  const c = await bmailu.emails.cancel(r.id);
  assert.ok(c);
  const csv = await bmailu.emails.export();
  assert.equal(typeof csv, 'string');
});

test('contacts, events, segments, topics', async () => {
  const c = await bmailu.contacts.create({ email: 'ann@example.com', first_name: 'Ann' });
  assert.equal(c.object, 'contact');
  assert.equal(c.email, 'ann@example.com');
  const got = await bmailu.contacts.get('ann@example.com');
  assert.equal(got.id, c.id);
  const e = await bmailu.events.send({ event: 'signed_up', email: 'ann@example.com', data: { plan: 'pro' } });
  assert.equal(e.object, 'event');
  assert.equal(e.event, 'signed_up');
  const events = await bmailu.events.list({ contact: 'ann@example.com' });
  assert.ok(events);
  const seg = await bmailu.segments.create({ name: 'VIP' });
  await bmailu.segments.addContacts(seg.id, ['ann@example.com']);
  await bmailu.segments.removeContact(seg.id, 'ann@example.com');
  await bmailu.segments.delete(seg.id);
  const topic = await bmailu.topics.create({ name: 'News' });
  assert.ok((await bmailu.topics.list()).data.some((t) => t.id === topic.id));
  await bmailu.topics.delete(topic.id);
  const list = await bmailu.contacts.list({ limit: 5 });
  assert.equal(list.object, 'list');
});

test('create + publish a template and send with it', async () => {
  const t = await bmailu.templates.create({
    name: 'Welcome', alias: 'welcome-sdk', subject: 'Hi {{name}}', html: '<p>Hi {{name}}</p>', from: FROM,
    variables: [{ key: 'name', fallback_value: 'friend' }],
  });
  assert.ok(t.id);
  await bmailu.templates.publish(t.id);
  const r = await bmailu.emails.send({ to: 'delivered@simulator.bmailu.com', template: { id: 'welcome-sdk', variables: { name: 'Ann' } } });
  assert.ok(r.id);
  const got = await bmailu.emails.get(r.id);
  assert.equal(got.subject, 'Hi Ann');
  const rendered = await bmailu.templates.render(t.id, { variables: { name: 'Bo' } });
  assert.match(JSON.stringify(rendered), /Bo/);
  const html = await bmailu.templates.preview(t.id);
  assert.equal(typeof html, 'string');
  assert.match(html, /friend/);
  assert.ok((await bmailu.templates.versions(t.id)));
});

test('webhooks list', async () => {
  const w = await bmailu.webhooks.list();
  assert.equal(w.object, 'list');
  assert.ok(Array.isArray(w.data));
});

test('broadcasts: create, get, stats, delete', async () => {
  const b = await bmailu.broadcasts.create({ name: 'Nov news', from: FROM, subject: 'News', html: '<p>News {{{UNSUBSCRIBE_URL}}}</p>' });
  assert.equal(b.object, 'broadcast');
  assert.equal((await bmailu.broadcasts.get(b.id)).id, b.id);
  assert.ok(await bmailu.broadcasts.stats(b.id));
  await bmailu.broadcasts.delete(b.id);
});

test('misc reads: me, domains, suppressions, metrics, logs, automations', async () => {
  assert.equal((await bmailu.me.get()).email, 'sdk@example.com');
  assert.ok(Array.isArray((await bmailu.domains.list()).domains));
  await bmailu.suppressions.create('gone@example.com');
  assert.ok(JSON.stringify(await bmailu.suppressions.list()).includes('gone@example.com'));
  await bmailu.suppressions.delete('gone@example.com');
  assert.ok(await bmailu.metrics.get({ days: 7 }));
  assert.ok(await bmailu.logs.list({ limit: 5 }));
  assert.ok(await bmailu.automations.list());
});

test('error: bad from domain -> domain_not_verified', async () => {
  await assert.rejects(
    bmailu.emails.send({ from: 'x@not-mine.test', to: 'ann@example.com', subject: 'x', text: 'x' }),
    (e) => e instanceof BmailuError && e.code === 'domain_not_verified' && e.status >= 400 && typeof e.message === 'string',
  );
});

test('sending-only key is refused on GET /domains', async () => {
  const sending = new Bmailu(srv.sending_key, { baseUrl: srv.api });
  await assert.rejects(sending.domains.list(), (e) => e instanceof BmailuError && e.code === 'restricted_api_key' && e.status === 403);
  // ...but may send
  assert.ok((await sending.emails.send({ from: FROM, to: 'delivered@simulator.bmailu.com', subject: 'k', text: 'k' })).id);
});

test('request() sends the right headers; custom fetch and timeout', async () => {
  let seen;
  const fake = async (url, init) => { seen = { url, init }; return new Response('{"ok":true}', { status: 200, headers: { 'content-type': 'application/json' } }); };
  const c = new Bmailu('bmk_test', { baseUrl: 'https://example.test/api/v1/', fetch: fake });
  assert.deepEqual(await c.request('DELETE', '/topics/a b', undefined, { x: 1, y: undefined }), { ok: true });
  assert.equal(seen.url, 'https://example.test/api/v1/topics/a b?x=1');
  assert.equal(seen.init.headers.Authorization, 'Bearer bmk_test');
  assert.equal(seen.init.headers['Content-Type'], 'application/json');
  assert.equal(seen.init.headers['User-Agent'], 'bmailu-node/0.1.0');
  await c.topics.delete('a b');
  assert.equal(seen.url, 'https://example.test/api/v1/topics/a%20b');
  await c.inbox(7).messages.update('m1', { read: true });
  assert.equal(seen.url, 'https://example.test/api/v1/mailboxes/7/messages/m1');
  assert.equal(seen.init.method, 'PATCH');
  const slow = (url, init) => new Promise((_, rej) => init.signal.addEventListener('abort', () => rej(Object.assign(new Error('aborted'), { name: 'AbortError' }))));
  await assert.rejects(new Bmailu('k', { fetch: slow, timeoutMs: 50 }).me.get(), (e) => e.code === 'timeout' && e.status === 0);
  assert.throws(() => { const saved = process.env.BMAILU_API_KEY; delete process.env.BMAILU_API_KEY; try { new Bmailu(); } finally { if (saved) process.env.BMAILU_API_KEY = saved; } }, (e) => e.code === 'missing_api_key');
});

test('webhook verify against the panel sign(), tampered body, old timestamp', () => {
  const secret = 'whsec_' + crypto.randomBytes(24).toString('base64');
  const id = 'evt_' + crypto.randomUUID();
  const ts = Math.floor(Date.now() / 1000);
  const body = JSON.stringify({ type: 'email.delivered', data: { email_id: 'x' } });
  const sig = panelSign(secret, id, ts, body);
  assert.match(sig, /^v1,/);
  const headers = { 'webhook-id': id, 'webhook-timestamp': String(ts), 'webhook-signature': `v1,AAAA ${sig}` };
  assert.equal(Bmailu.webhooks.verify(body, headers, secret), true);
  assert.equal(verifyWebhook(Buffer.from(body), new Headers(headers), secret), true);
  assert.equal(bmailu.webhooks.verify(body, { 'Webhook-Id': id, 'Webhook-Timestamp': String(ts), 'Webhook-Signature': sig }, secret), true);
  assert.throws(() => Bmailu.webhooks.verify(body.replace('delivered', 'bounced'), headers, secret), (e) => e.code === 'invalid_signature');
  assert.throws(() => Bmailu.webhooks.verify(body, headers, 'whsec_' + crypto.randomBytes(24).toString('base64')), (e) => e.code === 'invalid_signature');
  const oldTs = ts - 600;
  const oldHeaders = { 'webhook-id': id, 'webhook-timestamp': String(oldTs), 'webhook-signature': panelSign(secret, id, oldTs, body) };
  assert.throws(() => Bmailu.webhooks.verify(body, oldHeaders, secret), (e) => e.code === 'invalid_signature');
  assert.throws(() => Bmailu.webhooks.verify(body, {}, secret), (e) => e.code === 'invalid_signature');
});

test('ESM import works', async () => {
  const m = await import('bmailu');
  assert.equal(typeof m.Bmailu, 'function');
  assert.equal(typeof m.BmailuError, 'function');
});
