defmodule Bmailu.JSON do
  @moduledoc false
  # Elixir's JSON (1.18+), else Jason if the app has it, else OTP 27's :json. Called with apply/3 so no warnings.

  def encode!(term) do
    cond do
      Code.ensure_loaded?(JSON) -> apply(JSON, :encode!, [term])
      Code.ensure_loaded?(Jason) -> apply(Jason, :encode!, [term])
      Code.ensure_loaded?(:json) -> term |> otp_prepare() |> then(&apply(:json, :encode, [&1])) |> IO.iodata_to_binary()
      true -> raise "Bmailu needs Elixir 1.18+, OTP 27+ or the :jason package for JSON"
    end
  end

  def decode(text) do
    cond do
      Code.ensure_loaded?(JSON) ->
        apply(JSON, :decode, [text])

      Code.ensure_loaded?(Jason) ->
        apply(Jason, :decode, [text])

      Code.ensure_loaded?(:json) ->
        try do
          {:ok, otp_null(apply(:json, :decode, [text]))}
        rescue
          e -> {:error, e}
        end

      true ->
        raise "Bmailu needs Elixir 1.18+, OTP 27+ or the :jason package for JSON"
    end
  end

  defp otp_prepare(nil), do: :null
  defp otp_prepare(m) when is_map(m), do: Map.new(m, fn {k, v} -> {to_string(k), otp_prepare(v)} end)
  defp otp_prepare(l) when is_list(l), do: Enum.map(l, &otp_prepare/1)
  defp otp_prepare(v), do: v

  defp otp_null(:null), do: nil
  defp otp_null(m) when is_map(m), do: Map.new(m, fn {k, v} -> {k, otp_null(v)} end)
  defp otp_null(l) when is_list(l), do: Enum.map(l, &otp_null/1)
  defp otp_null(v), do: v
end
