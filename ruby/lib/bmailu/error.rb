# frozen_string_literal: true

module Bmailu
  # An error answer from the API (or a network failure, status 0).
  class Error < StandardError
    attr_reader :status, :code, :body

    def initialize(status, code, message, body = nil)
      super("#{code}: #{message} (HTTP #{status})")
      @status = status
      @code = code
      @error_message = message
      @body = body
    end

    # The plain-words message from the API.
    def error_message
      @error_message
    end
  end

  # The webhook signature or timestamp is not valid.
  class WebhookVerificationError < StandardError; end
end
