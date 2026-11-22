'use strict';
// Bmailu Node.js SDK. Zero dependencies: global fetch (Node >= 18) and node:crypto.
const crypto = require('node:crypto');

const VERSION = '0.1.0';
const DEFAULT_BASE_URL = 'https://app.bmailu.com/api/v1';

class BmailuError extends Error {
  constructor(status, code, message, body) {
    super(message);
    this.name = 'BmailuError';
    this.status = status;
    this.code = code;
    this.body = body;
  }
}

const enc = encodeURIComponent;

// Standard Webhooks / Svix scheme, the same as the panel's sign().
function verifyWebhook(body, headers, secret, { toleranceSeconds = 300, now = Date.now() } = {}) {
  const get = (name) => {
    if (!headers) return undefined;
    if (typeof headers.get === 'function') return headers.get(name) || undefined;
    for (const k of Object.keys(headers)) if (k.toLowerCase() === name) return Array.isArray(headers[k]) ? headers[k][0] : headers[k];
    return undefined;
  };
  const id = get('webhook-id');
  const ts = get('webhook-timestamp');
  const sigs = get('webhook-signature');
  if (!id || !ts || !sigs) throw new BmailuError(400, 'invalid_signature', 'Missing webhook-id, webhook-timestamp or webhook-signature header');
  const t = Number(ts);
  if (!Number.isFinite(t) || Math.abs(now / 1000 - t) > toleranceSeconds) throw new BmailuError(400, 'invalid_signature', 'Webhook timestamp is too old or too far in the future');
  const payload = Buffer.isBuffer(body) ? body.toString('utf8') : typeof body === 'string' ? body : JSON.stringify(body);
  const key = Buffer.from(String(secret || '').replace(/^whsec_/, ''), 'base64');
  const expected = crypto.createHmac('sha256', key).update(`${id}.${ts}.${payload}`).digest();
  for (const part of String(sigs).split(' ')) {
    const [version, sig] = part.split(',');
    if (version !== 'v1' || !sig) continue;
    const got = Buffer.from(sig, 'base64');
    if (got.length === expected.length && crypto.timingSafeEqual(got, expected)) return true;
  }
  throw new BmailuError(400, 'invalid_signature', 'No matching webhook signature');
}

