// End-to-end coverage for the Updates / Changelog owner screen
// (app/links/[id]/updates.tsx) and its API lib (lib/api/updates.ts).
//
// Following the source-driven convention of test-import-url.mjs, this test
// runs what ships instead of re-implementing it:
//
//   1. Screen wiring — the entries list loads via listOwnerEntries in a
//      react-query query, the create/update/delete mutations invalidate that
//      query, and the FlatList renders the EmptyState when there are no
//      entries.
//   2. Status badges & tag labels — the REAL tagColor function is lifted
//      from the screen and executed (per-tag color contract); the badge
//      markup renders "Draft"/"Published" off entry.status and tag chips
//      resolve labels through ENTRY_TAG_LABELS (all 5 tags covered).
//   3. CRUD flow END-TO-END through the shipped client stack: the screen's
//      real mutation call expressions → the REAL updates.ts helpers → the
//      REAL apiFetch (lib/api.ts, transpiled from the shipped TS, with
//      Bearer token + envelope handling) → an actual HTTP round-trip to a
//      local server speaking the /api/v1/me/updates contract (verified
//      against UpdatesApiController): load list → create → edit → delete,
//      asserting method, path, auth header, JSON body, and envelope unwrap
//      at every step — plus the {error:{...}} → ApiError path.
//
// Run via `node scripts/test-updates-crud.mjs` (package script
// `test:updates-crud`, chained into `test:unit` → the mobile-unit workflow).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import http from "node:http";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedCall as runExtractedCallShared } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const screenSrc = readFileSync(join(root, "app", "links", "[id]", "updates.tsx"), "utf8");
const apiSrc = readFileSync(join(root, "lib", "api.ts"), "utf8");
const updatesSrc = readFileSync(join(root, "lib", "api", "updates.ts"), "utf8");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

const runExtractedCall = (expr, scope, label) =>
  runExtractedCallShared(expr, scope, label, { test: "test-updates-crud" });

// Balanced-brace extraction of a function starting at `signature`.
function extractFn(src, signature, file) {
  const at = src.indexOf(signature);
  assert.notEqual(at, -1, `${signature} not found in ${file}`);
  const open = src.indexOf("{", at);
  let depth = 0;
  let end = -1;
  for (let i = open; i < src.length; i++) {
    if (src[i] === "{") depth++;
    else if (src[i] === "}") {
      depth--;
      if (depth === 0) {
        end = i + 1;
        break;
      }
    }
  }
  assert.notEqual(end, -1, `could not balance braces for ${signature}`);
  return src.slice(at, end);
}

// Balanced-paren extraction of the argument list of `name(` starting at from.
function extractCallArgs(src, name, file, from = 0) {
  const at = src.indexOf(`${name}(`, from);
  assert.notEqual(at, -1, `${name}( not found in ${file}`);
  const open = at + name.length;
  let depth = 0;
  let end = -1;
  for (let i = open; i < src.length; i++) {
    const ch = src[i];
    if (ch === "(") depth++;
    else if (ch === ")") {
      depth--;
      if (depth === 0) {
        end = i;
        break;
      }
    }
  }
  assert.notEqual(end, -1, `unterminated ${name}(...) call in ${file}`);
  return src.slice(open + 1, end);
}

// ===========================================================================
// 1. Screen wiring: load list / mutations / empty state.
// ===========================================================================
console.log("[test-updates-crud] screen wiring");

assert.match(
  screenSrc,
  /queryKey: \["updates-entries", linkId\],\s*\n\s*queryFn: \(\) => listOwnerEntries\(linkId\)/,
  "entries list must load via listOwnerEntries keyed on the link id",
);
assert.match(
  screenSrc,
  /const invalidate = \(\) => qc\.invalidateQueries\(\{ queryKey: \["updates-entries", linkId\] \}\)/,
  "mutations must invalidate the entries query so the list refreshes",
);
ok("list loads via listOwnerEntries and mutations invalidate it");

assert.match(
  screenSrc,
  /createUpdateEntry\(linkId, input\)/,
  "create mutation must call createUpdateEntry(linkId, input)",
);
assert.match(
  screenSrc,
  /updateUpdateEntry\(linkId, entryId, input\)/,
  "edit mutation must call updateUpdateEntry(linkId, entryId, input)",
);
assert.match(
  screenSrc,
  /deleteUpdateEntry\(linkId, entryId\)/,
  "delete mutation must call deleteUpdateEntry(linkId, entryId)",
);
ok("create / edit / delete mutations call the shipped updates helpers");

