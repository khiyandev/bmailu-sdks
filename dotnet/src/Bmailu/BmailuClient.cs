using System;
using System.Collections.Generic;
using System.Linq;
using System.Net.Http;
using System.Text;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Threading;
using System.Threading.Tasks;

namespace Bmailu
{
    /// <summary>An error from the Bmailu API ({"error":{"code","message"}}) or from the network.</summary>
    public class BmailuException : Exception
    {
        /// <summary>HTTP status, or 0 when the request did not reach the API.</summary>
        public int StatusCode { get; }
        /// <summary>Machine-readable code, e.g. not_found, validation_error, domain_not_verified.</summary>
        public string Code { get; }

        public BmailuException(int statusCode, string code, string message, Exception? inner = null) : base(message, inner)
        {
            StatusCode = statusCode;
            Code = code;
        }
    }

    /// <summary>
    /// Bmailu API client. Bodies are any object System.Text.Json can write (anonymous objects, dictionaries, classes);
    /// answers are <see cref="JsonElement"/>.
    /// <code>var email = await new BmailuClient("bmk_...").Emails.SendAsync(new { from = "...", to = "...", subject = "Hi", html = "..." });</code>
    /// </summary>
    public class BmailuClient
    {
        public const string Version = "0.1.0";
        public const string DefaultBaseUrl = "https://app.bmailu.com/api/v1";

        internal static readonly JsonSerializerOptions JsonOptions = new JsonSerializerOptions
        {
            DefaultIgnoreCondition = JsonIgnoreCondition.WhenWritingNull,
        };

        private readonly string _apiKey;
        private readonly string _baseUrl;
        private readonly HttpClient _http;

        public EmailsResource Emails { get; }
        public EventsResource Events { get; }
        public ContactsResource Contacts { get; }
        public BroadcastsResource Broadcasts { get; }
        public TemplatesResource Templates { get; }
        public WebhooksResource Webhooks { get; }

        /// <param name="apiKey">API key; defaults to the BMAILU_API_KEY environment variable.</param>
        /// <param name="baseUrl">Defaults to BMAILU_BASE_URL or https://app.bmailu.com/api/v1.</param>
        /// <param name="httpClient">Optional HttpClient to reuse.</param>
        public BmailuClient(string? apiKey = null, string? baseUrl = null, HttpClient? httpClient = null)
        {
            var key = apiKey ?? Environment.GetEnvironmentVariable("BMAILU_API_KEY");
            if (string.IsNullOrEmpty(key)) throw new ArgumentException("Missing API key: pass it or set BMAILU_API_KEY");
            var b = baseUrl ?? Environment.GetEnvironmentVariable("BMAILU_BASE_URL");
            if (string.IsNullOrEmpty(b)) b = DefaultBaseUrl;
            _apiKey = key!;
            _baseUrl = b!.TrimEnd('/');
            _http = httpClient ?? new HttpClient { Timeout = TimeSpan.FromSeconds(60) };
            Emails = new EmailsResource(this);
            Events = new EventsResource(this);
            Contacts = new ContactsResource(this);
            Broadcasts = new BroadcastsResource(this);
            Templates = new TemplatesResource(this);
            Webhooks = new WebhooksResource(this);
        }

