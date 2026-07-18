import { useQueryClient } from "@tanstack/react-query";
import { useEffect, useRef } from "react";
import { AppState } from "react-native";

import {
  getStoredContactSyncFingerprint,
  importDeviceContacts,
  setStoredContactSyncFingerprint,
} from "@/lib/deviceContacts";

// Don't re-run the full sync more than once a minute when the app bounces in
// and out of the foreground — enough to feel "near-instant" without hammering
// the device address book or the API.
const MIN_INTERVAL_MS = 60_000;

/**
 * Silent background contacts sync (ported from the standalone dialer app).
 *
 * While enabled (i.e. signed in and unlocked) this re-imports the device
 * address book on app start and whenever the app returns to the foreground —
 * but only when the contacts permission was already granted
 * (`requestPermission: false`), so it never prompts the user. The manual
 * "Import from phone" button on the Contacts screen remains the only place
 * that asks for permission.
 *
 * No alerts are shown; on a successful import the contacts and
 * duplicate-count queries are invalidated so open screens refresh.
 *
 * Change detection survives app restarts: the fingerprint of the last synced
 * payload is persisted per signed-in user (AsyncStorage) and seeded into the
 * in-memory ref before the first sync, so a cold start with an unchanged
 * address book also skips the bulk POST. Keying by `userId` means switching
 * accounts never skips a sync the new account still needs.
 */
export function useContactAutoSync(
  enabled: boolean,
  userId?: number | string | null,
) {
  const qc = useQueryClient();
  const lastRun = useRef(0);
  const running = useRef(false);
  // Fingerprint of the last payload we synced (or confirmed unchanged), so
  // foreground resumes with an unchanged address book skip the network POST.
  const lastFingerprint = useRef<string | null>(null);
  // Which user the refs above belong to; a different signed-in user resets
  // the fingerprint and the throttle so the new account syncs right away.
  const lastUserId = useRef<number | string | null>(null);

  useEffect(() => {
    if (!enabled || userId == null) return;
    let mounted = true;

    if (lastUserId.current !== userId) {
      lastUserId.current = userId;
      lastFingerprint.current = null;
      lastRun.current = Date.now() - MIN_INTERVAL_MS;
    }

    const runSync = async () => {
      if (running.current) return;
      if (Date.now() - lastRun.current < MIN_INTERVAL_MS) return;
      running.current = true;
      lastRun.current = Date.now();
      let changed = false;
      try {
        const out = await importDeviceContacts({
          requestPermission: false,
          unchangedFingerprint: lastFingerprint.current,
        });
        if ("fingerprint" in out) {
          lastFingerprint.current = out.fingerprint;
          void setStoredContactSyncFingerprint(userId, out.fingerprint);
        }
        if (out.ok) changed = true;
      } catch {
        // Import is best-effort; ignore device/permission failures.
      }
      running.current = false;
      if (mounted && changed) {
        qc.invalidateQueries({ queryKey: ["contacts"] });
        qc.invalidateQueries({ queryKey: ["contact-duplicate-count"] });
      }
    };

    const start = async () => {
      // Seed the in-memory fingerprint from the per-user persisted copy so a
      // cold app start with an unchanged address book skips the POST too.
      if (lastFingerprint.current === null) {
        const stored = await getStoredContactSyncFingerprint(userId);
        if (stored && mounted && lastFingerprint.current === null) {
          lastFingerprint.current = stored;
        }
      }
      await runSync();
    };

    void start();

    const sub = AppState.addEventListener("change", (state) => {
      if (state === "active") void runSync();
    });

    return () => {
      mounted = false;
      sub.remove();
    };
  }, [enabled, qc, userId]);
}
