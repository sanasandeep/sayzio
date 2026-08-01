---
name: Zio Browser main-process store live e2e
description: How to exercise zio-browser main-process stores (notes-store etc.) against the real Laravel API without launching Electron.
---

To verify a zio-browser main-process store (e.g. notes-store) end-to-end against
the live Laravel API, skip the Electron GUI entirely: write a temporary vitest
file under `artifacts/zio-browser/tests/` that `vi.mock('electron', ...)` with
`app.getPath → tmpdir` and `safeStorage.isEncryptionAvailable → false` (auth-store
then stores tokens as `plain:`), call `initDb('/tmp/.../zio.db')` explicitly, seed
`storeToken(sanctumToken)` + `setPreference(SAYZIO_API_BASE_URL, 'http://127.0.0.1:5000')`.

**Why:** better-sqlite3 is prebuilt in the workspace and db.ts only touches
`electron.app` when no explicit db path is given, so the whole offline-queue /
cache / flush machinery runs under plain vitest.

**How to apply:** start the `artifacts/1inme: web` workflow, mint a token via
tinker `--execute` (User model is `\App\Modules\User\Models\User`), run vitest
with `--testTimeout=180000` (cold RDS makes 5s defaults fail everything), and
simulate offline by pointing SAYZIO_API_BASE_URL at an unreachable port.
Delete the test file + test notes/user afterwards — it depends on a live server.
