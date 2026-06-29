import { useState } from "react";
import { AnimatePresence, motion, useReducedMotion } from "framer-motion";
import { Phone, X } from "lucide-react";
import {
  QuickContactFields,
  assistantTokens,
  postJson,
  useIsDark,
} from "@/components/site-assistant";

const BRAND_ACCENT = "#3d6bff";

interface QuickContactResponse {
  ok?: boolean;
  message?: string;
  error?: string;
}

/**
 * Standalone multi-channel quick-contact widget for the marketing site.
 *
 * Unlike the assistant chat (which is login-gated), this widget is open to
 * anonymous visitors: it posts a callback / WhatsApp-call / email request
 * straight to the Laravel admin Contact Inbox (which also emails the admin).
 * Reuses the assistant's QuickContactFields + theme tokens so the two
 * surfaces stay visually and behaviourally in lockstep.
 */
export default function QuickContact() {
  const isDark = useIsDark();
  const reduce = useReducedMotion();
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState("");
  const [error, setError] = useState("");
  const t = assistantTokens(isDark);

  const submit = async (values: Record<string, string>) => {
    if (busy) return;
    setBusy(true);
    setError("");
    try {
      const res = await postJson<QuickContactResponse>(
        "/assistant/quick-contact",
        {
          channel: values.channel || "",
          phone: values.phone || "",
          email: values.email || "",
          message: values.message || "",
        }
      );
      if (res?.ok) {
        setDone(
          res.message ||
            "Thanks! We've got your request and will be in touch soon."
        );
      } else {
        setError(res?.error || "Something went wrong. Please try again.");
      }
    } catch {
      setError("Network error. Please try again.");
    } finally {
      setBusy(false);
    }
  };

  return (
    <div
      style={{
        position: "fixed",
        left: 20,
        bottom: 20,
        zIndex: 2147483000,
      }}
    >
      <AnimatePresence>
        {open && (
          <motion.div
            initial={reduce ? false : { opacity: 0, y: 12, scale: 0.96 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={reduce ? { opacity: 0 } : { opacity: 0, y: 12, scale: 0.96 }}
            transition={{ duration: 0.2 }}
            style={{
              position: "absolute",
              bottom: 64,
              left: 0,
              width: 320,
              maxWidth: "calc(100vw - 40px)",
              borderRadius: 16,
              overflow: "hidden",
              background: t.panelBg,
              border: `1px solid ${t.panelBorder}`,
              boxShadow: "0 20px 60px rgba(0,0,0,0.35)",
              backdropFilter: "blur(16px)",
            }}
          >
            <div
              style={{
                display: "flex",
                alignItems: "center",
                justifyContent: "space-between",
                padding: "12px 14px",
                borderBottom: `1px solid ${t.panelBorder}`,
              }}
            >
              <div>
                <div style={{ fontSize: 14, fontWeight: 700, color: t.text }}>
                  Quick contact
                </div>
                <div style={{ fontSize: 12, color: t.sub }}>
                  We'll reach out your way.
                </div>
              </div>
              <button
                type="button"
                aria-label="Close"
                onClick={() => setOpen(false)}
                style={{
                  display: "flex",
                  border: 0,
                  background: "transparent",
                  cursor: "pointer",
                  color: t.sub,
                  padding: 4,
                }}
              >
                <X size={18} />
              </button>
            </div>
            <div style={{ padding: 12 }}>
              {done ? (
                <div
                  style={{
                    padding: "16px 8px",
                    textAlign: "center",
                    fontSize: 13,
                    color: t.text,
                    lineHeight: 1.5,
                  }}
                >
                  {done}
                </div>
              ) : (
                <>
                  {error && (
                    <div
                      style={{
                        marginBottom: 8,
                        fontSize: 12,
                        color: "#ef4444",
                      }}
                    >
                      {error}
                    </div>
                  )}
                  <QuickContactFields
                    tokens={t}
                    busy={busy}
                    submitLabel={busy ? "Sending…" : "Send request"}
                    onSubmit={submit}
                  />
                </>
              )}
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      <motion.button
        type="button"
        aria-label={open ? "Close quick contact" : "Open quick contact"}
        onClick={() => setOpen((v) => !v)}
        whileHover={reduce ? undefined : { scale: 1.05 }}
        whileTap={reduce ? undefined : { scale: 0.95 }}
        style={{
          display: "flex",
          alignItems: "center",
          justifyContent: "center",
          width: 52,
          height: 52,
          borderRadius: "50%",
          border: 0,
          cursor: "pointer",
          color: "#fff",
          boxShadow: "0 10px 30px rgba(61,107,255,0.45)",
          background: `linear-gradient(135deg, ${BRAND_ACCENT}, #a855f7)`,
        }}
      >
        {open ? <X size={22} /> : <Phone size={22} />}
      </motion.button>
    </div>
  );
}
