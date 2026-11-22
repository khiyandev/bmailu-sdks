# frozen_string_literal: true

require_relative "lib/bmailu/version"

Gem::Specification.new do |s|
  s.name = "bmailu"
  s.version = Bmailu::VERSION
  s.summary = "Official Ruby SDK for the Bmailu Email API"
  s.description = "Send email, manage contacts, broadcasts, templates and webhooks with the Bmailu API. Standard library only."
  s.authors = ["Bmailu"]
  s.homepage = "https://bmailu.com/"
  s.license = "MIT"
  s.required_ruby_version = ">= 2.7"
  s.files = Dir["lib/**/*.rb"] + %w[README.md LICENSE bmailu.gemspec]
  s.require_paths = ["lib"]
  s.metadata = { "documentation_uri" => "https://bmailu.com/docs/", "homepage_uri" => "https://bmailu.com/" }
end