// Delete is confirm-gated (RN-web needs showAlert, not Alert.alert).
assert.match(
  screenSrc,
  /showAlert\("Delete Entry"[\s\S]{0,300}onPress: \(\) => deleteMutation\.mutate\(entry\.id\)/,
  "delete must be confirm-gated via showAlert before mutating",
);
ok("delete goes through a showAlert confirmation");

// Empty state: FlatList's ListEmptyComponent renders the EmptyState.
assert.match(
  screenSrc,
  /ListEmptyComponent=\{\s*<EmptyState\s+icon="file-text"\s+title="No entries yet"/,
  "empty list must render the 'No entries yet' EmptyState",
);
assert.match(
  screenSrc,
  /data=\{data \?\? \[\]\}/,
  "FlatList must fall back to [] so EmptyState shows before data arrives",
);
ok("empty state renders when there are no entries");

// The + header button opens the New Entry modal; save requires a title.
assert.match(screenSrc, /headerRight:[\s\S]{0,200}onPress=\{openNew\}/, "header + opens the new-entry modal");
assert.match(screenSrc, /if \(!title\.trim\(\)\) \{\s*\n\s*showAlert\("Validation", "Title is required\."/, "save validates the title");
ok("new-entry modal opens from the header and validates the title");

// ===========================================================================
// 2. Status badges & tag labels.
// ===========================================================================
console.log("[test-updates-crud] status badges & tag labels");

// Load the REAL updates.ts constants (transpiled below for the e2e leg too),
// but first exercise the real tagColor function lifted from the screen.
const tagColorSrc = extractFn(screenSrc, "function tagColor", "updates.tsx").replace(
  /function tagColor\([^)]*\)(: string)?/,
  "function tagColor(tag, colors)",
);
// eslint-disable-next-line no-new-func
const tagColor = new Function(`${tagColorSrc}\nreturn tagColor;`)();

const colors = {
  primary: "#0a84ff",
  destructive: "#ff3b30",
  success: "#34c759",
  mutedForeground: "#8e8e93",
};
assert.equal(tagColor("feature", colors), colors.primary);
assert.equal(tagColor("fix", colors), colors.destructive);
assert.equal(tagColor("improvement", colors), colors.success);
assert.equal(tagColor("breaking", colors), "#f59e0b");
assert.equal(tagColor("announcement", colors), "#8b5cf6");
assert.equal(tagColor(null, colors), colors.mutedForeground, "no tag falls back to muted");
assert.equal(tagColor("unknown-future-tag", colors), colors.mutedForeground);
ok("real tagColor maps every tag to its distinct color (muted fallback)");

// Status badge text derives from entry.status: draft → "Draft", else "Published".
assert.match(
  screenSrc,
  /const isDraft = status === "draft";/,
  "badge must branch on status === 'draft'",
);
assert.match(
  screenSrc,
  /\{isDraft \? "Draft" : "Published"\}/,
  "badge text must be Draft / Published",
);
assert.match(
  screenSrc,
  /\{statusBadge\(entry\.status, colors\)\}/,
  "each entry row renders its status badge",
);
ok("status badge renders Draft/Published off entry.status");

// Tag chips resolve their label through ENTRY_TAG_LABELS (raw-tag fallback),
// and hide entirely for untagged entries.
assert.match(
  screenSrc,
  /\{ENTRY_TAG_LABELS\[entry\.tag\] \?\? entry\.tag\}/,
  "tag chip text must come from ENTRY_TAG_LABELS",
);
assert.match(
  screenSrc,
  /\{entry\.tag \? \(/,
  "untagged entries must not render a tag chip",
);
ok("tag chips use ENTRY_TAG_LABELS and hide when there is no tag");

// ===========================================================================
// 3. CRUD flow end-to-end: real screen expressions → real updates.ts helpers
//    → real apiFetch → HTTP round-trip against the /api/v1/me/updates
//    contract (mirrors UpdatesApiController's envelopes).
// ===========================================================================
console.log("[test-updates-crud] CRUD end-to-end (real client stack over HTTP)");

const tsMod = await import("typescript");
const ts = tsMod.default ?? tsMod;

function loadModule(source, fileName, requireMap) {
  const js = ts.transpileModule(source, {
    compilerOptions: {
      module: ts.ModuleKind.CommonJS,
      target: ts.ScriptTarget.ES2020,
      esModuleInterop: true,
    },
    fileName,
  }).outputText;
  const module = { exports: {} };
  const req = (name) => {
    if (name in requireMap) return requireMap[name];
    throw new Error(`unexpected import "${name}" in ${fileName}`);
  };
  // eslint-disable-next-line no-new-func
  new Function("require", "module", "exports", "__DEV__", js)(
    req,
    module,
    module.exports,
    false,
  );
  return module.exports;
}

const TEST_TOKEN = "e2e-updates-crud-token";

const apiModule = loadModule(apiSrc, "lib/api.ts", {
  "react-native": { Platform: { OS: "ios", select: (o) => o.ios } },
  "expo-constants": { default: { expoConfig: { version: "1.0.0" } } },
  "@/lib/secure": { getToken: async () => TEST_TOKEN },
});
const updatesModule = loadModule(updatesSrc, "lib/api/updates.ts", {
  "@/lib/api": apiModule,
});

for (const fn of ["listOwnerEntries", "createUpdateEntry", "updateUpdateEntry", "deleteUpdateEntry"]) {
  assert.equal(typeof updatesModule[fn], "function", `real ${fn} loaded`);
}

// The lib's ENTRY_TAGS / ENTRY_TAG_LABELS stay in lockstep and cover all 5 tags.
assert.deepEqual(
  [...updatesModule.ENTRY_TAGS],
  ["feature", "fix", "improvement", "breaking", "announcement"],
  "ENTRY_TAGS covers the 5 backend tags",
);
for (const t of updatesModule.ENTRY_TAGS) {
  assert.ok(updatesModule.ENTRY_TAG_LABELS[t], `ENTRY_TAG_LABELS must label "${t}"`);
}
// The modal's tag picker offers every tag plus "None".
assert.match(screenSrc, /\{\[null, \.\.\.ENTRY_TAGS\]\.map\(/, "modal tag picker offers None + all tags");
ok("ENTRY_TAGS / ENTRY_TAG_LABELS lockstep; picker offers None + all tags");

// In-memory backend speaking the UpdatesApiController contract.
const LINK_ID = 4242;
let nextId = 1;
const entriesStore = new Map();
const requests = [];

const entryJson = (e) => ({
  id: e.id,
  link_id: LINK_ID,
  title: e.title,
  body: e.body ?? null,
  image: e.image ?? null,
  tag: e.tag ?? null,
  status: e.status ?? "draft",
  published_date: e.published_date ?? null,
  sort_order: e.sort_order ?? 0,
  notified_at: null,
  created_at: "2026-07-19T00:00:00Z",
  updated_at: "2026-07-19T00:00:00Z",
});

const server = http.createServer((req, res) => {
  let body = "";
  req.on("data", (c) => (body += c));
  req.on("end", () => {
    const parsed = body ? JSON.parse(body) : null;
    requests.push({
      method: req.method,
      url: req.url,
      auth: req.headers.authorization,
      contentType: req.headers["content-type"],
      body: parsed,
    });
    res.setHeader("Content-Type", "application/json");

    const listRe = new RegExp(`^/api/v1/me/updates/${LINK_ID}/entries$`);
    const oneRe = new RegExp(`^/api/v1/me/updates/${LINK_ID}/entries/(\\d+)$`);

    if (req.headers.authorization !== `Bearer ${TEST_TOKEN}`) {
      res.statusCode = 401;
      res.end(JSON.stringify({ error: { message: "Unauthenticated.", code: "unauthenticated" } }));
      return;
    }

    if (req.method === "GET" && listRe.test(req.url)) {
      res.end(JSON.stringify({ data: { entries: [...entriesStore.values()].map(entryJson) } }));
      return;
    }
    if (req.method === "POST" && listRe.test(req.url)) {
      if (!parsed?.title) {
        res.statusCode = 422;
        res.end(
          JSON.stringify({
            error: {
              message: "The title field is required.",
              code: "validation_failed",
              details: { title: ["The title field is required."] },
            },
          }),
        );
        return;
      }
      const e = { id: nextId++, ...parsed, status: parsed.status ?? "draft" };
      entriesStore.set(e.id, e);
      res.statusCode = 201;
      res.end(JSON.stringify({ data: entryJson(e) }));
      return;
    }
    const m = req.url.match(oneRe);
    if (m) {
      const id = Number(m[1]);
      const existing = entriesStore.get(id);
      if (!existing) {
        res.statusCode = 404;
        res.end(JSON.stringify({ error: { message: "Not found", code: "not_found" } }));
        return;
      }
      if (req.method === "PUT") {
        Object.assign(existing, parsed);
        res.end(JSON.stringify({ data: entryJson(existing) }));
        return;
      }
      if (req.method === "DELETE") {
        entriesStore.delete(id);
        res.end(JSON.stringify({ data: { deleted: true } }));
        return;
      }
    }
    res.statusCode = 404;
    res.end(JSON.stringify({ error: { message: "Not found" } }));
  });
});
await new Promise((r) => server.listen(0, "127.0.0.1", r));
const port = server.address().port;
process.env.EXPO_PUBLIC_API_BASE_URL = `http://127.0.0.1:${port}`;

// The screen's REAL mutation call expressions, executed with the REAL helpers.
const createArgs = extractCallArgs(screenSrc, "createUpdateEntry", "updates.tsx", screenSrc.indexOf("createMutation"));
const updateArgs = extractCallArgs(screenSrc, "updateUpdateEntry", "updates.tsx", screenSrc.indexOf("updateMutation"));
const deleteArgs = extractCallArgs(screenSrc, "deleteUpdateEntry", "updates.tsx", screenSrc.indexOf("deleteMutation"));

const runCreate = (input) =>
  runExtractedCall(
    `createUpdateEntry(${createArgs})`,
    { createUpdateEntry: updatesModule.createUpdateEntry, linkId: LINK_ID, input },
    "createUpdateEntry",
  );
const runUpdate = (entryId, input) =>
  runExtractedCall(
    `updateUpdateEntry(${updateArgs})`,
    { updateUpdateEntry: updatesModule.updateUpdateEntry, linkId: LINK_ID, entryId, input },
    "updateUpdateEntry",
  );
const runDelete = (entryId) =>
  runExtractedCall(
    `deleteUpdateEntry(${deleteArgs})`,
    { deleteUpdateEntry: updatesModule.deleteUpdateEntry, linkId: LINK_ID, entryId },
    "deleteUpdateEntry",
  );

try {
  // --- load: empty list ----------------------------------------------------
  const empty = await updatesModule.listOwnerEntries(LINK_ID);
  assert.deepEqual(empty, [], "fresh link has no entries (screen shows EmptyState)");
  {
    const r = requests.at(-1);
    assert.equal(r.method, "GET");
    assert.equal(r.url, `/api/v1/me/updates/${LINK_ID}/entries`);
    assert.equal(r.auth, `Bearer ${TEST_TOKEN}`, "list sends the bearer token");
  }
  ok("GET entries: empty list unwraps to [] (empty-state path)");

  // --- create ----------------------------------------------------------------
  // Exactly what the modal's handleSave assembles for a new entry.
  const created = await runCreate({
    title: "Launched dark mode",
    body: "It is very dark.",
    tag: "feature",
    published_date: "2026-07-19",
    status: "published",
  });
  assert.equal(created.id, 1);
  assert.equal(created.title, "Launched dark mode");
  assert.equal(created.status, "published");
  assert.equal(created.tag, "feature");
  {
    const r = requests.at(-1);
    assert.equal(r.method, "POST");
    assert.equal(r.url, `/api/v1/me/updates/${LINK_ID}/entries`);
    assert.match(r.contentType, /application\/json/);
    assert.deepEqual(r.body, {
      title: "Launched dark mode",
      body: "It is very dark.",
      tag: "feature",
      published_date: "2026-07-19",
      status: "published",
    });
  }
  ok("POST entry: auth + JSON payload + {data} envelope unwrap");

  // List now returns it, with the fields the row renders (badge, tag, date).
  const afterCreate = await updatesModule.listOwnerEntries(LINK_ID);
  assert.equal(afterCreate.length, 1);
  assert.equal(afterCreate[0].status, "published", "row would show the Published badge");
  assert.equal(
    updatesModule.ENTRY_TAG_LABELS[afterCreate[0].tag],
    "Feature",
    "row would show the Feature tag label",
  );
  ok("GET entries: created entry comes back with status + tag intact");

  // --- edit -------------------------------------------------------------------
  const edited = await runUpdate(created.id, {
    title: "Launched dark mode (beta)",
    body: null,
    tag: "improvement",
    published_date: "2026-07-19",
    status: "draft",
  });
  assert.equal(edited.id, created.id);
  assert.equal(edited.title, "Launched dark mode (beta)");
  assert.equal(edited.status, "draft", "row would flip to the Draft badge");
  assert.equal(updatesModule.ENTRY_TAG_LABELS[edited.tag], "Improvement");
  {
    const r = requests.at(-1);
    assert.equal(r.method, "PUT");
    assert.equal(r.url, `/api/v1/me/updates/${LINK_ID}/entries/${created.id}`);
    assert.equal(r.body.status, "draft");
  }
  ok("PUT entry: edit round-trips title/tag/status");

  // --- delete -----------------------------------------------------------------
  await runDelete(created.id);
  {
    const r = requests.at(-1);
    assert.equal(r.method, "DELETE");
    assert.equal(r.url, `/api/v1/me/updates/${LINK_ID}/entries/${created.id}`);
  }
  const afterDelete = await updatesModule.listOwnerEntries(LINK_ID);
  assert.deepEqual(afterDelete, [], "deleted entry is gone → EmptyState again");
  ok("DELETE entry: list is empty again (back to empty state)");

  // --- error envelope ----------------------------------------------------------
  let thrown = null;
  try {
    await runCreate({ title: "", status: "draft" });
  } catch (e) {
    thrown = e;
  }
  assert.ok(thrown, "validation rejection must throw");
  assert.equal(thrown.status, 422);
  assert.equal(thrown.code, "validation_failed");
  assert.match(thrown.message, /title field is required/i);
  ok("error envelope surfaces ApiError {status, code} on validation failure");
} finally {
  server.close();
}

console.log(`\n[test-updates-crud] all assertions passed (${passed} groups)`);
