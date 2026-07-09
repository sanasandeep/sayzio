import React, { useEffect, useRef, useState } from "react";
import { api, ApiError } from "../lib/api";

export interface NotifItem {
  id: number;
  type: string;
  data: Record<string, unknown>;
  read_at: string | null;
  created_at: string;
  message?: string | null;
}

interface Props {
  onUnreadChange: (n: number) => void;
  showToast: (t: { kind: "success" | "error" | "info"; text: string }) => void;
}

const TYPE_ICON: Record<string, string> = {
  new_subscriber:        "👤",
  new_follower:          "➕",
  form_submission:       "📝",
  restaurant_order:      "🍽️",
  store_order:           "🛒",
  link_milestone:        "🔗",
  review_received:       "⭐",
  dialer_callback_due:   "📞",
  payment_received:      "💰",
  low_coins:             "🪙",
  api_usage_warning:     "⚠️",
};

function typeIcon(type: string): string {
  for (const [k, v] of Object.entries(TYPE_ICON)) {
    if (type.includes(k)) return v;
  }
  return "🔔";
}

function relativeTime(iso: string): string {
  const delta = (Date.now() - new Date(iso).getTime()) / 1000;
  if (delta < 60) return "just now";
  if (delta < 3600) return `${Math.floor(delta / 60)}m ago`;
  if (delta < 86400) return `${Math.floor(delta / 3600)}h ago`;
  if (delta < 604800) return `${Math.floor(delta / 86400)}d ago`;
  return new Date(iso).toLocaleDateString();
}

function notifLabel(n: NotifItem): string {
  if (n.message) return n.message;
  const d = n.data;
  if (typeof d.message === "string") return d.message;
  if (typeof d.body === "string") return d.body;
  if (typeof d.title === "string") return d.title;
  return n.type.replace(/_/g, " ");
}

export function NotificationsView({ onUnreadChange, showToast }: Props) {
  const [items, setItems] = useState<NotifItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [markingAll, setMarkingAll] = useState(false);
  const mountedRef = useRef(true);

  const load = async () => {
    setLoading(true);
    try {
      const resp = await api.getNotifications({ perPage: 30 });
      if (!mountedRef.current) return;
      setItems(resp.items ?? []);
      const unread = (resp.items ?? []).filter((n: NotifItem) => !n.read_at).length;
      onUnreadChange(unread);
    } catch (e: any) {
      if (!mountedRef.current) return;
      showToast({ kind: "error", text: e instanceof ApiError ? e.message : "Could not load notifications" });
    } finally {
      if (mountedRef.current) setLoading(false);
    }
  };

  useEffect(() => {
    mountedRef.current = true;
    load();
    return () => { mountedRef.current = false; };
  }, []);

  const markRead = async (id: number) => {
    try {
      await api.markNotificationRead(id);
      setItems((prev) => prev.map((n) => n.id === id ? { ...n, read_at: new Date().toISOString() } : n));
      const unread = items.filter((n) => !n.read_at && n.id !== id).length;
      onUnreadChange(Math.max(0, unread));
    } catch { /* best-effort */ }
  };

  const markAll = async () => {
    setMarkingAll(true);
    try {
      await api.markAllNotificationsRead();
      setItems((prev) => prev.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() })));
      onUnreadChange(0);
    } catch (e: any) {
      showToast({ kind: "error", text: e instanceof ApiError ? e.message : "Could not mark all read" });
    } finally {
      setMarkingAll(false);
    }
  };

  const unread = items.filter((n) => !n.read_at);

  return (
    <div className="body" style={{ padding: 0 }}>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "10px 14px 6px" }}>
        <span style={{ fontWeight: 600, fontSize: 14 }}>Notifications</span>
        {unread.length > 0 && (
          <button className="btn-link" disabled={markingAll} onClick={markAll} style={{ fontSize: 12 }}>
            {markingAll ? "Marking…" : "Mark all read"}
          </button>
        )}
      </div>

      {loading && items.length === 0 && (
        <div className="muted" style={{ padding: "24px 14px", textAlign: "center" }}>Loading…</div>
      )}
      {!loading && items.length === 0 && (
        <div className="muted" style={{ padding: "24px 14px", textAlign: "center" }}>You're all caught up 🎉</div>
      )}

      <div style={{ overflowY: "auto", maxHeight: 360 }}>
        {items.map((n) => (
          <div
            key={n.id}
            style={{
              display: "flex",
              gap: 10,
              padding: "9px 14px",
              borderBottom: "1px solid var(--border-color, rgba(255,255,255,.07))",
              background: n.read_at ? "transparent" : "rgba(59,130,246,.06)",
              cursor: n.read_at ? "default" : "pointer",
              alignItems: "flex-start",
            }}
            onClick={() => { if (!n.read_at) markRead(n.id); }}
          >
            <span style={{ fontSize: 18, lineHeight: 1.4, flexShrink: 0 }}>{typeIcon(n.type)}</span>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontSize: 13, lineHeight: 1.4, wordBreak: "break-word" }}>
                {notifLabel(n)}
              </div>
              <div style={{ fontSize: 11, opacity: 0.5, marginTop: 2 }}>{relativeTime(n.created_at)}</div>
            </div>
            {!n.read_at && (
              <span style={{ width: 7, height: 7, borderRadius: "50%", background: "#3b82f6", flexShrink: 0, marginTop: 6 }} />
            )}
          </div>
        ))}
      </div>
    </div>
  );
}
