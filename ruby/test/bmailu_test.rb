# frozen_string_literal: true

# End-to-end tests against sdks/test-server.js (started by test.sh, config in BMAILU_TEST_JSON).
require "minitest/autorun"
require "base64"
require "json"
require "open3"
require "securerandom"
require "bmailu"

CFG = JSON.parse(ENV.fetch("BMAILU_TEST_JSON"))
FROM = "Shop <hello@shop.test>"
TO = "delivered@simulator.bmailu.com"

def panel_sign(secret, id, ts, body)
  js = "process.stdout.write(require(process.argv[1]).sign(process.argv[2], process.argv[3], process.argv[4], process.argv[5]))"
  path = File.expand_path("../../../panel/src/webhook-routes.js", __dir__)
  out, status = Open3.capture2("node", "-e", js, path, secret, id, ts.to_s, body)
  raise "node sign failed" unless status.success?

  out
end

class BmailuTest < Minitest::Test
  def setup
    @b = Bmailu::Client.new(CFG["key"], base_url: CFG["api"])
  end

  def test_send_idempotent_get
    msg = { from: FROM, to: TO, subject: "Hello", html: "<p>Hello</p>" }
    a = @b.emails.send(msg, idempotency_key: "rb-idem-1")
    c = @b.emails.send(msg, idempotency_key: "rb-idem-1")
    assert_equal a["id"], c["id"]
    got = @b.emails.get(a["id"])
    assert_equal "email", got["object"]
    assert_equal "Hello", got["subject"]
    assert @b.emails.list(limit: 5).key?("data")
    assert @b.emails.export.start_with?("id,")
  end

  def test_batch
    msg = { from: FROM, to: TO, subject: "Batch", text: "Hi" }
    assert_equal 2, @b.emails.batch([msg, msg])["data"].size
  end

  def test_contact_and_event
    c = @b.contacts.create(email: "rb-ann@example.com", first_name: "Ann")
    assert_equal "rb-ann@example.com", c["email"]
    e = @b.events.send(event: "signed_up", email: "rb-ann@example.com")
    assert_equal "event", e["object"]
    assert_equal c["id"], e["contact_id"]
  end

  def test_template_publish_send
    t = @b.templates.create(name: "Welcome rb", alias: "welcome-rb", subject: "Hi {{name}}",
                            html: "<p>Hi {{name}}</p>", variables: [{ key: "name", fallback_value: "there" }])
    assert_equal 1, @b.templates.publish(t["id"])["published_version"]
    assert_includes @b.templates.preview("welcome-rb"), "Hi there"
    assert_kind_of Hash, @b.templates.render("welcome-rb")
    r = @b.emails.send({ from: FROM, to: TO, template: { id: "welcome-rb", variables: { name: "Ann" } } })
    assert_equal "Hi Ann", @b.emails.get(r["id"])["subject"]
  end

  def test_webhooks_list
    r = @b.webhooks.list
    assert_equal "list", r["object"]
    assert_kind_of Array, r["data"]
  end

  def test_error_domain_not_verified
    e = assert_raises(Bmailu::Error) { @b.emails.send({ from: "x@nope.test", to: TO, subject: "x", text: "x" }) }
    assert_equal "domain_not_verified", e.code
    assert_equal 403, e.status
  end

  def test_sending_key_restricted
    s = Bmailu::Client.new(CFG["sending_key"], base_url: CFG["api"])
    e = assert_raises(Bmailu::Error) { s.domains.list }
    assert_equal "restricted_api_key", e.code
  end

  def test_generic_request
    assert_kind_of Hash, @b.request("GET", "/me")
    e = assert_raises(Bmailu::Error) { @b.suppressions.delete("nobody@example.com") }
    assert_equal "not_found", e.code
  end

  def test_webhook_verify
    secret = "whsec_#{Base64.strict_encode64(SecureRandom.random_bytes(24))}"
    body = JSON.generate(type: "email.delivered", data: { email_id: "e1" })
    ts = Time.now.to_i
    sig = panel_sign(secret, "msg_1", ts, body)
    headers = { "Webhook-Id" => "msg_1", "webhook-timestamp" => ts.to_s, "webhook-signature" => "v1,bogus #{sig}" }
    assert_equal "email.delivered", Bmailu::Webhook.verify(body, headers, secret)["type"]
    assert_raises(Bmailu::WebhookVerificationError) { Bmailu::Webhook.verify(body.sub("e1", "e2"), headers, secret) }
    assert_raises(Bmailu::WebhookVerificationError) { Bmailu::Webhook.verify(body, headers, secret, now: ts + 301) }
  end

  # Every operation in the served OpenAPI spec has a method (the client's request is swapped for a recorder).
  def test_every_operation
    spec = JSON.parse(Net::HTTP.get(URI("#{CFG['api']}/openapi.json")))
    want = spec["paths"].flat_map do |p, ops|
      ops.keys.select { |m| %w[get post put patch delete].include?(m) }.map { |m| "#{m.upcase} #{p.gsub(/\{[^}]+\}/, 'X')}" }
    end
    seen = []
    b = Bmailu::Client.new("bmk_test", base_url: "http://localhost:1")
    b.define_singleton_method(:request) { |method, path, **_| seen << "#{method} #{path}" }
    Bmailu::RESOURCES.each_key do |name|
      res = b.public_send(name)
      res.class.instance_methods(false).each do |m|
        args = res.method(m).parameters.select { |kind, _| kind == :req }.map { "X" }
        res.public_send(m, *args)
      end
    end
    assert_equal [], want - seen, "spec operations without a method"
    assert_equal [], seen.uniq - want, "methods not in the spec"
  end
end
