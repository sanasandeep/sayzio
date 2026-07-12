import { describe, it, expect } from "vitest";
import {
  checkConstants,
  collectDocTokens,
  deriveSourceSets,
  CURATED,
  DOC_FILES,
  REPO_ROOT,
  type SourceSets,
} from "./check-doc-constants.js";
import fs from "node:fs";
import path from "node:path";

/**
 * Regression suite for the documentation constant drift guard.
 *
 * Pins the cross-reference core so a future refactor can't silently turn the
 * guard into a no-op:
 *   - checkConstants() must flag a documented constant that no longer exists in
 *     its source set (MISSING-FROM-SOURCE — the real drift class), and a curated
 *     constant the docs no longer name (MISSING-FROM-DOCS), while passing a
 *     constant present in both.
 *   - deriveSourceSets() must parse the live PHP source into non-empty sets.
 *   - The real corpus + curated list must be internally consistent (green).
 * Run: pnpm --filter @workspace/scripts run test
 */

const SETS: SourceSets = {
  ai: new Set(["persona", "companion", "card_scan"]),
  link: new Set(["biolink", "ai_chat"]),
  block: new Set(["reviews_wall"]),
  plan: new Set(["max_links"]),
};

describe("checkConstants — drift detection", () => {
  it("passes when a curated constant is in both source and docs", () => {
    const docs = collectDocTokens(["... uses `persona` for tone ..."]);
    const drift = checkConstants(SETS, docs, [{ name: "persona", category: "ai" }]);
    expect(drift).toEqual([]);
  });

  it("flags MISSING-FROM-SOURCE when the code renamed/removed the constant", () => {
    // Docs still say `ai_persona`, but source only knows `persona`.
    const docs = collectDocTokens(["Persona Generator key is `ai_persona`."]);
    const drift = checkConstants(SETS, docs, [{ name: "ai_persona", category: "ai" }]);
    expect(drift).toEqual([{ name: "ai_persona", category: "ai", reason: "missing-from-source" }]);
  });

  it("flags MISSING-FROM-DOCS when the docs no longer name a curated constant", () => {
    const docs = collectDocTokens(["nothing relevant here"]);
    const drift = checkConstants(SETS, docs, [{ name: "persona", category: "ai" }]);
    expect(drift).toEqual([{ name: "persona", category: "ai", reason: "missing-from-docs" }]);
  });

  it("checks source membership per category (right name, wrong category fails)", () => {
    const docs = collectDocTokens(["`biolink` and `persona`"]);
    // `biolink` is a link type, not an AI feature.
    const drift = checkConstants(SETS, docs, [{ name: "biolink", category: "ai" }]);
    expect(drift).toEqual([{ name: "biolink", category: "ai", reason: "missing-from-source" }]);
  });
});

describe("collectDocTokens", () => {
  it("collects only backtick-wrapped snake/dotted tokens", () => {
    const tokens = collectDocTokens(["use `max_links` and stats.retention but not plainword"]);
    expect(tokens.has("max_links")).toBe(true);
    expect(tokens.has("plainword")).toBe(false);
  });
});

describe("live source + curated list", () => {
  const sets = deriveSourceSets();

  it("derives non-empty sets from PHP source", () => {
    expect(sets.ai.size).toBeGreaterThan(0);
    expect(sets.link.size).toBeGreaterThan(0);
    expect(sets.block.size).toBeGreaterThan(0);
    expect(sets.plan.size).toBeGreaterThan(0);
  });

  it("passes the real guard: every curated documented constant still exists in source", () => {
    const docTexts = DOC_FILES.map((rel) =>
      fs.readFileSync(path.join(REPO_ROOT, rel), "utf8"),
    );
    const drift = checkConstants(sets, collectDocTokens(docTexts), CURATED);
    expect(drift).toEqual([]);
  });
});
