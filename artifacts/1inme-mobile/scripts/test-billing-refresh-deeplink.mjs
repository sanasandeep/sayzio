// Coverage for the post-payment "deep-link back into the app" hand-off.
//
// Flow (task: make the pricing page deep-link back into the app after a
// successful upgrade):
//   1. The app opens the website pricing page with a `client=app` marker so the
//      website knows the checkout started in the native app.
//   2. After payment, the website's billing success page fires the deep link
//      `sayzio://billing/refresh`.
//   3. The app receives it: `+native-intent.ts` routes it to the billing/plans
//      screen (so it never lands on +not-found), and DeepLinkRouter invalidates
//      the cached ["billing","plans"] / ["billing","subscription"] queries and
//      re-pulls the user so the new plan shows immediately.
//
// This pins all three legs against the REAL shipped source (no
// re-implementation). Follows the source-driven convention of
// test-import-url-route.mjs / test-native-route.mjs. Run via
// `node scripts/test-billing-refresh-deeplink.mjs` (package script
// `test:billing-refresh`, chained into `test:unit`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

const tsMod = await import("typescript");
const ts = tsMod.default ?? tsMod;

function loadModule(source, fileName, requireMap = {}) {
  const js = ts.transpileModule(source, {
    compilerOptions: {
      module: ts.ModuleKind.CommonJS,
      target: ts.ScriptTarget.ES2020,
      esModuleInterop: true,
      jsx: ts.JsxEmit.React,
    },
    fileName,
  }).outputText;
  const module = { exports: {} };
  const req = (name) => {
    if (name in requireMap) return requireMap[name];
    throw new Error(`unexpected import "${name}" in ${fileName}`);
  };
  // eslint-disable-next-line no-new-func
  new Function("require", "module", "exports", "__DEV__", "React", js)(
    req,
    module,
    module.exports,
    false,
    { createElement: () => null },
  );
  return module.exports;
}

// ===========================================================================
// 1. The app carries the `client=app` marker to the website pricing page.
// ===========================================================================
console.log("[test-billing-refresh-deeplink] client=app marker");

for (const file of ["plans.tsx", "upgrade.tsx"]) {
  const src = readFileSync(join(root, "app", file), "utf8");
  assert.ok(
    /\/pricing\?client=app/.test(src),
    `app/${file} must open /pricing?client=app so the website can fire the return deep link`,
  );
  // No lingering marker-less /pricing opener (would silently skip the hand-off).
  assert.ok(
    !/`\$\{getBaseUrl\(\)\}\/pricing`/.test(src),
    `app/${file} must not open a marker-less /pricing URL`,
  );
  ok(`app/${file} opens /pricing?client=app`);
}

// ===========================================================================
// 2. +native-intent routes sayzio://billing/refresh to the billing screen.
// ===========================================================================
console.log("[test-billing-refresh-deeplink] +native-intent routing");

const intentSrc = readFileSync(join(root, "app", "+native-intent.ts"), "utf8");
const { redirectSystemPath } = loadModule(intentSrc, "app/+native-intent.ts");
assert.equal(typeof redirectSystemPath, "function");

assert.equal(
  redirectSystemPath({ path: "/billing/refresh", initial: true }),
  "/plans",
  "sayzio://billing/refresh must route to the billing/plans screen",
);
assert.equal(
  redirectSystemPath({ path: "/BILLING/REFRESH", initial: false }),
  "/plans",
  "billing/refresh routing must be case-insensitive",
);
assert.equal(
  redirectSystemPath({
    path: "https://sayzio.app/billing/refresh",
    initial: true,
  }),
  "/plans",
  "the universal-link form must also route to the billing/plans screen",
);
// A plain handle still hits the biolink catch-all (routing table intact).
assert.equal(
  redirectSystemPath({ path: "/somecreator", initial: false }),
  "/biolink/somecreator",
  "sanity: a plain handle still resolves to /biolink/<handle>",
);
ok("billing/refresh → /plans (scheme + universal, case-insensitive)");

// ===========================================================================
// 3. DeepLinkRouter recognizes the link and refreshes billing state.
// ===========================================================================
console.log("[test-billing-refresh-deeplink] DeepLinkRouter handling");

const routerSrc = readFileSync(
  join(root, "components", "DeepLinkRouter.tsx"),
  "utf8",
);

