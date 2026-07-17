import { useQueryClient } from "@tanstack/react-query";
import { useEffect, useRef } from "react";
import { AppState } from "react-native";

import { importDeviceContacts } from "@/lib/deviceContacts";

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
 */
export function useContactAutoSync(enabled: boolean) {
  const qc = useQueryClient();
  const lastRun = useRef(0);
  const running = useRef(false);

  useEffect(() => {
    if (!enabled) return;
    let mounted = true;

    const runSync = async () => {
      if (running.current) return;
      if (Date.now() - lastRun.current < MIN_INTERVAL_MS) return;
      running.current = true;
      lastRun.current = Date.now();
      let changed = false;
      try {
        const out = await importDeviceContacts({ requestPermission: false });
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

    void runSync();

    const sub = AppState.addEventListener("change", (state) => {
      if (state === "active") void runSync();
    });

    return () => {
      mounted = false;
      sub.remove();
    };
  }, [enabled, qc]);
}
