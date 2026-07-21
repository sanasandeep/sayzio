import { Platform } from "react-native";

import { listContacts } from "@/lib/api/contacts";
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
    const { items } = await listContacts();
    const dir: { n: string; name: string; photo?: string; org?: string }[] =
      [];
    for (const c of items) {
      const name =
        c.display_name ??
        [c.given_name, c.family_name].filter(Boolean).join(" ").trim();
      if (!name) continue;
      for (const p of c.phones ?? []) {
        const num = (p.value_e164 ?? p.value ?? "").trim();
        if (!num) continue;
        const entry: { n: string; name: string; photo?: string; org?: string } =
          { n: num, name };
        if (c.photo_url) entry.photo = c.photo_url;
        if (c.organization) entry.org = c.organization;
        dir.push(entry);
      }
    }
    ZioTelephony.setCallerDirectory(JSON.stringify(dir));
  } catch {
    // Directory refresh is best-effort; the old snapshot keeps working.
  }
}
