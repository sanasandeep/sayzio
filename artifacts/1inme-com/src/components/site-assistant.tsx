import { useCallback, useEffect, useRef, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { ArrowLeft, LogOut, Phone } from "lucide-react";
import { useTheme } from "@/components/theme-provider";
import { ASSISTANT_API_BASE, LOGIN_URL } from "@/config";
import zioBotMascot from "@assets/ChatGPT_Image_Jun_26,_2026_at_09_24_23_AM_1782451375104.png";
import zioBotPeek from "@assets/ChatGPT_Image_Jun_26,_2026_at_11_40_07_AM_1782454328455.png";

/**
 * Floating "Zio Bot" assistant widget for the marketing site.
 *
 * The marketing site has no AI runtime of its own — this widget is a thin
 * cross-origin client for the product app's site-wide assistant endpoints
 * (`/assistant/*` on 1in.me, base from config.ASSISTANT_API_BASE). Visitors
 * here are always anonymous, so the backend forces the "marketing" surface
 * and no cookies/CSRF are needed; we send no credentials.
 *
 * It mirrors the Laravel widget's runtime contract (bootstrap → session →
 * message/choice/handoff) and renders the same block types (buttons, list,
 * image, form), but uses the non-streaming /assistant/message endpoint for
 * simplicity and cross-origin reliability.
 *
 * Default mascot — single source of truth: the backend. Both the launcher
 * and the chat header resolve the avatar from the `avatar_url` returned by
 * /assistant/bootstrap (see SiteAssistantSettings::avatarUrlFor in the
 * Laravel app, which falls back to public/branding/zio-bot.png when no
 * admin avatar is set). We fetch bootstrap once on mount so the launcher
 * reflects a backend mascot change with no React edit. The bundled
 * `zioBotMascot` import below is ONLY an offline fallback for first paint /
 * when the backend is unreachable.
 */

const BRAND_ACCENT = "#3d6bff";
const TOKEN_KEY = "sa_visitor_token_v1";
// Sanctum bearer token minted by in-chat passwordless login. Distinct from
// the anonymous visitor_token above (conversation continuity): this one
// authenticates the visitor so the cross-origin widget can chat in place.
const AUTH_TOKEN_KEY = "sa_auth_token_v1";

function readAuthToken(): string {
  try {
    return (typeof window !== "undefined" && localStorage.getItem(AUTH_TOKEN_KEY)) || "";
  } catch {
    return "";
  }
}

/** Drop the stored bearer token (sign-out / token rejected). Leaves the
 *  anonymous visitor_token + conversation untouched. */
function clearAuthToken(): void {
  try {
    if (typeof window !== "undefined") localStorage.removeItem(AUTH_TOKEN_KEY);
  } catch {
    /* ignore storage failures */
  }
}

/** Thrown by postJson when a gated /assistant/* call is rejected with 401
 *  (token expired/revoked). Callers swallow it instead of rendering a raw
 *  error — the registered handler re-shows the login gate in place. */
class AssistantUnauthorizedError extends Error {
  constructor() {
    super("assistant_unauthorized");
    this.name = "AssistantUnauthorizedError";
  }
}

// Module-level hook the mounted widget registers so postJson (which lives
// outside the component) can re-show the login gate when a bearer token is
// rejected mid-chat. Cleared on unmount.
let onUnauthorized: (() => void) | null = null;
function setUnauthorizedHandler(fn: (() => void) | null): void {
  onUnauthorized = fn;
}

interface AssistantBlockOption {
  label?: string;
  value?: string;
  title?: string;
  description?: string;
  thumbnail?: string;
  action?: string;
}
interface AssistantFormField {
  name?: string;
  label?: string;
  type?: string;
  required?: boolean;
}
interface AssistantBlock {
  type: "buttons" | "list" | "image" | "form" | string;
  template?: string | null;
  options?: AssistantBlockOption[];
  images?: { src?: string; alt?: string }[];
  src?: string;
  alt?: string;
  fields?: AssistantFormField[];
  submit_label?: string;
  action?: string;
}
interface AssistantMessage {
  role: "assistant" | "user";
  content?: string;
  blocks?: AssistantBlock[] | null;
}
interface BootstrapResponse {
  enabled: boolean;
  accent_color?: string;
  avatar_url?: string;
  brand_name?: string;
  greeting?: string;
  starter_prompts?: string[];
  input_placeholder?: string;
  send_label?: string;
  subheading?: string;
  handoff_enabled?: boolean;
  auth_required?: boolean;
  auth_required_note?: string;
  login_url?: string;
  email_otp_enabled?: boolean;
  mobile_login_enabled?: boolean;
  registration_paused?: boolean;
}
interface SessionResponse {
  ok?: boolean;
  visitor_token?: string;
  greeting?: string;
  messages?: AssistantMessage[];
  page_suggestions?: (string | { label?: string })[];
  starter_prompts?: string[];
  handed_off?: boolean;
}
interface TurnResponse {
  ok?: boolean;
  visitor_token?: string;
  assistant_message?: AssistantMessage;
  handed_off?: boolean;
  error?: string;
}
interface QuickContactResponse {
  ok?: boolean;
  message?: string;
  error?: string;
}

function escapeHtml(s: string): string {
  return String(s ?? "").replace(/[&<>"']/g, (c) => {
    const map: Record<string, string> = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    };
    return map[c];
  });
}

