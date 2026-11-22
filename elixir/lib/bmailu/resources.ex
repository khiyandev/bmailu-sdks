defmodule Bmailu.Emails do
  @moduledoc "Emails: send (with optional `idempotency_key:`), batch, get, list, update (reschedule), cancel."
  import Bmailu, only: [request: 5, request: 4, request: 3, seg: 1, idem: 1]

  @doc "POST /emails. Option `idempotency_key:` — the same key within 24 hours returns the first email instead of sending again."
  def send(client, email, opts \\ []), do: request(client, :post, "/emails", email, idem(opts))
  @doc "POST /emails/batch with a list of up to 100 emails. Option `idempotency_key:`."
  def batch(client, emails, opts \\ []) when is_list(emails), do: request(client, :post, "/emails/batch", emails, idem(opts))
  def get(client, id), do: request(client, :get, "/emails/" <> seg(id))
  @doc "Query: limit, before, status, source, key, domain, q, days."
  def list(client, query \\ []), do: request(client, :get, "/emails", nil, query: query)
  @doc "Reschedule: `%{scheduled_at: \"in 10 minutes\"}`."
  def update(client, id, changes), do: request(client, :patch, "/emails/" <> seg(id), changes)
  def cancel(client, id), do: request(client, :post, "/emails/" <> seg(id) <> "/cancel")
end

defmodule Bmailu.Events do
  @moduledoc "Custom events for automations."
  import Bmailu, only: [request: 5, request: 4]

  @doc "`%{event: \"signup\", email: \"ann@example.com\", data: %{...}}`."
  def send(client, event), do: request(client, :post, "/events", event)
  @doc "Query: contact (id or email)."
  def list(client, query \\ []), do: request(client, :get, "/events", nil, query: query)
end

defmodule Bmailu.Contacts do
  @moduledoc "Contacts; the id may also be the contact's email address."
  import Bmailu, only: [request: 5, request: 4, request: 3, seg: 1]

  @doc "Creates or updates by email."
  def create(client, contact), do: request(client, :post, "/contacts", contact)
  @doc "Query: segment_id, status, q, limit, position."
  def list(client, query \\ []), do: request(client, :get, "/contacts", nil, query: query)
  def get(client, id_or_email), do: request(client, :get, "/contacts/" <> seg(id_or_email))
  def update(client, id_or_email, changes), do: request(client, :patch, "/contacts/" <> seg(id_or_email), changes)
  def delete(client, id_or_email), do: request(client, :delete, "/contacts/" <> seg(id_or_email))
  @doc "`%{csv: \"...\", mode: \"confirm\" | \"attested\", ...}`."
  def import(client, options), do: request(client, :post, "/contacts/import", options)
end

defmodule Bmailu.Broadcasts do
  @moduledoc "Broadcasts (each one is approved by Bmailu before it goes out)."
  import Bmailu, only: [request: 4, request: 3, seg: 1]

  def create(client, broadcast), do: request(client, :post, "/broadcasts", broadcast)
  def list(client), do: request(client, :get, "/broadcasts")
  def get(client, id), do: request(client, :get, "/broadcasts/" <> seg(id))
  def update(client, id, changes), do: request(client, :patch, "/broadcasts/" <> seg(id), changes)
  def delete(client, id), do: request(client, :delete, "/broadcasts/" <> seg(id))
  @doc "Options: `%{scheduled_at: \"in 2 hours\"}`."
  def send(client, id, options \\ nil), do: request(client, :post, "/broadcasts/" <> seg(id) <> "/send", options)
  def cancel(client, id), do: request(client, :post, "/broadcasts/" <> seg(id) <> "/cancel")
  def test(client, id, to), do: request(client, :post, "/broadcasts/" <> seg(id) <> "/test", %{to: to})
  def stats(client, id), do: request(client, :get, "/broadcasts/" <> seg(id) <> "/stats")
end

defmodule Bmailu.Templates do
  @moduledoc "Templates; the id may also be the alias."
  import Bmailu, only: [request: 4, request: 3, seg: 1]

  def create(client, template), do: request(client, :post, "/templates", template)
  def list(client), do: request(client, :get, "/templates")
  def get(client, id), do: request(client, :get, "/templates/" <> seg(id))
  def update(client, id, changes), do: request(client, :patch, "/templates/" <> seg(id), changes)
  def delete(client, id), do: request(client, :delete, "/templates/" <> seg(id))
  def publish(client, id), do: request(client, :post, "/templates/" <> seg(id) <> "/publish")
  def duplicate(client, id), do: request(client, :post, "/templates/" <> seg(id) <> "/duplicate")
  def versions(client, id), do: request(client, :get, "/templates/" <> seg(id) <> "/versions")

  def publish_version(client, id, version) when is_integer(version),
    do: request(client, :post, "/templates/" <> seg(id) <> "/versions/#{version}/publish")

  @doc "Options: `%{variables: %{...}, version: 2}`."
  def render(client, id, options \\ nil), do: request(client, :post, "/templates/" <> seg(id) <> "/render", options)
end

defmodule Bmailu.Webhooks do
  @moduledoc "Webhook endpoints. To check a delivery's signature use `Bmailu.Webhook.verify/3`."
  import Bmailu, only: [request: 4, request: 3, seg: 1]

  @doc "`%{url: \"https://...\", events: [\"email.delivered\"]}`; the answer holds `signing_secret`."
  def create(client, webhook), do: request(client, :post, "/webhooks", webhook)
  def list(client), do: request(client, :get, "/webhooks")
  def get(client, id), do: request(client, :get, "/webhooks/" <> seg(id))
  def update(client, id, changes), do: request(client, :patch, "/webhooks/" <> seg(id), changes)
  def delete(client, id), do: request(client, :delete, "/webhooks/" <> seg(id))
  def rotate_secret(client, id), do: request(client, :post, "/webhooks/" <> seg(id) <> "/rotate-secret")
  def test(client, id), do: request(client, :post, "/webhooks/" <> seg(id) <> "/test")
  def deliveries(client, id), do: request(client, :get, "/webhooks/" <> seg(id) <> "/deliveries")
  def replay(client, delivery_id), do: request(client, :post, "/webhook-deliveries/" <> seg(delivery_id) <> "/replay")
end
