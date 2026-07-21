import { Platform } from "react-native";

import { listContacts, logContactCalls, type Contact } from "@/lib/api/contacts";
import { flagNumber, listFlaggedNumbers } from "@/lib/api/dialer";
import { ZioTelephony } from "@/modules/zio-telephony";

/**
 * JS-side glue for the Truecaller-style incoming-call alert.
 *
 * The heavy lifting (call-screening service, floating overlay, offline
 * lookups) is native; this module reads/updates the native state and keeps
 * the native "caller directory" (synced Sayzio contacts) fresh so the
 * overlay can identify callers even when the JS runtime is dead.
 *
 * Android-only: on iOS/web every function degrades to "unsupported".
 */

export type CallerIdStatus = {
  /** Native module present + Android 10+ with the call-screening role available. */
  supported: boolean;
  /** "Display over other apps" granted. */
  overlayGranted: boolean;
  /** We are the device's caller ID & spam app. */
  roleHeld: boolean;
  /** The user's on/off toggle. */
  enabled: boolean;
  /** Everything is in place and alerts will actually show. */
  active: boolean;
};

export function getCallerIdStatus(): CallerIdStatus {
  if (Platform.OS !== "android" || !ZioTelephony) {
    return {
      supported: false,
      overlayGranted: false,
      roleHeld: false,
      enabled: false,
      active: false,
    };
  }
  try {
    const supported = ZioTelephony.isCallerIdAlertSupported();
    const overlayGranted = ZioTelephony.hasOverlayPermission();
    const roleHeld = ZioTelephony.hasCallScreeningRole();
    const enabled = ZioTelephony.isCallerIdAlertEnabled();
    return {
      supported,
      overlayGranted,
      roleHeld,
      enabled,
      active: supported && overlayGranted && roleHeld && enabled,
    };
  } catch {
    return {
      supported: false,
      overlayGranted: false,
      roleHeld: false,
      enabled: false,
      active: false,
    };
  }
}

export function setCallerIdEnabled(enabled: boolean): void {
  if (Platform.OS !== "android" || !ZioTelephony) return;
  try {
    ZioTelephony.setCallerIdAlertEnabled(enabled);
  } catch {
    /* non-fatal */
  }
}

/** Open the system "Display over other apps" settings page for this app. */
export function openOverlaySettings(): boolean {
  if (Platform.OS !== "android" || !ZioTelephony) return false;
  try {
    return ZioTelephony.openOverlayPermissionSettings();
  } catch {
    return false;
  }
}

/** System prompt: set Zio Dialer as the caller ID & spam app. */
export async function requestCallScreeningRole(): Promise<boolean> {
  if (Platform.OS !== "android" || !ZioTelephony) return false;
  try {
    return await ZioTelephony.requestCallScreeningRole();
  } catch {
    return false;
  }
}

/** Preview the floating card (settings screen "See a preview" button). */
export function showTestAlert(number: string): boolean {
  if (Platform.OS !== "android" || !ZioTelephony) return false;
  try {
    return ZioTelephony.showTestCallerIdAlert(number);
  } catch {
    return false;
  }
}

// Guard against overlapping flushes when open + foreground fire together.
let flushingReports = false;

/**
 * Push "Report spam" taps made on the incoming-call overlay (queued natively
 * while the JS runtime was dead) to POST /dialer/flag, then force-refresh the
 * caller directory so the server-backed red warning replaces the local
 * override. Best-effort and silent: failed posts stay queued for next time.
 * Display-only — flagging never blocks or silences any call.
 */
export async function flushPendingSpamReports(): Promise<void> {
  if (Platform.OS !== "android" || !ZioTelephony) return;
  if (flushingReports) return;
  flushingReports = true;
  try {
    let pending: string[] = [];
    try {
      pending = ZioTelephony.getPendingSpamReports();
    } catch {
      return;
    }
    if (!pending.length) return;
    let anySynced = false;
    for (const number of pending) {
      try {
        await flagNumber({ number, is_spam: true });
        ZioTelephony.removePendingSpamReport(number);
        anySynced = true;
      } catch {
        // Offline or server error — keep it queued and retry next time.
      }
    }
    if (anySynced) await syncCallerDirectory({ force: true });
  } finally {
    flushingReports = false;
  }
}

// Throttle directory refreshes — the contact auto-sync already runs at most
// once a minute; mirror that here so the native write stays cheap.
let lastDirectorySync = 0;
const DIRECTORY_MIN_INTERVAL_MS = 60_000;

/**
 * Push the user's synced Sayzio contacts into the native caller directory
 * so incoming numbers resolve to names/photos while the app is dead.
 * Best-effort and silent; safe to call after every contact sync.
 */
