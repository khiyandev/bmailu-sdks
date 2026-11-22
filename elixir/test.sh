#!/usr/bin/env bash
# Runs the Elixir SDK tests end to end: starts the shared local API (../test-server.js), makes a webhook signature with
# the panel's sign(), then runs `mix test` with the local Elixir.
set -euo pipefail
cd "$(dirname "$0")"
PORT="${PORT:-47126}"
LOG="$(mktemp)"
node ../test-server.js "$PORT" > "$LOG" &
PID=$!; disown "$PID"
trap 'kill $PID 2>/dev/null || true; rm -f "$LOG"' EXIT
for _ in $(seq 100); do [ -s "$LOG" ] && break; sleep 0.1; done
INFO="$(head -1 "$LOG")"
field() { node -e 'console.log(JSON.parse(process.argv[1])[process.argv[2]])' "$INFO" "$1"; }
export BMAILU_TEST_KEY="$(field key)" BMAILU_TEST_SENDING_KEY="$(field sending_key)"
export BMAILU_TEST_URL="http://127.0.0.1:$PORT/api/v1"
# A webhook signed by the panel's own sign() (../../panel/src/webhook-routes.js).
eval "$(node -e '
const { sign } = require("../../panel/src/webhook-routes.js");
const secret = "whsec_" + Buffer.from("bmailu-sdk-test-secret-0123456789").toString("base64");
const id = "msg_test_1", ts = String(Math.floor(Date.now() / 1000));
const body = JSON.stringify({ type: "email.delivered", created_at: new Date().toISOString(), data: { email_id: "e1", to: ["a@b.test"] } });
const q = (s) => "\x27" + s.replace(/\x27/g, "\x27\\\x27\x27") + "\x27";
for (const [k, v] of Object.entries({ WH_SECRET: secret, WH_ID: id, WH_TS: ts, WH_BODY: body, WH_SIG: sign(secret, id, ts, body) })) console.log(`export ${k}=${q(v)}`);
')"
mix test
echo "elixir: all tests passed"
