'use strict';
// n8n node for the Bmailu Email API. Plain JavaScript, no build step, no runtime dependencies.

const show = (resource, operation) => ({ show: { resource: [resource], operation: [operation] } });

function parseJsonParam(value, name) {
  if (value === undefined || value === null || value === '') return undefined;
  if (typeof value === 'object') return value;
  try { return JSON.parse(value); } catch { throw new Error(`${name} is not valid JSON`); }
}

function splitList(value) {
  if (value === undefined || value === null || value === '') return undefined;
  const list = (Array.isArray(value) ? value : String(value).split(',')).map((v) => String(v).trim()).filter(Boolean);
  return list.length === 1 ? list[0] : list;
}

function apiError(e) {
  const res = e && (e.response || (e.cause && e.cause.response));
  let body = res && (res.body !== undefined ? res.body : res.data);
  if (typeof body === 'string') { try { body = JSON.parse(body); } catch { /* keep text */ } }
  const err = body && body.error;
  if (err && err.code) {
    const status = res.status || res.statusCode || e.httpCode;
    return Object.assign(new Error(`Bmailu ${err.code}${status ? ` (HTTP ${status})` : ''}: ${err.message}`), { code: err.code, status, description: err.message });
  }
  return e;
}

class Bmailu {
  constructor() {
    this.description = {
      displayName: 'Bmailu',
      name: 'bmailu',
      icon: 'file:bmailu.svg',
      group: ['output'],
      version: 1,
      subtitle: '={{$parameter["operation"] + ": " + $parameter["resource"]}}',
      description: 'Send email, manage contacts, send events and broadcasts with Bmailu',
      defaults: { name: 'Bmailu' },
      inputs: ['main'],
      outputs: ['main'],
      credentials: [{ name: 'bmailuApi', required: true }],
      properties: [
        {
          displayName: 'Resource',
          name: 'resource',
          type: 'options',
          noDataExpression: true,
          options: [
            { name: 'Broadcast', value: 'broadcast' },
            { name: 'Contact', value: 'contact' },
            { name: 'Email', value: 'email' },
            { name: 'Event', value: 'event' },
          ],
          default: 'email',
        },
        {
          displayName: 'Operation', name: 'operation', type: 'options', noDataExpression: true,
          displayOptions: { show: { resource: ['email'] } },
          options: [
            { name: 'Send', value: 'send', action: 'Send an email', description: 'Send an email' },
            { name: 'Get', value: 'get', action: 'Get an email', description: 'Get an email with its status and events' },
          ],
          default: 'send',
        },
        {
          displayName: 'Operation', name: 'operation', type: 'options', noDataExpression: true,
          displayOptions: { show: { resource: ['contact'] } },
          options: [
            { name: 'Create or Update', value: 'upsert', action: 'Create or update a contact', description: 'Add a contact, or update it if the email exists' },
            { name: 'Delete', value: 'delete', action: 'Delete a contact', description: 'Delete a contact' },
          ],
          default: 'upsert',
        },
        {
          displayName: 'Operation', name: 'operation', type: 'options', noDataExpression: true,
          displayOptions: { show: { resource: ['event'] } },
          options: [{ name: 'Send', value: 'send', action: 'Send an event', description: 'Send a custom event for a contact (starts automations)' }],
          default: 'send',
        },
        {
          displayName: 'Operation', name: 'operation', type: 'options', noDataExpression: true,
          displayOptions: { show: { resource: ['broadcast'] } },
          options: [
            { name: 'Create', value: 'create', action: 'Create a broadcast', description: 'Create a draft broadcast' },
            { name: 'Send', value: 'send', action: 'Send a broadcast', description: 'Send or schedule a broadcast' },
          ],
          default: 'create',
        },

        // Email → Send
        { displayName: 'From', name: 'from', type: 'string', default: '', required: true, placeholder: 'Shop <hello@shop.com>', description: 'An address on a verified sending domain', displayOptions: show('email', 'send') },
        { displayName: 'To', name: 'to', type: 'string', default: '', required: true, placeholder: 'ann@example.com', description: 'One address or a comma-separated list', displayOptions: show('email', 'send') },
        { displayName: 'Subject', name: 'subject', type: 'string', default: '', displayOptions: show('email', 'send') },
        { displayName: 'HTML', name: 'html', type: 'string', typeOptions: { rows: 5 }, default: '', displayOptions: show('email', 'send') },
        { displayName: 'Text', name: 'text', type: 'string', typeOptions: { rows: 3 }, default: '', description: 'Plain text; made from the HTML when left empty', displayOptions: show('email', 'send') },
        {
          displayName: 'Additional Fields', name: 'additionalFields', type: 'collection', placeholder: 'Add Field', default: {},
          displayOptions: show('email', 'send'),
          options: [
            { displayName: 'BCC', name: 'bcc', type: 'string', default: '', description: 'Comma-separated' },
            { displayName: 'CC', name: 'cc', type: 'string', default: '', description: 'Comma-separated' },
            { displayName: 'Idempotency Key', name: 'idempotencyKey', type: 'string', default: '', description: 'Same key + same body within 24 h returns the first answer (safe retries)' },
            { displayName: 'Reply To', name: 'replyTo', type: 'string', default: '', description: 'Comma-separated' },
            { displayName: 'Scheduled At', name: 'scheduledAt', type: 'string', default: '', placeholder: 'in 1 hour', description: 'ISO 8601 with zone or "in 10 minutes"; ≤ 30 days ahead' },
            { displayName: 'Tags', name: 'tags', type: 'string', default: '', placeholder: 'kind=welcome,plan=pro', description: 'name=value pairs, comma-separated' },
            { displayName: 'Template ID or Alias', name: 'templateId', type: 'string', default: '', description: 'Send the published template instead of HTML/Text' },
            { displayName: 'Template Variables (JSON)', name: 'templateVariables', type: 'json', default: '{}' },
          ],
        },

        // Email → Get
        { displayName: 'Email ID', name: 'emailId', type: 'string', default: '', required: true, displayOptions: show('email', 'get') },

        // Contact → Create or Update
        { displayName: 'Email', name: 'email', type: 'string', placeholder: 'ann@example.com', default: '', required: true, displayOptions: show('contact', 'upsert') },
        {
          displayName: 'Additional Fields', name: 'contactFields', type: 'collection', placeholder: 'Add Field', default: {},
          displayOptions: show('contact', 'upsert'),
          options: [
            { displayName: 'First Name', name: 'first_name', type: 'string', default: '' },
            { displayName: 'Last Name', name: 'last_name', type: 'string', default: '' },
            { displayName: 'Properties (JSON)', name: 'properties', type: 'json', default: '{}' },
            { displayName: 'Segment IDs', name: 'segment_ids', type: 'string', default: '', description: 'Comma-separated' },
            { displayName: 'Unsubscribed', name: 'unsubscribed', type: 'boolean', default: false },
          ],
        },

        // Contact → Delete
        { displayName: 'Contact ID or Email', name: 'contact', type: 'string', default: '', required: true, displayOptions: show('contact', 'delete') },

        // Event → Send
        { displayName: 'Event Name', name: 'eventName', type: 'string', default: '', required: true, placeholder: 'signed_up', displayOptions: show('event', 'send') },
        { displayName: 'Contact Email', name: 'eventEmail', type: 'string', default: '', required: true, placeholder: 'ann@example.com', description: 'A new contact is created if needed', displayOptions: show('event', 'send') },
        { displayName: 'Data (JSON)', name: 'eventData', type: 'json', default: '{}', displayOptions: show('event', 'send') },

        // Broadcast → Create
        { displayName: 'Name', name: 'broadcastName', type: 'string', default: '', required: true, displayOptions: show('broadcast', 'create') },
        { displayName: 'From', name: 'broadcastFrom', type: 'string', default: '', placeholder: 'Shop <news@shop.com>', displayOptions: show('broadcast', 'create') },
        { displayName: 'Subject', name: 'broadcastSubject', type: 'string', default: '', displayOptions: show('broadcast', 'create') },
        { displayName: 'HTML', name: 'broadcastHtml', type: 'string', typeOptions: { rows: 5 }, default: '', description: '{{{UNSUBSCRIBE_URL}}} places the unsubscribe link', displayOptions: show('broadcast', 'create') },
        {
          displayName: 'Additional Fields', name: 'broadcastFields', type: 'collection', placeholder: 'Add Field', default: {},
          displayOptions: show('broadcast', 'create'),
          options: [
            { displayName: 'Reply To', name: 'reply_to', type: 'string', default: '' },
            { displayName: 'Segment ID', name: 'segment_id', type: 'string', default: '', description: 'Empty: all subscribed contacts' },
            { displayName: 'Text', name: 'text', type: 'string', default: '' },
            { displayName: 'Topic ID', name: 'topic_id', type: 'string', default: '' },
            { displayName: 'Track Clicks', name: 'track_clicks', type: 'boolean', default: true },
            { displayName: 'Track Opens', name: 'track_opens', type: 'boolean', default: true },
          ],
        },

        // Broadcast → Send
        { displayName: 'Broadcast ID', name: 'broadcastId', type: 'string', default: '', required: true, displayOptions: show('broadcast', 'send') },
        { displayName: 'Scheduled At', name: 'broadcastScheduledAt', type: 'string', default: '', placeholder: 'in 2 hours', description: 'Empty: send now (after approval during early access)', displayOptions: show('broadcast', 'send') },
      ],
    };
  }

