import { useQueryClient } from "@tanstack/react-query";
import { useEffect, useRef } from "react";
import { AppState } from "react-native";

import { googleContacts } from "@/lib/api/contacts";
import { importDeviceContacts } from "@/lib/deviceContacts";

// Don't re-run the full sync more than once a minute when the app bounces in
// and out of the foreground — enough to feel "near-instant" without hammering
// the device address book or the API.
const MIN_INTERVAL_MS = 60_000;

/**
 * Near-instant contacts sync.
 *
 * While enabled (i.e. signed in) this:
 *  - imports the device address book on app open (prompting for the contacts
 *    permission the first time),
 *  - re-imports whenever the app returns to the foreground, which catches
 *    address-book edits made while the app was backgrounded, and
 *  - triggers the account's Google Contacts sync on open / foreground.
 *
 * Everything runs silently; the manual Contacts-tab actions remain for
 * on-demand, user-visible syncs.
 */
export function useContactAutoSync(enabled: boolean) {
  const qc = useQueryClient();
  const lastRun = useRef(0);
  const running = useRef(false);

  useEffect(() => {
    if (!enabled) return;
    let mounted = true;

    const runSync = async (requestPermission: boolean) => {
      if (running.current) return;
      if (Date.now() - lastRun.current < MIN_INTERVAL_MS) return;
      running.current = true;
      lastRun.current = Date.now();
      let changed = false;
      try {
        const out = await importDeviceContacts({ requestPermission });
        if (out.ok) changed = true;
      } catch {
        // Import is best-effort; ignore device/permission failures.
      }
      try {
        // Trigger the account's Google Contacts sync. Fails harmlessly when
        // no Google account is connected.
        await googleContacts.sync();
        changed = true;
      } catch {
        // No connected Google account (or transient error) — ignore.
      }
      running.current = false;
      if (mounted && changed) {
        qc.invalidateQueries({ queryKey: ["contacts"] });
      }
    };

    void runSync(true);

    const sub = AppState.addEventListener("change", (state) => {
      if (state === "active") void runSync(false);
    });

    return () => {
      mounted = false;
      sub.remove();
    };
  }, [enabled, qc]);
}