class Bmailu {
  constructor(apiKey, options = {}) {
    if (apiKey && typeof apiKey === 'object') { options = apiKey; apiKey = options.apiKey; }
    this.apiKey = apiKey || process.env.BMAILU_API_KEY;
    if (!this.apiKey) throw new BmailuError(0, 'missing_api_key', 'No API key: pass one to new Bmailu() or set BMAILU_API_KEY');
    this.baseUrl = String(options.baseUrl || process.env.BMAILU_BASE_URL || DEFAULT_BASE_URL).replace(/\/+$/, '');
    this.fetch = options.fetch || globalThis.fetch;
    this.timeoutMs = options.timeoutMs || 30000;
    if (typeof this.fetch !== 'function') throw new BmailuError(0, 'missing_fetch', 'No fetch: use Node >= 18 or pass options.fetch');

    const r = (m, p, b, q, h) => this.request(m, p, b, q, h);
    const idem = (o) => (o && o.idempotencyKey ? { 'Idempotency-Key': o.idempotencyKey } : undefined);

    this.me = { get: () => r('GET', '/me'), update: (p) => r('PATCH', '/me', p) };

    const batchSend = (emails, o) => r('POST', '/emails/batch', emails, undefined, idem(o));
    this.emails = {
      send: (p, o) => r('POST', '/emails', p, undefined, idem(o)),
      batch: batchSend,
      list: (q) => r('GET', '/emails', undefined, q),
      export: (q) => r('GET', '/emails/export', undefined, q),
      get: (id) => r('GET', `/emails/${enc(id)}`),
      update: (id, p) => r('PATCH', `/emails/${enc(id)}`, p),
      reschedule: (id, scheduledAt) => r('PATCH', `/emails/${enc(id)}`, { scheduled_at: scheduledAt }),
      cancel: (id) => r('POST', `/emails/${enc(id)}/cancel`),
    };
    this.batch = { send: batchSend };

    this.events = {
      send: (p) => r('POST', '/events', p),
      list: (q) => r('GET', '/events', undefined, q),
    };

    this.contacts = {
      create: (p) => r('POST', '/contacts', p),
      upsert: (p) => r('POST', '/contacts', p),
      list: (q) => r('GET', '/contacts', undefined, q),
      import: (p) => r('POST', '/contacts/import', p),
      get: (idOrEmail) => r('GET', `/contacts/${enc(idOrEmail)}`),
      update: (idOrEmail, p) => r('PATCH', `/contacts/${enc(idOrEmail)}`, p),
      delete: (idOrEmail) => r('DELETE', `/contacts/${enc(idOrEmail)}`),
    };

    this.segments = {
      list: () => r('GET', '/segments'),
      create: (p) => r('POST', '/segments', p),
      get: (id) => r('GET', `/segments/${enc(id)}`),
      update: (id, p) => r('PATCH', `/segments/${enc(id)}`, p),
      delete: (id) => r('DELETE', `/segments/${enc(id)}`),
      addContacts: (id, contacts) => r('POST', `/segments/${enc(id)}/contacts`, Array.isArray(contacts) ? { contacts } : contacts),
      removeContact: (id, contact) => r('DELETE', `/segments/${enc(id)}/contacts/${enc(contact)}`),
    };

    this.topics = {
      list: () => r('GET', '/topics'),
      create: (p) => r('POST', '/topics', p),
      delete: (id) => r('DELETE', `/topics/${enc(id)}`),
    };

    this.broadcasts = {
      list: () => r('GET', '/broadcasts'),
      create: (p) => r('POST', '/broadcasts', p),
      get: (id) => r('GET', `/broadcasts/${enc(id)}`),
      update: (id, p) => r('PATCH', `/broadcasts/${enc(id)}`, p),
      delete: (id) => r('DELETE', `/broadcasts/${enc(id)}`),
      send: (id, p) => r('POST', `/broadcasts/${enc(id)}/send`, p || {}),
      cancel: (id) => r('POST', `/broadcasts/${enc(id)}/cancel`),
      test: (id, p) => r('POST', `/broadcasts/${enc(id)}/test`, typeof p === 'string' ? { to: p } : p),
      stats: (id) => r('GET', `/broadcasts/${enc(id)}/stats`),
    };

    this.templates = {
      list: () => r('GET', '/templates'),
      create: (p) => r('POST', '/templates', p),
      get: (id) => r('GET', `/templates/${enc(id)}`),
      update: (id, p) => r('PATCH', `/templates/${enc(id)}`, p),
      delete: (id) => r('DELETE', `/templates/${enc(id)}`),
      publish: (id) => r('POST', `/templates/${enc(id)}/publish`),
      duplicate: (id) => r('POST', `/templates/${enc(id)}/duplicate`),
      versions: (id) => r('GET', `/templates/${enc(id)}/versions`),
      publishVersion: (id, version) => r('POST', `/templates/${enc(id)}/versions/${enc(version)}/publish`),
      render: (id, p) => r('POST', `/templates/${enc(id)}/render`, p || {}),
      preview: (id) => r('GET', `/templates/${enc(id)}/preview.html`),
    };

    this.webhooks = {
      list: () => r('GET', '/webhooks'),
      create: (p) => r('POST', '/webhooks', p),
      get: (id) => r('GET', `/webhooks/${enc(id)}`),
      update: (id, p) => r('PATCH', `/webhooks/${enc(id)}`, p),
      delete: (id) => r('DELETE', `/webhooks/${enc(id)}`),
      rotateSecret: (id) => r('POST', `/webhooks/${enc(id)}/rotate-secret`),
      test: (id) => r('POST', `/webhooks/${enc(id)}/test`),
      deliveries: (id) => r('GET', `/webhooks/${enc(id)}/deliveries`),
      replay: (deliveryId) => r('POST', `/webhook-deliveries/${enc(deliveryId)}/replay`),
      verify: verifyWebhook,
    };

    this.suppressions = {
      list: (q) => r('GET', '/suppressions', undefined, q),
      create: (p) => r('POST', '/suppressions', typeof p === 'string' ? { email: p } : p),
      delete: (email) => r('DELETE', `/suppressions/${enc(email)}`),
    };

    this.automations = {
      list: () => r('GET', '/automations'),
      create: (p) => r('POST', '/automations', p),
      get: (id) => r('GET', `/automations/${enc(id)}`),
      update: (id, p) => r('PATCH', `/automations/${enc(id)}`, p),
      delete: (id) => r('DELETE', `/automations/${enc(id)}`),
      start: (id) => r('POST', `/automations/${enc(id)}/start`),
      stop: (id) => r('POST', `/automations/${enc(id)}/stop`),
      duplicate: (id) => r('POST', `/automations/${enc(id)}/duplicate`),
      runs: (id, q) => r('GET', `/automations/${enc(id)}/runs`, undefined, q),
      getRun: (id, runId) => r('GET', `/automations/${enc(id)}/runs/${enc(runId)}`),
    };

    this.mailboxes = {
      create: (domainId, p) => r('POST', `/domains/${enc(domainId)}/mailboxes`, typeof p === 'string' ? { local_part: p } : p),
      delete: (id) => r('DELETE', `/mailboxes/${enc(id)}`),
      restore: (id) => r('POST', `/mailboxes/${enc(id)}/restore`),
      resetPassword: (id) => r('POST', `/mailboxes/${enc(id)}/password`),
      setForward: (id, to) => r('PUT', `/mailboxes/${enc(id)}/forward`, { to: to === undefined ? null : to }),
      sendTestEmail: (id) => r('POST', `/mailboxes/${enc(id)}/test-email`),
    };

    this.aliases = {
      create: (domainId, p) => r('POST', `/domains/${enc(domainId)}/aliases`, p),
      delete: (id) => r('DELETE', `/aliases/${enc(id)}`),
    };

    this.domains = {
      list: () => r('GET', '/domains'),
      create: (p) => r('POST', '/domains', typeof p === 'string' ? { name: p } : p),
      get: (id) => r('GET', `/domains/${enc(id)}`),
      delete: (id) => r('DELETE', `/domains/${enc(id)}`),
      verify: (id) => r('POST', `/domains/${enc(id)}/verify`),
      dnsCheck: (id) => r('POST', `/domains/${enc(id)}/dns-check`),
      setCatchAll: (id, mailboxId) => r('PUT', `/domains/${enc(id)}/catch-all`, { mailbox_id: mailboxId === undefined ? null : mailboxId }),
      createMailbox: (id, p) => this.mailboxes.create(id, p),
      createAlias: (id, p) => this.aliases.create(id, p),
    };

    this.metrics = { get: (q) => r('GET', '/metrics', undefined, q) };
    this.logs = { list: (q) => r('GET', '/logs', undefined, q) };
  }

