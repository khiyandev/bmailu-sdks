// Type definitions for bmailu 0.1.0 (hand-written from the Bmailu OpenAPI 3.1 spec).
// Answers are typed loosely (Record<string, any>) on purpose: see https://bmailu.com/docs/ for every field.

export type Json = Record<string, any>;
export type Query = Record<string, string | number | boolean | undefined | null>;
export type Addresses = string | string[];

export interface BmailuOptions {
  /** Default: env BMAILU_BASE_URL or https://app.bmailu.com/api/v1 */
  baseUrl?: string;
  /** A fetch implementation (default: global fetch, Node >= 18). */
  fetch?: typeof fetch;
  /** Per-request timeout in milliseconds (default 30000). */
  timeoutMs?: number;
  apiKey?: string;
}

export interface Tag { name: string; value: string }
/** content is base64; "path" (download from a URL) is refused by the API. */
export interface Attachment { filename: string; content: string; content_type?: string; content_id?: string }

export interface SendEmailParams {
  from?: string;
  to: Addresses;
  cc?: Addresses;
  bcc?: Addresses;
  reply_to?: Addresses;
  subject?: string;
  html?: string;
  text?: string;
  template?: { id: string; variables?: Record<string, any> };
  headers?: Record<string, string>;
  tags?: Tag[];
  attachments?: Attachment[];
  /** ISO 8601 with zone or "in 10 minutes" */
  scheduled_at?: string;
}

export interface SendOptions { idempotencyKey?: string }

export interface ListEmailsQuery extends Query { limit?: number; before?: string; status?: string; source?: string; key?: string; domain?: string; q?: string; days?: number }

export interface SendEventParams { event: string; email?: string; contact_id?: string; first_name?: string; data?: Record<string, any> }

export interface ContactParams {
  email: string;
  first_name?: string;
  last_name?: string;
  properties?: Record<string, string | number | boolean | null>;
  unsubscribed?: boolean;
  segment_ids?: string | string[];
}

export interface BroadcastParams {
  name?: string;
  segment_id?: string | null;
  topic_id?: string | null;
  from?: string | null;
  reply_to?: string | null;
  subject?: string | null;
  html?: string | null;
  text?: string | null;
  track_opens?: boolean;
  track_clicks?: boolean;
}

export interface TemplateVariable { key: string; type?: 'string' | 'number'; fallback_value?: string | number }
export interface TemplateParams {
  name?: string;
  alias?: string | null;
  subject?: string | null;
  from?: string | null;
  reply_to?: string | null;
  html?: string | null;
  text?: string | null;
  variables?: TemplateVariable[];
  publish?: boolean;
  [k: string]: any;
}

export interface WebhookParams { url?: string; events?: string[]; [k: string]: any }

export type WebhookHeaders = Record<string, string | string[] | undefined> | { get(name: string): string | null };

export interface VerifyOptions { toleranceSeconds?: number; now?: number }

export class BmailuError extends Error {
  /** HTTP status (0 for network errors and timeouts). */
  status: number;
  /** e.g. not_found, validation_error, domain_not_verified, restricted_api_key */
  code: string;
  message: string;
  body?: unknown;
  constructor(status: number, code: string, message: string, body?: unknown);
}

/** Throws BmailuError (code invalid_signature) unless a v1 signature matches and the timestamp is within 5 minutes. */
export function verifyWebhook(body: string | Buffer, headers: WebhookHeaders, secret: string, options?: VerifyOptions): true;

export interface Inbox {
  messages: {
    list(query?: Query): Promise<Json>;
    create(params: Json): Promise<Json>;
    get(messageId: string): Promise<Json>;
    update(messageId: string, params: { read?: boolean; flagged?: boolean; labels_add?: string | string[]; labels_remove?: string | string[]; folder?: string }): Promise<Json>;
    trash(messageId: string): Promise<Json>;
    delete(messageId: string): Promise<Json>;
    /** Returns the raw bytes. */
    attachment(messageId: string, blobId: string): Promise<Buffer>;
    reply(messageId: string, params: Json): Promise<Json>;
    forward(messageId: string, params: Json): Promise<Json>;
    /** Send a draft. */
    send(messageId: string): Promise<Json>;
  };
  threads: { get(threadId: string): Promise<Json> };
  folders: {
    list(): Promise<Json>;
    create(params: string | { name: string; parent_id?: string }): Promise<Json>;
    delete(folderId: string): Promise<Json>;
  };
}

export class Bmailu {
  constructor(apiKey?: string, options?: BmailuOptions);
  constructor(options: BmailuOptions);

  static webhooks: { verify: typeof verifyWebhook };
  static BmailuError: typeof BmailuError;
  static VERSION: string;
  static DEFAULT_BASE_URL: string;

  apiKey: string;
  baseUrl: string;
  timeoutMs: number;

  /** Generic call. JSON answers are parsed; text/* comes back as a string; other types as a Buffer. */
  request<T = any>(method: string, path: string, body?: unknown, query?: Query, headers?: Record<string, string>): Promise<T>;

  me: { get(): Promise<Json>; update(params: { recovery_email: string }): Promise<Json> };

  emails: {
    send(params: SendEmailParams, options?: SendOptions): Promise<{ id: string }>;
    batch(emails: SendEmailParams[], options?: SendOptions): Promise<{ data: { id: string }[] }>;
    list(query?: ListEmailsQuery): Promise<Json>;
    /** CSV text */
    export(query?: Query): Promise<string>;
    get(emailId: string): Promise<Json>;
    update(emailId: string, params: { scheduled_at: string }): Promise<Json>;
    reschedule(emailId: string, scheduledAt: string): Promise<Json>;
    cancel(emailId: string): Promise<Json>;
  };
  batch: { send(emails: SendEmailParams[], options?: SendOptions): Promise<{ data: { id: string }[] }> };

