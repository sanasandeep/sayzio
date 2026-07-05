import { useCallback, useEffect, useRef, useState } from "react";

import { getEvent } from "@/lib/api/events";
import { listNotifications, markRead } from "@/lib/api/notifications";

const POLL_MS = 3 * 60 * 1000;

export type NearbyEventAlert = {
  notificationId: number;
  alias: string;
  title: string;
  cover_image_url: string | null;
};

function aliasFromUrl(url: string | null): string | null {
  if (!url) return null;
  const path = url.replace(/^https?:\/\/[^/]+/i, "");
  const seg = path.split("/").filter(Boolean).pop();
  return seg || null;
}

/**
 * Surfaces the *existing server-side* "new event near you" notification
 * (`event.new_nearby`) as an in-app banner + tab badge while the dialer app
 * is foregrounded. This deliberately reads the same generic
 * `GET /api/v1/notifications` feed every other in-app notification uses,
 * rather than re-deriving proximity/preference logic on-device: the backend
 * (`SendNewEventAlerts` + `NotificationService`) is the single source of
 * truth for whether an alert should fire at all, gated by the user's own
 * `event.new_nearby` preference and their saved alert location/radius. See
 * `.agents/memory/event-new-nearby-alert-source.md`.
 *
 * Dismissing the banner calls `markRead` against the real notification, so
 * it also disappears from the account's main notification history — this
 * is a real in-app notification, not a purely local flag.
 */
export function useNearbyEventAlerts(enabled: boolean) {
  const [latest, setLatest] = useState<NearbyEventAlert | null>(null);
  const [count, setCount] = useState(0);
  const shownIdRef = useRef<number | null>(null);
  const inFlight = useRef(false);

  const poll = useCallback(async () => {
    if (inFlight.current) return;
    inFlight.current = true;
    try {
      const { items } = await listNotifications();
      const unread = items.filter(
        (n) =>
          n.type === "event.new_nearby" &&
          !n.read_at &&
          n.data &&
          (n.data as Record<string, unknown>).link_id != null,
      );
      setCount(unread.length);

      const top = unread[0];
      if (!top) {
        setLatest(null);
        shownIdRef.current = null;
        return;
      }
      if (top.id === shownIdRef.current) return;

      const alias = aliasFromUrl(top.url);
      if (!alias) return;

      shownIdRef.current = top.id;
      const data = top.data as { title?: string } | null;
      let coverImageUrl: string | null = null;
      try {
        const full = await getEvent(alias);
        coverImageUrl = full.cover_image_url;
      } catch {
        // best-effort only — the banner still works without a cover image
      }
      setLatest({
        notificationId: top.id,
        alias,
        title: data?.title ?? top.body ?? "New event near you",
        cover_image_url: coverImageUrl,
      });
    } catch {
      // best-effort background nicety — a failed poll just skips this cycle
    } finally {
      inFlight.current = false;
    }
  }, []);

  useEffect(() => {
    if (!enabled) {
      setLatest(null);
      setCount(0);
      shownIdRef.current = null;
      return;
    }
    void poll();
    const id = setInterval(() => void poll(), POLL_MS);
    return () => clearInterval(id);
  }, [enabled, poll]);

  const dismiss = useCallback(() => {
    const toDismiss = latest;
    setLatest(null);
    if (toDismiss) {
      void markRead(toDismiss.notificationId).catch(() => {
        // best-effort — worst case it reappears server-side as still-unread
      });
    }
  }, [latest]);

  return { latest, count, dismiss };
}
