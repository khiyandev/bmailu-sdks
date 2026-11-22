defmodule BmailuTest do
  # Runs against the shared local API (sdks/test-server.js), started by test.sh.
  use ExUnit.Case, async: true

  @from "Shop <hello@shop.test>"
  @to "delivered@simulator.bmailu.com"

  defp env(name), do: System.get_env(name) || flunk("#{name} is not set; run test.sh")
  defp client, do: Bmailu.new(env("BMAILU_TEST_KEY"), base_url: env("BMAILU_TEST_URL"))

  test "send, get, list and the idempotency key" do
    c = client()
    key = "elixir-#{System.unique_integer([:positive])}-#{System.os_time()}"
    email = %{from: @from, to: @to, subject: "Idempotent", html: "<p>Hello from Elixir</p>"}
    {:ok, %{"id" => id}} = Bmailu.Emails.send(c, email, idempotency_key: key)
    {:ok, %{"id" => ^id}} = Bmailu.Emails.send(c, email, idempotency_key: key)
    assert {:ok, %{"id" => ^id, "subject" => "Idempotent"}} = Bmailu.Emails.get(c, id)
    assert {:ok, %{"object" => "list", "data" => [_ | _]}} = Bmailu.Emails.list(c, limit: 5)
  end

  test "batch of two" do
    {:ok, %{"data" => data}} =
      Bmailu.Emails.batch(client(), [
        %{from: @from, to: @to, subject: "Batch 1", text: "one"},
        %{"from" => @from, "to" => [@to], "subject" => "Batch 2", "text" => "two"}
      ])

    assert [%{"id" => _}, %{"id" => _}] = data
  end

  test "schedule, reschedule and cancel" do
    c = client()
    # Test addresses are sent at once, so use a real-looking one (the test server stubs delivery).
    {:ok, %{"id" => id}} =
      Bmailu.Emails.send(c, %{from: @from, to: "scheduled.elixir@example.com", subject: "Later", text: "x", scheduled_at: "in 1 hour"})

    assert {:ok, _} = Bmailu.Emails.update(c, id, %{scheduled_at: "in 2 hours"})
    assert {:ok, _} = Bmailu.Emails.cancel(c, id)
    assert {:ok, %{"last_event" => "canceled"}} = Bmailu.Emails.get(c, id)
  end

  test "contact and event" do
    c = client()
    addr = "ann.elixir@example.com"
    {:ok, %{"object" => "contact", "id" => cid}} = Bmailu.Contacts.create(c, %{email: addr, first_name: "Ann"})
    assert {:ok, %{"email" => ^addr}} = Bmailu.Contacts.get(c, addr)

    assert {:ok, %{"object" => "event", "contact_id" => ^cid}} =
             Bmailu.Events.send(c, %{event: "signup", email: addr, data: %{plan: "free"}})
  end

  test "template: create, publish, send with it" do
    c = client()

    {:ok, %{"status" => "draft"}} =
      Bmailu.Templates.create(c, %{
        name: "Elixir welcome",
        alias: "elixir-welcome",
        subject: "Hi {{NAME}}",
        html: "<p>Hi {{NAME}}</p>",
        variables: [%{key: "NAME", type: "string", fallback_value: "there"}]
      })

    assert {:ok, %{"status" => "published"}} = Bmailu.Templates.publish(c, "elixir-welcome")
    {:ok, %{"id" => id}} = Bmailu.Emails.send(c, %{from: @from, to: @to, template: %{id: "elixir-welcome", variables: %{NAME: "Bo"}}})
    assert {:ok, %{"subject" => "Hi Bo"}} = Bmailu.Emails.get(c, id)
  end

  test "broadcast draft" do
    c = client()
    {:ok, %{"status" => "draft", "id" => id}} = Bmailu.Broadcasts.create(c, %{name: "Elixir broadcast"})
    assert {:ok, %{"object" => "broadcast"}} = Bmailu.Broadcasts.get(c, id)
    assert {:ok, _} = Bmailu.Broadcasts.delete(c, id)
  end

  test "list webhooks and the generic request" do
    c = client()
    assert {:ok, %{"object" => "list"}} = Bmailu.Webhooks.list(c)
    assert {:ok, %{}} = Bmailu.request(c, :get, "/me")
  end

  test "bad from domain is an error" do
    assert {:error, %Bmailu.Error{code: "domain_not_verified", status: status}} =
             Bmailu.Emails.send(client(), %{from: "Shop <hello@not-mine.test>", to: @to, subject: "x", text: "x"})

    assert status >= 400
  end

  test "the sending key is refused on GET /domains" do
    sending = Bmailu.new(env("BMAILU_TEST_SENDING_KEY"), base_url: env("BMAILU_TEST_URL"))
    assert {:error, %Bmailu.Error{code: "restricted_api_key", status: 403}} = Bmailu.request(sending, :get, "/domains")
  end

  test "webhook verification against the panel's sign()" do
    secret = env("WH_SECRET")
    body = env("WH_BODY")
    headers = [{"Webhook-Id", env("WH_ID")}, {"webhook-timestamp", env("WH_TS")}, {"webhook-signature", env("WH_SIG")}]
    assert {:ok, %{"type" => "email.delivered"}} = Bmailu.Webhook.verify(secret, body, headers)
    assert {:error, %Bmailu.Error{code: "invalid_signature"}} = Bmailu.Webhook.verify(secret, String.replace(body, "e1", "e2"), headers)
    assert {:error, _} = Bmailu.Webhook.verify_signature(secret, body, env("WH_ID"), "1000000000", env("WH_SIG"))
    assert {:error, _} = Bmailu.Webhook.verify("whsec_AQID", body, Map.new(headers))
  end
end
