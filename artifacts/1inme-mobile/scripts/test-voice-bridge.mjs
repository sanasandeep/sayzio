// Source-driven tests for the mobile Voice Assistant's client_action /
// navigate_to bridge.
//
// The web app guards this surface bridge with a Playwright spec
// (artifacts/1inme/tests/Browser/voice-assistant-bridge.spec.ts): it drives the
// real `voiceAssistant` Alpine component's sendTurn() with a mocked STT/LLM/TTS
// turn and asserts the focused surface reacts to `client_action` and that a
// `navigate_to` is DEFERRED until the spoken reply finishes (so speech isn't
// cut off). The Expo app (this package) has its OWN voice runtime — a module
// singleton `setVoiceSurface`, an `onVoiceAction`/`emitVoiceAction` bus, and the
// turn handler `sendClip` in components/VoiceAssistant.tsx — and that runtime
// had NO equivalent coverage. A regression in the mobile voice→surface bridge
// (action never dispatched, nav fired immediately / to the browser, surface
// hint dropped) would go unnoticed.
//
// Mirroring the convention in test-auth-flow.mjs / test-wizard-flow.mjs, this is
// a source-driven test (NOT a headless RN/Playwright click-through): it lifts
// the REAL functions out of the TS source, strips the (simple) TS annotations,
// and runs them against injected mocks — so it exercises the actual code, not a
// re-implementation. Specifically it loads, verbatim from VoiceAssistant.tsx:
//   - the client_action bus (`setVoiceSurface` / `onVoiceAction` /
//     `emitVoiceAction` + the `activeVoiceSurface` singleton),
//   - the web→expo route mapper `mapNavTarget`,
//   - the turn handler `sendClip`,
// and drives sendClip with mocked turn responses.
//
// DEVIATION FROM THE WEB SPEC (intentional, documented): on the web the
// navigate_to is deferred until the TTS `ended` event; on mobile it is deferred
// via a short fixed timer (audio plays independently through expo-audio). The
// user-visible parity guarantee — navigation does NOT fire synchronously with
// the turn, so the "Opening …" reply + speech get a beat first — is what this
// test asserts.
//
// Run via `node scripts/test-voice-bridge.mjs` (package script `test:voice-bridge`).

import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const __dirname = dirname(fileURLToPath(import.meta.url));
const root = join(__dirname, "..");

const vaSrc = readFileSync(
  join(root, "components", "VoiceAssistant.tsx"),
  "utf8",
);
const voiceApiSrc = readFileSync(join(root, "lib", "api", "voice.ts"), "utf8");
const createSrc = readFileSync(
  join(root, "app", "(tabs)", "create.tsx"),
  "utf8",
);
const linksSrc = readFileSync(
  join(root, "app", "(tabs)", "links.tsx"),
  "utf8",
);
const wizardSrc = readFileSync(
  join(root, "app", "links", "wizard.tsx"),
  "utf8",
);

