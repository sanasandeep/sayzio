import { getBaseUrl, MOBILE_USER_AGENT } from "@/lib/api";
import { getToken } from "@/lib/secure";

/**
 * Paid DMs (Task #1210) — viewer-side endpoints living on the public
 * `/viewer/dm/...` surface (NOT under /api/v1), since they share the
 * same controllers as the web app and need to deep-link out to the
 * hosted-checkout return URL exactly the way the web flow does.
 *
 * Mobile auth: send the same Bearer token the rest of the app uses
 * (cookies don't survive WebBrowser hand-offs on native), and let the
 * Laravel auth middleware pull the user out of the token guard.
 */

export type DmAttachment = {
  id: number;
  kind: "image" | "gallery" | "video" | "audio" | "voice" | "file";
  thumb_url: string | null;
  url: string | null;
  duration_seconds: number | null;
  lock_price_cents: number;
  lock_currency: string;
  is_locked: boolean;
};

export type DmMessage = {
  id: number;
  side: "viewer" | "owner";
  kind: "text" | "attachment" | "tip" | "system";
  body: string;
  tip_id: number | null;
  attachments: DmAttachment[];
  sent_at: string | null;
  read_at: string | null;
  is_ai: boolean;
};

export type DmPolicy = {
  mode: "open" | "subs" | "paid" | "closed";
  reason:
    | "ok"
    | "owner"
    | "self"
    | "login_required"
    | "closed"
    | "account_blocked"
    | "thread_blocked"
    | "subs_required"
    | "paid_required"
    | "throttled";
  can: boolean;
  price_cents: number;
  currency: string;
  min_tier_id: number | null;
  min_tier_name: string | null;
  paid: boolean;
  subscribed: boolean;
};

export type DmThread = {
  conversation_id: number;
  state: { sent: number; owner_replied: boolean; blocked: boolean; throttled: boolean; paid: boolean };
  policy: DmPolicy;
  messages: DmMessage[];
};

async function jsonFetch<T>(path: string, init?: RequestInit): Promise<T> {
  // Mobile-only: hit /api/v1/dm/* (Sanctum Bearer, CSRF-exempt).
  // The web surface keeps using cookie-authed /viewer/dm/* routes
  // with a CSRF token header on the modal.
  const url = `${getBaseUrl()}/api/v1${path}`;
  const token = await getToken();
  const headers: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
    "User-Agent": MOBILE_USER_AGENT,
    "X-1INME-Client": MOBILE_USER_AGENT,
    ...((init?.headers as Record<string, string>) ?? {}),
  };
  if (token) headers.Authorization = `Bearer ${token}`;
  const res = await fetch(url, { ...(init ?? {}), headers });
  const body = (await res.json().catch(() => ({}))) as T & {
    ok?: boolean;
    checkout_url?: string;
    reason?: string;
  };
  if (!res.ok && !(body && (body as any).checkout_url)) {
    throw Object.assign(new Error((body as any)?.reason || `HTTP ${res.status}`), {
      status: res.status,
      body,
    });
  }
  return body as T;
}

export async function dmAccess(handle: string) {
  return jsonFetch<{ ok: true; creator: { id: number; name: string; handle: string; avatar: string | null }; conversation_id: number | null; policy: DmPolicy }>(
    `/dm/profile/${encodeURIComponent(handle)}/access`,
  );
}

export async function dmThread(handle: string) {
  return jsonFetch<{ ok: true } & DmThread>(`/dm/profile/${encodeURIComponent(handle)}/thread`);
}

export async function dmSend(handle: string, body: string, returnUrl?: string) {
  return jsonFetch<
    | { ok: true; message: DmMessage; state: DmThread["state"] }
    | { ok: false; reason: "paid_required"; checkout_url: string; price_cents: number; currency: string }
    | { ok: false; reason: string }
  >(`/dm/profile/${encodeURIComponent(handle)}/send`, {
    method: "POST",
    body: JSON.stringify({ body, return_url: returnUrl }),
  });
}

export async function dmUnlockAttachment(attachmentId: number, returnUrl?: string) {
  return jsonFetch<{ ok: true; checkout_url?: string; already?: boolean; url?: string; price_cents: number; currency: string }>(
    `/dm/attachments/${attachmentId}/unlock`,
    { method: "POST", body: JSON.stringify({ return_url: returnUrl }) },
  );
}

export async function dmTip(conversationId: number, amountCents: number, note?: string) {
  return jsonFetch<{ ok: true; checkout_url: string }>(
    `/dm/threads/${conversationId}/tip`,
    { method: "POST", body: JSON.stringify({ amount_cents: amountCents, note }) },
  );
}
