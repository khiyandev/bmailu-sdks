# frozen_string_literal: true

require "base64"
require "json"
require "openssl"

module Bmailu
  # Verify Bmailu webhooks (Standard Webhooks / Svix scheme).
  module Webhook
    module_function

    # Checks the signature and returns the parsed JSON body (string keys).
    # payload: the raw request body. headers: a Hash of request headers (any case).
    # secret: the endpoint's signing secret (whsec_...). Raises Bmailu::WebhookVerificationError.
    def verify(payload, headers, secret, tolerance: 300, now: nil)
      h = {}
      headers.each { |k, v| h[k.to_s.downcase.tr("_", "-").sub(/\Ahttp-/, "")] = Array(v).first.to_s }
      msg_id = h["webhook-id"]
      ts = h["webhook-timestamp"]
      sigs = h["webhook-signature"]
      [["webhook-id", msg_id], ["webhook-timestamp", ts], ["webhook-signature", sigs]].each do |name, value|
        raise WebhookVerificationError, "Missing #{name} header" if value.nil? || value.empty?
      end
      raise WebhookVerificationError, "Bad webhook-timestamp" unless ts.match?(/\A\d+\z/)
      raise WebhookVerificationError, "Timestamp is more than 5 minutes away" if ((now || Time.now.to_i) - ts.to_i).abs > tolerance

      key = Base64.strict_decode64(secret.sub(/\Awhsec_/, ""))
      body = payload.to_s
      expected = Base64.strict_encode64(OpenSSL::HMAC.digest("SHA256", key, "#{msg_id}.#{ts}.#{body}"))
      ok = sigs.split(" ").any? do |part|
        version, sig = part.split(",", 2)
        version == "v1" && sig && secure_compare(sig, expected)
      end
      raise WebhookVerificationError, "No matching signature" unless ok

      body.empty? ? nil : JSON.parse(body)
    rescue ArgumentError
      raise WebhookVerificationError, "Bad secret"
    end

    def secure_compare(a, b)
      return false unless a.bytesize == b.bytesize

      r = 0
      a.bytes.zip(b.bytes) { |x, y| r |= x ^ y }
      r.zero?
    end
  end
end
