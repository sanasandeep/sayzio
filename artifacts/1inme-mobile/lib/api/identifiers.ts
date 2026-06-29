import { apiFetch } from "@/lib/api";

/**
 * Linked identifiers (Task #2779) — Bearer-token client for the Laravel
 * /api/v1/me/identifiers/* endpoints. Mirrors the web "Linked identifiers"
 * Account Settings section: list every verified email/phone/social, add +
 * verify a new email/phone, remove a non-primary one, and promote any
 * verified email/phone to primary.
 *
 * Remove + promote are backed by the same AccountMergeService guards as web
 * (and the WhatsApp disconnect flow), so each row carries up-front
 * eligibility (can_remove / can_promote) plus a human reason when blocked.
 *
 * Adding is stateless: the value is passed again on verify alongside the
 * code, so the client owns the "pending value" between the two calls.
 */

export type IdentifierKind = "email" | "phone" | "social";

export type LinkedIdentifier = {
  id: number;
  kind: IdentifierKind;
  kind_label: string;
  // Display value: the email/phone, or a friendly social label.
  value: string;
  is_primary: boolean;
  verified: boolean;
  can_remove: boolean;
  remove_blocked_reason: string | null;
  can_promote: boolean;
  promote_blocked_reason: string | null;
};

export type IdentifiersList = {
  identifiers: LinkedIdentifier[];
  // Kinds the client may add via this surface (social is OAuth-only).
  addable_kinds: ("email" | "phone")[];
};

export type IdentifierSendResult = {
  sent: boolean;
  kind: "email" | "phone";
  value: string;
  // Admin "Demo mode" toggle: the live code prefixed for on-screen display,
  // or null when the toggle is off.
  demo_reveal?: string | null;
};

export type IdentifierMutationResult = {
  identifiers: LinkedIdentifier[];
};

/** List every linked identifier with its remove/promote eligibility. */
export async function getIdentifiers(): Promise<IdentifiersList> {
  const res = await apiFetch<{ data: IdentifiersList }>("/me/identifiers");
  return res.data;
}

/** Step 1 — send a 6-digit code to the new email/phone being added. */
export async function sendIdentifierCode(
  kind: "email" | "phone",
  value: string,
): Promise<IdentifierSendResult> {
  const res = await apiFetch<{ data: IdentifierSendResult }>(
    "/me/identifiers/send",
    { method: "POST", body: JSON.stringify({ kind, value }) },
  );
  return res.data;
}

/** Step 2 — verify the code and link the new email/phone to this account. */
export async function verifyIdentifierCode(
  kind: "email" | "phone",
  value: string,
  code: string,
): Promise<IdentifierMutationResult> {
  const res = await apiFetch<{ data: IdentifierMutationResult }>(
    "/me/identifiers/verify",
    { method: "POST", body: JSON.stringify({ kind, value, code }) },
  );
  return res.data;
}

/** Remove a non-primary identifier from this account. */
export async function removeIdentifier(
  id: number,
): Promise<IdentifierMutationResult> {
  const res = await apiFetch<{ data: IdentifierMutationResult }>(
    `/me/identifiers/${id}`,
    { method: "DELETE" },
  );
  return res.data;
}

/** Promote a verified email/phone to be the account's primary identifier. */
export async function promoteIdentifier(
  id: number,
): Promise<IdentifierMutationResult> {
  const res = await apiFetch<{ data: IdentifierMutationResult }>(
    `/me/identifiers/${id}/promote`,
    { method: "POST" },
  );
  return res.data;
}
