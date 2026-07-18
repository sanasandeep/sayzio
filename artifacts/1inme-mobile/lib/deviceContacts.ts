import AsyncStorage from "@react-native-async-storage/async-storage";

import { bulkImportContacts, type ContactImportPayload } from "@/lib/api/contacts";

// Per-user persisted fingerprint of the last synced contact payload, so cold
// app starts with an unchanged address book skip the bulk POST too (the
// in-memory ref in useContactAutoSync only survives while the app stays open).
// Keyed by user id so switching accounts never skips a needed sync. The value
// is a cheap non-sensitive payload hash — AsyncStorage, not SecureStore.
const CONTACT_SYNC_FINGERPRINT_KEY_PREFIX = "contact_sync_fingerprint:";

export async function getStoredContactSyncFingerprint(
  userId: number | string,
): Promise<string | null> {
  try {
    return await AsyncStorage.getItem(
      `${CONTACT_SYNC_FINGERPRINT_KEY_PREFIX}${userId}`,
    );
  } catch {
    return null;
  }
}

export async function setStoredContactSyncFingerprint(
  userId: number | string,
  fingerprint: string,
): Promise<void> {
  try {
    await AsyncStorage.setItem(
      `${CONTACT_SYNC_FINGERPRINT_KEY_PREFIX}${userId}`,
      fingerprint,
    );
  } catch {
    // Best-effort cache; worst case the next cold start re-uploads once.
  }
}

export type DeviceImportResult = {
  created: number;
  updated: number;
  skipped: number;
  /** How many freshly created contacts now match an existing one. */
  duplicates_found: number;
};

export type DeviceImportOutcome =
  | { ok: true; result: DeviceImportResult; imported: number; fingerprint: string }
  | { ok: false; reason: "unavailable" | "denied" | "empty" }
  | { ok: false; reason: "unchanged"; fingerprint: string };

/**
 * Read the device address book and push it to the Sayzio contacts API.
 *
 * Mirrors the standalone dialer app's import flow: dynamic expo-contacts
 * import (so web/builds without the native module degrade to "unavailable"),
 * explicit permission handling, then a single bulk POST. When
 * `requestPermission` is false we only proceed if access was already granted,
 * so silent re-syncs never re-prompt the user.
 *
 * Change detection: when `unchangedFingerprint` is provided and the freshly
 * built payload hashes to the same fingerprint, the bulk POST is skipped and
 * `{ ok: false, reason: "unchanged", fingerprint }` is returned — so silent
 * background re-syncs of an unchanged address book cost zero network calls.
 * Successful imports also return the payload `fingerprint` for callers to
 * remember. The hash is computed inline (FNV-1a over the JSON payload) so
 * this function stays self-contained.
 */
export async function importDeviceContacts(opts?: {
  requestPermission?: boolean;
  unchangedFingerprint?: string | null;
}): Promise<DeviceImportOutcome> {
  const Contacts = await import("expo-contacts").catch(() => null);
  if (!Contacts) return { ok: false, reason: "unavailable" };

  let status = (await Contacts.getPermissionsAsync()).status;
  if (status !== "granted") {
    if (opts?.requestPermission) {
      status = (await Contacts.requestPermissionsAsync()).status;
    }
    if (status !== "granted") return { ok: false, reason: "denied" };
  }

  const { data } = await Contacts.getContactsAsync({
    fields: [
      Contacts.Fields.FirstName,
      Contacts.Fields.LastName,
      Contacts.Fields.Name,
      Contacts.Fields.Company,
      Contacts.Fields.Emails,
      Contacts.Fields.PhoneNumbers,
    ],
    pageSize: 500,
  });

  const payload: ContactImportPayload[] = data
    .map((c: any) => ({
      display_name: c.name ?? null,
      given_name: c.firstName ?? null,
      family_name: c.lastName ?? null,
      organization: c.company ?? null,
      emails: (c.emails ?? [])
        .filter((e: any) => e?.email)
        .map((e: any) => ({ value: e.email, label: e.label ?? null })),
      phones: (c.phoneNumbers ?? [])
        .filter((p: any) => p?.number)
        .map((p: any) => ({ value: p.number, label: p.label ?? null })),
    }))
    .filter((c) => (c.emails?.length ?? 0) || (c.phones?.length ?? 0) || c.display_name);

  if (!payload.length) return { ok: false, reason: "empty" };

  // Cheap change detection: FNV-1a hash of the exact payload we'd POST.
  const json = JSON.stringify(payload);
  let hash = 0x811c9dc5;
  for (let i = 0; i < json.length; i++) {
    hash = Math.imul(hash ^ json.charCodeAt(i), 0x01000193) >>> 0;
  }
  const fingerprint = `${json.length}:${hash.toString(16)}`;
  if (opts?.unchangedFingerprint && opts.unchangedFingerprint === fingerprint) {
    return { ok: false, reason: "unchanged", fingerprint };
  }

  const result = await bulkImportContacts(payload);
  return { ok: true, result, imported: payload.length, fingerprint };
}
