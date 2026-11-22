#!/bin/sh
# Runs the n8n node tests end to end (the test file starts and stops sdks/test-server.js).
set -e
cd "$(dirname "$0")"
node --test 'test/*.test.js'
echo "npm pack --dry-run files:"
npm pack --dry-run --json 2>/dev/null | node -e 'let s="";process.stdin.on("data",c=>s+=c).on("end",()=>{const f=JSON.parse(s)[0].files.map(x=>x.path).sort();console.log(f.join("\n"));if(f.some(p=>p.startsWith("test/")))process.exit(1)})'
