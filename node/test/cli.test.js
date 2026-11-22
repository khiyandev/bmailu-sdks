'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { startServer } = require('./helper');

const BIN = path.join(__dirname, '..', 'bin', 'bmailu.js');
const FROM = 'Shop <hello@shop.test>';
let srv, home;

test.before(async () => {
  srv = await startServer();
  home = fs.mkdtempSync(path.join(os.tmpdir(), 'bmailu-cli-'));
});
test.after(() => { if (srv) srv.stop(); if (home) fs.rmSync(home, { recursive: true, force: true }); });

// Runs the CLI with a clean environment: its own HOME, no saved key unless login put one there.
function cli(args, { env = {}, input } = {}) {
  const base = { PATH: process.env.PATH, HOME: home, BMAILU_BASE_URL: srv.api };
  const r = spawnSync(process.execPath, [BIN, ...args], { env: { ...base, ...env }, input, encoding: 'utf8' });
  return { code: r.status, out: r.stdout, err: r.stderr };
}
const withKey = (key) => ({ env: { BMAILU_API_KEY: key || srv.key } });
const cfgFile = () => path.join(home, '.config', 'bmailu', 'config.json');

test('--help everywhere and usage errors exit 2', () => {
  for (const args of [['--help'], ['send', '--help'], ['emails', '--help'], ['api', '--help'], ['login', '--help'], ['whoami', '--help'], ['help', 'events']]) {
    const r = cli(args);
    assert.equal(r.code, 0, args.join(' '));
    assert.match(r.out, /Usage|Commands/);
  }
  assert.equal(cli([]).code, 2);
  assert.equal(cli(['frobnicate']).code, 2);
  assert.equal(cli(['send', '--from', FROM], withKey()).code, 2);
  assert.equal(cli(['send', '--from', FROM, '--to', 'a@example.com', '--subject', 's'], withKey()).code, 2);
  assert.equal(cli(['send', '--to'], withKey()).code, 2);
  assert.equal(cli(['emails', 'get'], withKey()).code, 2);
  assert.equal(cli(['events', 'send', 'x', '--email', 'a@example.com', '--data', '{bad'], withKey()).code, 2);
  const nokey = cli(['whoami']);
  assert.equal(nokey.code, 2);
  assert.match(nokey.err, /bmailu login/);
});

test('login reads the key from stdin, stores it 0600, never prints it; whoami; logout', () => {
  const r = cli(['login'], { input: srv.key + '\n' });
  assert.equal(r.code, 0, r.err);
  assert.ok(!r.out.includes(srv.key) && !r.err.includes(srv.key));
  assert.match(r.out, /sdk@example\.com/);
  const st = fs.statSync(cfgFile());
  assert.equal(st.mode & 0o777, 0o600);
  const cfg = JSON.parse(fs.readFileSync(cfgFile(), 'utf8'));
  assert.equal(cfg.api_key, srv.key);
  assert.equal(cfg.base_url, srv.api);

  // Saved key + saved base URL are used without any env.
  const who = spawnSync(process.execPath, [BIN, 'whoami'], { env: { PATH: process.env.PATH, HOME: home }, encoding: 'utf8' });
  assert.equal(who.status, 0, who.stderr);
  assert.match(who.stdout, /sdk@example\.com/);
  const whoJson = cli(['whoami', '--json']);
  assert.equal(JSON.parse(whoJson.out).email, 'sdk@example.com');

  // A wrong key is refused and not saved.
  const bad = cli(['login'], { input: 'bmk_wrong\n' });
  assert.equal(bad.code, 1);
  assert.equal(JSON.parse(fs.readFileSync(cfgFile(), 'utf8')).api_key, srv.key);

  assert.equal(cli(['logout']).code, 0);
  assert.ok(!fs.existsSync(cfgFile()));
  assert.equal(cli(['whoami']).code, 2);
});