/** Tiny markdown subset matching the Laravel widget's mdLite. */
function mdLite(s: string): string {
  let out = escapeHtml(s);
  out = out.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
  out = out.replace(/\*([^*]+)\*/g, "<em>$1</em>");
  out = out.replace(/`([^`]+)`/g, "<code>$1</code>");
  out = out.replace(
    /\[([^\]]+)\]\((https?:\/\/[^)]+)\)/g,
    '<a href="$2" target="_blank" rel="noopener" style="color:#7d9bff;text-decoration:underline">$1</a>'
  );
  out = out.replace(/\n/g, "<br>");
  return out;
}

function api(path: string): string {
  return `${ASSISTANT_API_BASE}${path}`;
}

export async function postJson<T>(path: string, body: unknown): Promise<T> {
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
    Accept: "application/json",
  };
  // After in-chat login the cross-origin widget has no session cookie, so it
  // carries the Sanctum bearer token on every /assistant/* call.
  const tok = readAuthToken();
  if (tok) headers.Authorization = `Bearer ${tok}`;
  const res = await fetch(api(path), {
    method: "POST",
    headers,
    body: JSON.stringify(body),
  });
  // A stored bearer token that's expired/revoked yields 401 on any gated
  // /assistant/* call. Drop the dead token and re-show the login gate
  // instead of bubbling up a raw error — the conversation/visitor_token
  // is left intact so the visitor can sign back in in place.
  if (res.status === 401) {
    clearAuthToken();
    onUnauthorized?.();
    throw new AssistantUnauthorizedError();
  }
  return (await res.json()) as T;
}

export function useIsDark(): boolean {
  const { theme } = useTheme();
  const [systemDark, setSystemDark] = useState(
    () =>
      typeof window !== "undefined" &&
      window.matchMedia?.("(prefers-color-scheme: dark)").matches
  );
  useEffect(() => {
    if (theme !== "system") return;
    const mq = window.matchMedia("(prefers-color-scheme: dark)");
    const onChange = () => setSystemDark(mq.matches);
    mq.addEventListener("change", onChange);
    return () => mq.removeEventListener("change", onChange);
  }, [theme]);
  if (theme === "dark") return true;
  if (theme === "light") return false;
  return systemDark;
}

export default function SiteAssistant() {
  const isDark = useIsDark();
  const [open, setOpen] = useState(false);
  const [booted, setBooted] = useState(false);
  const [sending, setSending] = useState(false);
  const [cfg, setCfg] = useState<BootstrapResponse | null>(null);
  const [messages, setMessages] = useState<AssistantMessage[]>([]);
  const [suggested, setSuggested] = useState<string[]>([]);
  const [handedOff, setHandedOff] = useState(false);
  // "Contact us" view: the former standalone quick-contact widget, folded
  // into the assistant. Unlike the chat it is NOT login-gated — it posts to
  // /assistant/quick-contact (anonymous-friendly, time-trap protected) and
  // lands in the admin Contact Inbox.
  const [contactView, setContactView] = useState(false);
  const [contactBusy, setContactBusy] = useState(false);
  const [contactDone, setContactDone] = useState("");
  const [contactError, setContactError] = useState("");
  // Time-trap: stamp when the contact form opened so the server can reject a
  // submission filled+posted implausibly fast. A same-clock delta.
  const contactOpenedAt = useRef<number>(Date.now());
  // Login gate: the marketing surface is always anonymous, so the server
  // returns auth_required=true whenever the admin gate is on. We then
  // swap the composer for a login CTA and block message sending.
  const [authRequired, setAuthRequired] = useState(false);
  const [authNote, setAuthNote] = useState("Please log in to chat with us.");
  const [loginUrl, setLoginUrl] = useState(LOGIN_URL);
  // Which passwordless methods the in-chat login form may offer (from
  // bootstrap). When both are false the gate shows the full-page login CTA.
  const [emailOtpEnabled, setEmailOtpEnabled] = useState(true);
  const [mobileLoginEnabled, setMobileLoginEnabled] = useState(false);
  const [input, setInput] = useState("");
  const [tooltip, setTooltip] = useState<string | null>(null);
  // Default mascot resolved from the backend bootstrap, populated on mount
  // so the launcher reflects an admin mascot change before the chat is
  // ever opened. Empty until the first bootstrap resolves (then the
  // bundled import is used as the offline fallback).
  const [bootAvatar, setBootAvatar] = useState("");
  // Whether a Sanctum bearer token is currently stored (signed in via the
  // in-chat login). Drives the header "Sign out" control. Kept in state so
  // the control appears/disappears as the token is minted/cleared.
  const [authed, setAuthed] = useState<boolean>(() => !!readAuthToken());

  const tokenRef = useRef<string>(
    (typeof window !== "undefined" && localStorage.getItem(TOKEN_KEY)) || ""
  );
  const bodyRef = useRef<HTMLDivElement | null>(null);
  const tooltipDismissed = useRef(false);
  // Cache the bootstrap fetch so the mount-time avatar load and the
  // open-time full boot share a single network request.
  const bootstrapPromiseRef = useRef<Promise<BootstrapResponse | null> | null>(
    null
  );

  const setToken = useCallback((t?: string) => {
    if (t && t.trim()) {
      tokenRef.current = t;
      try {
        localStorage.setItem(TOKEN_KEY, t);
      } catch {
        /* ignore */
      }
    }
  }, []);

  const scrollBottom = useCallback(() => {
    requestAnimationFrame(() => {
      const b = bodyRef.current;
      if (b) b.scrollTop = b.scrollHeight;
    });
  }, []);

  // Fetch (and cache) the assistant bootstrap config. Shared by the
  // mount-time avatar load and the open-time full boot so we only hit
  // /assistant/bootstrap once. Resolves null when the backend is
  // unreachable so callers fall back to the bundled mascot.
  const loadBootstrap = useCallback((): Promise<BootstrapResponse | null> => {
    if (!bootstrapPromiseRef.current) {
      bootstrapPromiseRef.current = (async () => {
        try {
          const bootHeaders: Record<string, string> = { Accept: "application/json" };
          const tok = readAuthToken();
          if (tok) bootHeaders.Authorization = `Bearer ${tok}`;
          const res = await fetch(api("/assistant/bootstrap?surface=marketing"), {
            headers: bootHeaders,
          });
          return (await res.json()) as BootstrapResponse;
        } catch {
          // Don't cache a transient failure — drop the cached promise so a
          // later open (or re-open) retries the fetch instead of being
          // permanently poisoned with a null result.
          bootstrapPromiseRef.current = null;
          return null;
        }
      })();
    }
    return bootstrapPromiseRef.current;
  }, []);

  // Resolve the default mascot from the backend on mount so the launcher
  // shows the current admin mascot without waiting for the chat to open.
  useEffect(() => {
    let cancelled = false;
    void loadBootstrap().then((data) => {
      if (cancelled) return;
      const url = (data?.avatar_url || "").trim();
      if (url) setBootAvatar(url);
    });
    return () => {
      cancelled = true;
    };
  }, [loadBootstrap]);

  // ── bootstrap + session (runs once, on first open) ──────────────
  const boot = useCallback(async () => {
    setBooted(true);
    try {
      const data = await loadBootstrap();
      if (!data) {
        // Backend unreachable — NOT the same as "disabled". Fall through to
        // the catch so the launcher stays visible (with the bundled
        // fallback mascot) and shows a recoverable message; booted is reset
        // there so a later re-open retries.
        throw new Error("bootstrap_unreachable");
      }
      if (!data.enabled) {
        setCfg({ enabled: false });
        return;
      }
      setCfg(data);
      if (data.auth_required) {
        // auth_required while a bearer token is stored means the token was
        // expired/revoked (a valid one would resolve a user). Drop the dead
        // token so we stop replaying it; the visitor_token/conversation stays.
        clearAuthToken();
        setAuthed(false);
        setAuthRequired(true);
        if (data.auth_required_note) setAuthNote(data.auth_required_note);
        if (data.login_url) setLoginUrl(data.login_url);
      } else {
        setAuthRequired(false);
        setAuthed(!!readAuthToken());
      }
      if (typeof data.email_otp_enabled === "boolean")
        setEmailOtpEnabled(data.email_otp_enabled);
      if (typeof data.mobile_login_enabled === "boolean")
        setMobileLoginEnabled(data.mobile_login_enabled);
      const session = await postJson<SessionResponse>("/assistant/session", {
        visitor_token: tokenRef.current,
        surface: "marketing",
        page: pageMeta(),
      });
      if (session?.visitor_token) setToken(session.visitor_token);
      if (session?.messages && session.messages.length) {
        setMessages(session.messages);
      } else {
        const greeting =
          data.greeting ||
          session?.greeting ||
          "Hi! I'm Zio Bot. How can I help?";
        setMessages([{ role: "assistant", content: greeting }]);
      }
      const combined = [
        ...(session?.page_suggestions || []).map((p) =>
          typeof p === "string" ? p : p.label || ""
        ),
        ...(session?.starter_prompts || data.starter_prompts || []),
      ].filter(Boolean) as string[];
      setSuggested(combined);
      if (session?.handed_off) setHandedOff(true);
    } catch {
      // Reset booted so re-opening the chat retries the connection. The
      // launcher itself stays mounted (cfg is never set to enabled:false
      // here), so the bundled fallback mascot remains visible offline.
      setBooted(false);
      setMessages([
        {
          role: "assistant",
          content:
            "I couldn't connect right now. Please try again in a moment.",
        },
      ]);
    } finally {
      scrollBottom();
    }
  }, [scrollBottom, setToken, loadBootstrap]);

  const toggle = useCallback(
    (next: boolean) => {
      setOpen(next);
      if (next) {
        setTooltip(null);
        tooltipDismissed.current = true;
        if (!booted) void boot();
      } else {
        setContactView(false);
      }
    },
    [booted, boot]
  );

  // In-chat passwordless login succeeded on the marketing (cross-origin)
  // widget: persist the bearer token and re-bootstrap as the signed-in user
  // so the chat unlocks in place — no full-page redirect.
  const onAuthSuccess = useCallback(
    (tok: string) => {
      try {
        localStorage.setItem(AUTH_TOKEN_KEY, tok);
      } catch {
        /* ignore */
      }
      setAuthed(true);
      setAuthRequired(false);
      setBooted(false);
      // Force a fresh bootstrap (now authenticated) instead of the cached one.
      bootstrapPromiseRef.current = null;
      void boot();
    },
    [boot]
  );

  // Header "Sign out" control: clear the bearer token and re-show the login
  // gate WITHOUT touching the anonymous visitor_token / conversation, then
  // re-bootstrap so the chat reflects the now-anonymous state in place.
  const onSignOut = useCallback(() => {
    clearAuthToken();
    setAuthed(false);
    setAuthRequired(true);
    setBooted(false);
    // Drop the cached (authenticated) bootstrap so the re-boot fetches the
    // anonymous one and renders the gate.
    bootstrapPromiseRef.current = null;
    void boot();
  }, [boot]);

  // Let the module-level postJson re-show the login gate when a stored bearer
  // token is rejected with 401 mid-chat (token already cleared in postJson).
  useEffect(() => {
    setUnauthorizedHandler(() => {
      setAuthed(false);
      setAuthRequired(true);
    });
    return () => setUnauthorizedHandler(null);
  }, []);

  useEffect(() => {
    if (open) scrollBottom();
  }, [messages, open, scrollBottom]);

  // Rotating launcher tooltip while the panel is closed (parity with the
  // Laravel widget's nudge). Dismissed for the session once the user opens
  // the chat. Suppressed under reduced-motion.
  useEffect(() => {
    if (open || tooltipDismissed.current) return;
    const reduced =
      window.matchMedia?.("(prefers-reduced-motion:reduce)").matches;
    if (reduced) return;
    const nudges = ["Need a hand? 👋", "Ask me anything", "Questions? Chat with me"];
    let i = 0;
    const showOne = () => {
      if (open || tooltipDismissed.current) return;
      setTooltip(nudges[i % nudges.length]);
      i += 1;
      window.setTimeout(() => setTooltip(null), 5000);
    };
    const first = window.setTimeout(showOne, 6000);
    const loop = window.setInterval(showOne, 22000);
    return () => {
      window.clearTimeout(first);
      window.clearInterval(loop);
    };
  }, [open]);

  const pushMessage = useCallback((m: AssistantMessage) => {
    setMessages((prev) => [...prev, m]);
  }, []);

  const handleTurn = useCallback(
    (res: TurnResponse) => {
      if (res?.visitor_token) setToken(res.visitor_token);
      if (!res || !res.ok) {
        pushMessage({
          role: "assistant",
          content: res?.error || "Sorry, something went wrong.",
        });
        return;
      }
      if (res.assistant_message) pushMessage(res.assistant_message);
      if (res.handed_off) setHandedOff(true);
    },
    [pushMessage, setToken]
  );

  const send = useCallback(
    async (text: string) => {
      const msg = text.trim();
      if (!msg || sending || authRequired) return;
      setInput("");
      setSending(true);
      pushMessage({ role: "user", content: msg });
      try {
        const res = await postJson<TurnResponse>("/assistant/message", {
          visitor_token: tokenRef.current,
          surface: "marketing",
          message: msg,
          page: pageMeta(),
        });
        handleTurn(res);
      } catch (e) {
        // A 401 already cleared the token + re-showed the login gate; don't
        // also render a raw error bubble.
        if (e instanceof AssistantUnauthorizedError) return;
        pushMessage({
          role: "assistant",
          content: "Network error. Please try again.",
        });
      } finally {
        setSending(false);
        scrollBottom();
      }
    },
    [sending, authRequired, pushMessage, handleTurn, scrollBottom]
  );

  const submitChoice = useCallback(
    async (choice: {
      label?: string;
      value?: string;
      template?: string | null;
      values?: Record<string, string>;
    }) => {
      if (sending) return;
      setSending(true);
      pushMessage({ role: "user", content: choice.label || "Selected" });
      try {
        const res = await postJson<TurnResponse>("/assistant/choice", {
          visitor_token: tokenRef.current,
          surface: "marketing",
          choice,
          page: pageMeta(),
        });
        handleTurn(res);
      } catch (e) {
        if (e instanceof AssistantUnauthorizedError) return;
        pushMessage({
          role: "assistant",
          content: "Network error. Please try again.",
        });
      } finally {
        setSending(false);
        scrollBottom();
      }
    },
    [sending, pushMessage, handleTurn, scrollBottom]
  );

  const submitHandoff = useCallback(
    async (values: Record<string, string>) => {
      if (sending) return;
      setSending(true);
      try {
        const res = await postJson<TurnResponse>("/assistant/handoff", {
          visitor_token: tokenRef.current,
          surface: "marketing",
          name: values.name || values.Name || "",
          email: values.email || values.Email || "",
          phone: values.phone || values.Phone || "",
          channel: values.channel || "",
          message: values.message || values.Message || "",
          page: pageMeta(),
        });
        if (res?.ok && res.assistant_message) {
          pushMessage(res.assistant_message);
          setHandedOff(true);
        } else if (res?.error) {
          pushMessage({ role: "assistant", content: res.error });
        }
      } catch (e) {
        if (e instanceof AssistantUnauthorizedError) return;
        pushMessage({
          role: "assistant",
          content: "Network error. Please try again.",
        });
      } finally {
        setSending(false);
        scrollBottom();
      }
    },
    [sending, pushMessage, scrollBottom]
  );

  const openContact = useCallback(() => {
    setContactDone("");
    setContactError("");
    contactOpenedAt.current = Date.now();
    setContactView(true);
  }, []);

  const submitQuickContact = useCallback(
    async (values: Record<string, string>) => {
      if (contactBusy) return;
      setContactBusy(true);
      setContactError("");
      try {
        const res = await postJson<QuickContactResponse>(
          "/assistant/quick-contact",
          {
            channel: values.channel || "",
            phone: values.phone || "",
            email: values.email || "",
            message: values.message || "",
            elapsed_ms: Date.now() - contactOpenedAt.current,
          }
        );
        if (res?.ok) {
          setContactDone(
            res.message ||
              "Thanks! We've got your request and will be in touch soon."
          );
        } else {
          setContactError(res?.error || "Something went wrong. Please try again.");
        }
      } catch {
        setContactError("Network error. Please try again.");
      } finally {
        setContactBusy(false);
      }
    },
    [contactBusy]
  );

  const avatar = cfg?.avatar_url || bootAvatar || zioBotMascot;
  const reduceMotion = useReducedMotion();
  const brand = cfg?.brand_name || "Zio Bot";
  const subheading = cfg?.subheading || "How can I help?";
  const placeholder = cfg?.input_placeholder || "Type a message…";
  const sendLabel = cfg?.send_label || "Send";

  // ── theme tokens ────────────────────────────────────────────────
  const t = assistantTokens(isDark);

  if (cfg && cfg.enabled === false) return null;

  return (
    <div
      style={{
        position: "fixed",
        right: 20,
        bottom: 20,
        zIndex: 2147483000,
      }}
    >
      <AnimatePresence>
        {open && (
          <motion.div
            key="panel"
            initial={{ opacity: 0, y: 24, scale: 0.96 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 24, scale: 0.96 }}
            transition={{ type: "spring", stiffness: 320, damping: 28 }}
            style={{
              position: "absolute",
              bottom: 78,
              right: 0,
              width: "min(380px, calc(100vw - 32px))",
              height: "min(560px, calc(100vh - 120px))",
              display: "flex",
              flexDirection: "column",
              borderRadius: 20,
              overflow: "hidden",
              background: t.panelBg,
              border: `1px solid ${t.panelBorder}`,
              boxShadow:
                "0 24px 60px -20px rgba(40,70,160,0.5), 0 0 0 1px rgba(61,107,255,0.08)",
              backdropFilter: "blur(20px)",
              WebkitBackdropFilter: "blur(20px)",
              color: t.text,
              fontFamily:
                "'Space Grotesk', system-ui, -apple-system, sans-serif",
            }}
          >
            {/* header */}
            <div
              style={{
                display: "flex",
                alignItems: "center",
                gap: 10,
                padding: "14px 16px",
                background:
                  "linear-gradient(135deg, rgba(61,107,255,0.22), rgba(110,97,255,0.12))",
                borderBottom: `1px solid ${t.panelBorder}`,
              }}
            >
              <div style={{ lineHeight: 1.2 }}>
                <div style={{ fontWeight: 600, fontSize: 14 }}>{brand}</div>
                <div style={{ fontSize: 11, color: t.sub }}>{subheading}</div>
              </div>
              {authed && (
                <button
                  type="button"
                  aria-label="Sign out"
                  title="Sign out"
                  onClick={onSignOut}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.background = isDark
                      ? "rgba(255,255,255,0.08)"
                      : "rgba(17,17,30,0.06)";
                    e.currentTarget.style.color = t.text;
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.background = "transparent";
                    e.currentTarget.style.color = t.sub;
                  }}
                  style={{
                    marginLeft: "auto",
                    display: "inline-flex",
                    alignItems: "center",
                    gap: 5,
                    height: 30,
                    padding: "0 9px",
                    background: "transparent",
                    border: 0,
                    borderRadius: 8,
                    color: t.sub,
                    fontSize: 12,
                    fontWeight: 600,
                    lineHeight: 1,
                    cursor: "pointer",
                    transition: "background .15s ease, color .15s ease",
                  }}
                >
                  <LogOut size={14} aria-hidden="true" />
                  <span>Sign out</span>
                </button>
              )}
              <button
                type="button"
                aria-label="Close assistant"
                onClick={() => toggle(false)}
                onMouseEnter={(e) => {
                  e.currentTarget.style.background = isDark
                    ? "rgba(255,255,255,0.08)"
                    : "rgba(17,17,30,0.06)";
                  e.currentTarget.style.color = t.text;
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.background = "transparent";
                  e.currentTarget.style.color = t.sub;
                }}
                style={{
                  marginLeft: "auto",
                  display: "inline-flex",
                  alignItems: "center",
                  justifyContent: "center",
                  width: 30,
                  height: 30,
                  background: "transparent",
                  border: 0,
                  borderRadius: 8,
                  color: t.sub,
                  fontSize: 20,
                  lineHeight: 1,
                  cursor: "pointer",
                  transition: "background .15s ease, color .15s ease",
                }}
              >
                ×
              </button>
            </div>

            {/* "Contact us" entry point on its own action row below the header
                so it reads as a distinct, one-tap action with breathing room —
                no longer squeezed between the brand title and the close (×).
                Mirrors the Laravel widget's .sa-actions / .sa-contact-btn. */}
            {!contactView && (
              <div style={{ display: "flex", padding: "12px 14px 2px" }}>
                <button
                  type="button"
                  aria-label="Contact us"
                  onClick={openContact}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.background = BRAND_ACCENT;
                    e.currentTarget.style.borderColor = "transparent";
                    e.currentTarget.style.color = "#fff";
                    e.currentTarget.style.transform = "translateY(-1px)";
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.background = t.chip;
                    e.currentTarget.style.borderColor = t.chipBorder;
                    e.currentTarget.style.color = t.chipText;
                    e.currentTarget.style.transform = "none";
                  }}
                  onMouseDown={(e) => {
                    e.currentTarget.style.transform = "none";
                  }}
                  style={{
                    display: "inline-flex",
                    alignItems: "center",
                    gap: 7,
                    background: t.chip,
                    border: `1px solid ${t.chipBorder}`,
                    color: t.chipText,
                    fontSize: 12.5,
                    fontWeight: 600,
                    fontFamily: "inherit",
                    lineHeight: 1,
                    padding: "9px 15px",
                    borderRadius: 999,
                    cursor: "pointer",
                    transition:
                      "background .15s ease, border-color .15s ease, color .15s ease, transform .15s ease",
                  }}
                >
                  <Phone size={14} /> Contact us
                </button>
              </div>
            )}

            {contactView ? (
              <AssistantContactView
                tokens={t}
                busy={contactBusy}
                done={contactDone}
                error={contactError}
                onSubmit={submitQuickContact}
                onBack={() => setContactView(false)}
              />
            ) : (
              <>
            {/* messages */}
            <div
              ref={bodyRef}
              style={{
                flex: 1,
                overflowY: "auto",
                padding: 14,
                display: "flex",
                flexDirection: "column",
                gap: 10,
              }}
            >
              {!booted && messages.length === 0 && (
                <div style={{ color: t.sub, fontSize: 13 }}>Loading…</div>
              )}
              {messages.map((m, idx) => (
                <MessageBubble
                  key={idx}
                  message={m}
                  tokens={t}
                  onChoice={submitChoice}
                  onHandoff={submitHandoff}
                />
              ))}
              {sending && (
                <div style={{ color: t.sub, fontSize: 12, fontStyle: "italic" }}>
                  Zio Bot is typing…
                </div>
              )}
            </div>

            {/* suggested prompts */}
            {!handedOff && !authRequired && suggested.length > 0 && (
              <div
                style={{
                  display: "flex",
                  flexWrap: "wrap",
                  gap: 6,
                  padding: "0 14px 10px",
                }}
              >
                {suggested.slice(0, 6).map((p, i) => (
                  <button
                    key={i}
                    type="button"
                    onClick={() => void send(p)}
                    style={chipStyle(t)}
                  >
                    {p}
                  </button>
                ))}
              </div>
            )}

            {/* input — or the in-chat login gate when auth is required */}
            {authRequired ? (
              <LoginGate
                t={t}
                authNote={authNote}
                loginUrl={loginUrl}
                emailEnabled={emailOtpEnabled}
                mobileEnabled={mobileLoginEnabled}
                onSuccess={onAuthSuccess}
              />
            ) : (
              <div
                style={{
                  display: "flex",
                  gap: 8,
                  padding: 12,
                  borderTop: `1px solid ${t.panelBorder}`,
                }}
              >
                <textarea
                  rows={1}
                  value={input}
                  disabled={handedOff}
                  placeholder={
                    handedOff ? "Our team will reply by email." : placeholder
                  }
                  onChange={(e) => setInput(e.target.value)}
                  onKeyDown={(e) => {
                    if (e.key === "Enter" && !e.shiftKey) {
                      e.preventDefault();
                      void send(input);
                    }
                  }}
                  style={{
                    flex: 1,
                    resize: "none",
                    maxHeight: 96,
                    padding: "10px 12px",
                    borderRadius: 12,
                    border: `1px solid ${t.inputBorder}`,
                    background: t.inputBg,
                    color: t.text,
                    fontSize: 13,
                    fontFamily: "inherit",
                    outline: "none",
                  }}
                />
                <button
                  type="button"
                  disabled={handedOff || sending}
                  onClick={() => void send(input)}
                  style={{
                    alignSelf: "stretch",
                    padding: "0 16px",
                    borderRadius: 12,
                    border: 0,
                    cursor: handedOff || sending ? "not-allowed" : "pointer",
                    opacity: handedOff || sending ? 0.5 : 1,
                    color: "#fff",
                    fontWeight: 600,
                    fontSize: 13,
                    background: `linear-gradient(135deg, ${BRAND_ACCENT}, #a855f7)`,
                  }}
                >
                  {sendLabel}
                </button>
              </div>
            )}
              </>
            )}
          </motion.div>
        )}

        {/* mascot peeking over the top edge of the panel */}
        {open && (
          <motion.div
            key="peek"
            initial={reduceMotion ? false : { opacity: 0, y: 34 }}
            animate={{ opacity: 1, y: 0 }}
            exit={reduceMotion ? { opacity: 0 } : { opacity: 0, y: 20 }}
            transition={{ duration: 0.8, ease: [0.22, 1, 0.36, 1], delay: 0.15 }}
            style={{
              position: "absolute",
              bottom: "calc(78px + min(560px, 100vh - 120px) - 12px)",
              right: "calc(min(380px, 100vw - 32px) / 2 - clamp(72px, 18vw, 96px) / 2)",
              width: "clamp(72px, 18vw, 96px)",
              pointerEvents: "none",
              zIndex: 1,
              lineHeight: 0,
            }}
          >
            <motion.img
              src={zioBotPeek}
              alt=""
              aria-hidden="true"
              animate={reduceMotion ? undefined : { y: [0, -6, 0] }}
              transition={
                reduceMotion
                  ? undefined
                  : { duration: 4.5, repeat: Infinity, ease: "easeInOut", delay: 1 }
              }
              style={{
                width: "100%",
                height: "auto",
                display: "block",
                filter: "drop-shadow(0 6px 10px rgba(15,23,42,.35))",
              }}
            />
          </motion.div>
        )}
      </AnimatePresence>

      {/* tooltip nudge */}
      <AnimatePresence>
        {tooltip && !open && (
          <motion.div
            key="tooltip"
            initial={{ opacity: 0, y: 8, scale: 0.9 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 8, scale: 0.9 }}
            style={{
              position: "absolute",
              bottom: 78,
              right: 4,
              maxWidth: 220,
              padding: "9px 13px",
              borderRadius: "14px 14px 4px 14px",
              background: t.panelBg,
              border: `1px solid ${t.panelBorder}`,
              boxShadow: "0 12px 30px -10px rgba(40,70,160,0.4)",
              backdropFilter: "blur(12px)",
              WebkitBackdropFilter: "blur(12px)",
              color: t.text,
              fontSize: 13,
              fontFamily:
                "'Space Grotesk', system-ui, -apple-system, sans-serif",
            }}
          >
            {tooltip}
          </motion.div>
        )}
      </AnimatePresence>

      {/* launcher */}
      <motion.button
        type="button"
        aria-label={open ? "Close assistant" : "Open assistant"}
        onClick={() => toggle(!open)}
        whileTap={{ scale: 0.94 }}
        style={{
          position: "relative",
          width: 64,
          height: 64,
          border: 0,
          cursor: "pointer",
          padding: 0,
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          background: "transparent",
          boxShadow: "none",
        }}
      >
        {open ? (
          <span
            style={{
              color: isDark ? "#fff" : "#1e1b2e",
              fontSize: 26,
              lineHeight: 1,
            }}
          >
            ×
          </span>
        ) : (
          <motion.img
            src={avatar}
            alt=""
            aria-hidden="true"
            animate={{ y: [0, -2, 0], rotate: [0, -3, 0] }}
            transition={{ duration: 4, repeat: Infinity, ease: "easeInOut" }}
            style={{
              width: 60,
              height: 60,
              objectFit: "contain",
              filter: "drop-shadow(0 2px 5px rgba(0,0,0,.32))",
              pointerEvents: "none",
            }}
          />
        )}
      </motion.button>
    </div>
  );
}

