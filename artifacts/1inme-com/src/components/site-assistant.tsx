import { useCallback, useEffect, useRef, useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { useTheme } from "@/components/theme-provider";
import { ASSISTANT_API_BASE } from "@/config";
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

async function postJson<T>(path: string, body: unknown): Promise<T> {
  const res = await fetch(api(path), {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify(body),
  });
  return (await res.json()) as T;
}

function useIsDark(): boolean {
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
  const [input, setInput] = useState("");
  const [tooltip, setTooltip] = useState<string | null>(null);
  // Default mascot resolved from the backend bootstrap, populated on mount
  // so the launcher reflects an admin mascot change before the chat is
  // ever opened. Empty until the first bootstrap resolves (then the
  // bundled import is used as the offline fallback).
  const [bootAvatar, setBootAvatar] = useState("");

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
          const res = await fetch(api("/assistant/bootstrap?surface=marketing"), {
            headers: { Accept: "application/json" },
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
      }
    },
    [booted, boot]
  );

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
      if (!msg || sending) return;
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
      } catch {
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
      } catch {
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
          message: values.message || values.Message || "",
          page: pageMeta(),
        });
        if (res?.ok && res.assistant_message) {
          pushMessage(res.assistant_message);
          setHandedOff(true);
        } else if (res?.error) {
          pushMessage({ role: "assistant", content: res.error });
        }
      } catch {
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

  const avatar = cfg?.avatar_url || bootAvatar || zioBotMascot;
  const reduceMotion = useReducedMotion();
  const brand = cfg?.brand_name || "Zio Bot";
  const subheading = cfg?.subheading || "How can I help?";
  const placeholder = cfg?.input_placeholder || "Type a message…";
  const sendLabel = cfg?.send_label || "Send";

  // ── theme tokens ────────────────────────────────────────────────
  const t = isDark
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
              <button
                type="button"
                aria-label="Close assistant"
                onClick={() => toggle(false)}
                style={{
                  marginLeft: "auto",
                  background: "transparent",
                  border: 0,
                  color: t.sub,
                  fontSize: 20,
                  lineHeight: 1,
                  cursor: "pointer",
                }}
              >
                ×
              </button>
            </div>

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
            {!handedOff && suggested.length > 0 && (
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

            {/* input */}
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

interface ThemeTokens {
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