// 3a. The recognizer helper matches host `billing` + path `refresh` and the
// custom `sayzio` scheme — extract and execute it with a stubbed expo-linking
// parse so we test the real matching predicate.
const parse = (url) => {
  // Minimal stand-in mirroring expo-linking's parse for our shapes.
  const m = /^([a-z]+):\/\/([^/?#]*)(\/[^?#]*)?/i.exec(url);
  if (!m) return { scheme: null, hostname: null, path: null };
  return {
    scheme: m[1] ?? null,
    hostname: m[2] || null,
    path: (m[3] ?? "").replace(/^\//, "") || null,
  };
};
const fnMatch = routerSrc.match(
  /export function _isBillingRefreshUrl\(url: string\): boolean \{[\s\S]*?\n\}/m,
);
assert.ok(fnMatch, "DeepLinkRouter must export _isBillingRefreshUrl");
const isBillingJs = fnMatch[0]
  .replace(/export function _isBillingRefreshUrl\(url: string\): boolean/, "function _isBillingRefreshUrl(url)")
  .replace(/Linking\.parse/g, "parse");
// eslint-disable-next-line no-new-func
const { _isBillingRefreshUrl } = new Function(
  "parse",
  `${isBillingJs}; return { _isBillingRefreshUrl };`,
)(parse);

assert.equal(
  _isBillingRefreshUrl("sayzio://billing/refresh"),
  true,
  "sayzio://billing/refresh must be recognized",
);
assert.equal(
  _isBillingRefreshUrl("sayzio://billing/refresh/"),
  true,
  "a trailing slash must not break recognition",
);
assert.equal(
  _isBillingRefreshUrl("sayzio://biolink/alice"),
  false,
  "unrelated custom-scheme links must NOT be treated as billing refresh",
);
assert.equal(
  _isBillingRefreshUrl("https://sayzio.app/somehandle"),
  false,
  "https biolink links must NOT be treated as billing refresh",
);
ok("_isBillingRefreshUrl matches only sayzio://billing/refresh");

// 3b. The handler invalidates BOTH cached billing query keys and refreshes the
// user — the whole point of the hand-off.
assert.ok(
  /invalidateQueries\(\{\s*queryKey:\s*\["billing",\s*"plans"\]\s*\}\)/.test(
    routerSrc,
  ),
  "DeepLinkRouter must invalidate the [\"billing\",\"plans\"] query",
);
assert.ok(
  /queryKey:\s*\[\s*"billing",\s*"subscription"\s*\]/.test(routerSrc),
  "DeepLinkRouter must invalidate the [\"billing\",\"subscription\"] query",
);
assert.ok(
  /const \{ refresh \} = useAuth\(\)/.test(routerSrc) &&
    /refresh\(\),/.test(routerSrc),
  "DeepLinkRouter must call the auth refresh() so the new plan is re-pulled",
);
ok("handler invalidates both billing queries + calls refresh()");

// 3c. Cold-start vs warm-start dispatch. Custom-scheme deep links reach the app
// through two different OS paths depending on process state, and the hand-off
// must work in BOTH:
//   - Cold start (app killed): the launch URL is read once via
//     Linking.getInitialURL().
//   - Warm start (app backgrounded): the URL arrives as a runtime "url" event
//     via Linking.addEventListener.
// Both must feed the SAME handle() so billing state refreshes identically. This
// is the code-level guard for the on-device cold/warm requirement (a real
// device round-trip still needs manual QA, but a regression that drops either
// entry point — or points them at different logic — is caught here).
assert.ok(
  /Linking\.getInitialURL\(\)\.then\(\s*\(?[a-zA-Z_]+\)?\s*=>\s*handle\(/.test(
    routerSrc,
  ),
  "DeepLinkRouter must feed the cold-start launch URL (getInitialURL) into handle()",
);
assert.ok(
  /Linking\.addEventListener\(\s*"url"\s*,[\s\S]*?handle\(\s*url\s*\)/.test(
    routerSrc,
  ),
  "DeepLinkRouter must feed warm-start 'url' events (addEventListener) into handle()",
);
// The billing short-circuit must return BEFORE the biolink probe so a
// refresh link never gets mis-treated as a handle and pushed to /biolink.
const handleBody = routerSrc.match(
  /async function handle\([\s\S]*?\n {4}\}\n/,
);
assert.ok(handleBody, "DeepLinkRouter must define an async handle()");
const refreshIdx = handleBody[0].indexOf("_isBillingRefreshUrl(url)");
const aliasIdx = handleBody[0].indexOf("_aliasFromUrl(url)");
assert.ok(
  refreshIdx !== -1 && aliasIdx !== -1 && refreshIdx < aliasIdx,
  "the billing-refresh branch must short-circuit before the biolink alias probe",
);
ok("cold-start (getInitialURL) + warm-start (url event) both dispatch to handle()");

console.log(
  `test-billing-refresh-deeplink: all assertions passed (${passed} checks)`,
);