export async function syncCallerDirectory(opts?: {
  force?: boolean;
}): Promise<void> {
  if (Platform.OS !== "android" || !ZioTelephony) return;
  if (
    !opts?.force &&
    Date.now() - lastDirectorySync < DIRECTORY_MIN_INTERVAL_MS
  ) {
    return;
  }
  lastDirectorySync = Date.now();
  try {
    // Spam/blocked flags are best-effort — the directory still syncs if the
    // flags request fails (warnings are display-only, never blocking).
    const [{ items }, flags] = await Promise.all([
      listContacts(),
      listFlaggedNumbers().catch(
        () => [] as { number_e164: string; is_spam: boolean; is_blocked: boolean }[],
      ),
    ]);

    // Mirror the native last-9-digits key so flag/contact matching agrees
    // with CallerIdStore.normalizeKey.
    const normalizeKey = (num: string): string => {
      const digits = num.replace(/\D/g, "");
      return digits.length > 9 ? digits.slice(-9) : digits;
    };
    const flagByKey = new Map<
      string,
      { spam: boolean; blocked: boolean }
    >();
    for (const f of flags) {
      const key = normalizeKey(f.number_e164);
      if (!key) continue;
      flagByKey.set(key, {
        spam: !!f.is_spam,
        blocked: !!f.is_blocked,
      });
    }

    type DirEntry = {
      n: string;
      name?: string;
      photo?: string;
      org?: string;
      spam?: boolean;
      blocked?: boolean;
    };
    const dir: DirEntry[] = [];
    const coveredKeys = new Set<string>();
    for (const c of items) {
      const name =
        c.display_name ??
        [c.given_name, c.family_name].filter(Boolean).join(" ").trim();
      if (!name) continue;
      for (const p of c.phones ?? []) {
        const num = (p.value_e164 ?? p.value ?? "").trim();
        if (!num) continue;
        const entry: DirEntry = { n: num, name };
        if (c.photo_url) entry.photo = c.photo_url;
        if (c.organization) entry.org = c.organization;
        const key = normalizeKey(num);
        const flag = key ? flagByKey.get(key) : undefined;
        if (flag) {
          if (flag.spam) entry.spam = true;
          if (flag.blocked) entry.blocked = true;
        }
        if (key) coveredKeys.add(key);
        dir.push(entry);
      }
    }
    // Flagged numbers that aren't saved contacts still get a directory
    // entry so the overlay can warn about them at ring time.
    for (const f of flags) {
      const key = normalizeKey(f.number_e164);
      if (!key || coveredKeys.has(key)) continue;
      const entry: DirEntry = { n: f.number_e164 };
      if (f.is_spam) entry.spam = true;
      if (f.is_blocked) entry.blocked = true;
      if (entry.spam || entry.blocked) dir.push(entry);
    }
    ZioTelephony.setCallerDirectory(JSON.stringify(dir));
  } catch {
    // Directory refresh is best-effort; the old snapshot keeps working.
  }
}

// ── Identified-call queue drain (CRM history) ──────────────────────────

/** One queued event from the native call-screening service. */
type IdentifiedCallEvent = {
  /** Raw caller number as the screening service saw it. */
  n: string;
  /** Directory name the caller resolved to at ring time. */
  name?: string;
  org?: string;
  /** Epoch millis of the ring. */
  ts: number;
};

/** Last-9-digits key mirroring the native CallerIdStore.normalizeKey. */
function phoneKey(number: string): string {
  const digits = number.replace(/\D/g, "");
  return digits.length > 9 ? digits.slice(-9) : digits;
}

let drainInFlight = false;

/**
 * Drain the native identified-incoming-call queue (rings the screening
 * service saw while the JS runtime was dead) into the Sayzio contact
 * history: matched events are posted to the contact's structured
 * call-history endpoint (`POST /contacts/{id}/calls`), which the profile
 * renders as a "Call history" timeline. Older note-line entries from the
 * notes-append v1 are left untouched (still readable in Notes).
 *
 * Idempotent: the server dedupes on (contact, number, occurred_at), and
 * the native queue is only cleared after every matched event was persisted
 * (unmatched events are dropped — they can never sync). Android-only no-op
 * elsewhere. Returns the number of events logged.
 */
export async function drainIdentifiedCalls(): Promise<number> {
  if (Platform.OS !== "android" || !ZioTelephony) return 0;
  if (drainInFlight) return 0;
  drainInFlight = true;
  try {
    let events: IdentifiedCallEvent[] = [];
    try {
      const raw = ZioTelephony.getIdentifiedCallQueue();
      const parsed = JSON.parse(raw || "[]");
      if (Array.isArray(parsed)) {
        events = parsed.filter(
          (e): e is IdentifiedCallEvent =>
            !!e && typeof e.n === "string" && typeof e.ts === "number",
        );
      }
    } catch {
      return 0;
    }
    if (events.length === 0) return 0;
    const drained = events.length;

    const { items } = await listContacts();
    const byKey = new Map<string, Contact>();
    for (const c of items) {
      for (const p of c.phones ?? []) {
        const key = phoneKey(p.value_e164 ?? p.value ?? "");
        if (key && !byKey.has(key)) byKey.set(key, c);
      }
    }

    // Group events per matched contact so each contact gets one batched
    // POST regardless of how many calls queued up.
    const pending = new Map<
      number,
      { calls: { number: string; occurred_at: string }[] }
    >();
    for (const e of events) {
      const contact = byKey.get(phoneKey(e.n));
      if (!contact) continue; // Contact deleted since the ring — drop.
      const occurredAt = new Date(e.ts);
      if (Number.isNaN(occurredAt.getTime())) continue;
      const bucket = pending.get(contact.id) ?? { calls: [] };
      bucket.calls.push({ number: e.n, occurred_at: occurredAt.toISOString() });
      pending.set(contact.id, bucket);
    }

    let logged = 0;
    for (const [contactId, { calls }] of pending) {
      // Any failure aborts the drain WITHOUT clearing the queue so the
      // events retry on the next foreground (server dedupes replays).
      await logContactCalls(contactId, calls);
      logged += calls.length;
    }

    ZioTelephony.clearIdentifiedCallQueue(drained);
    return logged;
  } catch {
    // Best-effort: leave the queue intact and retry on the next foreground.
    return 0;
  } finally {
    drainInFlight = false;
  }
}
