#!/bin/sh
# Runs the Node SDK + CLI tests end to end. Each test file starts its own sdks/test-server.js and stops it when done.
set -e
cd "$(dirname "$0")"
node --test 'test/*.test.js'

# The package must contain only the intended files.
expected="LICENSE
README.md
bin/bmailu.js
index.d.ts
lib/index.js
package.json"
actual=$(npm pack --dry-run --json 2>/dev/null | node -e 'let s="";process.stdin.on("data",c=>s+=c).on("end",()=>console.log(JSON.parse(s)[0].files.map(f=>f.path).sort().join("\n")))')
if [ "$actual" != "$expected" ]; then
  echo "npm pack lists unexpected files:"; echo "$actual"; exit 1
fi
echo "npm pack --dry-run: OK ($(echo "$actual" | wc -l | tr -d ' ') files)"