        /// <summary>Any API call: method ("GET", "POST", ...), path under /api/v1 (e.g. "/domains"), optional body and query.</summary>
        public async Task<JsonElement> RequestAsync(string method, string path, object? body = null,
            IDictionary<string, object?>? query = null, IDictionary<string, string>? headers = null, CancellationToken cancellationToken = default)
        {
            var url = new StringBuilder(_baseUrl).Append(path.StartsWith("/") ? path : "/" + path);
            if (query != null)
            {
                var sep = path.Contains("?") ? '&' : '?';
                foreach (var kv in query)
                {
                    if (kv.Value == null) continue;
                    var v = kv.Value is bool bv ? (bv ? "true" : "false") : Convert.ToString(kv.Value, System.Globalization.CultureInfo.InvariantCulture);
                    url.Append(sep).Append(Uri.EscapeDataString(kv.Key)).Append('=').Append(Uri.EscapeDataString(v ?? ""));
                    sep = '&';
                }
            }
            using var req = new HttpRequestMessage(new HttpMethod(method.ToUpperInvariant()), url.ToString());
            var json = body == null ? "" : JsonSerializer.Serialize(body, body.GetType(), JsonOptions);
            // Content-Type goes on every request, even GET and DELETE.
            req.Content = new StringContent(json, new UTF8Encoding(false), "application/json");
            req.Content.Headers.ContentType!.CharSet = null;
            req.Headers.TryAddWithoutValidation("Authorization", "Bearer " + _apiKey);
            req.Headers.TryAddWithoutValidation("Accept", "application/json");
            req.Headers.TryAddWithoutValidation("User-Agent", "bmailu-dotnet/" + Version);
            if (headers != null) foreach (var h in headers) if (h.Value != null) req.Headers.TryAddWithoutValidation(h.Key, h.Value);

            HttpResponseMessage res;
            string text;
            try
            {
                res = await _http.SendAsync(req, cancellationToken).ConfigureAwait(false);
                text = await res.Content.ReadAsStringAsync().ConfigureAwait(false);
            }
            catch (HttpRequestException e) { throw new BmailuException(0, "network_error", "Could not reach Bmailu: " + e.Message, e); }
            catch (TaskCanceledException e) when (!cancellationToken.IsCancellationRequested) { throw new BmailuException(0, "network_error", "Request timed out", e); }

            using (res)
            {
                JsonElement data = default;
                var isJson = false;
                if (text.Length > 0)
                {
                    try { using var doc = JsonDocument.Parse(text); data = doc.RootElement.Clone(); isJson = true; }
                    catch (JsonException) { using var doc = JsonDocument.Parse(JsonSerializer.Serialize(text)); data = doc.RootElement.Clone(); }
                }
                var status = (int)res.StatusCode;
                if (status >= 400)
                {
                    var code = "http_" + status;
                    var message = text.Length > 0 ? text : "HTTP " + status;
                    if (isJson && data.ValueKind == JsonValueKind.Object && data.TryGetProperty("error", out var err) && err.ValueKind == JsonValueKind.Object)
                    {
                        if (err.TryGetProperty("code", out var c) && c.ValueKind == JsonValueKind.String) code = c.GetString()!;
                        if (err.TryGetProperty("message", out var m) && m.ValueKind == JsonValueKind.String) message = m.GetString()!;
                    }
                    throw new BmailuException(status, code, message);
                }
                return data;
            }
        }

        internal static string Seg(string s) => Uri.EscapeDataString(s);

        internal static IDictionary<string, string>? Idem(string? key) =>
            key == null ? null : new Dictionary<string, string> { ["Idempotency-Key"] = key };

        internal static IDictionary<string, object?>? Query(object? q)
        {
            if (q == null) return null;
            if (q is IDictionary<string, object?> d) return d;
            using var doc = JsonDocument.Parse(JsonSerializer.Serialize(q, q.GetType(), JsonOptions));
            return doc.RootElement.EnumerateObject().ToDictionary(p => p.Name, p => (object?)(p.Value.ValueKind == JsonValueKind.String ? p.Value.GetString() : p.Value.GetRawText()));
        }
    }

    /// <summary>Emails: send (with optional idempotency key), batch, get, list, update (reschedule), cancel.</summary>
    public class EmailsResource
    {
        private readonly BmailuClient _c;
        internal EmailsResource(BmailuClient c) { _c = c; }

        /// <summary>POST /emails. The same idempotency key within 24 hours returns the first email instead of sending again.</summary>
        public Task<JsonElement> SendAsync(object email, string? idempotencyKey = null, CancellationToken ct = default) =>
            _c.RequestAsync("POST", "/emails", email, null, BmailuClient.Idem(idempotencyKey), ct);
        /// <summary>POST /emails/batch with up to 100 emails.</summary>
        public Task<JsonElement> BatchAsync(IEnumerable<object> emails, string? idempotencyKey = null, CancellationToken ct = default) =>
            _c.RequestAsync("POST", "/emails/batch", emails.ToList(), null, BmailuClient.Idem(idempotencyKey), ct);
        public Task<JsonElement> GetAsync(string id, CancellationToken ct = default) =>
            _c.RequestAsync("GET", "/emails/" + BmailuClient.Seg(id), null, null, null, ct);
        /// <summary>Query (object or dictionary): limit, before, status, source, key, domain, q, days.</summary>
        public Task<JsonElement> ListAsync(object? query = null, CancellationToken ct = default) =>
            _c.RequestAsync("GET", "/emails", null, BmailuClient.Query(query), null, ct);
        /// <summary>Reschedule: new { scheduled_at = "in 10 minutes" }.</summary>
        public Task<JsonElement> UpdateAsync(string id, object changes, CancellationToken ct = default) =>
            _c.RequestAsync("PATCH", "/emails/" + BmailuClient.Seg(id), changes, null, null, ct);
        public Task<JsonElement> CancelAsync(string id, CancellationToken ct = default) =>
            _c.RequestAsync("POST", "/emails/" + BmailuClient.Seg(id) + "/cancel", null, null, null, ct);
    }

