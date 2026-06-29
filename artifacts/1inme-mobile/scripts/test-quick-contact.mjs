// Source-driven smoke test for the mobile "Contact us" (quick-contact)
// screen — the flow that lets a user request a call back / WhatsApp call /
// email reply, posting to the shared POST /api/v1/assistant/quick-contact
// contract (backend coverage: artifacts/1inme/tests/Feature/QuickContactApiTest.php).
//
// A contact request must never silently fail, so this pins two things the
// screen promises:
//
//   1. The channel picker renders all three channels (callback / whatsapp /
//      email) the backend accepts — a drifted picker would offer a channel
//      the server rejects (or hide one it supports).
//   2. Submit is disabled until the channel's REQUIRED field is filled
//      (phone for callback/whatsapp, email for the email channel), so a
//      visitor can't fire an empty request that the server would 422.
//
// Following the convention in test-upgrade-hint.mjs / test-citation-href.mjs
// we avoid a full TS/RN test runner: we read the shipped source and (a) eval
// the real CHANNELS array literal and (b) reconstruct the `canSubmit`
// expression as a function from its exact source text. This keeps the test
// honest — it exercises what ships, not a re-implementation.
//
// Run via `node scripts/test-quick-contact.mjs` (package script
// `test:quick-contact`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const screenSrc = readFileSync(
  join(root, "app", "info", "contact.tsx"),
  "utf8",
);
const apiSrc = readFileSync(join(root, "lib", "api", "assistant.ts"), "utf8");

// ---------------------------------------------------------------------------
// 1. The channel picker offers exactly the three channels the backend accepts.
//    We eval the real `const CHANNELS = [...]` literal so a drift in the
//    shipped array fails this test.
// ---------------------------------------------------------------------------
function extractChannelsLiteral(src) {
  const start = src.indexOf("const CHANNELS");
  if (start === -1) throw new Error("could not find CHANNELS in contact.tsx");
  // Skip past the `= ` (the type annotation before it contains a `[]` we must
  // not match), then find the opening `[` of the array literal itself.
  const eq = src.indexOf("= [", start);
  if (eq === -1) throw new Error("could not find CHANNELS array assignment");
  const open = src.indexOf("[", eq);
  // Walk to the matching closing bracket of the array literal.
  let depth = 0;
  let end = -1;
  for (let i = open; i < src.length; i++) {
    const ch = src[i];
    if (ch === "[") depth++;
    else if (ch === "]") {
      depth--;
      if (depth === 0) {
        end = i;
        break;
      }
    }
  }
  if (end === -1) throw new Error("unterminated CHANNELS array literal");
  return src.slice(open, end + 1);
}

const channelsLiteral = extractChannelsLiteral(screenSrc).replace(
  // Drop the `keyof typeof Feather.glyphMap`-typed icon's quotes? No — the
  // values are plain string literals already; nothing to strip here.
  /\u0000/g,
  "",
);

// eslint-disable-next-line no-new-func
const CHANNELS = new Function(`return ${channelsLiteral};`)();

assert.equal(CHANNELS.length, 3, "the picker must render three channels");
const channelValues = CHANNELS.map((c) => c.value);
assert.deepEqual(
  channelValues.slice().sort(),
  ["callback", "email", "whatsapp"],
  "channels must be exactly callback / whatsapp / email (backend contract)",
);
for (const c of CHANNELS) {
  assert.ok(c.label && typeof c.label === "string", `${c.value} needs a label`);
  assert.ok(c.icon && typeof c.icon === "string", `${c.value} needs an icon`);
}

// The picker actually maps the CHANNELS array into pressable pills, and the
// submit Button is gated on `!canSubmit`.
assert.ok(
  /CHANNELS\.map\(/.test(screenSrc),
  "the screen must render the channel picker by mapping CHANNELS",
);
assert.ok(
  /disabled=\{!canSubmit\}/.test(screenSrc),
  "the submit Button must be disabled by !canSubmit",
);

// ---------------------------------------------------------------------------
// 2. `canSubmit` stays false until the channel's required field is filled.
//    We reconstruct the exact expression from source so this tracks the
//    shipped gating, not a copy.
// ---------------------------------------------------------------------------
function extractCanSubmit(src) {
  const m = src.match(/const canSubmit\s*=\s*([\s\S]*?);/);
  if (!m) throw new Error("could not find canSubmit in contact.tsx");
  return m[1].trim();
}

const canSubmitExpr = extractCanSubmit(screenSrc);

// eslint-disable-next-line no-new-func
const canSubmit = new Function(
  "channel",
  "email",
  "phone",
  `return ${canSubmitExpr};`,
);

// callback: needs a phone, ignores email.
assert.equal(
  canSubmit("callback", "", ""),
  false,
  "callback submit is disabled with no phone",
);
assert.equal(
  canSubmit("callback", "", "   "),
  false,
  "callback submit stays disabled when phone is only whitespace",
);
assert.equal(
  canSubmit("callback", "", "98765 43210"),
  true,
  "callback submit enables once a phone is typed",
);

// whatsapp: needs a phone too.
assert.equal(
  canSubmit("whatsapp", "", ""),
  false,
  "whatsapp submit is disabled with no phone",
);
assert.equal(
  canSubmit("whatsapp", "", "+1 555 123 4567"),
  true,
  "whatsapp submit enables once a phone is typed",
);

// email: needs an email, ignores phone.
assert.equal(
  canSubmit("email", "", ""),
  false,
  "email submit is disabled with no email",
);
assert.equal(
  canSubmit("email", "  ", "9876543210"),
  false,
  "email submit stays disabled when email is only whitespace (phone ignored)",
);
assert.equal(
  canSubmit("email", "me@example.com", ""),
  true,
  "email submit enables once an email is typed",
);

// ---------------------------------------------------------------------------
// 3. The screen surfaces server-side validation (a 422) inline instead of
//    failing quietly: it reads apiError.status === 422 into a fieldError and
//    shows the non-422 error path too.
// ---------------------------------------------------------------------------
assert.ok(
  /apiError\?\.status === 422 \? apiError\.message : null/.test(screenSrc),
  "a 422 from the server must be surfaced as an inline fieldError",
);
assert.ok(
  /submit\.isError && !fieldError/.test(screenSrc),
  "a non-422 failure must still show a visible error (never silent)",
);

// And the request goes to the shared quick-contact contract.
assert.ok(
  /"\/assistant\/quick-contact"/.test(apiSrc),
  "the mobile client must POST to /assistant/quick-contact (under /api/v1)",
);

console.log(
  "ok — quick-contact screen (channel picker + submit gating + 422 surfacing)",
);
