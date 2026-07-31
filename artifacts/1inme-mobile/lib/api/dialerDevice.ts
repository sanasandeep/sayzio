import AsyncStorage from "@react-native-async-storage/async-storage";
import * as Device from "expo-device";
import { Platform } from "react-native";

import { apiFetch } from "@/lib/api";

/**
 * Dialer device registration — records this install with the backend at
 * sign-in/unlock, independent of push-token registration, so the Zio
 * Browser Dialer pane knows a phone is linked even when notification
 * permission was denied (task #6353).
 *
 * The device key is a random per-install identifier persisted locally; it
 * carries no secrets (rows are scoped server-side to the signed-in user).
 */

const DEVICE_KEY_STORAGE = "1inme.dialer.deviceKey";

let syncing = false;
let lastRegisteredKey: string | null = null;

function generateDeviceKey(): string {
  // 32 hex chars; Math.random is fine here — the key only identifies the
  // install and every server operation is additionally user-scoped.
  let key = "";
  for (let i = 0; i < 32; i++) {
    key += Math.floor(Math.random() * 16).toString(16);
  }
  return key;
}

/** Read the persisted device key without creating one. */
async function readDeviceKey(): Promise<string | null> {
  try {
    return (await AsyncStorage.getItem(DEVICE_KEY_STORAGE)) || null;
  } catch {
    return null;
  }
}

/** Read the persisted device key, generating and storing one if absent. */
async function ensureDeviceKey(): Promise<string | null> {
  const existing = await readDeviceKey();
  if (existing) return existing;
  const fresh = generateDeviceKey();
  try {
    await AsyncStorage.setItem(DEVICE_KEY_STORAGE, fresh);
  } catch {
    // Storage unavailable — registration would churn a new key every
    // launch, so skip rather than pile up server rows.
    return null;
  }
  return fresh;
}

/**
 * Register (or heartbeat) this install with the backend. Safe to call on
 * every sign-in / unlock — it de-dupes within the session and the server
 * upserts on (user, device_key). Best-effort: never throws.
 */
export async function syncDialerDeviceRegistration(): Promise<void> {
  if (syncing) return;
  syncing = true;
  try {
    const key = await ensureDeviceKey();
    if (!key || key === lastRegisteredKey) return;
    await apiFetch(`/dialer/device`, {
      method: "POST",
      body: JSON.stringify({
        device_key: key,
        platform: Platform.OS,
        device_name: Device.deviceName ?? undefined,
      }),
    });
    lastRegisteredKey = key;
  } catch {
    /* best-effort — the pane's status check just stays as-is */
  } finally {
    syncing = false;
  }
}

/**
 * Detach this install from the user (best-effort, on sign-out, while the
 * bearer token is still valid). The local key is kept so a later sign-in
 * re-registers the same install.
 */
export async function clearDialerDeviceRegistration(): Promise<void> {
  lastRegisteredKey = null;
  const key = await readDeviceKey();
  if (!key) return;
  try {
    await apiFetch(`/dialer/device`, {
      method: "DELETE",
      body: JSON.stringify({ device_key: key }),
    });
  } catch {
    /* best-effort — sign-out should never block on this */
  }
}
