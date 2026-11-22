#!/usr/bin/env node
'use strict';
// bmailu command-line tool. Zero dependencies.
// Exit codes: 0 ok, 1 API error (code + message on stderr), 2 usage error.
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { Bmailu, BmailuError, VERSION } = require('../lib/index.js');

class UsageError extends Error {}

const BOOLEAN_FLAGS = new Set(['json', 'help', 'version']);
const REPEATABLE = new Set(['tag', 'to', 'cc', 'bcc', 'reply-to']);

function parseArgs(argv) {
  const args = [];
  const flags = {};
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--') { args.push(...argv.slice(i + 1)); break; }
    if (a === '-h') { flags.help = true; continue; }
    if (!a.startsWith('--') || a === '--') { args.push(a); continue; }
    let name = a.slice(2);
    let value;
    const eq = name.indexOf('=');
    if (eq >= 0) { value = name.slice(eq + 1); name = name.slice(0, eq); }
    if (BOOLEAN_FLAGS.has(name)) { flags[name] = value === undefined ? true : value !== 'false'; continue; }
    if (value === undefined) {
      if (i + 1 >= argv.length) throw new UsageError(`--${name} needs a value`);
      value = argv[++i];
    }
    if (REPEATABLE.has(name)) (flags[name] = flags[name] || []).push(value);
    else flags[name] = value;
  }
  return { args, flags };
}

const HELP = {
  main: `bmailu ${VERSION} - the Bmailu command line

Usage: bmailu <command> [options]

Commands:
  login                      Save an API key (read from stdin) in ~/.config/bmailu/config.json
  logout                     Remove the saved API key
  whoami                     Show the account the key belongs to
  send                       Send an email
  emails list|get|cancel     List, read or cancel emails
  domains list               List mailbox domains
  events send <name>         Send a custom event for a contact
  contacts add <email>       Add or update a contact
  api <METHOD> <path>        Call any API endpoint

Global options:
  --api-key <key>            API key (else env BMAILU_API_KEY, else the saved key)
  --base-url <url>           API base URL (else env BMAILU_BASE_URL, default https://app.bmailu.com/api/v1)
  --json                     Print the raw JSON answer
  --help                     Help for a command

Docs: https://bmailu.com/docs/`,
  login: `Usage: bmailu login [--base-url <url>]

Reads an API key from stdin (typed or piped), checks it with GET /me
and saves it in ~/.config/bmailu/config.json (mode 0600). The key is never printed.`,
  logout: `Usage: bmailu logout

Removes the saved API key from ~/.config/bmailu/config.json.`,
  whoami: `Usage: bmailu whoami [--json]

Shows the account the API key belongs to (GET /me).`,
  send: `Usage: bmailu send --from <addr> --to <addr> --subject <text> (--text <text> | --html <html> | --html-file <file>)
                   [--cc <addr>] [--bcc <addr>] [--reply-to <addr>] [--tag name=value] [--scheduled-at <when>]
                   [--idempotency-key <key>] [--json]

--to, --cc, --bcc, --reply-to and --tag can be given more than once (or as a comma-separated list).
--scheduled-at takes ISO 8601 with a zone or "in 10 minutes".`,
  emails: `Usage: bmailu emails list [--limit <n>] [--status <s>] [--json]
       bmailu emails get <id> [--json]
       bmailu emails cancel <id> [--json]`,
  domains: `Usage: bmailu domains list [--json]`,
  events: `Usage: bmailu events send <name> --email <addr> [--data <json>] [--json]`,
  contacts: `Usage: bmailu contacts add <email> [--first-name <name>] [--last-name <name>] [--json]`,
  api: `Usage: bmailu api <METHOD> <path> [--data <json>]

Examples:
  bmailu api GET /emails?limit=5
  bmailu api POST /topics --data '{"name":"News"}'

Always prints the JSON answer.`,
};

function configPath() {
  const base = process.env.XDG_CONFIG_HOME || path.join(os.homedir(), '.config');
  return path.join(base, 'bmailu', 'config.json');
}

function readConfig() {
  try { return JSON.parse(fs.readFileSync(configPath(), 'utf8')); } catch { return {}; }
}

function writeConfig(cfg) {
  const file = configPath();
  fs.mkdirSync(path.dirname(file), { recursive: true, mode: 0o700 });
  fs.writeFileSync(file, JSON.stringify(cfg, null, 2) + '\n', { mode: 0o600 });
  fs.chmodSync(file, 0o600);
}

function client(flags) {
  const cfg = readConfig();
  const apiKey = flags['api-key'] || process.env.BMAILU_API_KEY || cfg.api_key;
  if (!apiKey) throw new UsageError('No API key. Run "bmailu login", set BMAILU_API_KEY or pass --api-key.');
  const baseUrl = flags['base-url'] || process.env.BMAILU_BASE_URL || cfg.base_url;
  return new Bmailu(apiKey, { baseUrl });
}

