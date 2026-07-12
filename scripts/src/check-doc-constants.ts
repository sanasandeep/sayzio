/**
 * Documentation constant drift guard (docs -> source).
 *
 * The five Sayzio doc files under `artifacts/1inme/docs/` are the training /
 * reference corpus that powers Ask Zio (customer chatbot), the Claude technical
 * brief, the human knowledge base, the feature catalogue and the REST API
 * reference:
 *
 *   chatbot-training.md · claude-training.md · knowledge-base.md ·
 *   features.md · api.md
 *
 * They name a lot of HIGH-STAKES machine identifiers verbatim — AI-credit
 * feature keys, `links.type` slugs, biolink block-type ids and plan feature
 * keys. When the code renames or removes one of those constants but nobody
 * updates the docs, the docs silently rot: Ask Zio then answers with a slug or
 * key that no longer exists, developers copy a dead `links.type` out of the API
 * reference, etc. Nothing used to catch that — the existing
 * `parity:check-mobile-docs` guard runs the OTHER direction (it makes sure new
 * web types get a doc *mention*); it never verifies that a name ALREADY written
 * in the docs still resolves to something real in the code.
 *
 * This guard closes that gap. It:
 *   1. Derives the authoritative constant sets straight from the PHP source of
 *      truth at run time (so a code rename is reflected immediately), and
 *   2. Cross-references a curated list of ~25 high-stakes constants that the
 *      docs name verbatim against those sets.
 *
 * It FAILS (exit 1) when a curated, documented constant:
 *   - MISSING-FROM-SOURCE: no longer exists in the code set for its category
 *     (the real drift this task guards against — a stale doc name), or
 *   - MISSING-FROM-DOCS: is no longer written in any doc file (the curated list
 *     has gone stale and should be pruned so the guard keeps checking only
 *     things the docs actually claim).
 *
 * Source-of-truth files (parsed, not hard-coded — see deriveSourceSets):
 *   - AI-credit feature keys  app/Services/AI/AiFeatureCatalog.php  (FEATURES)
 *   - links.type slugs        app/Modules/User/Models/Link.php      (TYPE_* consts)
 *                             app/Modules/User/Support/LinkTypeCategories.php
 *   - biolink block-type ids  app/Modules/User/Models/BiolinkBlock.php (TYPES)
 *                             app/Modules/User/Support/BlockTypeRegistry.php (NEW_TYPES)
 *   - plan feature keys       app/Modules/Common/Support/PremiumFeatures.php (catalogue)
 *
 * If a source file cannot be parsed into a non-empty set the guard exits 2
 * (configuration error) rather than passing — so a refactor that moves a
 * constant table can never silently turn the check into a no-op.
 *
 * Run:  pnpm --filter @workspace/scripts run check:doc-constants
 *       (add `--explain` to print the curated constants + derived sets, exit 0)
 */

import { fileURLToPath, pathToFileURL } from "node:url";
import fs from "node:fs";
import path from "node:path";

export const REPO_ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");

/** Root of the Laravel app whose source is the constant source-of-truth. */
const APP_ROOT = "artifacts/1inme/app";

/** Doc corpus scanned for verbatim constant mentions (relative to repo root). */
export const DOC_FILES = [
  "artifacts/1inme/docs/chatbot-training.md",
  "artifacts/1inme/docs/claude-training.md",
  "artifacts/1inme/docs/knowledge-base.md",
  "artifacts/1inme/docs/features.md",
  "artifacts/1inme/docs/api.md",
];

export type Category = "ai" | "link" | "block" | "plan";

export const CATEGORY_LABEL: Record<Category, string> = {
  ai: "AI-credit feature key",
  link: "links.type slug",
  block: "biolink block-type id",
  plan: "plan feature key",
};

export type SourceSets = Record<Category, Set<string>>;

type Curated = { name: string; category: Category };

/**
 * ~25 high-stakes constants the docs name verbatim. Each is spelled exactly as
 * it should appear in BOTH the docs (as a `backtick` token) and its source set.
 * Keep this list to genuinely load-bearing identifiers Ask Zio / the API
 * reference hand to users — not every enum value.
 */
