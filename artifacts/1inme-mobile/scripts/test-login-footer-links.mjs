// Source-driven regression test for the mobile login screen's footer links.
//
// The login screen (app/(auth)/index.tsx) renders a footer row (the
// `styles.infoLinks` View) with five links separated by "·" dots:
//
//   About · Help · Privacy · Terms · Website
//
// The first four navigate in-app via `router.push("/info/<page>")`; the last,
// "Website", is an EXTERNAL link that must open `https://sayzio.app` via
// `Linking.openURL(...)` rather than an in-app route. Nothing covered these
// footer links, so a regression (a broken/removed link, a wrong route, the
// Website link pointing at the wrong URL or using router.push, or a missing
// separator) could ship unnoticed — the exact drift this guard prevents.
//
// This is a source-driven test (NOT a headless browser click-through),
// following the convention in test-login-auth-config.mjs: we read the shipped
// screen source, isolate the real footer block, assert every link renders with
// its separator, and then EVALUATE each real onPress handler against a mocked
// `router` / `Linking` so the navigation target of every link is exercised —
// not just pattern-matched.
//
// Run via `node scripts/test-login-footer-links.mjs` (package script
// `test:login-footer-links`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";
import { runExtractedCall } from "./lib/extract.mjs";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const screenSrc = readFileSync(
  join(root, "app", "(auth)", "index.tsx"),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

// ---------------------------------------------------------------------------
// Isolate the real footer block: the <View style={styles.infoLinks}> ... </View>
// that holds the five links. We slice from the opening tag to the first
// balanced closing </View> so a later View can't leak into the match.
// ---------------------------------------------------------------------------
function extractInfoLinksBlock(src) {
  const start = src.indexOf("<View style={styles.infoLinks}>");
  assert.ok(
    start !== -1,
    "the login screen must render a <View style={styles.infoLinks}> footer row",
  );
  // Walk forward counting <View ...> / </View> to find the balanced close.
  let depth = 0;
  const re = /<View\b|<\/View>/g;
  re.lastIndex = start;
  let m;
  while ((m = re.exec(src))) {
    if (m[0] === "</View>") {
      depth -= 1;
      if (depth === 0) return src.slice(start, re.lastIndex);
    } else {
      depth += 1;
    }
  }
  throw new Error("could not find the balanced </View> closing the footer row");
}

const footer = extractInfoLinksBlock(screenSrc);

// ---------------------------------------------------------------------------
// Parse the footer into an ordered list of { onPress, label } for each
// <Pressable> ... </Pressable> so we can both assert the rendered label AND
// evaluate the real handler.
// ---------------------------------------------------------------------------
function parseLinks(block) {
  const links = [];
  const pressRe = /<Pressable\b[\s\S]*?<\/Pressable>/g;
  let m;
  while ((m = pressRe.exec(block))) {
    const chunk = m[0];
    const onPress = chunk.match(/onPress=\{(\(\)\s*=>\s*[^\n]*?)\}\s*hitSlop/);
    assert.ok(onPress, `a footer <Pressable> is missing an onPress handler:\n${chunk}`);
    const label = chunk.match(/<Text[\s\S]*?>\s*([A-Za-z]+)\s*<\/Text>/);
    assert.ok(label, `a footer <Pressable> is missing a text label:\n${chunk}`);
    links.push({ onPress: onPress[1].trim(), label: label[1] });
  }
  return links;
}

const links = parseLinks(footer);

// ===========================================================================
// 1. All five footer links render, in order, each with its label.
// ===========================================================================
console.log("[test-login-footer-links] all five links render");

const EXPECTED_LABELS = ["About", "Help", "Privacy", "Terms", "Website"];
assert.deepEqual(
  links.map((l) => l.label),
  EXPECTED_LABELS,
  `the footer must render exactly these links in order: ${EXPECTED_LABELS.join(" · ")}`,
);
ok("footer renders About · Help · Privacy · Terms · Website (in order)");

// ===========================================================================
// 2. Separators: five links means four "·" dots between them.
// ===========================================================================
console.log("[test-login-footer-links] separators between links");

const dots = footer.match(/<Text style=\{\[styles\.infoDot[\s\S]*?>·<\/Text>/g) || [];
assert.equal(
  dots.length,
  EXPECTED_LABELS.length - 1,
  `there must be ${EXPECTED_LABELS.length - 1} "·" separators for ${EXPECTED_LABELS.length} links (found ${dots.length})`,
);
ok("four '·' separators sit between the five links");

// ===========================================================================
// 3. Each handler navigates to the right target.
//
// We evaluate the REAL onPress arrow against a mocked router / Linking and
// record what it invoked. The four in-app links must router.push their
// /info/<page> route; the Website link must open the external URL via
// Linking.openURL — NOT router.push.
// ===========================================================================
console.log("[test-login-footer-links] each link navigates to its target");

function runHandler(expr) {
  const calls = { push: [], openURL: [] };
  const router = { push: (r) => calls.push.push(r) };
  const Linking = { openURL: (u) => calls.openURL.push(u) };
  const handler = runExtractedCall(expr, { router, Linking }, "footer onPress", {
    test: "test-login-footer-links",
  });
  handler();
  return calls;
}

const ROUTER_TARGETS = {
  About: "/info/about",
  Help: "/info/help",
  Privacy: "/info/privacy",
  Terms: "/info/terms",
};

for (const { label, onPress } of links) {
  const calls = runHandler(onPress);
  if (label === "Website") {
    assert.deepEqual(
      calls.openURL,
      ["https://sayzio.app"],
      "the Website link must open https://sayzio.app via Linking.openURL",
    );
    assert.equal(
      calls.push.length,
      0,
      "the Website link must NOT use router.push (it is an external URL)",
    );
  } else {
    const target = ROUTER_TARGETS[label];
    assert.deepEqual(
      calls.push,
      [target],
      `the ${label} link must router.push("${target}")`,
    );
    assert.equal(
      calls.openURL.length,
      0,
      `the ${label} link must navigate in-app, not via Linking.openURL`,
    );
  }
}
ok("About/Help/Privacy/Terms push their /info route; Website opens https://sayzio.app");

console.log(`\n[test-login-footer-links] all ${passed} checks passed`);
