'use strict';
// Loads the n8n node and runs execute() with a fake `this` against the shared local Bmailu API.
const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const { spawn } = require('node:child_process');
const pkg = require('../package.json');
const { Bmailu } = require('../dist/nodes/Bmailu/Bmailu.node.js');
const { BmailuApi } = require('../dist/credentials/BmailuApi.credentials.js');

const SDKS = path.join(__dirname, '..', '..');
const FROM = 'Shop <hello@shop.test>';
let srv, child;

test.before(() => new Promise((resolve, reject) => {
  child = spawn(process.execPath, [path.join(SDKS, 'test-server.js')], { cwd: SDKS, stdio: ['ignore', 'pipe', 'inherit'] });
  let buf = '';
  child.stdout.on('data', (c) => { buf += c; if (buf.includes('\n')) { srv = JSON.parse(buf.split('\n')[0]); resolve(); } });
  child.on('error', reject);
}));
test.after(() => child && child.kill());

// Resolves '=Bearer {{$credentials.apiKey}}' style expressions the way n8n does for these simple cases.
const resolveExpr = (v, creds) => (typeof v === 'string' && v.startsWith('=') ? v.slice(1).replace(/\{\{\s*\$credentials\.(\w+)\s*\}\}/g, (_, k) => creds[k]) : v);

// A minimal stand-in for n8n's httpRequestWithAuthentication: applies the credential's authenticate block.
async function httpWithCredential(creds, opts) {
  const cred = new BmailuApi();
  const headers = { ...(opts.headers || {}) };
  for (const [k, v] of Object.entries(cred.authenticate.properties.headers)) headers[k] = resolveExpr(v, creds);
  let url = (opts.baseURL ? resolveExpr(opts.baseURL, creds) : '') + opts.url;
  if (opts.qs) url += '?' + new URLSearchParams(opts.qs);
  const res = await fetch(url, { method: opts.method || 'GET', headers, body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined });
  const body = await res.json();
  if (!res.ok) throw Object.assign(new Error(`Request failed with status code ${res.status}`), { response: { status: res.status, body } });
  return body;
}

function fakeThis(params, items = [{ json: {} }], { key, continueOnFail = false } = {}) {
  const creds = { apiKey: key || srv.key, baseUrl: srv.api };
  const calls = [];
  const ctx = {
    getInputData: () => items,
    getCredentials: async (name) => { assert.equal(name, 'bmailuApi'); return creds; },
    getNodeParameter: (name, i, fallback) => {
      const p = typeof params === 'function' ? params(i) : params;
      return name in p ? p[name] : fallback;
    },
    continueOnFail: () => continueOnFail,
    getNode: () => ({ name: 'Bmailu' }),
    helpers: {
      async httpRequestWithAuthentication(credType, opts) {
        assert.equal(credType, 'bmailuApi');
        assert.equal(this, ctx);
        calls.push(opts);
        return httpWithCredential(creds, opts);
      },
    },
  };
  return { ctx, calls };
}

const run = (params, items, o) => { const f = fakeThis(params, items, o); return new Bmailu().execute.call(f.ctx).then((r) => ({ r, calls: f.calls })); };

test('package.json follows n8n community-node conventions', () => {
  assert.ok(pkg.keywords.includes('n8n-community-node-package'));
  assert.equal(pkg.n8n.n8nNodesApiVersion, 1);
  for (const f of [...pkg.n8n.credentials, ...pkg.n8n.nodes]) require(path.join(__dirname, '..', f));
  assert.deepEqual(Object.keys(pkg.dependencies || {}), []);
  const d = new Bmailu().description;
  assert.equal(d.name, 'bmailu');
  assert.equal(d.icon, 'file:bmailu.svg');
  assert.deepEqual(d.credentials, [{ name: 'bmailuApi', required: true }]);
  const ops = d.properties.filter((p) => p.name === 'operation').flatMap((p) => p.options.map((o) => `${p.displayOptions.show.resource[0]}.${o.value}`));
  assert.deepEqual(ops.sort(), ['broadcast.create', 'broadcast.send', 'contact.delete', 'contact.upsert', 'email.get', 'email.send', 'event.send']);
  require('node:fs').accessSync(path.join(__dirname, '..', 'dist/nodes/Bmailu/bmailu.svg'));
});

