---
name: Throwaway-Expo e2e harness must process.exit(0)
description: Mobile e2e harnesses using expo-web-server.mjs hang forever without an explicit exit; manager API is acquireServer.
---
# Throwaway-Expo harness exit + manager API

`createExpoServerManager(log)` returns `{ acquireServer(label, explicitUrl) }`
(NOT start/stop — older harnesses written against a start() API throw
"manager.start is not a function"). `acquireServer` returns
`{ appUrl, child, explicit }` or null (callers SKIP on null).

After a PASS the harness MUST call `process.exit(0)`: the detached throwaway
Expo child keeps the Node event loop alive, so the script (and any validation
runner waiting on it) hangs forever with PASS already printed in the log. The
manager's synchronous process "exit" handler reaps the Expo child.

**How to apply:** any new `test-*-e2e.mjs` in artifacts/1inme-mobile/scripts
copying the sibling pattern — end main() with process.exit(0) and use
acquireServer.