export const CURATED: Curated[] = [
  // ── AI-credit feature keys (AiFeatureCatalog::FEATURES) ──────────────
  { name: "mind", category: "ai" },
  { name: "persona", category: "ai" },
  { name: "companion", category: "ai" },
  { name: "ask_coach", category: "ai" },
  { name: "card_scan", category: "ai" },
  { name: "resume_import", category: "ai" },
  { name: "resume_tailor", category: "ai" },
  { name: "inbox_agent", category: "ai" },
  { name: "brand_kit", category: "ai" },
  { name: "qr_art", category: "ai" },
  { name: "marketing_strategist", category: "ai" },
  { name: "competitor_teardown", category: "ai" },
  { name: "biolink_builder", category: "ai" },
  { name: "audience_type_estimation", category: "ai" },

  // ── links.type slugs (Link::TYPE_* / LinkTypeCategories) ─────────────
  { name: "biolink", category: "link" },
  { name: "conversational", category: "link" },
  { name: "slides", category: "link" },
  { name: "ai_chat", category: "link" },
  { name: "restaurant_menu", category: "link" },
  { name: "store_menu", category: "link" },
  { name: "service_booking", category: "link" },
  { name: "reviews", category: "link" },
  { name: "resume", category: "link" },
  { name: "paid_page", category: "link" },
  { name: "calendar", category: "link" },

  // ── biolink block-type ids (BiolinkBlock::TYPES / BlockTypeRegistry) ─
  { name: "reviews_wall", category: "block" },
  { name: "map_location", category: "block" },
  { name: "paypal", category: "block" },

  // ── plan feature keys (PremiumFeatures::catalogue) ───────────────────
  { name: "max_links", category: "plan" },
  { name: "max_biolinks", category: "plan" },
  { name: "analytics_export", category: "plan" },
  { name: "stats_retention_days", category: "plan" },
  { name: "api_calls_monthly", category: "plan" },
];

function readApp(rel: string): string {
  return fs.readFileSync(path.join(REPO_ROOT, APP_ROOT, rel), "utf8");
}

/** Extract every single-quoted token from a slice of PHP source. */
function quotedTokens(src: string): string[] {
  return [...src.matchAll(/'([a-z0-9_.]+)'/g)].map((m) => m[1] ?? "");
}

/** The `[ ... ]` body of a `const NAME = [` array literal (brace-balanced-lite). */
function constArrayBody(src: string, constName: string): string {
  const start = src.indexOf(`const ${constName} = [`);
  if (start === -1) return "";
  // Walk from the opening bracket to its matching close bracket.
  const open = src.indexOf("[", start);
  let depth = 0;
  for (let i = open; i < src.length; i++) {
    const ch = src[i];
    if (ch === "[") depth++;
    else if (ch === "]") {
      depth--;
      if (depth === 0) return src.slice(open + 1, i);
    }
  }
  return "";
}

