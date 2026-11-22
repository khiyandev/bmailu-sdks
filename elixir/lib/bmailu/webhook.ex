defmodule Bmailu.Webhook do
  @moduledoc """
  Checks the signature of a Bmailu webhook delivery (Standard Webhooks / Svix scheme).

      {:ok, event} = Bmailu.Webhook.verify(secret, raw_body, conn.req_headers)
  """

  @tolerance 5 * 60

  @doc """
  `headers` is a map or a list of `{name, value}`; names are matched without regard to case.
  Returns `{:ok, decoded_body}` or `{:error, %Bmailu.Error{code: "invalid_signature"}}`.
  Timestamps more than 5 minutes away are refused.
  """
  def verify(secret, payload, headers) do
    h = Map.new(headers, fn {k, v} -> {String.downcase(to_string(k)), to_string(v)} end)

    with {:ok, id} <- fetch(h, "webhook-id"),
         {:ok, ts} <- fetch(h, "webhook-timestamp"),
         {:ok, sig} <- fetch(h, "webhook-signature"),
         :ok <- verify_signature(secret, payload, id, ts, sig) do
      case Bmailu.JSON.decode(payload) do
        {:ok, v} -> {:ok, v}
        _ -> fail("Webhook body is not JSON")
      end
    end
  end

  @doc "Checks one delivery given the three header values. `:ok` when one of the signatures matches."
  def verify_signature(secret, payload, id, timestamp, signature) do
    now = System.os_time(:second)

    with {ts, ""} <- Integer.parse(String.trim(timestamp)),
         true <- abs(now - ts) <= @tolerance || :too_old,
         {:ok, key} <- Base.decode64(String.replace_prefix(secret, "whsec_", ""), padding: false) do
      expected = :crypto.mac(:hmac, :sha256, key, "#{id}.#{timestamp}.#{payload}")

      matched =
        signature
        |> String.split(" ", trim: true)
        |> Enum.any?(fn
          "v1," <> b64 ->
            case Base.decode64(b64, padding: false) do
              {:ok, given} -> secure_equal?(expected, given)
              :error -> false
            end

          _ ->
            false
        end)

      if matched, do: :ok, else: fail("No matching webhook signature")
    else
      :too_old -> fail("Webhook timestamp is too old or too new")
      :error -> fail("Bad webhook secret")
      _ -> fail("Bad webhook-timestamp")
    end
  end

  defp fetch(h, name) do
    case Map.fetch(h, name) do
      {:ok, v} -> {:ok, v}
      :error -> fail("Missing webhook headers")
    end
  end

  defp secure_equal?(a, b) when byte_size(a) != byte_size(b), do: false

  defp secure_equal?(a, b) do
    if function_exported?(:crypto, :hash_equals, 2) do
      :crypto.hash_equals(a, b)
    else
      :crypto.exor(a, b) |> :binary.bin_to_list() |> Enum.all?(&(&1 == 0))
    end
  end

  defp fail(message), do: {:error, %Bmailu.Error{status: 400, code: "invalid_signature", message: message}}
end
