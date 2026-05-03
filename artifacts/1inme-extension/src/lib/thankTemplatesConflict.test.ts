// Run with: node --test --experimental-strip-types
//   artifacts/1inme-extension/src/lib/thankTemplatesConflict.test.ts
//
// Covers the offline-edit-then-conflict scenario for the thank-template
// sync: two browsers both edit while offline, one pushes first, then
// the second's push should be rejected so the first browser's work
// isn't silently lost.

import { strict as assert } from "node:assert";
import { test } from "node:test";

import {
  isThankTemplatesConflict,
  mergeThankTemplatesPerId,
  pickConflictResolution,
  type ThankTemplate,
  type ThankTemplatesConflict,
} from "./thankTemplatesConflict.ts";

const T = (id: string, body: string): ThankTemplate => ({
  id, name: id, channel: "email", subject: "", body,
});

test("isThankTemplatesConflict detects when the server moved past our last sync", () => {
  // Both browsers last saw the server stamp 100. Browser A pushed and
  // the server is now at 200. Browser B (still on 100) tries to push.
  assert.equal(isThankTemplatesConflict(100, 200), true);
});

test("isThankTemplatesConflict ignores in-sync state", () => {
  assert.equal(isThankTemplatesConflict(100, 100), false);
  assert.equal(isThankTemplatesConflict(null, null), false);
  assert.equal(isThankTemplatesConflict(null, 0), false);
});

test("mergeThankTemplatesPerId prefers local on id collisions and caps at 3", () => {
  const local = [T("a", "local-a"), T("b", "local-b")];
  const server = [T("a", "server-a"), T("c", "server-c"), T("d", "server-d")];
  const merged = mergeThankTemplatesPerId(local, server);
  assert.deepEqual(merged.map((t) => t.id), ["a", "b", "c"]);
  assert.equal(merged[0].body, "local-a", "local should win on id collision");
});

test("offline-edit-then-conflict: both browsers edit, second push surfaces conflict", () => {
  // Shared starting point — both browsers synced when server ts was 100.
  const lastSeenServerTs = 100;

  // Browser A goes offline, edits template "a", and pushes first. Its
  // push succeeds and the server stamp moves to 200.
  const browserAEdit = [T("a", "A-edit"), T("b", "shared-b")];
  void browserAEdit;
  const newServerTs = 200;

  // Browser B was also offline, edited template "b", and now tries to
  // push while still expecting ts=100.
  const browserBEdit = [T("a", "shared-a"), T("b", "B-edit")];

  // The server-side check (mirrored by isThankTemplatesConflict) must
  // reject the push instead of silently overwriting A's work.
  assert.equal(isThankTemplatesConflict(lastSeenServerTs, newServerTs), true);

  // The 409 response surfaces a conflict object the editor uses to ask
  // the user what to do.
  const conflict: ThankTemplatesConflict = {
    local: browserBEdit,
    server: browserAEdit,
    serverUpdatedAtMs: newServerTs,
  };

  // "Keep mine" → B's edits win.
  const keepMine = pickConflictResolution(conflict, "mine");
  assert.deepEqual(keepMine, browserBEdit);

  // "Use server" → adopt A's copy locally.
  const useServer = pickConflictResolution(conflict, "server");
  assert.deepEqual(useServer, browserAEdit);

  // "Merge per-template" → for each id, prefer the local edit (B's).
  // Both browsers had ids "a" and "b", so this returns B's copy here —
  // the value is in the *uncollided* case below.
  const merged = pickConflictResolution(conflict, "merge");
  assert.equal(merged.length, 2);
  assert.equal(merged.find((t) => t.id === "b")?.body, "B-edit");
});

test("merge resolution keeps both sides' net-new templates", () => {
  // Browser A added "x"; Browser B added "y". Merge should keep both.
  const conflict: ThankTemplatesConflict = {
    local: [T("shared", "B"), T("y", "B-only")],
    server: [T("shared", "A"), T("x", "A-only")],
    serverUpdatedAtMs: 200,
  };
  const merged = pickConflictResolution(conflict, "merge");
  const ids = merged.map((t) => t.id).sort();
  assert.deepEqual(ids, ["shared", "x", "y"]);
  // Local wins on the id collision.
  assert.equal(merged.find((t) => t.id === "shared")?.body, "B");
});
