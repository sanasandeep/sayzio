#!/usr/bin/env node
/**
 * Static guard: every tab screen under app/(tabs)/ that renders a vertical
 * scroller (ScrollView / FlatList / SectionList, incl. Animated.* variants)
 * must wire that scroller's `onScroll` to the shared TabBarContext
 * `reportScroll` — the hook that auto-hides the top bar and the floating
 * bottom tab bar on scroll-down (contexts/TabBarContext.tsx).
 *
 * Why this exists: the runtime autohide e2e
 * (scripts/test-topbar-autohide-e2e.mjs) exercises the hide/show geometry on
 * the Links tab only. The other tab screens (Home, Create, Inbox, Profile)
 * each carry their own `onScroll={(e) => reportScroll(...)}` — a refactor
 * dropping that prop on any one screen would permanently pin both bars on
 * that screen and no check would notice. Booting a browser per tab is too
 * slow, so this source-level guard asserts the wiring can't silently
 * disappear per-screen.
 *
 * Rules, per .tsx screen under app/(tabs)/ (excluding _layout.tsx):
 *   • Every vertical scroller opening tag must carry an `onScroll` prop
 *     whose expression references `reportScroll`.
 *   • Scrollers with the `horizontal` prop are exempt (they never drive the
 *     vertical autohide) — but a horizontal scroller must not be the ONLY
 *     scroller excuse: screens with zero vertical scrollers are simply
 *     skipped.
 *   • A screen that wires reportScroll must also obtain it from useTabBar()
 *     (a stale local shadow would compile but do nothing).
 *
 * Opening tags are extracted with a small angle/brace-aware scanner (JSX
 * props contain `>` inside arrow functions), after stripping comments.
 *
 * Usage:
 *   pnpm --filter @workspace/1inme-mobile run test:tabbar-scroll-wiring
 */

import { readdirSync, readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const TABS_DIR = join(__dirname, "..", "app", "(tabs)");

function log(...args) {
  console.log("[tabbar-scroll-wiring]", ...args);
}
function fail(msg) {
  console.error("[tabbar-scroll-wiring] FAIL:", msg);
  process.exit(1);
}

// Strip // and /* */ comments while preserving newlines (keeps line numbers
// stable for error reporting). Naive about comment markers inside string
// literals, which is fine for these screens.
function stripComments(src) {
  return src
    .replace(/\/\*[\s\S]*?\*\//g, (m) => m.replace(/[^\n]/g, " "))
    .replace(/([^:])\/\/[^\n]*/g, (m, p1) => p1 + " ".repeat(m.length - 1));
}

const SCROLLER_TAG_RE =
  /<((?:Animated\.)?(?:ScrollView|FlatList|SectionList))\b/g;

// From the index of "<Tag", scan forward to the matching ">" of the OPENING
// tag, skipping over balanced {...} prop expressions (which may contain ">",
// "=>" etc.). Returns the prop text between the tag name and that ">".
function extractOpeningTag(src, startIdx) {
  let i = startIdx;
  let brace = 0;
  let inStr = null;
  for (; i < src.length; i++) {
    const ch = src[i];
    if (inStr) {
      if (ch === "\\") i++;
      else if (ch === inStr) inStr = null;
      continue;
    }
    if (ch === '"' || ch === "'" || ch === "`") {
      inStr = ch;
    } else if (ch === "{") {
      brace++;
    } else if (ch === "}") {
      brace--;
    } else if (ch === ">" && brace === 0) {
      return src.slice(startIdx, i + 1);
    }
  }
  return null;
}

function lineOf(src, idx) {
  return src.slice(0, idx).split("\n").length;
}

const files = readdirSync(TABS_DIR)
  .filter((f) => f.endsWith(".tsx") && f !== "_layout.tsx")
  .sort();

if (files.length === 0) fail(`no screen files found under ${TABS_DIR}`);

const problems = [];
let checkedScrollers = 0;
let checkedScreens = 0;

for (const file of files) {
  const raw = readFileSync(join(TABS_DIR, file), "utf8");
  const src = stripComments(raw);

  let m;
  let verticalScrollers = 0;
  SCROLLER_TAG_RE.lastIndex = 0;
  while ((m = SCROLLER_TAG_RE.exec(src))) {
    const tag = extractOpeningTag(src, m.index);
    const line = lineOf(src, m.index);
    if (!tag) {
      problems.push(
        `${file}:${line} — could not parse the <${m[1]}> opening tag`,
      );
      continue;
    }
    // Horizontal scrollers never drive the vertical autohide.
    if (/\bhorizontal\b/.test(tag)) continue;
    verticalScrollers++;
    checkedScrollers++;
    if (!/\bonScroll\s*=/.test(tag)) {
      problems.push(
        `${file}:${line} — vertical <${m[1]}> has NO onScroll prop; it must pass ` +
          `onScroll={(e) => reportScroll(e.nativeEvent.contentOffset.y)}`,
      );
    } else if (!/\bonScroll\s*=\s*\{[^]*?reportScroll/.test(tag)) {
      problems.push(
        `${file}:${line} — vertical <${m[1]}> has an onScroll prop that does not ` +
          `reference reportScroll (TabBarContext autohide wiring dropped)`,
      );
    }
  }

  if (verticalScrollers > 0) {
    checkedScreens++;
    if (!/\breportScroll\b[^]*?=\s*useTabBar\s*\(|useTabBar\s*\(\)/.test(src)) {
      problems.push(
        `${file} — references a vertical scroller but never calls useTabBar() ` +
          `(reportScroll must come from the shared TabBarContext)`,
      );
    } else if (!/\{[^}]*\breportScroll\b[^}]*\}\s*=\s*useTabBar\s*\(\s*\)/.test(src)) {
      problems.push(
        `${file} — calls useTabBar() but never destructures reportScroll from it`,
      );
    }
  }
}

if (problems.length) {
  for (const p of problems) console.error("[tabbar-scroll-wiring]  ✗", p);
  fail(
    `${problems.length} tab screen scroller(s) missing the reportScroll autohide wiring`,
  );
}

log(
  `PASS — ${checkedScrollers} vertical scroller(s) across ${checkedScreens} tab screen(s) ` +
    `all wire onScroll → reportScroll from useTabBar() (screens scanned: ${files.join(", ")})`,
);