/** Top-level array KEYS (`'key' => [`) of a PHP associative array body. */
function arrayKeys(body: string): string[] {
  return [...body.matchAll(/'([a-z0-9_]+)'\s*=>\s*\[/g)].map((m) => m[1] ?? "");
}

/**
 * Derive the four authoritative constant sets from PHP source. Throws if any
 * set comes back empty (a parse regression) so the guard fails loud rather than
 * silently passing.
 */
export function deriveSourceSets(): SourceSets {
  // AI feature keys.
  const aiSrc = readApp("Services/AI/AiFeatureCatalog.php");
  const aiBody = constArrayBody(aiSrc, "FEATURES");
  const ai = new Set(quotedTokens(aiBody));

  // links.type slugs: Link::TYPE_* consts + LinkTypeCategories 'value' entries.
  const linkSrc = readApp("Modules/User/Models/Link.php");
  const linkConsts = [...linkSrc.matchAll(/const TYPE_[A-Z_]+\s*=\s*'([a-z0-9_]+)'/g)].map(
    (m) => m[1] ?? "",
  );
  const ltcSrc = readApp("Modules/User/Support/LinkTypeCategories.php");
  const ltcVals = [...ltcSrc.matchAll(/'value'\s*=>\s*'([a-z0-9_]+)'/g)].map((m) => m[1] ?? "");
  const link = new Set([...linkConsts, ...ltcVals]);

  // block-type ids: BiolinkBlock::TYPES keys + BlockTypeRegistry::NEW_TYPES keys.
  const bbSrc = readApp("Modules/User/Models/BiolinkBlock.php");
  const btrSrc = readApp("Modules/User/Support/BlockTypeRegistry.php");
  const block = new Set([
    ...arrayKeys(constArrayBody(bbSrc, "TYPES")),
    ...arrayKeys(constArrayBody(btrSrc, "NEW_TYPES")),
  ]);

  // plan feature keys: PremiumFeatures catalogue 'key' => '...' entries.
  const pfSrc = readApp("Modules/Common/Support/PremiumFeatures.php");
  const plan = new Set(
    [...pfSrc.matchAll(/'key'\s*=>\s*'([a-z0-9_]+)'/g)].map((m) => m[1] ?? ""),
  );

  const sets: SourceSets = { ai, link, block, plan };
  for (const cat of Object.keys(sets) as Category[]) {
    if (sets[cat].size === 0) {
      throw new Error(
        `doc-constants guard: derived an EMPTY ${CATEGORY_LABEL[cat]} set — source parsing regressed. Refusing to pass.`,
      );
    }
  }
  return sets;
}

/** Set of every `backtick` snake/dotted token written across all doc files. */
export function collectDocTokens(docTexts: string[]): Set<string> {
  const tokens = new Set<string>();
  for (const text of docTexts) {
    for (const m of text.matchAll(/`([a-z][a-z0-9_.]*)`/g)) tokens.add(m[1] ?? "");
  }
  return tokens;
}

export type Drift = {
  name: string;
  category: Category;
  reason: "missing-from-source" | "missing-from-docs";
};

/**
 * Core cross-reference. For each curated constant, verify it (a) still exists in
 * its category's source set, and (b) is still written verbatim in the docs.
 */
export function checkConstants(
  sets: SourceSets,
  docTokens: Set<string>,
  curated: Curated[] = CURATED,
): Drift[] {
  const drift: Drift[] = [];
  for (const { name, category } of curated) {
    if (!sets[category].has(name)) {
      drift.push({ name, category, reason: "missing-from-source" });
    } else if (!docTokens.has(name)) {
      drift.push({ name, category, reason: "missing-from-docs" });
    }
  }
  return drift;
}

function readDocs(): string[] {
  return DOC_FILES.map((rel) => {
    try {
      return fs.readFileSync(path.join(REPO_ROOT, rel), "utf8");
    } catch {
      return "";
    }
  });
}

function printExplain(sets: SourceSets): void {
  console.log("Documentation constant drift guard — curated high-stakes constants:\n");
  for (const cat of ["ai", "link", "block", "plan"] as Category[]) {
    const names = CURATED.filter((c) => c.category === cat).map((c) => c.name);
    console.log(`  ${CATEGORY_LABEL[cat]} (${names.length}): ${names.join(", ")}`);
  }
  console.log("\nDerived source-of-truth set sizes:");
  for (const cat of ["ai", "link", "block", "plan"] as Category[]) {
    console.log(`  ${CATEGORY_LABEL[cat]}: ${sets[cat].size} value(s)`);
  }
  console.log("\nDoc corpus scanned:");
  for (const d of DOC_FILES) console.log(`  • ${d}`);
}

function main(): void {
  let sets: SourceSets;
  try {
    sets = deriveSourceSets();
  } catch (err) {
    console.error("✗ " + (err instanceof Error ? err.message : String(err)));
    process.exit(2);
  }

  if (process.argv.includes("--explain")) {
    printExplain(sets);
    process.exit(0);
  }

  const docTokens = collectDocTokens(readDocs());
  const drift = checkConstants(sets, docTokens);

  if (drift.length === 0) {
    console.log(
      `✓ doc-constants guard passed — all ${CURATED.length} curated documented constants still exist in source.`,
    );
    process.exit(0);
  }

  console.error("✗ doc-constants guard FAILED — documented constant(s) drifted from source:\n");
  for (const d of drift) {
    if (d.reason === "missing-from-source") {
      console.error(
        `  [${CATEGORY_LABEL[d.category]}] \`${d.name}\` is documented but NO LONGER EXISTS in source.`,
      );
      console.error(
        `      Fix the docs (grep the five docs/ files for "${d.name}") to the current name, or add it back to the code.`,
      );
    } else {
      console.error(
        `  [${CATEGORY_LABEL[d.category]}] \`${d.name}\` is curated but no longer written in any doc file.`,
      );
      console.error(
        `      Prune it from CURATED in scripts/src/check-doc-constants.ts (the docs no longer name it).`,
      );
    }
  }
  console.error(
    `\n${drift.length} drift(s). Stale doc constants make Ask Zio / the API reference answer with names that no longer exist.`,
  );
  console.error(
    "Run `pnpm --filter @workspace/scripts run check:doc-constants -- --explain` to see the curated list and derived sets.",
  );
  process.exit(1);
}

if (import.meta.url === pathToFileURL(process.argv[1] ?? "").href) {
  main();
}