  async execute() {
    const items = this.getInputData();
    const out = [];
    const creds = await this.getCredentials('bmailuApi');
    const baseUrl = String(creds.baseUrl || 'https://app.bmailu.com/api/v1').replace(/\/+$/, '');
    const call = async (method, path, body, headers) => {
      try {
        return await this.helpers.httpRequestWithAuthentication.call(this, 'bmailuApi', {
          method,
          url: baseUrl + path,
          headers: { 'Content-Type': 'application/json', 'User-Agent': 'n8n-nodes-bmailu/0.1.0', ...(headers || {}) },
          ...(body !== undefined ? { body } : {}),
          json: true,
        });
      } catch (e) {
        throw apiError(e);
      }
    };
    const enc = encodeURIComponent;

    for (let i = 0; i < items.length; i++) {
      try {
        const resource = this.getNodeParameter('resource', i);
        const operation = this.getNodeParameter('operation', i);
        let result;

        if (resource === 'email' && operation === 'send') {
          const extra = this.getNodeParameter('additionalFields', i, {});
          const body = { from: this.getNodeParameter('from', i), to: splitList(this.getNodeParameter('to', i)) };
          const subject = this.getNodeParameter('subject', i, '');
          const html = this.getNodeParameter('html', i, '');
          const text = this.getNodeParameter('text', i, '');
          if (subject) body.subject = subject;
          if (html) body.html = html;
          if (text) body.text = text;
          if (extra.cc) body.cc = splitList(extra.cc);
          if (extra.bcc) body.bcc = splitList(extra.bcc);
          if (extra.replyTo) body.reply_to = splitList(extra.replyTo);
          if (extra.scheduledAt) body.scheduled_at = extra.scheduledAt;
          if (extra.tags) {
            body.tags = String(extra.tags).split(',').map((t) => t.trim()).filter(Boolean).map((t) => {
              const at = t.indexOf('=');
              if (at < 1) throw new Error(`Tag "${t}" must be name=value`);
              return { name: t.slice(0, at), value: t.slice(at + 1) };
            });
          }
          if (extra.templateId) body.template = { id: extra.templateId, variables: parseJsonParam(extra.templateVariables, 'Template Variables') || {} };
          result = await call('POST', '/emails', body, extra.idempotencyKey ? { 'Idempotency-Key': extra.idempotencyKey } : undefined);
        } else if (resource === 'email' && operation === 'get') {
          result = await call('GET', `/emails/${enc(this.getNodeParameter('emailId', i))}`);
        } else if (resource === 'contact' && operation === 'upsert') {
          const f = this.getNodeParameter('contactFields', i, {});
          const body = { email: this.getNodeParameter('email', i) };
          if (f.first_name) body.first_name = f.first_name;
          if (f.last_name) body.last_name = f.last_name;
          if (f.unsubscribed !== undefined) body.unsubscribed = !!f.unsubscribed;
          const props = parseJsonParam(f.properties, 'Properties');
          if (props && Object.keys(props).length) body.properties = props;
          if (f.segment_ids) body.segment_ids = splitList(f.segment_ids);
          result = await call('POST', '/contacts', body);
        } else if (resource === 'contact' && operation === 'delete') {
          result = await call('DELETE', `/contacts/${enc(this.getNodeParameter('contact', i))}`);
        } else if (resource === 'event' && operation === 'send') {
          const body = { event: this.getNodeParameter('eventName', i), email: this.getNodeParameter('eventEmail', i) };
          const data = parseJsonParam(this.getNodeParameter('eventData', i, '{}'), 'Data');
          if (data && Object.keys(data).length) body.data = data;
          result = await call('POST', '/events', body);
        } else if (resource === 'broadcast' && operation === 'create') {
          const f = this.getNodeParameter('broadcastFields', i, {});
          const body = { name: this.getNodeParameter('broadcastName', i) };
          for (const [param, key] of [['broadcastFrom', 'from'], ['broadcastSubject', 'subject'], ['broadcastHtml', 'html']]) {
            const v = this.getNodeParameter(param, i, '');
            if (v) body[key] = v;
          }
          for (const k of ['reply_to', 'segment_id', 'topic_id', 'text']) if (f[k]) body[k] = f[k];
          for (const k of ['track_opens', 'track_clicks']) if (f[k] !== undefined) body[k] = !!f[k];
          result = await call('POST', '/broadcasts', body);
        } else if (resource === 'broadcast' && operation === 'send') {
          const when = this.getNodeParameter('broadcastScheduledAt', i, '');
          result = await call('POST', `/broadcasts/${enc(this.getNodeParameter('broadcastId', i))}/send`, when ? { scheduled_at: when } : {});
        } else {
          throw new Error(`Unsupported operation ${resource}.${operation}`);
        }

        out.push({ json: result, pairedItem: { item: i } });
      } catch (e) {
        if (this.continueOnFail()) {
          out.push({ json: { error: e.message, code: e.code }, pairedItem: { item: i } });
          continue;
        }
        throw e;
      }
    }
    return [out];
  }
}

module.exports = { Bmailu };
