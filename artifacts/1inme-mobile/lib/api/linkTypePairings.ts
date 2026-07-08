import { apiFetch } from "@/lib/api";

// Bearer-token parity for the web admin "Perfect Pairings" toggles page.
// Read the per-page cross-promo card catalog with enabled flags, save the
// checkbox state, or restore defaults. The catalog itself is code-defined on
// the server; admins only enable/disable individual cards per page type.
// Every endpoint is gated server-side behind `settings.manage` (403 otherwise).

export type PairingCard = {
  name: string;
  type: string;
  icon: string;
  benefit: string;
  enabled: boolean;
};

export type PairingSection = {
  key: string;
  label: string;
  items: PairingCard[];
};

export type PairingsStatus = {
  sections: PairingSection[];
};

// Save payload mirrors the web form: a map of pageKey => list of ENABLED card
// types. Anything in the catalog not listed as enabled is stored as disabled;
// omitting a page key disables all of its cards (hides the whole section).
export type PairingsUpdate = {
  enabled: Record<string, string[]>;
};

export async function getLinkTypePairings(): Promise<PairingsStatus> {
  const res = await apiFetch<{ data: PairingsStatus }>("/admin/link-type-pairings");
  return res.data;
}

export async function updateLinkTypePairings(
  update: PairingsUpdate,
): Promise<PairingsStatus> {
  const res = await apiFetch<{ data: PairingsStatus }>("/admin/link-type-pairings", {
    method: "PUT",
    body: JSON.stringify(update),
  });
  return res.data;
}

export async function restoreLinkTypePairingDefaults(): Promise<PairingsStatus> {
  const res = await apiFetch<{ data: PairingsStatus }>(
    "/admin/link-type-pairings/restore-defaults",
    { method: "POST" },
  );
  return res.data;
}