test('credential test request works against the API', async () => {
  const cred = new BmailuApi();
  assert.equal(cred.name, 'bmailuApi');
  assert.equal(cred.properties.find((p) => p.name === 'baseUrl').default, 'https://app.bmailu.com/api/v1');
  const r = await httpWithCredential({ apiKey: srv.key, baseUrl: srv.api }, cred.test.request);
  assert.equal(r.object, 'list');
  await assert.rejects(httpWithCredential({ apiKey: 'bmk_wrong', baseUrl: srv.api }, cred.test.request));
});

test('Email → Send, then Email → Get', async () => {
  const { r, calls } = await run({
    resource: 'email', operation: 'send', from: FROM, to: 'delivered@simulator.bmailu.com, bounced@simulator.bmailu.com', subject: 'From n8n',
    html: '<p>Hi from n8n</p>', text: '', additionalFields: { tags: 'source=n8n', idempotencyKey: 'n8n-test-1' },
  }, [{ json: {} }, { json: {} }]);
  assert.equal(r[0].length, 2);
  const id = r[0][0].json.id;
  assert.match(id, /^[0-9a-f-]{36}$/);
  assert.equal(r[0][1].json.id, id, 'same idempotency key → same email');
  assert.equal(calls[0].headers['Idempotency-Key'], 'n8n-test-1');
  assert.deepEqual(calls[0].body.to, ['delivered@simulator.bmailu.com', 'bounced@simulator.bmailu.com']);
  assert.deepEqual(r[0][0].pairedItem, { item: 0 });

  const got = await run({ resource: 'email', operation: 'get', emailId: id });
  assert.equal(got.r[0][0].json.subject, 'From n8n');
  assert.deepEqual(got.r[0][0].json.tags, [{ name: 'source', value: 'n8n' }]);
});

test('Event → Send', async () => {
  const { r, calls } = await run({ resource: 'event', operation: 'send', eventName: 'signed_up', eventEmail: 'n8n@example.com', eventData: '{"plan":"pro"}' });
  assert.equal(r[0][0].json.object, 'event');
  assert.equal(r[0][0].json.event, 'signed_up');
  assert.deepEqual(calls[0].body, { event: 'signed_up', email: 'n8n@example.com', data: { plan: 'pro' } });
});

test('Contact → Create/Update and Delete', async () => {
  const c = await run({ resource: 'contact', operation: 'upsert', email: 'n8n-c@example.com', contactFields: { first_name: 'Nate', properties: '{"plan":"pro"}' } });
  assert.equal(c.r[0][0].json.email, 'n8n-c@example.com');
  assert.equal(c.r[0][0].json.first_name, 'Nate');
  const d = await run({ resource: 'contact', operation: 'delete', contact: 'n8n-c@example.com' });
  assert.ok(d.r[0][0].json);
});

test('Broadcast → Create and Send', async () => {
  const b = await run({ resource: 'broadcast', operation: 'create', broadcastName: 'n8n news', broadcastFrom: FROM, broadcastSubject: 'News', broadcastHtml: '<p>News {{{UNSUBSCRIBE_URL}}}</p>', broadcastFields: { track_opens: false } });
  const bc = b.r[0][0].json;
  assert.equal(bc.object, 'broadcast');
  assert.equal(bc.track_opens, false);
  const s = await run({ resource: 'broadcast', operation: 'send', broadcastId: bc.id, broadcastScheduledAt: '' });
  assert.equal(s.r[0][0].json.id, bc.id);
  assert.notEqual(s.r[0][0].json.status, 'draft');
});

test('API errors carry the Bmailu code; continueOnFail keeps going', async () => {
  const bad = { resource: 'email', operation: 'send', from: 'x@not-mine.test', to: 'ann@example.com', subject: 's', text: 't' };
  await assert.rejects(run(bad), (e) => e.code === 'domain_not_verified' && /domain_not_verified/.test(e.message));
  const { r } = await run(bad, [{ json: {} }], { continueOnFail: true });
  assert.equal(r[0][0].json.code, 'domain_not_verified');
  await assert.rejects(run({ resource: 'event', operation: 'send', eventName: 'x', eventEmail: 'a@example.com', eventData: '{bad' }), /not valid JSON/);
});
