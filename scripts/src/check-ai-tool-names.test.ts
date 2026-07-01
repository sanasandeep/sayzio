/**
 * Regression tests for the AI tool-name drift guard.
 *
 * The guard's whole-label matching is deliberately delicate: it must flag a
 * label that is nothing but a bare tool name ("Personas", "Coach") while
 * passing entity-noun labels that merely contain that word ("All Personas",
 * "Coach usage & quality", "Coach Defaults", "AI Usage"). A well-meaning regex
 * tweak could re-introduce those false positives, so this file pins both the
 * drift cases (must flag) and the must-not-flag cases for scanLabelContexts and
 * scanSource. Run: pnpm --filter @workspace/scripts run test
 */

import { test } from "node:test";
import assert from "node:assert/strict";

import { scanSource, scanLabelContexts, blankComments } from "./check-ai-tool-names.js";

const ADMIN = "artifacts/1inme/resources/views/admin";
const AI_VIEW = `${ADMIN}/ai-personas/index.blade.php`;
const NON_AI_VIEW = `${ADMIN}/coach-defaults/index.blade.php`;
const GENERIC_VIEW = `${ADMIN}/dashboard/index.blade.php`;

function canonicals(offenders: { canonical: string }[]): string[] {
  return offenders.map((o) => o.canonical);
}

// ---------------------------------------------------------------------------
// scanLabelContexts — DRIFT cases (a bare single-word label must be flagged)
// ---------------------------------------------------------------------------

test("scanLabelContexts flags a bare tool name in @section('title')", () => {
  const src = `@section('title', 'Personas')`;
  const hits = scanLabelContexts(GENERIC_VIEW, src);
  assert.deepEqual(canonicals(hits), ["AI Personas"]);
});

test("scanLabelContexts flags a bare tool name in @section('page-title')", () => {
  const src = `@section('page-title', 'Resume')`;
  const hits = scanLabelContexts(GENERIC_VIEW, src);
  assert.deepEqual(canonicals(hits), ["AI Resume"]);
});

test("scanLabelContexts flags a bare tool name in a nav-label span", () => {
  const src = `<span class="nav-label">Companions</span>`;
  const hits = scanLabelContexts(GENERIC_VIEW, src);
  assert.deepEqual(canonicals(hits), ["AI Companions"]);
});

test("scanLabelContexts flags a bare tool name in a sidebar-tooltip span", () => {
  const src = `<span class="sidebar-tooltip">Coach</span>`;
  const hits = scanLabelContexts(GENERIC_VIEW, src);
  assert.deepEqual(canonicals(hits), ["AI Coach"]);
});

test("scanLabelContexts flags a bare tool-name heading inside an AI view dir", () => {
  const src = `<h1>Personas</h1>`;
  const hits = scanLabelContexts(AI_VIEW, src);
  assert.deepEqual(canonicals(hits), ["AI Personas"]);
});

// ---------------------------------------------------------------------------
// scanLabelContexts — MUST-NOT-FLAG cases (entity nouns / out of context)
// ---------------------------------------------------------------------------

test("scanLabelContexts passes 'All Personas' (longer entity-noun label)", () => {
  const src = `<span class="nav-label">All Personas</span>`;
  assert.deepEqual(scanLabelContexts(GENERIC_VIEW, src), []);
});

test("scanLabelContexts passes 'Coach usage & quality' in a section title", () => {
  const src = `@section('title', 'Coach usage & quality')`;
  assert.deepEqual(scanLabelContexts(GENERIC_VIEW, src), []);
});

test("scanLabelContexts passes 'Coach usage &amp; quality' (encoded entity)", () => {
  const src = `<span class="nav-label">Coach usage &amp; quality</span>`;
  assert.deepEqual(scanLabelContexts(GENERIC_VIEW, src), []);
});

test("scanLabelContexts passes 'Coach Defaults' (entity noun, not the tool)", () => {
  const src = `<span class="nav-label">Coach Defaults</span>`;
  assert.deepEqual(scanLabelContexts(GENERIC_VIEW, src), []);
});

