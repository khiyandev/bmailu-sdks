defmodule Bmailu.MixProject do
  use Mix.Project

  @version "0.1.0"

  def project do
    [
      app: :bmailu,
      version: @version,
      elixir: "~> 1.14",
      start_permanent: Mix.env() == :prod,
      description: "Elixir SDK for the Bmailu Email API",
      package: [
        licenses: ["MIT"],
        links: %{"Docs" => "https://bmailu.com/docs/", "GitHub" => "https://github.com/khiyandev/bmailu-sdks"}
      ],
      docs: [main: "readme", extras: ["README.md"]],
      deps: []
    ]
  end

  # No dependencies: HTTP is OTP's :httpc. JSON is Elixir's JSON module (1.18+), or Jason if your app has it.
  def application do
    [extra_applications: [:inets, :ssl, :public_key, :crypto]]
  end
end