export interface ThemeTokens {
  panelBg: string;
  panelBorder: string;
  text: string;
  sub: string;
  botBubble: string;
  botText: string;
  chip: string;
  chipText: string;
  chipBorder: string;
  inputBg: string;
  inputBorder: string;
  listBg: string;
}

/** Build the assistant theme tokens for the given mode. Shared so the
 *  standalone quick-contact widget matches the assistant chrome. */
export function assistantTokens(isDark: boolean): ThemeTokens {
  return isDark
    ? {
        panelBg: "rgba(15, 14, 26, 0.92)",
        panelBorder: "rgba(255,255,255,0.08)",
        text: "#e9e7f5",
        sub: "rgba(233,231,245,0.6)",
        botBubble: "rgba(255,255,255,0.06)",
        botText: "#e9e7f5",
        chip: "rgba(61,107,255,0.16)",
        chipText: "#c4b5fd",
        chipBorder: "rgba(61,107,255,0.4)",
        inputBg: "rgba(255,255,255,0.05)",
        inputBorder: "rgba(255,255,255,0.1)",
        listBg: "rgba(255,255,255,0.04)",
      }
    : {
        panelBg: "rgba(255,255,255,0.96)",
        panelBorder: "rgba(17,17,30,0.08)",
        text: "#1e1b2e",
        sub: "rgba(30,27,46,0.55)",
        botBubble: "rgba(61,107,255,0.07)",
        botText: "#1e1b2e",
        chip: "rgba(61,107,255,0.08)",
        chipText: "#2342c7",
        chipBorder: "rgba(61,107,255,0.25)",
        inputBg: "rgba(17,17,30,0.03)",
        inputBorder: "rgba(17,17,30,0.1)",
        listBg: "rgba(61,107,255,0.05)",
      };
}