test("scanLabelContexts passes 'AI Usage' (already prefixed / different noun)", () => {
  const src = `<span class="nav-label">AI Usage</span>`;
  assert.deepEqual(scanLabelContexts(GENERIC_VIEW, src), []);
});

test("scanLabelContexts passes a bare tool name inside a blade comment", () => {
  const src = `{{-- <span class="nav-label">Personas</span> --}}`;
  assert.deepEqual(scanLabelContexts(GENERIC_VIEW, src), []);
});

test("scanLabelContexts passes a bare tool name inside an HTML comment", () => {
  const src = `<!-- <h1>Coach</h1> -->`;
  assert.deepEqual(scanLabelContexts(AI_VIEW, src), []);
});

test("scanLabelContexts passes a dynamic (concatenated) section title", () => {
  // Not a plain string literal, so the section-title regex never matches it.
  const src = `@section('title', 'AI Usage — '.$user->name)`;
  assert.deepEqual(scanLabelContexts(GENERIC_VIEW, src), []);
});

test("scanLabelContexts ignores a bare heading OUTSIDE the AI view dirs", () => {
  const src = `<h1>Coach</h1>`;
  assert.deepEqual(scanLabelContexts(NON_AI_VIEW, src), []);
});

// ---------------------------------------------------------------------------
// scanSource — DRIFT cases (bare multi-word tool phrases must be flagged)
// ---------------------------------------------------------------------------

test("scanSource flags a bare 'Knowledge Bases'", () => {
  const hits = scanSource(GENERIC_VIEW, `<p>Manage Knowledge Bases here</p>`);
  assert.deepEqual(canonicals(hits), ["AI Knowledge Base(s)"]);
});

test("scanSource flags the retired 'Ask Coach' name", () => {
  const hits = scanSource(GENERIC_VIEW, `<a>Ask Coach</a>`);
  assert.deepEqual(canonicals(hits), ["AI Coach"]);
});

test("scanSource flags a bare 'Voice Assistant'", () => {
  const hits = scanSource(GENERIC_VIEW, `<h2>Voice Assistant</h2>`);
  assert.deepEqual(canonicals(hits), ["AI Voice Assistant"]);
});

test("scanSource flags a bare 'Brand Kit'", () => {
  const hits = scanSource(GENERIC_VIEW, `Open the Brand Kit`);
  assert.deepEqual(canonicals(hits), ["AI Brand Kit"]);
});

// ---------------------------------------------------------------------------
// scanSource — MUST-NOT-FLAG cases
// ---------------------------------------------------------------------------

test("scanSource passes the correctly prefixed 'AI Knowledge Bases'", () => {
  assert.deepEqual(scanSource(GENERIC_VIEW, `<p>AI Knowledge Bases</p>`), []);
});

test("scanSource passes a count badge '{{ $n }} Knowledge Bases'", () => {
  assert.deepEqual(scanSource(GENERIC_VIEW, `<span>{{ $n }} Knowledge Bases</span>`), []);
});

test("scanSource passes a numeric count badge '5 Knowledge Bases'", () => {
  assert.deepEqual(scanSource(GENERIC_VIEW, `<span>5 Knowledge Bases</span>`), []);
});

test("scanSource passes lowercase descriptive prose", () => {
  assert.deepEqual(scanSource(GENERIC_VIEW, `manage your knowledge bases and voice assistant`), []);
});

test("scanSource passes a bare tool phrase inside a comment", () => {
  assert.deepEqual(scanSource(GENERIC_VIEW, `{{-- Brand Kit --}}`), []);
});

// ---------------------------------------------------------------------------
// blankComments — preserves line/column geometry so reported positions hold
// ---------------------------------------------------------------------------

test("blankComments blanks comment bodies while preserving newlines", () => {
  const src = `a\n{{-- Brand Kit --}}\nb`;
  const out = blankComments(src);
  assert.equal(out.split("\n").length, src.split("\n").length);
  assert.ok(!out.includes("Brand Kit"));
  assert.equal(out.split("\n")[0], "a");
  assert.equal(out.split("\n")[2], "b");
});
