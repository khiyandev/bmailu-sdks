using System;
using System.Collections.Generic;
using System.Text.Json;
using System.Threading.Tasks;
using Bmailu;
using Xunit;

// Runs against the shared local API (sdks/test-server.js), started by test.sh.
public class BmailuTests
{
    const string From = "Shop <hello@shop.test>";
    const string To = "delivered@simulator.bmailu.com";

    static string Env(string name) => Environment.GetEnvironmentVariable(name) ?? throw new Exception(name + " is not set; run test.sh");
    static BmailuClient Client() => new BmailuClient(Env("BMAILU_TEST_KEY"), Env("BMAILU_TEST_URL"));
    static string Str(JsonElement e, string name) => e.GetProperty(name).GetString()!;

    [Fact]
    public async Task SendGetAndIdempotency()
    {
        var c = Client();
        var key = "dotnet-" + Guid.NewGuid();
        var email = new { from = From, to = To, subject = "Idempotent", html = "<p>Hello from .NET</p>" };
        var a = await c.Emails.SendAsync(email, key);
        var b = await c.Emails.SendAsync(email, key);
        Assert.Equal(Str(a, "id"), Str(b, "id"));
        var got = await c.Emails.GetAsync(Str(a, "id"));
        Assert.Equal("Idempotent", Str(got, "subject"));
        var list = await c.Emails.ListAsync(new { limit = 5 });
        Assert.Equal("list", Str(list, "object"));
        Assert.True(list.GetProperty("data").GetArrayLength() > 0);
    }

    [Fact]
    public async Task BatchOfTwo()
    {
        var r = await Client().Emails.BatchAsync(new object[] {
            new { from = From, to = To, subject = "Batch 1", text = "one" },
            new { from = From, to = new[] { To }, subject = "Batch 2", text = "two" },
        });
        Assert.Equal(2, r.GetProperty("data").GetArrayLength());
        Assert.False(string.IsNullOrEmpty(Str(r.GetProperty("data")[0], "id")));
    }

    [Fact]
    public async Task ScheduleAndCancel()
    {
        var c = Client();
        // Test addresses are sent at once, so use a real-looking one (the test server stubs delivery).
        var e = await c.Emails.SendAsync(new { from = From, to = "scheduled.dotnet@example.com", subject = "Later", text = "x", scheduled_at = "in 1 hour" });
        var id = Str(e, "id");
        await c.Emails.UpdateAsync(id, new { scheduled_at = "in 2 hours" });
        await c.Emails.CancelAsync(id);
        Assert.Equal("canceled", Str(await c.Emails.GetAsync(id), "last_event"));
    }

    [Fact]
    public async Task ContactAndEvent()
    {
        var c = Client();
        var addr = "ann.dotnet@example.com";
        var contact = await c.Contacts.CreateAsync(new { email = addr, first_name = "Ann" });
        Assert.Equal("contact", Str(contact, "object"));
        Assert.Equal(addr, Str(await c.Contacts.GetAsync(addr), "email"));
        var ev = await c.Events.SendAsync(new Dictionary<string, object> { ["event"] = "signup", ["email"] = addr, ["data"] = new { plan = "free" } });
        Assert.Equal(Str(contact, "id"), Str(ev, "contact_id"));
    }

    [Fact]
    public async Task TemplatePublishAndSend()
    {
        var c = Client();
        var t = await c.Templates.CreateAsync(new
        {
            name = "Dotnet welcome", alias = "dotnet-welcome", subject = "Hi {{NAME}}", html = "<p>Hi {{NAME}}</p>",
            variables = new[] { new { key = "NAME", type = "string", fallback_value = "there" } },
        });
        Assert.Equal("draft", Str(t, "status"));
        Assert.Equal("published", Str(await c.Templates.PublishAsync("dotnet-welcome"), "status"));
        var sent = await c.Emails.SendAsync(new { from = From, to = To, template = new { id = "dotnet-welcome", variables = new { NAME = "Bo" } } });
        Assert.Equal("Hi Bo", Str(await c.Emails.GetAsync(Str(sent, "id")), "subject"));
    }

    [Fact]
    public async Task BroadcastDraft()
    {
        var c = Client();
        var b = await c.Broadcasts.CreateAsync(new { name = "Dotnet broadcast" });
        Assert.Equal("draft", Str(b, "status"));
        Assert.Equal("broadcast", Str(await c.Broadcasts.GetAsync(Str(b, "id")), "object"));
        await c.Broadcasts.DeleteAsync(Str(b, "id"));
    }

    [Fact]
    public async Task ListWebhooksAndGenericRequest()
    {
        var c = Client();
        Assert.Equal("list", Str(await c.Webhooks.ListAsync(), "object"));
        var me = await c.RequestAsync("GET", "/me");
        Assert.Equal(JsonValueKind.Object, me.ValueKind);
    }

    [Fact]
    public async Task ErrorBadFromDomain()
    {
        var e = await Assert.ThrowsAsync<BmailuException>(() =>
            Client().Emails.SendAsync(new { from = "Shop <hello@not-mine.test>", to = To, subject = "x", text = "x" }));
        Assert.Equal("domain_not_verified", e.Code);
        Assert.True(e.StatusCode >= 400);
    }

    [Fact]
    public async Task SendingKeyIsRestricted()
    {
        var sending = new BmailuClient(Env("BMAILU_TEST_SENDING_KEY"), Env("BMAILU_TEST_URL"));
        var e = await Assert.ThrowsAsync<BmailuException>(() => sending.RequestAsync("GET", "/domains"));
        Assert.Equal("restricted_api_key", e.Code);
        Assert.Equal(403, e.StatusCode);
    }

    [Fact]
    public void WebhookVerification()
    {
        var secret = Env("WH_SECRET");
        var body = Env("WH_BODY");
        var headers = new Dictionary<string, string> { ["Webhook-Id"] = Env("WH_ID"), ["webhook-timestamp"] = Env("WH_TS"), ["webhook-signature"] = Env("WH_SIG") };
        var ev = Webhook.Verify(secret, body, headers);
        Assert.Equal("email.delivered", Str(ev, "type"));
        var tampered = Assert.Throws<BmailuException>(() => Webhook.Verify(secret, body.Replace("e1", "e2"), headers));
        Assert.Equal("invalid_signature", tampered.Code);
        Assert.Throws<BmailuException>(() => Webhook.Verify(secret, body, Env("WH_ID"), "1000000000", Env("WH_SIG")));
        Assert.Throws<BmailuException>(() => Webhook.Verify("whsec_" + Convert.ToBase64String(new byte[] { 1, 2, 3 }), body, headers));
    }
}
