defmodule Bmailu do
  @moduledoc """
  Elixir SDK for the Bmailu Email API.

      client = Bmailu.new("bmk_your_api_key")
      {:ok, email} = Bmailu.Emails.send(client, %{from: "Shop <hello@your-domain.com>", to: "ann@example.com", subject: "Hello", html: "<p>It works.</p>"})

  Every call returns `{:ok, map}` or `{:error, %Bmailu.Error{}}`. Bodies are maps (atom or string keys);
  answers are maps with string keys.
  """

  @version "0.1.0"
  @default_base_url "https://app.bmailu.com/api/v1"

  defstruct [:api_key, :base_url, timeout: 60_000]

  @type t :: %__MODULE__{api_key: String.t(), base_url: String.t(), timeout: pos_integer()}
  @type result :: {:ok, term()} | {:error, Bmailu.Error.t()}

  @doc "SDK version."
  def version, do: @version

  @doc """
  A client. The key defaults to `BMAILU_API_KEY`; options: `:base_url` (default `BMAILU_BASE_URL` or
  #{@default_base_url}) and `:timeout` in milliseconds.
  """
  @spec new(String.t() | nil, keyword()) :: t()
  def new(api_key \\ nil, opts \\ []) do
    key = api_key || System.get_env("BMAILU_API_KEY")
    if key in [nil, ""], do: raise(ArgumentError, "Missing API key: pass it or set BMAILU_API_KEY")

    base =
      Keyword.get(opts, :base_url) ||
        case System.get_env("BMAILU_BASE_URL") do
          nil -> @default_base_url
          "" -> @default_base_url
          url -> url
        end

    %__MODULE__{api_key: key, base_url: String.trim_trailing(base, "/"), timeout: Keyword.get(opts, :timeout, 60_000)}
  end

  @doc """
  Any API call: method (`:get`, `:post`, `:patch`, `:delete`, ...), path under /api/v1 (e.g. `"/domains"`),
  optional body. Options: `:query` (keyword or map), `:headers` (list of `{name, value}`).
  """
  @spec request(t(), atom() | String.t(), String.t(), term(), keyword()) :: result()
  def request(%__MODULE__{} = client, method, path, body \\ nil, opts \\ []) do
    method = method |> to_string() |> String.downcase() |> String.to_existing_atom()
    path = if String.starts_with?(path, "/"), do: path, else: "/" <> path

    url =
      case Keyword.get(opts, :query) do
        nil -> client.base_url <> path
        q when q == [] or q == %{} -> client.base_url <> path
        q -> client.base_url <> path <> "?" <> URI.encode_query(Enum.reject(q, fn {_, v} -> is_nil(v) end))
      end

    headers =
      [
        {"authorization", "Bearer " <> client.api_key},
        {"accept", "application/json"},
        {"user-agent", "bmailu-elixir/" <> @version}
      ] ++ Enum.map(Keyword.get(opts, :headers, []), fn {k, v} -> {to_string(k), to_string(v)} end)

    headers = Enum.map(headers, fn {k, v} -> {String.to_charlist(k), String.to_charlist(v)} end)
    payload = if is_nil(body), do: "", else: Bmailu.JSON.encode!(body)

    # Content-Type goes on every request, even GET and DELETE.
    req =
      if method in [:get, :head, :options] do
        {String.to_charlist(url), [{~c"content-type", ~c"application/json"} | headers]}
      else
        {String.to_charlist(url), headers, ~c"application/json", payload}
      end

    http_opts = [timeout: client.timeout, connect_timeout: 30_000] ++ ssl_opts(url)

    case :httpc.request(method, req, http_opts, body_format: :binary) do
      {:ok, {{_, status, _}, _headers, resp}} -> handle(status, resp)
      {:error, reason} -> {:error, %Bmailu.Error{status: 0, code: "network_error", message: "Could not reach Bmailu: " <> inspect(reason)}}
    end
  end

  defp handle(status, resp) do
    data =
      case resp do
        "" ->
          nil

        text ->
          case Bmailu.JSON.decode(text) do
            {:ok, v} -> v
            _ -> text
          end
      end

    if status >= 400 do
      {code, message} =
        case data do
          %{"error" => %{} = e} -> {e["code"] || "http_#{status}", e["message"] || resp}
          _ -> {"http_#{status}", if(resp == "", do: "HTTP #{status}", else: resp)}
        end

      {:error, %Bmailu.Error{status: status, code: code, message: message}}
    else
      {:ok, data}
    end
  end

  defp ssl_opts("https://" <> _) do
    cacerts =
      if function_exported?(:public_key, :cacerts_get, 0), do: [cacerts: :public_key.cacerts_get()], else: []

    [
      ssl:
        [verify: :verify_peer, depth: 4, customize_hostname_check: [match_fun: :public_key.pkix_verify_hostname_match_fun(:https)]] ++
          cacerts
    ]
  end

  defp ssl_opts(_), do: []

  @doc false
  def seg(s), do: URI.encode(to_string(s), &URI.char_unreserved?/1)

  @doc false
  def idem(opts) do
    case Keyword.get(opts, :idempotency_key) do
      nil -> []
      key -> [headers: [{"idempotency-key", key}]]
    end
  end
end