function chipStyle(t: ThemeTokens): React.CSSProperties {
  return {
    padding: "6px 12px",
    borderRadius: 999,
    border: `1px solid ${t.chipBorder}`,
    background: t.chip,
    color: t.chipText,
    fontSize: 12.5,
    cursor: "pointer",
    fontFamily: "inherit",
  };
}

function pageMeta() {
  return {
    route: "",
    path: typeof location !== "undefined" ? location.pathname : "/",
    title: typeof document !== "undefined" ? document.title : "",
    url: typeof location !== "undefined" ? location.href : "",
  };
}

function MessageBubble({
  message,
  tokens,
  onChoice,
  onHandoff,
}: {
  message: AssistantMessage;
  tokens: ThemeTokens;
  onChoice: (c: {
    label?: string;
    value?: string;
    template?: string | null;
    values?: Record<string, string>;
  }) => void;
  onHandoff: (values: Record<string, string>) => void;
}) {
  const isUser = message.role === "user";
  return (
    <div
      style={{
        display: "flex",
        flexDirection: "column",
        alignItems: isUser ? "flex-end" : "flex-start",
        gap: 8,
      }}
    >
      {message.content ? (
        <div
          style={{
            maxWidth: "85%",
            padding: "9px 12px",
            borderRadius: isUser ? "14px 14px 4px 14px" : "14px 14px 14px 4px",
            fontSize: 13.5,
            lineHeight: 1.5,
            background: isUser
              ? `linear-gradient(135deg, ${BRAND_ACCENT}, #a855f7)`
              : tokens.botBubble,
            color: isUser ? "#fff" : tokens.botText,
            wordBreak: "break-word",
          }}
          dangerouslySetInnerHTML={{ __html: mdLite(message.content) }}
        />
      ) : null}
      {message.blocks && message.blocks.length > 0 && (
        <div
          style={{
            display: "flex",
            flexDirection: "column",
            gap: 8,
            width: "100%",
          }}
        >
          {message.blocks.map((b, i) => (
            <BlockView
              key={i}
              block={b}
              tokens={tokens}
              onChoice={onChoice}
              onHandoff={onHandoff}
            />
          ))}
        </div>
      )}
    </div>
  );
}

