# frozen_string_literal: true

require "json"
require "net/http"
require "uri"

require_relative "bmailu/version"
require_relative "bmailu/error"
require_relative "bmailu/resource"
require_relative "bmailu/resources"
require_relative "bmailu/webhook"

# Bmailu Email API client. Standard library only (net/http + json).
#
#   bmailu = Bmailu::Client.new("bmk_...")
#   bmailu.emails.send({ from: "Shop <hello@shop.com>", to: "ann@example.com", subject: "Hi", html: "<p>Hi</p>" })
module Bmailu
  DEFAULT_BASE_URL = "https://app.bmailu.com/api/v1"

  RESOURCES = {
    me: Resources::Me, domains: Resources::Domains, mailboxes: Resources::Mailboxes, aliases: Resources::Aliases,
    api_keys: Resources::ApiKeys, sending_domains: Resources::SendingDomains, emails: Resources::Emails,
    webhooks: Resources::Webhooks, suppressions: Resources::Suppressions, metrics: Resources::Metrics,
    logs: Resources::Logs, inbox: Resources::Inbox, templates: Resources::Templates, contacts: Resources::Contacts,
    segments: Resources::Segments, topics: Resources::Topics, broadcasts: Resources::Broadcasts,
    events: Resources::Events, automations: Resources::Automations
  }.freeze

  # Shortcut: Bmailu.new(key) is Bmailu::Client.new(key).
  def self.new(*args, **opts)
    Client.new(*args, **opts)
  end

  class Client
    attr_reader :base_url

    RESOURCES.each_key { |name| attr_reader name }

    def initialize(api_key = nil, base_url: nil, timeout: 30)
      @api_key = api_key || ENV["BMAILU_API_KEY"]
      raise ArgumentError, "Missing API key: pass it to Bmailu::Client.new or set BMAILU_API_KEY" if @api_key.nil? || @api_key.empty?

      @base_url = (base_url || ENV["BMAILU_BASE_URL"] || DEFAULT_BASE_URL).sub(%r{/+\z}, "")
      @timeout = timeout
      RESOURCES.each { |name, klass| instance_variable_set("@#{name}", klass.new(self)) }
    end

    # Call any endpoint. Returns parsed JSON (string keys), a String for text answers and files.
    def request(method, path, body: nil, query: nil, headers: nil)
      uri = URI(@base_url + (path.start_with?("/") ? path : "/#{path}") + encode_query(query))
      req = Net::HTTPGenericRequest.new(method.to_s.upcase, !body.nil?, true, uri.request_uri)
      req["Authorization"] = "Bearer #{@api_key}"
      req["Content-Type"] = "application/json"
      req["Accept"] = "application/json"
      req["User-Agent"] = "bmailu-ruby/#{VERSION}"
      (headers || {}).each { |k, v| req[k] = v unless v.nil? }
      req.body = JSON.generate(body) unless body.nil?
      res = Net::HTTP.start(uri.host, uri.port, use_ssl: uri.scheme == "https",
                                                open_timeout: @timeout, read_timeout: @timeout) { |http| http.request(req) }
      handle(res)
    rescue SystemCallError, SocketError, Timeout::Error, IOError, OpenSSL::SSL::SSLError => e
      raise Error.new(0, "connection_error", e.message)
    end

    private

    def encode_query(query)
      return "" if query.nil? || query.empty?

      pairs = query.reject { |_, v| v.nil? }.map { |k, v| [k.to_s, v.to_s] }
      pairs.empty? ? "" : "?#{URI.encode_www_form(pairs)}"
    end

    def handle(res)
      raw = res.body.to_s
      type = res["Content-Type"].to_s.downcase
      parsed = if raw.empty? then nil
               elsif type.include?("json") then JSON.parse(raw)
               elsif type.start_with?("text/") then raw.dup.force_encoding("UTF-8")
               else raw
               end
      status = res.code.to_i
      return parsed if status >= 200 && status < 300

      err = parsed.is_a?(Hash) && parsed["error"].is_a?(Hash) ? parsed["error"] : nil
      raise Error.new(status, err["code"].to_s, err["message"].to_s, parsed) if err

      raise Error.new(status, "http_error", raw[0, 500], parsed)
    end
  end
end
