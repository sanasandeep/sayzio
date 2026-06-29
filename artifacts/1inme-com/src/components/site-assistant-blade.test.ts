import { describe, it, expect, beforeEach, afterEach, vi } from "vitest";
import { waitFor } from "@testing-library/dom";
import { readFileSync } from "fs";
import path from "path";

/**
 * Session-expiry recovery coverage for the Laravel-rendered "Ask Zio" widget
 * (the in-app blade partial that shares the /assistant/* contract with the
 * marketing React widget — the two front-ends must stay in lockstep).
 *
 * There is no JS toolchain on the Laravel side, so this is a source-driven test:
 * we read the sibling blade file, strip its Laravel directives, execute the
 * widget's IIFE inside jsdom, then assert that a 401 on the gated /assistant/stream
 * send re-shows the in-chat login gate IN PLACE without discarding the anonymous
 * visitor_token / visible conversation and without surfacing a raw error bubble.
 */

const BLADE_PATH = path.resolve(
  import.meta.dirname,
  "../../../1inme/resources/views/common/partials/site-assistant.blade.php",
);

const VISITOR_TOKEN_KEY = "sa_visitor_token_v1";

// Pull just the <script> body out of the blade partial.
function extractScript(raw: string): string {
  const open = raw.indexOf("<script>");
  const close = raw.indexOf("</script>", open);
  if (open === -1 || close === -1) throw new Error("widget <script> not found");
  return raw.slice(open + "<script>".length, close);
}

// Replace every `@json(<balanced-parens>)` with an empty-string literal. The
// blade only feeds @json server-localized strings (and one Array.isArray-guarded
// tooltip array), so "" is a safe, syntactically valid stand-in at runtime.
function stripJsonDirectives(src: string): string {
  let out = "";
  let i = 0;
  for (;;) {
    const at = src.indexOf("@json(", i);
    if (at === -1) {
      out += src.slice(i);
      break;
    }
    out += src.slice(i, at);
    let depth = 0;
    let j = at + "@json".length;
    for (; j < src.length; j++) {
      const ch = src[j];
      if (ch === "(") depth++;
      else if (ch === ")") {
        depth--;
        if (depth === 0) {
          j++;
          break;
        }
      }
    }
    out += '""';
    i = j;
  }
  return out;
}

function cleanWidgetScript(): string {
  const raw = readFileSync(BLADE_PATH, "utf8");
  let code = extractScript(raw);
  // Drop the inline @php ... @endphp block (tooltip seed computation).
  code = code.replace(/@php[\s\S]*?@endphp/g, "");
  code = stripJsonDirectives(code);
  if (code.includes("@json") || code.includes("@php")) {
    throw new Error("uncleaned blade directive remains in widget script");
  }
  return code;
}

function mountRoot() {
  document.body.innerHTML = `
    <div id="site-assistant-root"
      data-surface="app"
      data-route="dashboard"
      data-path="/dashboard"
      data-title="Dashboard"
      data-position="right"
      data-accent="#3d6bff"
      data-avatar=""
      data-brand="Ask Zio"
      data-peek-avatar="/peek.png"
      data-bootstrap-url="/assistant/bootstrap"
      data-session-url="/assistant/session"
      data-message-url="/assistant/message"
      data-stream-url="/assistant/stream"
      data-choice-url="/assistant/choice"
      data-handoff-url="/assistant/handoff"
      data-quick-contact-url="/assistant/quick-contact"
      data-low-balance-click-url="/assistant/low-balance-click"
      data-send-code-url="/assistant/auth/send-code"
      data-verify-code-url="/assistant/auth/verify-code"></div>`;
}

function runWidget() {
  const code = cleanWidgetScript();
  // eslint-disable-next-line no-new-func
  new Function(code)();
}

beforeEach(() => {
  localStorage.clear();
  document.documentElement.className = "";
});

afterEach(() => {
  document.body.innerHTML = "";
  vi.restoreAllMocks();
});

describe("Ask Zio blade widget — expired web session recovery", () => {
  it("re-shows the in-chat login gate in place on a 401, preserving the visitor token + conversation and showing no error bubble", async () => {
    localStorage.setItem(VISITOR_TOKEN_KEY, "visitor-blade");

    const fetchMock = vi.fn((url: string) => {
      if (url.includes("/assistant/bootstrap")) {
        return Promise.resolve({
          status: 200,
          ok: true,
          json: async () => ({
            auth_required: false,
            email_otp_enabled: true,
            mobile_login_enabled: false,
            greeting: "",
            templates: [],
            starter_prompts: [],
          }),
        });
      }
      if (url.includes("/assistant/session")) {
        return Promise.resolve({
          status: 200,
          ok: true,
          json: async () => ({
            ok: true,
            visitor_token: "visitor-blade",
            messages: [{ role: "assistant", content: "Welcome to chat" }],
          }),
        });
      }
      if (url.includes("/assistant/stream")) {
        // Web session expired/revoked mid-chat: the gated stream call 401s.
        return Promise.resolve({ status: 401, ok: false, json: async () => ({}) });
      }
      return Promise.resolve({ status: 200, ok: true, json: async () => ({ ok: true }) });
    });
    vi.stubGlobal("fetch", fetchMock);

    mountRoot();
    runWidget();

    // Open the panel → bootstrap() + session() run.
    const launcher = document.getElementById("sa-launcher") as HTMLButtonElement;
    expect(launcher).toBeTruthy();
    launcher.click();

    // Wait for the seeded conversation to paint.
    await waitFor(() => {
      const msgs = document.querySelectorAll("#sa-body .sa-msg");
      expect(msgs.length).toBe(1);
      expect(msgs[0].textContent).toContain("Welcome to chat");
    });

    // Type a message and send it → streamMessage() → 401.
    const input = document.getElementById("sa-input") as HTMLTextAreaElement;
    const send = document.getElementById("sa-send") as HTMLButtonElement;
    input.value = "Anyone there?";
    send.click();

    // The 401 swaps the composer for the in-chat login gate (email OTP form).
    await waitFor(() => {
      expect(document.querySelector("#sa-panel .sa-input-row.sa-gate")).toBeTruthy();
      expect(document.querySelector(".sa-gate-input")).toBeTruthy();
    });

    // The original composer textarea is gone (replaced by the gate).
    expect(document.getElementById("sa-input")).toBeNull();

    // No raw error bubble was rendered: only the welcome + the user's message
    // remain (the empty streaming placeholder was removed on 401).
    const finalMsgs = document.querySelectorAll("#sa-body .sa-msg");
    expect(finalMsgs.length).toBe(2);
    expect(document.querySelector("#sa-body .sa-msg.error")).toBeNull();
    expect(
      Array.from(finalMsgs).some((m) => m.textContent?.includes("Anyone there?")),
    ).toBe(true);

    // The anonymous visitor token / conversation is left untouched.
    expect(localStorage.getItem(VISITOR_TOKEN_KEY)).toBe("visitor-blade");
  });
});