function BlockView({
  block,
  tokens,
  onChoice,
  onHandoff,
}: {
  block: AssistantBlock;
  tokens: ThemeTokens;
  onChoice: (c: {
    label?: string;
    value?: string;
    template?: string | null;
    values?: Record<string, string>;
  }) => void;
  onHandoff: (values: Record<string, string>) => void;
}) {
  if (block.type === "buttons") {
    return (
      <div style={{ display: "flex", flexWrap: "wrap", gap: 6 }}>
        {(block.options || []).map((opt, i) => (
          <button
            key={i}
            type="button"
            onClick={() =>
              onChoice({
                label: opt.label,
                value: opt.value,
                template: block.template || null,
              })
            }
            style={chipStyle(tokens)}
          >
            {opt.label || opt.value || ""}
          </button>
        ))}
      </div>
    );
  }
  if (block.type === "list") {
    return (
      <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
        {(block.options || []).map((opt, i) => (
          <button
            key={i}
            type="button"
            onClick={() =>
              onChoice({
                label: opt.title || opt.label,
                value: opt.value || opt.action || "",
                template: block.template || null,
              })
            }
            style={{
              display: "flex",
              gap: 10,
              alignItems: "center",
              textAlign: "left",
              padding: 10,
              borderRadius: 12,
              border: `1px solid ${tokens.chipBorder}`,
              background: tokens.listBg,
              color: tokens.text,
              cursor: "pointer",
              fontFamily: "inherit",
            }}
          >
            {opt.thumbnail ? (
              <img
                src={opt.thumbnail}
                alt=""
                style={{
                  width: 40,
                  height: 40,
                  borderRadius: 8,
                  objectFit: "cover",
                  flexShrink: 0,
                }}
              />
            ) : null}
            <span>
              <span style={{ display: "block", fontSize: 13, fontWeight: 600 }}>
                {opt.title || opt.label || ""}
              </span>
              {opt.description ? (
                <span
                  style={{ display: "block", fontSize: 12, color: tokens.sub }}
                >
                  {opt.description}
                </span>
              ) : null}
            </span>
          </button>
        ))}
      </div>
    );
  }
  if (block.type === "image") {
    const imgs = block.images || [{ src: block.src, alt: block.alt }];
    return (
      <div style={{ display: "flex", flexDirection: "column", gap: 6 }}>
        {imgs
          .filter((im) => im && im.src)
          .map((im, i) => (
            <img
              key={i}
              src={im.src}
              alt={im.alt || ""}
              style={{ maxWidth: "100%", borderRadius: 12 }}
            />
          ))}
      </div>
    );
  }
  if (block.type === "form") {
    if (block.action === "handoff") {
      return (
        <QuickContactFields
          tokens={tokens}
          submitLabel={block.submit_label || "Send request"}
          onSubmit={onHandoff}
        />
      );
    }
    return (
      <AssistantForm
        block={block}
        tokens={tokens}
        onChoice={onChoice}
        onHandoff={onHandoff}
      />
    );
  }
  return null;
}