let passed = 0;
function ok(label) {
  passed += 1;
  console.log(`  ok — ${label}`);
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ---------------------------------------------------------------------------
// Lift the REAL voice bridge out of components/VoiceAssistant.tsx.
//
// We grab three real pieces verbatim:
//   1. the module-level bus block (activeVoiceSurface + setVoiceSurface +
//      voiceActionHandlers + onVoiceAction + emitVoiceAction),
//   2. the mapNavTarget() web→expo route mapper,
//   3. the sendClip useCallback (the turn handler),
// strip the simple TS annotations so they run as plain JS, then evaluate them
// together with injected mocks for the React state setters / refs / router /
// runTurn / playBase64. This runs the actual source, not a copy.
// ---------------------------------------------------------------------------
function extractBusBlock() {
  const m = vaSrc.match(
    /let activeVoiceSurface[\s\S]*?function emitVoiceAction\([\s\S]*?\n\}/,
  );
  if (!m) throw new Error("could not find the voice-action bus in VoiceAssistant.tsx");
  return m[0]
    .replace(/export /g, "")
    .replace(/^type VoiceActionHandler[^\n]*\n/m, "")
    .replace(/new Set<VoiceActionHandler>\(\)/g, "new Set()")
    .replace(/: string \| null/g, "")
    .replace(/: VoiceActionHandler/g, "")
    .replace(/: VoiceClientAction/g, "")
    .replace(/\): \(\) => void \{/g, ") {")
    .replace(/\): void \{/g, ") {");
}

function extractMapNavTarget() {
  const m = vaSrc.match(/function mapNavTarget\([\s\S]*?\n\}/);
  if (!m) throw new Error("could not find mapNavTarget in VoiceAssistant.tsx");
  return m[0].replace(
    /function mapNavTarget\(url: string \| undefined\): string \| null/,
    "function mapNavTarget(url)",
  );
}

function extractSendClip() {
  const m = vaSrc.match(
    /const sendClip = useCallback\(\s*async \([\s\S]*?\n {4}\[history, playBase64, router\],\n {2}\);/,
  );
  if (!m) throw new Error("could not find sendClip in VoiceAssistant.tsx");
  return m[0]
    .replace(/clip: \{ uri: string; mime: string; filename: string \},/, "clip,")
    .replace(/confirmedTools: Record<string, boolean> = \{\},/, "confirmedTools = {},")
    .replace(/router\.push\(target as never\)/, "router.push(target)")
    .replace(/catch \(e: unknown\)/, "catch (e)")
    .replace(
      /const err = e as \{ status\?: number; message\?: string \} \| undefined;/,
      "const err = e;",
    );
}

/**
 * Build a fresh bridge instance with recording mocks. Each call gets its OWN
 * module singletons (activeVoiceSurface / handler set) so tests don't bleed.
 */
function loadVoiceBridge({ turn, runTurnImpl } = {}) {
  const state = {
    phase: null,
    error: null,
    transcript: null,
    reply: null,
    pending: null,
    credits: null,
    balance: null,
    history: null,
    nfcReq: null,
  };
  const pushed = [];
  const playedAudio = [];
  const runTurnArgs = [];
  const pendingRef = { current: false };

  const runTurn =
    runTurnImpl ??
    (async (args) => {
      runTurnArgs.push(args);
      return {
        transcript: "test command",
        reply: "ok",
        audio_base64: null,
        tool_results: [],
        pending_confirmations: [],
        credits: { stt: 1, llm: 1, tts: 1, total: 3 },
        balance: 100,
        messages: [{ role: "assistant", content: "ok" }],
        ...turn,
      };
    });

  const js =
    `${extractBusBlock()}\n\n` +
    `${extractMapNavTarget()}\n\n` +
    `${extractSendClip()}\n` +
    `return { sendClip, mapNavTarget, onVoiceAction, setVoiceSurface, getSurface: () => activeVoiceSurface };`;

  // eslint-disable-next-line no-new-func
  const make = new Function(
    "useCallback",
    "runTurn",
    "history",
    "setPhase",
    "setError",
    "setTranscript",
    "setReply",
    "setPending",
    "pendingRef",
    "setLastCredits",
    "setBalance",
    "setHistory",
    "setNfcReq",
    "router",
    "playBase64",
    js,
  );

  const bridge = make(
    (fn) => fn,
    runTurn,
    [],
    (v) => (state.phase = v),
    (v) => (state.error = v),
    (v) => (state.transcript = v),
    (v) => (state.reply = v),
    (v) => (state.pending = v),
    pendingRef,
    (v) => (state.credits = v),
    (v) => (state.balance = v),
    (v) => (state.history = v),
    (v) => (state.nfcReq = v),
    { push: (t) => pushed.push(t) },
    async (b64) => playedAudio.push(b64),
  );

  return { bridge, state, pushed, playedAudio, runTurnArgs, pendingRef };
}

const CLIP = { uri: "file:///clip.m4a", mime: "audio/mp4", filename: "voice.m4a" };

// ===========================================================================
// 1. A spoken client_action is dispatched to the focused surface handler —
//    via the REAL onVoiceAction bus — and is DEFERRED (not fired during the
//    synchronous part of the turn).
// ===========================================================================
console.log("[test-voice-bridge] client_action surface dispatch");

{
  const { bridge } = loadVoiceBridge({
    turn: {
      tool_results: [
        { result: { client_action: { type: "select_link_type", link_type: "vcard" } } },
      ],
    },
  });

  const received = [];
  const off = bridge.onVoiceAction((a) => received.push(a));

  await bridge.sendClip(CLIP);
  // Deferred: nothing dispatched yet right after the turn resolves.
  assert.equal(
    received.length,
    0,
    "client_action must be deferred, not dispatched during the turn",
  );

  await sleep(400);
  assert.equal(received.length, 1, "the registered surface must receive the action");
  assert.deepEqual(
    received[0],
    { type: "select_link_type", link_type: "vcard" },
    "the surface must receive the exact client_action the tool returned",
  );

  // Unsubscribing (screen blur) stops further dispatch.
  off();
  const received2 = [];
  bridge.onVoiceAction((a) => received2.push(a));
  await bridge.sendClip(CLIP);
  await sleep(400);
  // Only the still-subscribed handler fires; the unsubscribed one stays silent.
  assert.equal(received.length, 1, "an unsubscribed handler must stop receiving actions");
  assert.equal(received2.length, 1, "a freshly subscribed handler receives actions");
}
ok("client_action is deferred then dispatched to the subscribed surface (real bus)");

// ===========================================================================
// 2. A navigate_to is mapped web→expo and pushed onto the router, DEFERRED
//    (the reply/TTS gets a beat first — parity with the web spec's intent).
// ===========================================================================
console.log("[test-voice-bridge] navigate_to deferral + route mapping");

{
  const { bridge, pushed } = loadVoiceBridge({
    turn: {
      reply: "Opening your links",
      audio_base64: "AAAA",
      tool_results: [{ result: { navigate_to: "https://app.1in.me/user/links" } }],
    },
  });

  await bridge.sendClip(CLIP);
  // Deferred: navigation has NOT happened synchronously with the turn.
  assert.equal(
    pushed.length,
    0,
    "navigate_to must be deferred, not pushed during the turn (speech first)",
  );

  await sleep(700);
  assert.deepEqual(
    pushed,
    ["/(tabs)/links"],
    "the deferred nav must push the mapped expo-router path exactly once",
  );
}
ok("navigate_to is deferred then pushed as the mapped expo-router path");

// ===========================================================================
// 3. mapNavTarget translates the web's full URLs to expo-router paths, and
//    returns null for unknown targets (so the user is never bounced out).
// ===========================================================================
console.log("[test-voice-bridge] mapNavTarget translation table");

{
  const { bridge } = loadVoiceBridge();
  const map = bridge.mapNavTarget;
  const cases = [
    ["https://app.1in.me/user/dashboard", "/(tabs)"],
    ["https://app.1in.me/user/links?type=qr", "/(tabs)/links"],
    ["https://app.1in.me/user/inbox", "/(tabs)/inbox"],
    ["https://app.1in.me/user/notifications", "/(tabs)/notifications"],
    ["https://app.1in.me/user/wallet", "/wallet"],
    ["https://app.1in.me/user/ai/credits", "/wallet"],
    ["https://app.1in.me/user/ai/companion", "/ai-coach"],
    ["https://app.1in.me/user/ai/ask-coach", "/ask-coach"],
    ["https://app.1in.me/user/ai/personas", "/ai-persona"],
    ["https://app.1in.me/user/upgrade", "/upgrade"],
    ["https://app.1in.me/user/profile", "/(tabs)/profile"],
  ];
  for (const [url, expected] of cases) {
    assert.equal(map(url), expected, `${url} → ${expected}`);
  }
  // Unknown / off-app targets are ignored (no jump to a browser).
  assert.equal(map("https://example.com/somewhere"), null, "unknown target → null");
  assert.equal(map(undefined), null, "missing target → null");
}
ok("mapNavTarget maps known dashboard URLs and returns null for unknown ones");

{
  // An unknown navigate_to must NOT cause any router.push.
  const { bridge, pushed } = loadVoiceBridge({
    turn: {
      reply: "Hmm",
      tool_results: [{ result: { navigate_to: "https://example.com/nope" } }],
    },
  });
  await bridge.sendClip(CLIP);
  await sleep(700);
  assert.equal(pushed.length, 0, "an unmapped navigate_to must not navigate anywhere");
}
ok("an unmapped navigate_to is ignored (no stray navigation)");

// ===========================================================================
// 4. The active surface hint is forwarded to the server on every turn — the
//    sharp edge of the surface-bridge contract (mobile sends a bare string).
// ===========================================================================
console.log("[test-voice-bridge] surface hint forwarding");

{
  const { bridge, runTurnArgs } = loadVoiceBridge();
  // No surface set on focus → undefined hint.
  await bridge.sendClip(CLIP);
  assert.equal(
    runTurnArgs[0].surface,
    undefined,
    "with no focused surface, the turn must send surface = undefined",
  );

  // A focused screen sets the surface; the next turn forwards it.
  bridge.setVoiceSurface("wizard");
  await bridge.sendClip(CLIP);
  assert.equal(
    runTurnArgs[1].surface,
    "wizard",
    "the focused surface must be forwarded to the turn",
  );

  // Blur clears it again.
  bridge.setVoiceSurface(null);
  await bridge.sendClip(CLIP);
  assert.equal(
    runTurnArgs[2].surface,
    undefined,
    "clearing the surface (blur) must drop the hint again",
  );
}
ok("the focused surface string is forwarded to runTurn, and cleared on blur");

// ===========================================================================
// 5. nfc_write, TTS audio, and the no-audio fast path.
// ===========================================================================
console.log("[test-voice-bridge] nfc_write + TTS audio side effects");

{
  const { bridge, state } = loadVoiceBridge({
    turn: {
      tool_results: [
        { result: { nfc_write: { link_id: 42, alias: "promo", url: "https://1in.me/promo" } } },
      ],
    },
  });
  await bridge.sendClip(CLIP);
  assert.deepEqual(
    state.nfcReq,
    { linkId: 42, url: "https://1in.me/promo" },
    "an nfc_write tool result must stage the NFC write request",
  );
}
ok("an nfc_write tool result stages the NFC write request");

{
  // audio_base64 present → TTS plays, no immediate idle (the player drives it).
  const { bridge, state, playedAudio } = loadVoiceBridge({
    turn: { reply: "Here you go", audio_base64: "QkFTRTY0" },
  });
  await bridge.sendClip(CLIP);
  assert.deepEqual(playedAudio, ["QkFTRTY0"], "the returned TTS clip must be played");
  assert.notEqual(
    state.phase,
    "idle",
    "with TTS audio, the turn must not jump straight to idle (the player ends it)",
  );
}
ok("a TTS clip is played and the turn waits on the player (not idle)");

{
  // No audio → the turn ends immediately (idle).
  const { bridge, state, playedAudio } = loadVoiceBridge({
    turn: { reply: "Done", audio_base64: null },
  });
  await bridge.sendClip(CLIP);
  assert.equal(playedAudio.length, 0, "no clip means nothing is played");
  assert.equal(state.phase, "idle", "with no TTS audio the turn returns to idle at once");
}
ok("with no TTS audio the turn returns to idle immediately");

// ===========================================================================
// 6. Turn bookkeeping + error handling.
// ===========================================================================
console.log("[test-voice-bridge] turn state + error handling");

{
  const { bridge, state } = loadVoiceBridge({
    turn: {
      transcript: "show my links",
      reply: "Opening links",
      balance: 73,
      messages: [{ role: "assistant", content: "Opening links" }],
      pending_confirmations: [
        { confirm_required: true, tool: "delete_link", arguments: {}, description: "Delete?" },
      ],
    },
  });
  await bridge.sendClip(CLIP);
  assert.equal(state.transcript, "show my links", "transcript is stored");
  assert.equal(state.reply, "Opening links", "reply is stored");
  assert.equal(state.balance, 73, "balance is stored");
  assert.deepEqual(state.history, [{ role: "assistant", content: "Opening links" }], "history is stored");
  assert.equal(state.pending.length, 1, "pending confirmations are surfaced");
}
ok("a turn stores transcript/reply/balance/history and surfaces confirmations");

{
  // A 402 (out of coins) surfaces a friendly error and returns to idle.
  const { bridge, state } = loadVoiceBridge({
    runTurnImpl: async () => {
      throw { status: 402, message: "You're out of coins — top up to keep going." };
    },
  });
  await bridge.sendClip(CLIP);
  assert.match(state.error ?? "", /out of coins/, "a 402 must surface its message");
  assert.equal(state.phase, "idle", "after an error the turn returns to idle");
}
ok("a 402 turn surfaces an out-of-coins message and returns to idle");

{
  // A generic failure surfaces a fallback message (still no crash).
  const { bridge, state } = loadVoiceBridge({
    runTurnImpl: async () => {
      throw { status: 500, message: "boom" };
    },
  });
  await bridge.sendClip(CLIP);
  assert.equal(state.error, "boom", "a non-402 error surfaces its message");
  assert.equal(state.phase, "idle", "a non-402 error also returns to idle");
}
ok("a non-402 turn failure surfaces its message and returns to idle");

// ===========================================================================
// 7. Wiring guards — pin the lifted bridge to the screens & API that use it,
//    so the logic above can't silently drift from the real surfaces.
// ===========================================================================
console.log("[test-voice-bridge] screen + API wiring guards");

// runTurn (lib/api/voice.ts) POSTs the turn and forwards the surface hint as a
// bare string inside the multipart `context` payload.
assert.match(
  voiceApiSrc,
  /`\$\{getBaseUrl\(\)\}\/api\/v1\/ai\/voice\/turn`/,
  "runTurn must POST to /api/v1/ai/voice/turn",
);
assert.match(
  voiceApiSrc,
  /\.\.\.\(args\.surface \? \{ surface: args\.surface \} : \{\}\)/,
  "runTurn must forward the surface hint (bare string) in the context payload",
);
ok("runTurn posts the turn and forwards the surface hint");

// The component auto-plays a returned TTS clip and, when it finishes, returns
// the UI to idle (the deferred-nav 'speech first' guarantee on mobile).
assert.match(
  vaSrc,
  /if \(out\.audio_base64\) \{\s*void playBase64\(out\.audio_base64\);/,
  "the turn handler must play a returned TTS clip",
);
assert.match(
  vaSrc,
  /didJustFinish[\s\S]*?setPhase\(\(p\) => \(p === "speaking" \? "idle" : p\)\)/,
  "the player must return the UI to idle when the spoken reply finishes",
);
ok("the component plays TTS audio and idles when the reply ends");

// create tab: a "create a vCard" turn returns select_link_type, which the
// create surface acts on; it registers on focus and clears on blur.
assert.match(
  createSrc,
  /a\.type === "select_link_type" && "link_type" in a/,
  "the create surface must handle the select_link_type action",
);
assert.match(
  createSrc,
  /setVoiceSurface\("create_link"\);[\s\S]*?onVoiceAction\(\(a\) => voiceHandlerRef\.current\(a\)\)/,
  "the create surface must register on the voice bus when focused",
);
ok("the create tab handles select_link_type and registers on the bus");

// links tab: a spoken "find my … link" → search action drops into the query.
assert.match(
  linksSrc,
  /a\.type === "search" && "query" in a/,
  "the links surface must handle the search action",
);
assert.match(
  linksSrc,
  /setVoiceSurface\("app"\);[\s\S]*?onVoiceAction\(\(a\) => voiceHandlerRef\.current\(a\)\)/,
  "the links surface must register on the voice bus when focused",
);
ok("the links tab handles search and registers on the bus");

// wizard: wizard_set_answer / wizard_advance / wizard_generate drive the steps.
assert.match(
  wizardSrc,
  /a\.type === "wizard_set_answer" && "field" in a/,
  "the wizard must handle wizard_set_answer",
);
assert.match(wizardSrc, /a\.type === "wizard_advance"/, "the wizard must handle wizard_advance");
assert.match(wizardSrc, /a\.type === "wizard_generate"/, "the wizard must handle wizard_generate");
assert.match(
  wizardSrc,
  /setVoiceSurface\("wizard"\);[\s\S]*?onVoiceAction\(\(a\) => voiceHandlerRef\.current\(a\)\)/,
  "the wizard must register on the voice bus when focused",
);
ok("the wizard handles its voice actions and registers on the bus");

console.log(`\n[test-voice-bridge] all ${passed} checks passed`);