test('send, emails list/get/cancel', () => {
  const sent = cli(['send', '--from', FROM, '--to', 'delivered@simulator.bmailu.com', '--subject', 'CLI hi', '--text', 'Hello',
    '--cc', 'bounced@simulator.bmailu.com', '--reply-to', 'help@shop.test', '--tag', 'kind=welcome', '--json'], withKey());
  assert.equal(sent.code, 0, sent.err);
  const id = JSON.parse(sent.out).id;
  assert.match(id, /^[0-9a-f-]{36}$/);

  const htmlFile = path.join(home, 'mail.html');
  fs.writeFileSync(htmlFile, '<p>From a file</p>');
  const plain = cli(['send', '--from', FROM, '--to', 'bob@example.com', '--subject', 'File', '--html-file', htmlFile], withKey());
  assert.equal(plain.code, 0, plain.err);
  assert.match(plain.out, /^Sent: [0-9a-f-]{36}/);

  const list = cli(['emails', 'list', '--limit', '5'], withKey());
  assert.equal(list.code, 0, list.err);
  assert.ok(list.out.includes(id));

  const got = cli(['emails', 'get', id, '--json'], withKey());
  assert.equal(got.code, 0, got.err);
  const email = JSON.parse(got.out);
  assert.equal(email.subject, 'CLI hi');
  assert.deepEqual(email.tags, [{ name: 'kind', value: 'welcome' }]);

  const later = cli(['send', '--from', FROM, '--to', 'bob@example.com', '--subject', 'Later', '--html', '<p>x</p>', '--scheduled-at', 'in 1 day'], withKey());
  assert.equal(later.code, 0, later.err);
  assert.match(later.out, /^Scheduled: /);
  const laterId = later.out.trim().split(' ')[1];
  const cancel = cli(['emails', 'cancel', laterId], withKey());
  assert.equal(cancel.code, 0, cancel.err);
  assert.match(cancel.out, /Cancelled/);
});

test('API errors exit 1 with code and message on stderr', () => {
  const r = cli(['send', '--from', 'x@not-mine.test', '--to', 'a@example.com', '--subject', 's', '--text', 't'], withKey());
  assert.equal(r.code, 1);
  assert.match(r.err, /domain_not_verified/);
  const d = cli(['domains', 'list'], withKey(srv.sending_key));
  assert.equal(d.code, 1);
  assert.match(d.err, /restricted_api_key/);
  assert.ok(!d.err.includes(srv.sending_key));
});

test('domains list, events send, contacts add, api', () => {
  const d = cli(['domains', 'list'], withKey());
  assert.equal(d.code, 0, d.err);
  assert.match(d.out, /No domains/);
  assert.deepEqual(JSON.parse(cli(['domains', 'list', '--json'], withKey()).out), { domains: [] });

  const c = cli(['contacts', 'add', 'cli@example.com', '--first-name', 'Cli'], withKey());
  assert.equal(c.code, 0, c.err);
  assert.match(c.out, /cli@example\.com/);

  const e = cli(['events', 'send', 'signed_up', '--email', 'cli@example.com', '--data', '{"plan":"pro"}', '--json'], withKey());
  assert.equal(e.code, 0, e.err);
  assert.equal(JSON.parse(e.out).event, 'signed_up');

  const g = cli(['api', 'GET', '/contacts/cli@example.com'], withKey());
  assert.equal(g.code, 0, g.err);
  assert.equal(JSON.parse(g.out).first_name, 'Cli');
  const t = cli(['api', 'post', '/topics', '--data', '{"name":"CLI news"}'], withKey());
  assert.equal(t.code, 0, t.err);
  assert.equal(JSON.parse(t.out).name, 'CLI news');
  const q = cli(['api', 'GET', '/emails?limit=1'], withKey());
  assert.equal(JSON.parse(q.out).object, 'list');
  const nf = cli(['api', 'GET', '/nope'], withKey());
  assert.equal(nf.code, 1);
  assert.match(nf.err, /not_found/);

  // --api-key and --base-url flags win over env
  const f = cli(['whoami', '--api-key', srv.key, '--base-url', srv.api], { env: { BMAILU_API_KEY: 'bmk_wrong', BMAILU_BASE_URL: 'http://127.0.0.1:9/api/v1' } });
  assert.equal(f.code, 0, f.err);
});
