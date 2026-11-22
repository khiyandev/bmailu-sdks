'use strict';
// Starts the shared local Bmailu API (sdks/test-server.js) and returns its JSON line.
const { spawn } = require('node:child_process');
const path = require('node:path');

const SDKS = path.join(__dirname, '..', '..');

function startServer() {
  return new Promise((resolve, reject) => {
    const child = spawn(process.execPath, [path.join(SDKS, 'test-server.js')], { cwd: SDKS, stdio: ['ignore', 'pipe', 'inherit'] });
    let buf = '';
    child.stdout.on('data', (c) => {
      buf += c;
      const nl = buf.indexOf('\n');
      if (nl >= 0) {
        const info = JSON.parse(buf.slice(0, nl));
        info.stop = () => child.kill();
        resolve(info);
      }
    });
    child.on('exit', (code) => reject(new Error(`test server exited (${code})`)));
    child.on('error', reject);
  });
}

// A signature made with the panel's own sign() in a separate node process.
function panelSign(secret, id, ts, body) {
  const { execFileSync } = require('node:child_process');
  const script = `const { sign } = require(${JSON.stringify(path.join(SDKS, '..', 'panel', 'src', 'webhook-routes.js'))});
process.stdout.write(sign(process.argv[1], process.argv[2], process.argv[3], process.argv[4]));`;
  return execFileSync(process.execPath, ['-e', script, secret, id, String(ts), body], { encoding: 'utf8' });
}

module.exports = { startServer, panelSign, SDKS };
