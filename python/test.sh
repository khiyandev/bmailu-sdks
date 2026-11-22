#!/bin/sh
# Runs the Python SDK tests against the shared local API (sdks/test-server.js). Nothing leaves the machine.
set -e
cd "$(dirname "$0")"
OUT=$(mktemp)
node ../test-server.js > "$OUT" &
PID=$!
trap 'kill $PID 2>/dev/null; rm -f "$OUT"' EXIT INT TERM
i=0
while [ ! -s "$OUT" ] && [ $i -lt 100 ]; do sleep 0.1; i=$((i + 1)); done
BMAILU_TEST_JSON=$(head -n 1 "$OUT")
[ -n "$BMAILU_TEST_JSON" ] || { echo "test server did not start" >&2; exit 1; }
export BMAILU_TEST_JSON
PYTHONDONTWRITEBYTECODE=1 python3 -m unittest discover -s tests -v