    /// <summary>Custom events for automations.</summary>
    public class EventsResource
    {
        private readonly BmailuClient _c;
        internal EventsResource(BmailuClient c) { _c = c; }

        /// <summary>new { @event = "signup", email = "ann@example.com", data = new { ... } }</summary>
        public Task<JsonElement> SendAsync(object evt, CancellationToken ct = default) => _c.RequestAsync("POST", "/events", evt, null, null, ct);
        /// <summary>Query: contact (id or email).</summary>
        public Task<JsonElement> ListAsync(object? query = null, CancellationToken ct = default) => _c.RequestAsync("GET", "/events", null, BmailuClient.Query(query), null, ct);
    }

    /// <summary>Contacts; the id may also be the contact's email address.</summary>
    public class ContactsResource
    {
        private readonly BmailuClient _c;
        internal ContactsResource(BmailuClient c) { _c = c; }

        /// <summary>Creates or updates by email.</summary>
        public Task<JsonElement> CreateAsync(object contact, CancellationToken ct = default) => _c.RequestAsync("POST", "/contacts", contact, null, null, ct);
        /// <summary>Query: segment_id, status, q, limit, position.</summary>
        public Task<JsonElement> ListAsync(object? query = null, CancellationToken ct = default) => _c.RequestAsync("GET", "/contacts", null, BmailuClient.Query(query), null, ct);
        public Task<JsonElement> GetAsync(string idOrEmail, CancellationToken ct = default) => _c.RequestAsync("GET", "/contacts/" + BmailuClient.Seg(idOrEmail), null, null, null, ct);
        public Task<JsonElement> UpdateAsync(string idOrEmail, object changes, CancellationToken ct = default) => _c.RequestAsync("PATCH", "/contacts/" + BmailuClient.Seg(idOrEmail), changes, null, null, ct);
        public Task<JsonElement> DeleteAsync(string idOrEmail, CancellationToken ct = default) => _c.RequestAsync("DELETE", "/contacts/" + BmailuClient.Seg(idOrEmail), null, null, null, ct);
        /// <summary>new { csv = "...", mode = "confirm" | "attested", ... }</summary>
        public Task<JsonElement> ImportAsync(object options, CancellationToken ct = default) => _c.RequestAsync("POST", "/contacts/import", options, null, null, ct);
    }

    /// <summary>Broadcasts (each one is approved by Bmailu before it goes out).</summary>
    public class BroadcastsResource
    {
        private readonly BmailuClient _c;
        internal BroadcastsResource(BmailuClient c) { _c = c; }
        private static string P(string id) => "/broadcasts/" + BmailuClient.Seg(id);

        public Task<JsonElement> CreateAsync(object broadcast, CancellationToken ct = default) => _c.RequestAsync("POST", "/broadcasts", broadcast, null, null, ct);
        public Task<JsonElement> ListAsync(CancellationToken ct = default) => _c.RequestAsync("GET", "/broadcasts", null, null, null, ct);
        public Task<JsonElement> GetAsync(string id, CancellationToken ct = default) => _c.RequestAsync("GET", P(id), null, null, null, ct);
        public Task<JsonElement> UpdateAsync(string id, object changes, CancellationToken ct = default) => _c.RequestAsync("PATCH", P(id), changes, null, null, ct);
        public Task<JsonElement> DeleteAsync(string id, CancellationToken ct = default) => _c.RequestAsync("DELETE", P(id), null, null, null, ct);
        /// <summary>Options: new { scheduled_at = "in 2 hours" }.</summary>
        public Task<JsonElement> SendAsync(string id, object? options = null, CancellationToken ct = default) => _c.RequestAsync("POST", P(id) + "/send", options, null, null, ct);
        public Task<JsonElement> CancelAsync(string id, CancellationToken ct = default) => _c.RequestAsync("POST", P(id) + "/cancel", null, null, null, ct);
        public Task<JsonElement> TestAsync(string id, string to, CancellationToken ct = default) => _c.RequestAsync("POST", P(id) + "/test", new { to }, null, null, ct);
        public Task<JsonElement> StatsAsync(string id, CancellationToken ct = default) => _c.RequestAsync("GET", P(id) + "/stats", null, null, null, ct);
    }

