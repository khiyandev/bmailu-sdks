# frozen_string_literal: true

require "erb"

module Bmailu
  module Resources
    # Base of every resource: holds the client and builds paths.
    class Resource
      def initialize(client)
        @client = client
      end

      private

      # One URL path segment, fully encoded.
      def seg(value)
        ERB::Util.url_encode(value.to_s)
      end

      def idem(key)
        key.nil? || key.to_s.empty? ? {} : { "Idempotency-Key" => key.to_s }
      end
    end
  end
end
