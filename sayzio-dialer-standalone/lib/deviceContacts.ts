import { bulkImportContacts } from "@/lib/api/contacts";

export type DeviceImportResult = {
  created: number;
  updated: number;
  skipped: number;
};

export type DeviceImportOutcome =
  | { ok: true; result: DeviceImportResult; imported: number }
  | { ok: false; reason: "unavailable" | "denied" | "empty" };

/**
 * Read the device address book and push it to the Sayzio contacts API.
 *
 * Shared by the manual "import" button (which surfaces the outcome) and the
 * near-instant auto-sync (which runs silently). When `requestPermission` is
 * false we only proceed if access was already granted, so background/foreground
 * re-syncs never re-prompt the user.
 */
export async function importDeviceContacts(opts?: {
  requestPermission?: boolean;
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

  const payload = data
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
    .filter((c: any) => c.emails.length || c.phones.length || c.display_name);

  if (!payload.length) return { ok: false, reason: "empty" };

  const result = await bulkImportContacts(payload);
  return { ok: true, result, imported: payload.length };
}