// Channel options shared by the assistant handoff form and the
// standalone quick-contact widget. callback/whatsapp collect a phone,
// email collects an email; the backend validates each per channel
// (callback = Indian phone, whatsapp = country-coded phone).
interface QcChannel {
  value: string;
  label: string;
  field: "phone" | "email";
  placeholder: string;
  inputType: string;
}
const QC_CHANNELS: QcChannel[] = [
  {
    value: "callback",
    label: "Call back",
    field: "phone",
    placeholder: "Your phone (+91, 10 digits)",
    inputType: "tel",
  },
  {
    value: "whatsapp",
    label: "WhatsApp call",
    field: "phone",
    placeholder: "WhatsApp number (with country code)",
    inputType: "tel",
  },
  {
    value: "email",
    label: "Email",
    field: "email",
    placeholder: "Your email",
    inputType: "email",
  },
];

interface SendCodeResponse {
  ok?: boolean;
  message?: string;
  demo_reveal?: string;
  error?: string;
}
interface VerifyCodeResponse {
  ok?: boolean;
  token?: string;
  twofactor?: boolean;
  login_url?: string;
  error?: string;
}

/**
 * In-chat passwordless login/signup for the cross-origin marketing widget.
 * A 2-step form (identifier → 6-digit code): login == signup, so a brand-new
 * account is created server-side on first successful verification. On success
 * the server mints a Sanctum bearer token (no cookies cross the origin) which
 * the parent persists + replays on every /assistant/* call. Honeypot +
 * time-trap mirror the quick-contact form. When no OTP method is enabled we
 * fall back to the full-page login CTA.
 */
