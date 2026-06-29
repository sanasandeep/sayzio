import { describe, it, expect, beforeEach, vi } from "vitest";
import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import SiteAssistant from "@/components/site-assistant";
import { ThemeProvider } from "@/components/theme-provider";

/**
 * Sign-out + session-expiry recovery coverage for the marketing-site "Ask Zio"
 * widget (the cross-origin React client at src/components/site-assistant.tsx).
 *
 * The widget carries a Sanctum bearer token (sa_auth_token_v1) for the signed-in
 * visitor and a separate anonymous visitor_token (sa_visitor_token_v1) that keeps
 * the conversation continuous. These tests assert that signing out OR a 401 on a
 * gated /assistant/* call re-shows the in-chat login gate WITHOUT discarding the
 * anonymous visitor_token / visible conversation.
 */

const AUTH_TOKEN_KEY = "sa_auth_token_v1";
const VISITOR_TOKEN_KEY = "sa_visitor_token_v1";

type FetchInit = { method?: string; headers?: Record<string, string> } | undefined;

function jsonResponse(status: number, data: unknown) {
  return Promise.resolve({
    status,
    ok: status >= 200 && status < 300,
    json: async () => data,
  });
}

function hasBearer(init: FetchInit): boolean {
  const auth = init?.headers?.Authorization || "";
  return /^Bearer\s+\S+/.test(auth);
}

function renderWidget() {
  // defaultTheme "light" keeps useIsDark off the system-theme matchMedia path.
  return render(
    <ThemeProvider defaultTheme="light" storageKey="vite-ui-theme-test">
      <SiteAssistant />
    </ThemeProvider>,
  );
}

beforeEach(() => {
  localStorage.clear();
});

describe("SiteAssistant — marketing widget sign-out", () => {
  it("shows Sign out when signed in, then click re-shows the login gate while the visitor token + conversation are preserved", async () => {
    localStorage.setItem(AUTH_TOKEN_KEY, "valid-bearer-token");
    localStorage.setItem(VISITOR_TOKEN_KEY, "visitor-abc");

    const fetchMock = vi.fn((url: string, init: FetchInit) => {
      if (url.includes("/assistant/bootstrap")) {
        // Signed in only while the bearer token rides along; once Sign out drops
        // it the anonymous bootstrap reports the gate is required again.
        if (hasBearer(init)) {
          return jsonResponse(200, {
            enabled: true,
            auth_required: false,
            email_otp_enabled: true,
            greeting: "Welcome back to Ask Zio",
          });
        }
        return jsonResponse(200, {
          enabled: true,
          auth_required: true,
          auth_required_note: "Please log in to chat with us.",
          email_otp_enabled: true,
        });
      }
      if (url.includes("/assistant/session")) {
        // The visitor_token / conversation is the same before and after sign-out.
        return jsonResponse(200, {
          ok: true,
          visitor_token: "visitor-abc",
          messages: [{ role: "assistant", content: "Conversation kept alive" }],
        });
      }
      return jsonResponse(200, { ok: true });
    });
    vi.stubGlobal("fetch", fetchMock);

    renderWidget();

    fireEvent.click(await screen.findByRole("button", { name: "Open assistant" }));

    const signOut = await screen.findByRole("button", { name: "Sign out" });
    expect(signOut).toBeTruthy();
    expect(await screen.findByText("Conversation kept alive")).toBeTruthy();

    fireEvent.click(signOut);

    // Login gate re-appears in place (email OTP form → "Send code" button).
    expect(await screen.findByRole("button", { name: /send code/i })).toBeTruthy();
    // Signed-out: the Sign out control is gone.
    await waitFor(() =>
      expect(screen.queryByRole("button", { name: "Sign out" })).toBeNull(),
    );

    // The bearer token is dropped, but the anonymous visitor token survives.
    expect(localStorage.getItem(AUTH_TOKEN_KEY)).toBeNull();
    expect(localStorage.getItem(VISITOR_TOKEN_KEY)).toBe("visitor-abc");
    // The conversation is preserved across the re-bootstrap.
    expect(screen.getByText("Conversation kept alive")).toBeTruthy();
  });
});

describe("SiteAssistant — marketing widget 401 recovery", () => {
  it("silently re-shows the login gate (no error bubble) when a gated /assistant/message 401s on a stale bearer token", async () => {
    localStorage.setItem(AUTH_TOKEN_KEY, "stale-bearer-token");
    localStorage.setItem(VISITOR_TOKEN_KEY, "visitor-xyz");

    const fetchMock = vi.fn((url: string, _init: FetchInit) => {
      if (url.includes("/assistant/bootstrap")) {
        // Bootstrap still reports a signed-in session (token not yet rejected),
        // so the composer renders and the visitor can type a message.
        return jsonResponse(200, {
          enabled: true,
          auth_required: false,
          email_otp_enabled: true,
          greeting: "Hi there",
        });
      }
      if (url.includes("/assistant/session")) {
        return jsonResponse(200, {
          ok: true,
          visitor_token: "visitor-xyz",
          messages: [{ role: "assistant", content: "Hi there" }],
        });
      }
      if (url.includes("/assistant/message")) {
        // The stored bearer token was revoked mid-chat: the gated call 401s.
        return jsonResponse(401, { error: { message: "Unauthenticated." } });
      }
      return jsonResponse(200, { ok: true });
    });
    vi.stubGlobal("fetch", fetchMock);

    renderWidget();

    fireEvent.click(await screen.findByRole("button", { name: "Open assistant" }));

    // Signed-in composer present.
    const textarea = await screen.findByPlaceholderText("Type a message…");
    expect(await screen.findByRole("button", { name: "Sign out" })).toBeTruthy();

    fireEvent.change(textarea, { target: { value: "Will this 401?" } });
    fireEvent.keyDown(textarea, { key: "Enter", shiftKey: false });

    // The 401 silently re-shows the login gate in place …
    expect(await screen.findByRole("button", { name: /send code/i })).toBeTruthy();
    // … and the user's message stays, but NO raw error bubble is rendered.
    expect(screen.getByText("Will this 401?")).toBeTruthy();
    expect(screen.queryByText(/network error/i)).toBeNull();
    expect(screen.queryByText(/something went wrong/i)).toBeNull();

    // Dead bearer token dropped; anonymous visitor token + conversation kept.
    expect(localStorage.getItem(AUTH_TOKEN_KEY)).toBeNull();
    expect(localStorage.getItem(VISITOR_TOKEN_KEY)).toBe("visitor-xyz");
  });
});