  events: {
    send(params: SendEventParams): Promise<Json>;
    list(query?: { contact?: string }): Promise<Json>;
  };

  contacts: {
    create(params: ContactParams): Promise<Json>;
    upsert(params: ContactParams): Promise<Json>;
    list(query?: { segment_id?: string; status?: string; q?: string; limit?: number; position?: number }): Promise<Json>;
    import(params: { csv: string; mode?: 'confirm' | 'attested'; from?: string; consent_statement?: string; segment_id?: string }): Promise<Json>;
    get(idOrEmail: string): Promise<Json>;
    update(idOrEmail: string, params: Partial<ContactParams> & Json): Promise<Json>;
    delete(idOrEmail: string): Promise<Json>;
  };

  segments: {
    list(): Promise<Json>;
    create(params: { name: string }): Promise<Json>;
    get(segmentId: string): Promise<Json>;
    update(segmentId: string, params: { name: string }): Promise<Json>;
    delete(segmentId: string): Promise<Json>;
    addContacts(segmentId: string, contacts: string[] | { contacts: string[] }): Promise<Json>;
    removeContact(segmentId: string, contact: string): Promise<Json>;
  };

  topics: {
    list(): Promise<Json>;
    create(params: { name: string; description?: string; default_subscription?: 'opt_in' | 'opt_out' }): Promise<Json>;
    delete(topicId: string): Promise<Json>;
  };

  broadcasts: {
    list(): Promise<Json>;
    create(params: BroadcastParams & { name: string }): Promise<Json>;
    get(broadcastId: string): Promise<Json>;
    update(broadcastId: string, params: BroadcastParams): Promise<Json>;
    delete(broadcastId: string): Promise<Json>;
    send(broadcastId: string, params?: { scheduled_at?: string }): Promise<Json>;
    cancel(broadcastId: string): Promise<Json>;
    test(broadcastId: string, to: string | { to: string }): Promise<Json>;
    stats(broadcastId: string): Promise<Json>;
  };

  templates: {
    list(): Promise<Json>;
    create(params: TemplateParams & { name: string }): Promise<Json>;
    get(idOrAlias: string): Promise<Json>;
    update(idOrAlias: string, params: TemplateParams): Promise<Json>;
    delete(idOrAlias: string): Promise<Json>;
    publish(idOrAlias: string): Promise<Json>;
    duplicate(idOrAlias: string): Promise<Json>;
    versions(idOrAlias: string): Promise<Json>;
    publishVersion(idOrAlias: string, version: number | string): Promise<Json>;
    render(idOrAlias: string, params?: { variables?: Record<string, any>; version?: number }): Promise<Json>;
    /** HTML text */
    preview(idOrAlias: string): Promise<string>;
  };

  webhooks: {
    list(): Promise<Json>;
    create(params: WebhookParams & { url: string }): Promise<Json>;
    get(webhookId: string): Promise<Json>;
    update(webhookId: string, params: WebhookParams): Promise<Json>;
    delete(webhookId: string): Promise<Json>;
    rotateSecret(webhookId: string): Promise<Json>;
    test(webhookId: string): Promise<Json>;
    deliveries(webhookId: string): Promise<Json>;
    replay(deliveryId: string): Promise<Json>;
    verify: typeof verifyWebhook;
  };

  suppressions: {
    list(query?: { email?: string }): Promise<Json>;
    create(params: string | { email: string }): Promise<Json>;
    delete(email: string): Promise<Json>;
  };

  automations: {
    list(): Promise<Json>;
    create(params: { name: string; trigger: string | { event: string }; steps?: Json[] }): Promise<Json>;
    get(automationId: string): Promise<Json>;
    update(automationId: string, params: Json): Promise<Json>;
    delete(automationId: string): Promise<Json>;
    start(automationId: string): Promise<Json>;
    stop(automationId: string): Promise<Json>;
    duplicate(automationId: string): Promise<Json>;
    runs(automationId: string, query?: { status?: string }): Promise<Json>;
    getRun(automationId: string, runId: string): Promise<Json>;
  };

  domains: {
    list(): Promise<Json>;
    create(params: string | { name: string }): Promise<Json>;
    get(domainId: number | string): Promise<Json>;
    delete(domainId: number | string): Promise<Json>;
    verify(domainId: number | string): Promise<Json>;
    dnsCheck(domainId: number | string): Promise<Json>;
    setCatchAll(domainId: number | string, mailboxId: number | null): Promise<Json>;
    createMailbox(domainId: number | string, params: string | { local_part: string }): Promise<Json>;
    createAlias(domainId: number | string, params: { local_part: string; mailbox_id: number }): Promise<Json>;
  };

  mailboxes: {
    create(domainId: number | string, params: string | { local_part: string }): Promise<Json>;
    delete(mailboxId: number | string): Promise<Json>;
    restore(mailboxId: number | string): Promise<Json>;
    resetPassword(mailboxId: number | string): Promise<Json>;
    setForward(mailboxId: number | string, to: string | null): Promise<Json>;
    sendTestEmail(mailboxId: number | string): Promise<Json>;
  };

  aliases: {
    create(domainId: number | string, params: { local_part: string; mailbox_id: number }): Promise<Json>;
    delete(aliasId: number | string): Promise<Json>;
  };

  metrics: { get(query?: { days?: number; domain?: string }): Promise<Json> };
  logs: { list(query?: { source?: string; status?: string; key?: string; limit?: number }): Promise<Json> };

  /** Inbox API for one mailbox. */
  inbox(mailboxId: number | string): Inbox;
}

export const VERSION: string;
export const DEFAULT_BASE_URL: string;
