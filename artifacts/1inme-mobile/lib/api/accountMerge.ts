import { apiFetch } from "@/lib/api";

/**
 * Native account-merge flow (Task #2174) — Bearer-token client for the
 * Laravel /api/v1/account/merge/* endpoints. Mirrors the web /user/merge
 * flow: challenge → verify → preview → confirm, where the proven secondary
 * account rides between steps in an opaque, server-encrypted `merge_token`.
 */

export type MergeKind = "email" | "phone";

export type MergeChallengeResult = {
  sent: boolean;
  kind: string;
  value: string;
  message: string;
};

export type MergePreviewItem = {
  key: string;
  label: string;
  count: number;
};

export type MergePreviewIdentifier = {
  kind: string;
  label: string;
};

export type MergePreviewParty = {
  name: string | null;
  email: string | null;
};

export type MergePreview = {
  total_records: number;
  items: MergePreviewItem[];
  identifiers: MergePreviewIdentifier[];
  primary_has_paid_plan: boolean;
  secondary_has_paid_plan: boolean;
  primary: MergePreviewParty;
  secondary: MergePreviewParty;
};

export type MergeVerifyResult = {
  merge_token: string;
  preview: MergePreview;
};

export type MergeConfirmResult = {
  merged: boolean;
  records_moved: number;
  kept_plan_from: "primary" | "secondary";
  secondary_email: string | null;
  user: unknown;
};

/** Step 1 — send a one-time code to the OTHER account's email/phone. */
export async function mergeChallenge(input: {
  kind: MergeKind;
  value: string;
}): Promise<MergeChallengeResult> {
  const res = await apiFetch<{ data: MergeChallengeResult }>(
    "/account/merge/challenge",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data;
}

/** Step 2 — verify the code; returns a merge token + a preview of what moves. */
export async function mergeVerify(input: {
  kind: MergeKind;
  value: string;
  code: string;
}): Promise<MergeVerifyResult> {
  const res = await apiFetch<{ data: MergeVerifyResult }>(
    "/account/merge/verify",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data;
}

/** Step 3 (optional re-fetch) — rebuild the preview from a still-valid token. */
export async function mergePreview(
  mergeToken: string,
): Promise<MergeVerifyResult> {
  const res = await apiFetch<{ data: MergeVerifyResult }>(
    "/account/merge/preview",
    { method: "POST", body: JSON.stringify({ merge_token: mergeToken }) },
  );
  return res.data;
}

/** Step 4 — execute the merge. This cannot be undone. */
export async function mergeConfirm(input: {
  merge_token: string;
  keep_plan_from?: "primary" | "secondary";
}): Promise<MergeConfirmResult> {
  const res = await apiFetch<{ data: MergeConfirmResult }>(
    "/account/merge/confirm",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data;
}
