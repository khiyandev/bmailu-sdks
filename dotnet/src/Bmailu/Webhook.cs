using System;
using System.Collections.Generic;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

namespace Bmailu
{
    /// <summary>Checks the signature of a Bmailu webhook delivery (Standard Webhooks / Svix scheme).</summary>
    public static class Webhook
    {
        /// <summary>Deliveries with a timestamp further than this from now are refused.</summary>
        public static readonly TimeSpan Tolerance = TimeSpan.FromMinutes(5);

        /// <summary>Header names are matched without regard to case. Returns the parsed body; throws BmailuException (invalid_signature).</summary>
        public static JsonElement Verify(string secret, string payload, IDictionary<string, string> headers)
        {
            string? id = null, ts = null, sig = null;
            foreach (var h in headers)
            {
                switch (h.Key.ToLowerInvariant())
                {
                    case "webhook-id": id = h.Value; break;
                    case "webhook-timestamp": ts = h.Value; break;
                    case "webhook-signature": sig = h.Value; break;
                }
            }
            Verify(secret, payload, id, ts, sig);
            using var doc = JsonDocument.Parse(payload);
            return doc.RootElement.Clone();
        }

        /// <summary>Throws BmailuException (invalid_signature) unless one of the signatures matches.</summary>
        public static void Verify(string secret, string payload, string? id, string? timestamp, string? signature)
        {
            if (secret == null || payload == null || id == null || timestamp == null || signature == null) throw Fail("Missing webhook headers");
            if (!long.TryParse(timestamp.Trim(), out var ts)) throw Fail("Bad webhook-timestamp");
            var now = DateTimeOffset.UtcNow.ToUnixTimeSeconds();
            if (Math.Abs(now - ts) > (long)Tolerance.TotalSeconds) throw Fail("Webhook timestamp is too old or too new");
            byte[] key;
            try { key = Convert.FromBase64String(secret.StartsWith("whsec_") ? secret.Substring(6) : secret); }
            catch (FormatException) { throw Fail("Bad webhook secret"); }
            byte[] expected;
            using (var hmac = new HMACSHA256(key)) expected = hmac.ComputeHash(Encoding.UTF8.GetBytes(id + "." + timestamp + "." + payload));
            foreach (var part in signature.Split(new[] { ' ' }, StringSplitOptions.RemoveEmptyEntries))
            {
                var comma = part.IndexOf(',');
                if (comma < 0 || part.Substring(0, comma) != "v1") continue;
                byte[] given;
                try { given = Convert.FromBase64String(part.Substring(comma + 1)); } catch (FormatException) { continue; }
                if (FixedTimeEquals(expected, given)) return;
            }
            throw Fail("No matching webhook signature");
        }

        private static bool FixedTimeEquals(byte[] a, byte[] b)
        {
            if (a.Length != b.Length) return false;
            var diff = 0;
            for (var i = 0; i < a.Length; i++) diff |= a[i] ^ b[i];
            return diff == 0;
        }

        private static BmailuException Fail(string message) => new BmailuException(400, "invalid_signature", message);
    }
}
