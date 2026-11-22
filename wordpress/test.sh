#!/bin/sh
# Bmailu WordPress plugin tests: php -l on every PHP file, then the plugin against the shared local API.
set -eu
cd "$(dirname "$0")"
for f in $(find bmailu tests -name '*.php'); do php -l "$f" >/dev/null || { echo "php -l failed: $f"; exit 1; }; done
echo "php -l: all files OK"

OUT=$(mktemp)
node ../test-server.js > "$OUT" &
PID=$!
trap 'kill $PID 2>/dev/null || true; wait $PID 2>/dev/null || true; rm -f "$OUT"' EXIT
i=0; while [ ! -s "$OUT" ] && [ $i -lt 100 ]; do sleep 0.1; i=$((i+1)); done
[ -s "$OUT" ] || { echo "test server did not start"; exit 1; }
API=$(php -r 'echo json_decode(file_get_contents($argv[1]))->api;' "$OUT")
KEY=$(php -r 'echo json_decode(file_get_contents($argv[1]))->key;' "$OUT")
BMAILU_TEST_API="$API" BMAILU_TEST_KEY="$KEY" php tests/run.php