function list(values) {
  if (!values) return undefined;
  const out = values.flatMap((v) => String(v).split(',')).map((v) => v.trim()).filter(Boolean);
  return out.length === 1 ? out[0] : out;
}

function parseJson(text, flag) {
  try { return JSON.parse(text); } catch { throw new UsageError(`--${flag} is not valid JSON`); }
}

function readSecret(prompt) {
  return new Promise((resolve, reject) => {
    const stdin = process.stdin;
    if (stdin.isTTY) {
      process.stderr.write(prompt);
      stdin.setRawMode(true);
      stdin.resume();
      stdin.setEncoding('utf8');
      let value = '';
      const onData = (chunk) => {
        for (const ch of chunk) {
          if (ch === '\r' || ch === '\n' || ch === '\u0004') {
            stdin.setRawMode(false); stdin.pause(); stdin.removeListener('data', onData);
            process.stderr.write('\n');
            return resolve(value.trim());
          }
          if (ch === '\u0003') { stdin.setRawMode(false); process.stderr.write('\n'); return reject(new UsageError('Cancelled')); }
          if (ch === '\u007f' || ch === '\b') value = value.slice(0, -1);
          else value += ch;
        }
      };
      stdin.on('data', onData);
    } else {
      let data = '';
      stdin.setEncoding('utf8');
      stdin.on('data', (c) => { data += c; });
      stdin.on('end', () => resolve(data.split(/\r?\n/)[0].trim()));
      stdin.on('error', reject);
    }
  });
}

function print(flags, data, human) {
  if (flags.json || !human) process.stdout.write(JSON.stringify(data, null, 2) + '\n');
  else process.stdout.write(human(data) + '\n');
}

const asList = (v) => (Array.isArray(v) ? v.join(', ') : v == null ? '' : String(v));