function LoginGate({
  t,
  authNote,
  loginUrl,
  emailEnabled,
  mobileEnabled,
  onSuccess,
}: {
  t: ThemeTokens;
  authNote: string;
  loginUrl: string;
  emailEnabled: boolean;
  mobileEnabled: boolean;
  onSuccess: (token: string) => void;
}) {
  const openedAt = useRef<number>(Date.now());
  const trapRef = useRef<HTMLInputElement | null>(null);
  const [step, setStep] = useState<"identifier" | "code">("identifier");
  const [type, setType] = useState<"email" | "mobile">(
    emailEnabled ? "email" : "mobile"
  );
  const [identifier, setIdentifier] = useState("");
  const [code, setCode] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [hint, setHint] = useState("");
  const [twofaUrl, setTwofaUrl] = useState("");

  const fallbackUrl = twofaUrl || loginUrl;

  // No passwordless method available → full-page login fallback only.
  if (!emailEnabled && !mobileEnabled) {
    return (
      <div
        style={{
          display: "flex",
          flexDirection: "column",
          gap: 8,
          padding: 12,
          textAlign: "center",
          borderTop: `1px solid ${t.panelBorder}`,
        }}
      >
        <div style={{ fontSize: 12.5, color: t.sub, lineHeight: 1.4 }}>
          {authNote}
        </div>
        <a href={loginUrl} style={gatePrimaryStyle}>
          Log in
        </a>
      </div>
    );
  }

  const trapValue = () => trapRef.current?.value || "";

  const sendCode = async () => {
    const idv = identifier.trim();
    if (busy) return;
    if (!idv) {
      setError(
        type === "email"
          ? "Please enter your email."
          : "Please enter your phone number."
      );
      return;
    }
    setBusy(true);
    setError("");
    try {
      const d = await postJson<SendCodeResponse>("/assistant/auth/send-code", {
        identifier: idv,
        type,
        website: trapValue(),
        elapsed_ms: Date.now() - openedAt.current,
      });
      if (d?.ok) {
        setStep("code");
        setCode("");
        setHint(
          d.demo_reveal || d.message || "We sent you a code. Enter it below."
        );
      } else {
        setError(d?.error || "Something went wrong. Please try again.");
      }
    } catch {
      setError("Network error. Please try again.");
    } finally {
      setBusy(false);
    }
  };

  const verifyCode = async () => {
    const c = code.trim();
    if (busy || c.length < 6) return;
    setBusy(true);
    setError("");
    try {
      const d = await postJson<VerifyCodeResponse>(
        "/assistant/auth/verify-code",
        {
          identifier: identifier.trim(),
          type,
          code: c,
          issue_token: true,
          device: "marketing-web",
          website: trapValue(),
          elapsed_ms: Date.now() - openedAt.current,
        }
      );
      if (d?.ok && d.token) {
        onSuccess(d.token);
        return;
      }
      if (d?.twofactor) {
        if (d.login_url) setTwofaUrl(d.login_url);
        setError(
          d.error ||
            "Finish signing in on the login page to complete two-factor."
        );
        return;
      }
      setError(d?.error || "Invalid or expired code.");
    } catch {
      setError("Network error. Please try again.");
    } finally {
      setBusy(false);
    }
  };

  const primaryLabel =
    step === "code"
      ? busy
        ? "Verifying…"
        : "Verify & continue"
      : busy
        ? "Sending…"
        : "Send code";

  const inputStyle: React.CSSProperties = {
    width: "100%",
    boxSizing: "border-box",
    background: t.inputBg,
    border: `1px solid ${t.inputBorder}`,
    color: t.text,
    borderRadius: 10,
    padding: "9px 12px",
    fontSize: 13,
    outline: "none",
    fontFamily: "inherit",
  };

  return (
    <div
      style={{
        display: "flex",
        flexDirection: "column",
        gap: 8,
        padding: 12,
        borderTop: `1px solid ${t.panelBorder}`,
      }}
    >
      <div style={{ fontSize: 12.5, color: t.sub, lineHeight: 1.4 }}>
        {authNote ||
          "Sign in or create your account to start chatting — no password needed."}
      </div>

      {/* honeypot: off-screen decoy a human never fills */}
      <input
        ref={trapRef}
        type="text"
        name="website"
        tabIndex={-1}
        autoComplete="off"
        aria-hidden="true"
        style={{
          position: "absolute",
          left: -9999,
          width: 1,
          height: 1,
          opacity: 0,
          pointerEvents: "none",
        }}
      />

      {step === "identifier" ? (
        <>
          {emailEnabled && mobileEnabled && (
            <div style={{ display: "flex", gap: 6 }}>
              {(
                [
                  ["email", "Email"],
                  ["mobile", "Phone"],
                ] as [("email" | "mobile"), string][]
              ).map(([val, label]) => (
                <button
                  key={val}
                  type="button"
                  onClick={() => {
                    setType(val);
                    setIdentifier("");
                  }}
                  style={{
                    flex: 1,
                    cursor: "pointer",
                    borderRadius: 9,
                    padding: "6px 8px",
                    fontSize: 12,
                    fontWeight: 600,
                    fontFamily: "inherit",
                    border: `1px solid ${
                      type === val ? BRAND_ACCENT : t.inputBorder
                    }`,
                    background: type === val ? BRAND_ACCENT : t.inputBg,
                    color: type === val ? "#fff" : t.sub,
                  }}
                >
                  {label}
                </button>
              ))}
            </div>
          )}
          <input
            type={type === "email" ? "email" : "tel"}
            value={identifier}
            autoComplete={type === "email" ? "email" : "tel"}
            placeholder={
              type === "email"
                ? "you@example.com"
                : "Phone (with country code)"
            }
            onChange={(e) => setIdentifier(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === "Enter") {
                e.preventDefault();
                void sendCode();
              }
            }}
            style={inputStyle}
          />
        </>
      ) : (
        <input
          type="text"
          inputMode="numeric"
          autoComplete="one-time-code"
          maxLength={6}
          value={code}
          placeholder="6-digit code"
          onChange={(e) => setCode(e.target.value.replace(/\D/g, ""))}
          onKeyDown={(e) => {
            if (e.key === "Enter") {
              e.preventDefault();
              void verifyCode();
            }
          }}
          style={inputStyle}
        />
      )}

      {hint && step === "code" && (
        <div style={{ fontSize: 11.5, color: t.chipText, lineHeight: 1.4 }}>
          {hint}
        </div>
      )}
      {error && (
        <div style={{ fontSize: 11.5, color: "#ef4444", lineHeight: 1.4 }}>
          {error}
        </div>
      )}

      <button
        type="button"
        disabled={busy}
        onClick={() => (step === "code" ? void verifyCode() : void sendCode())}
        style={{ ...gatePrimaryStyle, opacity: busy ? 0.6 : 1 }}
      >
        {primaryLabel}
      </button>

      <a
        href={fallbackUrl}
        style={{
          fontSize: 11.5,
          color: t.sub,
          textDecoration: "underline",
          textAlign: "center",
        }}
      >
        Log in on full page
      </a>
    </div>
  );
}

const gatePrimaryStyle: React.CSSProperties = {
  display: "block",
  width: "100%",
  boxSizing: "border-box",
  border: 0,
  cursor: "pointer",
  padding: "10px 14px",
  borderRadius: 12,
  color: "#fff",
  fontWeight: 600,
  fontSize: 13,
  textDecoration: "none",
  textAlign: "center",
  fontFamily: "inherit",
  background: `linear-gradient(135deg, ${BRAND_ACCENT}, #a855f7)`,
};

/**
 * In-assistant "Contact us" view. Replaces the chat surfaces with the
 * multi-channel quick-contact form (Call back / WhatsApp / Email). This is
 * the former standalone quick-contact widget, folded into the assistant so
 * there is a single floating launcher. It is intentionally NOT login-gated.
 */
