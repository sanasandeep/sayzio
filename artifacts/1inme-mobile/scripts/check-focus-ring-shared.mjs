#!/usr/bin/env node
/**
 * Static guard: the web keyboard `:focus-visible` ring treatment must live in
 * ONE shared module (lib/webFocusRing.ts) and every navigation surface under
 * components/ must consume it via that import — never re-inline its own
 * `dataSet` focus marker or `:focus-visible` stylesheet.
 *
 * Why this exists: RNW Pressables have no default focus outline, so each web
 * nav surface paints an on-brand `:focus-visible` ring via a `data-*` marker +
 * an injected global stylesheet. That treatment was duplicated inline across
 * FloatingTabBar and DrawerSidebar and has since been extracted into the shared
 * helper. Nothing else stops a THIRD nav surface (or a refactor) from quietly
 * re-inlining its own copy — drifting the keyboard-accessibility behaviour and
 * shipping a regression with no e2e harness of its own. This guard fails the
 * moment a component inlines the treatment instead of importing the helper.
 *
 * A component "inlines" the treatment when (after stripping comments) it:
 *   • contains a `:focus-visible` selector (an injected focus stylesheet), or
 *   • builds a `dataSet` marker with a focus-ring-style key, or
 *   • references a `--*-focus-ring` CSS custom property,
 * WITHOUT importing from lib/webFocusRing.
 *
 * Correct usage (spreading the helper's marker props / calling useWebFocusRing)
 * produces none of those literals, so the sanctioned surfaces pass.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:focus-ring-guard
 */

import { existsSync, readdirSync, readFileSync, statSync } from "node:fs";
import { dirname, join, relative } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT = join(__dirname, "..");
const COMPONENTS_DIR = join(ROOT, "components");
const SHARED_MODULE = join(ROOT, "lib", "webFocusRing.ts");

function log(...args) {
  console.log("[focus-ring-guard]", ...args);
}

function fail(msg) {
  console.error("[focus-ring-guard] FAIL:", msg);
  process.exit(1);
}

// Recursively collect .ts/.tsx/.js/.jsx files under a directory.
function listSourceFiles(dir) {
  const out = [];
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    const st = statSync(p);
    if (st.isDirectory()) {
      out.push(...listSourceFiles(p));
    } else if (/\.(tsx?|jsx?)$/.test(name)) {
      out.push(p);
    }
  }
  return out;
}

// Remove // line comments and /* */ block comments while PRESERVING string and
// template-literal contents — so a real CSS string like "…:focus-visible {…}"
// is still detected, but prose in a comment ("paints a :focus-visible ring")
// is not. A `//` or `/*` inside a string is not treated as a comment.
function stripComments(src) {
  let out = "";
  let i = 0;
  const n = src.length;
  let state = "code"; // code | line | block | sq | dq | tpl
  while (i < n) {
    const c = src[i];
    const c2 = src[i + 1];
    if (state === "code") {
      if (c === "/" && c2 === "/") {
        state = "line";
        i += 2;
        continue;
      }
      if (c === "/" && c2 === "*") {
        state = "block";
        i += 2;
        continue;
      }
      if (c === "'" || c === '"' || c === "`") {
        state = c === "'" ? "sq" : c === '"' ? "dq" : "tpl";
        out += c;
        i += 1;
        continue;
      }
      out += c;
      i += 1;
      continue;
    }
    if (state === "line") {
      if (c === "\n") {
        state = "code";
        out += c;
      }
      i += 1;
      continue;
    }
    if (state === "block") {
      if (c === "*" && c2 === "/") {
        state = "code";
        i += 2;
        continue;
      }
      if (c === "\n") out += c;
      i += 1;
      continue;
    }
    // string / template states — copy contents verbatim until the close quote.
    out += c;
    if (c === "\\") {
      out += c2 ?? "";
      i += 2;
      continue;
    }
    if (
      (state === "sq" && c === "'") ||
      (state === "dq" && c === '"') ||
      (state === "tpl" && c === "`")
    ) {
      state = "code";
    }
    i += 1;
  }
  return out;
}

function importsSharedHelper(src) {
  // e.g. import { useWebFocusRing } from "@/lib/webFocusRing";
  return /\bfrom\s+["'][^"']*\bwebFocusRing["']/.test(src);
}

// Inline focus-ring signals in comment-stripped source.
function inlineFocusRingSignals(code) {
  const signals = [];
  if (/:focus-visible/.test(code)) {
    signals.push("an inline `:focus-visible` stylesheet");
  }
  if (/dataSet\s*[:=]\s*\{[^}]*(?:focus|focusable|ring)/i.test(code)) {
    signals.push("a hand-rolled focus-ring `dataSet` marker");
  }
  if (/--[\w-]*focus-ring\b/.test(code)) {
    signals.push("an inline `--*-focus-ring` CSS custom property");
  }
  return signals;
}

function main() {
  if (!existsSync(SHARED_MODULE)) {
    fail(
      `the shared helper ${relative(ROOT, SHARED_MODULE)} is missing. The web ` +
        `focus-ring treatment must live there; recreate it and have the nav ` +
        `surfaces import it.`,
    );
  }
  if (!existsSync(COMPONENTS_DIR)) {
    fail(`components directory not found at ${COMPONENTS_DIR}`);
  }

  const files = listSourceFiles(COMPONENTS_DIR);
  const violations = [];

  for (const file of files) {
    const src = readFileSync(file, "utf8");
    const code = stripComments(src);
    const signals = inlineFocusRingSignals(code);
    if (signals.length > 0 && !importsSharedHelper(src)) {
      violations.push({ file: relative(ROOT, file), signals });
    }
  }

  if (violations.length > 0) {
    console.error(
      "[focus-ring-guard] FAIL: a component inlines the keyboard focus-ring " +
        "treatment instead of importing the shared helper (lib/webFocusRing).",
    );
    for (const v of violations) {
      console.error(`  • ${v.file}:`);
      for (const s of v.signals) console.error(`      - ${s}`);
    }
    console.error(
      "\nFix: delete the inline dataSet marker / :focus-visible stylesheet and " +
        "use the shared helper instead:\n" +
        '  import { useWebFocusRing, focusRingMarkerProps } from "@/lib/webFocusRing";\n' +
        "  // install once per surface: useWebFocusRing(<preset>, colors.primary)\n" +
        "  // spread onto each focusable control: {...focusRingMarkerProps(<preset>)}\n" +
        "Add a new preset (WebFocusRingConfig) to lib/webFocusRing.ts for a new surface.",
    );
    process.exit(1);
  }

  log(
    `PASS: ${files.length} component file(s) scanned; the keyboard focus-ring ` +
      `treatment is only defined in lib/webFocusRing.ts and every surface ` +
      `imports it.`,
  );
}

main();
