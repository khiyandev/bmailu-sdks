defmodule Bmailu.Error do
  @moduledoc "An error from the Bmailu API (`{\"error\":{\"code\",\"message\"}}`) or from the network. `status` is 0 when the API was not reached."
  defexception [:status, :code, :message]

  @type t :: %__MODULE__{status: non_neg_integer(), code: String.t(), message: String.t()}

  @impl true
  def message(%__MODULE__{status: s, code: c, message: m}), do: "bmailu error #{s} (#{c}): #{m}"
end