  // Inbox API for one mailbox: bmailu.inbox(12).messages.list({ unread: true })
  inbox(mailboxId) {
    const r = (m, p, b, q) => this.request(m, p, b, q);
    const base = `/mailboxes/${enc(mailboxId)}`;
    const msg = (id) => `${base}/messages/${enc(id)}`;
    return {
      messages: {
        list: (q) => r('GET', `${base}/messages`, undefined, q),
        create: (p) => r('POST', `${base}/messages`, p),
        get: (id) => r('GET', msg(id)),
        update: (id, p) => r('PATCH', msg(id), p),
        trash: (id) => r('DELETE', msg(id)),
        delete: (id) => r('DELETE', msg(id)),
        attachment: (id, blobId) => r('GET', `${msg(id)}/attachments/${enc(blobId)}`),
        reply: (id, p) => r('POST', `${msg(id)}/reply`, p),
        forward: (id, p) => r('POST', `${msg(id)}/forward`, p),
        send: (id) => r('POST', `${msg(id)}/send`),
      },
      threads: { get: (id) => r('GET', `${base}/threads/${enc(id)}`) },
      folders: {
        list: () => r('GET', `${base}/folders`),
        create: (p) => r('POST', `${base}/folders`, typeof p === 'string' ? { name: p } : p),
        delete: (id) => r('DELETE', `${base}/folders/${enc(id)}`),
      },
    };
  }

  // Generic escape hatch: bmailu.request('GET', '/emails', undefined, { limit: 5 })
  // JSON answers are parsed; text/csv and text/html come back as strings; anything else as a Buffer.
  async request(method, path, body, query, headers) {
    let url = this.baseUrl + (path.startsWith('/') ? path : '/' + path);
    if (query) {
      const qs = new URLSearchParams();
      for (const [k, v] of Object.entries(query)) if (v !== undefined && v !== null) qs.append(k, String(v));
      const s = qs.toString();
      if (s) url += (url.includes('?') ? '&' : '?') + s;
    }
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeoutMs);
    let res;
    try {
      res = await this.fetch(url, {
        method: String(method).toUpperCase(),
        headers: {
          Authorization: `Bearer ${this.apiKey}`,
          'Content-Type': 'application/json',
          'User-Agent': `bmailu-node/${VERSION}`,
          ...(headers || {}),
        },
        body: body === undefined ? undefined : JSON.stringify(body),
        signal: controller.signal,
      });
    } catch (e) {
      if (e && e.name === 'AbortError') throw new BmailuError(0, 'timeout', `Request timed out after ${this.timeoutMs} ms`);
      throw new BmailuError(0, 'network_error', (e && e.message) || 'Network error');
    } finally {
      clearTimeout(timer);
    }
    const type = (res.headers.get('content-type') || '').toLowerCase();
    let data;
    if (type.includes('json')) {
      const text = await res.text();
      try { data = text ? JSON.parse(text) : null; } catch { data = text; }
    } else if (type.startsWith('text/')) {
      data = await res.text();
    } else {
      data = Buffer.from(await res.arrayBuffer());
    }
    if (!res.ok) {
      const err = data && typeof data === 'object' && data.error ? data.error : {};
      throw new BmailuError(res.status, err.code || 'http_error', err.message || `HTTP ${res.status}`, data);
    }
    return data;
  }
}

Bmailu.webhooks = { verify: verifyWebhook };
Bmailu.BmailuError = BmailuError;
Bmailu.VERSION = VERSION;
Bmailu.DEFAULT_BASE_URL = DEFAULT_BASE_URL;

module.exports = { Bmailu, BmailuError, verifyWebhook, VERSION, DEFAULT_BASE_URL };