function AssistantContactView({
  tokens,
  busy,
  done,
  error,
  onSubmit,
  onBack,
}: {
  tokens: ThemeTokens;
  busy: boolean;
  done: string;
  error: string;
  onSubmit: (values: Record<string, string>) => void;
  onBack: () => void;
}) {
  return (
    <div
      style={{
        flex: 1,
        overflowY: "auto",
        padding: 14,
        display: "flex",
        flexDirection: "column",
        gap: 10,
      }}
    >
      <button
        type="button"
        onClick={onBack}
        style={{
          alignSelf: "flex-start",
          display: "inline-flex",
          alignItems: "center",
          gap: 5,
          background: "transparent",
          border: 0,
          color: tokens.sub,
          fontSize: 12,
          cursor: "pointer",
          fontFamily: "inherit",
          padding: 0,
        }}
      >
        <ArrowLeft size={14} /> Back to chat
      </button>
      {done ? (
        <div
          style={{
            padding: "16px 8px",
            textAlign: "center",
            fontSize: 13,
            color: tokens.text,
            lineHeight: 1.5,
          }}
        >
          {done}
        </div>
      ) : (
        <>
          <div style={{ fontSize: 12.5, color: tokens.sub, lineHeight: 1.45 }}>
            Prefer we reach out? Pick how you'd like to be contacted and we'll
            get back to you.
          </div>
          {error && <div style={{ fontSize: 12, color: "#ef4444" }}>{error}</div>}
          <QuickContactFields
            tokens={tokens}
            busy={busy}
            submitLabel={busy ? "Sending…" : "Send request"}
            onSubmit={onSubmit}
          />
        </>
      )}
    </div>
  );
}

/**
 * Multi-channel quick-contact fields: a channel selector + one
 * contextual contact input + an optional message. Used both inside the
 * assistant handoff and the in-assistant "Contact us" view. Purely
 * presentational — the caller decides where the values are submitted.
 */
export function QuickContactFields({
  tokens,
  busy,
  submitLabel,
  onSubmit,
}: {
  tokens: ThemeTokens;
  busy?: boolean;
  submitLabel: string;
  onSubmit: (values: Record<string, string>) => void;
}) {
  const [channel, setChannel] = useState(QC_CHANNELS[0].value);
  const [contact, setContact] = useState("");
  const [message, setMessage] = useState("");
  const [error, setError] = useState(false);
  const active = QC_CHANNELS.find((c) => c.value === channel) || QC_CHANNELS[0];

  const submit = () => {
    if (!contact.trim()) {
      setError(true);
      return;
    }
    const values: Record<string, string> = {
      channel,
      message: message.trim(),
    };
    if (active.field === "email") values.email = contact.trim();
    else values.phone = contact.trim();
    onSubmit(values);
  };

  return (
    <div
      style={{
        display: "flex",
        flexDirection: "column",
        gap: 8,
        padding: 10,
        borderRadius: 12,
        border: `1px solid ${tokens.chipBorder}`,
        background: tokens.listBg,
      }}
    >
      <div style={{ display: "flex", gap: 6, flexWrap: "wrap" }}>
        {QC_CHANNELS.map((c) => {
          const on = c.value === channel;
          return (
            <button
              key={c.value}
              type="button"
              onClick={() => {
                setChannel(c.value);
                setContact("");
                setError(false);
              }}
              style={{
                flex: 1,
                minWidth: 80,
                padding: "7px 8px",
                borderRadius: 8,
                fontSize: 12,
                fontWeight: 500,
                cursor: "pointer",
                border: on
                  ? "1px solid transparent"
                  : `1px solid ${tokens.chipBorder}`,
                color: on ? "#fff" : tokens.text,
                background: on
                  ? `linear-gradient(135deg, ${BRAND_ACCENT}, #a855f7)`
                  : tokens.chip,
              }}
            >
              {c.label}
            </button>
          );
        })}
      </div>
      <input
        type={active.inputType}
        value={contact}
        placeholder={active.placeholder}
        onChange={(e) => setContact(e.target.value)}
        style={{
          width: "100%",
          padding: "8px 10px",
          borderRadius: 10,
          border: `1px solid ${
            error && !contact.trim() ? "#ef4444" : tokens.inputBorder
          }`,
          background: tokens.inputBg,
          color: tokens.text,
          fontSize: 13,
          fontFamily: "inherit",
          outline: "none",
          boxSizing: "border-box" as const,
        }}
      />
      <textarea
        rows={2}
        value={message}
        placeholder="How can we help? (optional)"
        onChange={(e) => setMessage(e.target.value)}
        style={{
          width: "100%",
          padding: "8px 10px",
          borderRadius: 10,
          border: `1px solid ${tokens.inputBorder}`,
          background: tokens.inputBg,
          color: tokens.text,
          fontSize: 13,
          fontFamily: "inherit",
          outline: "none",
          resize: "none",
          boxSizing: "border-box" as const,
        }}
      />
      <button
        type="button"
        disabled={busy}
        onClick={submit}
        style={{
          padding: "9px 12px",
          borderRadius: 10,
          border: 0,
          cursor: busy ? "not-allowed" : "pointer",
          opacity: busy ? 0.6 : 1,
          color: "#fff",
          fontWeight: 600,
          fontSize: 13,
          background: `linear-gradient(135deg, ${BRAND_ACCENT}, #a855f7)`,
        }}
      >
        {submitLabel}
      </button>
    </div>
  );
}

function AssistantForm({
  block,
  tokens,
  onChoice,
  onHandoff,
}: {
  block: AssistantBlock;
  tokens: ThemeTokens;
  onChoice: (c: {
    label?: string;
    value?: string;
    template?: string | null;
    values?: Record<string, string>;
  }) => void;
  onHandoff: (values: Record<string, string>) => void;
}) {
  const [values, setValues] = useState<Record<string, string>>({});
  const [error, setError] = useState(false);
  const fields = block.fields || [];

  const submit = () => {
    let ok = true;
    for (const f of fields) {
      const key = f.name || f.label || "";
      if (f.required && !(values[key] || "").trim()) ok = false;
    }
    if (!ok) {
      setError(true);
      return;
    }
    if (block.action === "handoff") {
      onHandoff(values);
    } else {
      onChoice({
        label: block.submit_label || "Submitted",
        values,
        template: block.template || null,
      });
    }
  };

  return (
    <div
      style={{
        display: "flex",
        flexDirection: "column",
        gap: 8,
        padding: 10,
        borderRadius: 12,
        border: `1px solid ${tokens.chipBorder}`,
        background: tokens.listBg,
      }}
    >
      {fields.map((f, i) => {
        const key = f.name || f.label || `f${i}`;
        const common = {
          value: values[key] || "",
          onChange: (
            e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>
          ) => setValues((v) => ({ ...v, [key]: e.target.value })),
          placeholder: f.label || f.name || "",
          style: {
            width: "100%",
            padding: "8px 10px",
            borderRadius: 10,
            border: `1px solid ${
              error && f.required && !(values[key] || "").trim()
                ? "#ef4444"
                : tokens.inputBorder
            }`,
            background: tokens.inputBg,
            color: tokens.text,
            fontSize: 13,
            fontFamily: "inherit",
            outline: "none",
            boxSizing: "border-box" as const,
          },
        };
        return f.type === "textarea" ? (
          <textarea key={i} rows={3} {...common} />
        ) : (
          <input key={i} type={f.type || "text"} {...common} />
        );
      })}
      <button
        type="button"
        onClick={submit}
        style={{
          padding: "9px 12px",
          borderRadius: 10,
          border: 0,
          cursor: "pointer",
          color: "#fff",
          fontWeight: 600,
          fontSize: 13,
          background: `linear-gradient(135deg, ${BRAND_ACCENT}, #a855f7)`,
        }}
      >
        {block.submit_label || "Submit"}
      </button>
    </div>
  );
}