async function run(argv) {
  const { args, flags } = parseArgs(argv);
  const [cmd, sub, ...rest] = args;
  if (flags.version || cmd === 'version') { process.stdout.write(VERSION + '\n'); return 0; }
  if (!cmd || cmd === 'help') { process.stdout.write(HELP[sub] || HELP.main); process.stdout.write('\n'); return cmd ? 0 : (flags.help ? 0 : 2); }
  if (flags.help) {
    if (!HELP[cmd]) throw new UsageError(`Unknown command: ${cmd}`);
    process.stdout.write(HELP[cmd] + '\n');
    return 0;
  }

  switch (cmd) {
    case 'login': {
      const key = await readSecret('Bmailu API key: ');
      if (!key) throw new UsageError('No API key given');
      const baseUrl = flags['base-url'] || process.env.BMAILU_BASE_URL;
      const me = await new Bmailu(key, { baseUrl }).me.get();
      const cfg = { ...readConfig(), api_key: key };
      if (baseUrl) cfg.base_url = baseUrl; else delete cfg.base_url;
      writeConfig(cfg);
      process.stdout.write(`Logged in as ${me.email}. Key saved in ${configPath()}\n`);
      return 0;
    }
    case 'logout': {
      const cfg = readConfig();
      if (!cfg.api_key) { process.stdout.write('Not logged in.\n'); return 0; }
      delete cfg.api_key;
      delete cfg.base_url;
      if (Object.keys(cfg).length) writeConfig(cfg); else fs.rmSync(configPath(), { force: true });
      process.stdout.write('Logged out.\n');
      return 0;
    }
    case 'whoami': {
      const me = await client(flags).me.get();
      print(flags, me, (m) => `${m.email} (${m.status}${m.role ? ', ' + m.role : ''}${m.sending ? ', may send' : ''})`);
      return 0;
    }
    case 'send': {
      if (sub) throw new UsageError(`Unexpected argument: ${sub}`);
      const need = ['from', 'to', 'subject'].filter((f) => !flags[f]);
      if (need.length) throw new UsageError(`Missing --${need.join(', --')}`);
      const bodies = ['text', 'html', 'html-file'].filter((f) => flags[f] !== undefined);
      if (!bodies.length) throw new UsageError('Give --text, --html or --html-file');
      if (flags.html !== undefined && flags['html-file'] !== undefined) throw new UsageError('Use --html or --html-file, not both');
      const p = { from: flags.from, to: list(flags.to), subject: flags.subject };
      if (flags.text !== undefined) p.text = flags.text;
      if (flags.html !== undefined) p.html = flags.html;
      if (flags['html-file'] !== undefined) {
        try { p.html = fs.readFileSync(flags['html-file'], 'utf8'); } catch (e) { throw new UsageError(`Cannot read ${flags['html-file']}: ${e.code || e.message}`); }
      }
      if (flags.cc) p.cc = list(flags.cc);
      if (flags.bcc) p.bcc = list(flags.bcc);
      if (flags['reply-to']) p.reply_to = list(flags['reply-to']);
      if (flags.tag) {
        p.tags = flags.tag.map((t) => {
          const i = t.indexOf('=');
          if (i < 1) throw new UsageError(`--tag must be name=value, got "${t}"`);
          return { name: t.slice(0, i), value: t.slice(i + 1) };
        });
      }
      if (flags['scheduled-at']) p.scheduled_at = flags['scheduled-at'];
      const r = await client(flags).emails.send(p, { idempotencyKey: flags['idempotency-key'] });
      print(flags, r, (x) => (p.scheduled_at ? `Scheduled: ${x.id}` : `Sent: ${x.id}`));
      return 0;
    }
    case 'emails': {
      const b = () => client(flags);
      if (sub === 'list') {
        const q = {};
        for (const k of ['limit', 'before', 'status', 'source', 'domain', 'q', 'days']) if (flags[k] !== undefined) q[k] = flags[k];
        const r = await b().emails.list(q);
        print(flags, r, (x) => (x.data.length
          ? x.data.map((e) => [e.id, e.last_event || '', asList(e.to), JSON.stringify(e.subject || ''), e.created_at].join('  ')).join('\n')
          : 'No emails.'));
        return 0;
      }
      if (sub === 'get' || sub === 'cancel') {
        if (!rest[0]) throw new UsageError(`Usage: bmailu emails ${sub} <id>`);
        const r = sub === 'get' ? await b().emails.get(rest[0]) : await b().emails.cancel(rest[0]);
        print(flags, r, sub === 'cancel'
          ? (x) => `Cancelled: ${x.id || rest[0]}`
          : (x) => [`id: ${x.id}`, `from: ${x.from}`, `to: ${asList(x.to)}`, `subject: ${x.subject}`, `status: ${x.last_event}`, `created_at: ${x.created_at}`]
            .concat(x.scheduled_at ? [`scheduled_at: ${x.scheduled_at}`] : []).join('\n'));
        return 0;
      }
      throw new UsageError(HELP.emails);
    }
    case 'domains': {
      if (sub !== 'list') throw new UsageError(HELP.domains);
      const r = await client(flags).domains.list();
      print(flags, r, (x) => (x.domains.length ? x.domains.map((d) => `${d.id}  ${d.name}  ${d.status}`).join('\n') : 'No domains.'));
      return 0;
    }
    case 'events': {
      if (sub !== 'send' || !rest[0]) throw new UsageError(HELP.events);
      if (!flags.email) throw new UsageError('Missing --email');
      const p = { event: rest[0], email: flags.email };
      if (flags.data !== undefined) p.data = parseJson(flags.data, 'data');
      const r = await client(flags).events.send(p);
      print(flags, r, (x) => `Event ${x.event} accepted for contact ${x.contact_id} (runs started: ${x.runs_started || 0})`);
      return 0;
    }
    case 'contacts': {
      if (sub !== 'add' || !rest[0]) throw new UsageError(HELP.contacts);
      const p = { email: rest[0] };
      if (flags['first-name']) p.first_name = flags['first-name'];
      if (flags['last-name']) p.last_name = flags['last-name'];
      const r = await client(flags).contacts.create(p);
      print(flags, r, (x) => `Contact ${x.id} ${x.email} (${x.status})`);
      return 0;
    }
    case 'api': {
      const method = sub && sub.toUpperCase();
      const target = rest[0];
      if (!method || !target || !/^[A-Z]+$/.test(method)) throw new UsageError(HELP.api);
      let body;
      if (flags.data !== undefined) body = parseJson(flags.data, 'data');
      const r = await client(flags).request(method, target, body);
      if (typeof r === 'string') process.stdout.write(r.endsWith('\n') ? r : r + '\n');
      else if (Buffer.isBuffer(r)) process.stdout.write(r);
      else process.stdout.write(JSON.stringify(r, null, 2) + '\n');
      return 0;
    }
    default:
      throw new UsageError(`Unknown command: ${cmd}\n\n${HELP.main}`);
  }
}

module.exports = { run, parseArgs };

if (require.main === module) {
  run(process.argv.slice(2)).then((code) => { process.exitCode = code; }, (e) => {
    if (e instanceof UsageError) { process.stderr.write(e.message + '\n'); process.exitCode = 2; return; }
    if (e instanceof BmailuError) { process.stderr.write(`Error ${e.code}${e.status ? ` (HTTP ${e.status})` : ''}: ${e.message}\n`); process.exitCode = 1; return; }
    process.stderr.write(`Error: ${e && e.message ? e.message : e}\n`);
    process.exitCode = 1;
  });
}