    /// <summary>Templates; the id may also be the alias.</summary>
    public class TemplatesResource
    {
        private readonly BmailuClient _c;
        internal TemplatesResource(BmailuClient c) { _c = c; }
        private static string P(string id) => "/templates/" + BmailuClient.Seg(id);

        public Task<JsonElement> CreateAsync(object template, CancellationToken ct = default) => _c.RequestAsync("POST", "/templates", template, null, null, ct);
        public Task<JsonElement> ListAsync(CancellationToken ct = default) => _c.RequestAsync("GET", "/templates", null, null, null, ct);
        public Task<JsonElement> GetAsync(string idOrAlias, CancellationToken ct = default) => _c.RequestAsync("GET", P(idOrAlias), null, null, null, ct);
        public Task<JsonElement> UpdateAsync(string idOrAlias, object changes, CancellationToken ct = default) => _c.RequestAsync("PATCH", P(idOrAlias), changes, null, null, ct);
        public Task<JsonElement> DeleteAsync(string idOrAlias, CancellationToken ct = default) => _c.RequestAsync("DELETE", P(idOrAlias), null, null, null, ct);
        public Task<JsonElement> PublishAsync(string idOrAlias, CancellationToken ct = default) => _c.RequestAsync("POST", P(idOrAlias) + "/publish", null, null, null, ct);
        public Task<JsonElement> DuplicateAsync(string idOrAlias, CancellationToken ct = default) => _c.RequestAsync("POST", P(idOrAlias) + "/duplicate", null, null, null, ct);
        public Task<JsonElement> VersionsAsync(string idOrAlias, CancellationToken ct = default) => _c.RequestAsync("GET", P(idOrAlias) + "/versions", null, null, null, ct);
        public Task<JsonElement> PublishVersionAsync(string idOrAlias, int version, CancellationToken ct = default) => _c.RequestAsync("POST", P(idOrAlias) + "/versions/" + version + "/publish", null, null, null, ct);
        /// <summary>Options: new { variables = new { ... }, version = 2 }.</summary>
        public Task<JsonElement> RenderAsync(string idOrAlias, object? options = null, CancellationToken ct = default) => _c.RequestAsync("POST", P(idOrAlias) + "/render", options, null, null, ct);
    }

    /// <summary>Webhook endpoints. To check a delivery's signature use <see cref="Webhook.Verify(string,string,IDictionary{string,string})"/>.</summary>
    public class WebhooksResource
    {
        private readonly BmailuClient _c;
        internal WebhooksResource(BmailuClient c) { _c = c; }
        private static string P(string id) => "/webhooks/" + BmailuClient.Seg(id);

        /// <summary>new { url = "https://...", events = new[] { "email.delivered" } }; the answer holds signing_secret.</summary>
        public Task<JsonElement> CreateAsync(object webhook, CancellationToken ct = default) => _c.RequestAsync("POST", "/webhooks", webhook, null, null, ct);
        public Task<JsonElement> ListAsync(CancellationToken ct = default) => _c.RequestAsync("GET", "/webhooks", null, null, null, ct);
        public Task<JsonElement> GetAsync(string id, CancellationToken ct = default) => _c.RequestAsync("GET", P(id), null, null, null, ct);
        public Task<JsonElement> UpdateAsync(string id, object changes, CancellationToken ct = default) => _c.RequestAsync("PATCH", P(id), changes, null, null, ct);
        public Task<JsonElement> DeleteAsync(string id, CancellationToken ct = default) => _c.RequestAsync("DELETE", P(id), null, null, null, ct);
        public Task<JsonElement> RotateSecretAsync(string id, CancellationToken ct = default) => _c.RequestAsync("POST", P(id) + "/rotate-secret", null, null, null, ct);
        public Task<JsonElement> TestAsync(string id, CancellationToken ct = default) => _c.RequestAsync("POST", P(id) + "/test", null, null, null, ct);
        public Task<JsonElement> DeliveriesAsync(string id, CancellationToken ct = default) => _c.RequestAsync("GET", P(id) + "/deliveries", null, null, null, ct);
        public Task<JsonElement> ReplayAsync(string deliveryId, CancellationToken ct = default) => _c.RequestAsync("POST", "/webhook-deliveries/" + BmailuClient.Seg(deliveryId) + "/replay", null, null, null, ct);
    }
}
