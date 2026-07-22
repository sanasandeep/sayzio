import { apiFetch } from "@/lib/api";

export type BackupCodeStatus = {
  enabled: boolean;
  total: number;
  remaining: number;
  generated_at: string | null;
  last_used_at: string | null;
};

export type TrustedContact = {
  id: number;
  contact_user_id: number;
  contact_handle: string | null;
  contact_name: string | null;
  contact_avatar: string | null;
  status: "pending" | "active" | "revoked";
  invited_at: string;
  accepted_at: string | null;
};

export type TrustedContactInvitation = {
  id: number;
  owner_user_id: number;
  owner_handle: string | null;
  owner_name: string | null;
  owner_avatar: string | null;
  invited_at: string;
};

export type RecoveryRequest = {
  id: number;
  owner_user_id: number;
  owner_handle: string | null;
  owner_name: string | null;
  reason: string | null;
  status: "pending" | "approved" | "denied" | "expired" | "cancelled";
  confirmations_required: number;
  confirmations_received: number;
  my_confirmation: "confirmed" | "denied" | null;
  created_at: string;
  expires_at: string;
};

export type PendingSensitiveChange = {
  id: number;
  kind: "email" | "password";
  new_email_masked: string | null;
  requested_at: string;
  effective_at: string;
  ip_address: string | null;
  user_agent: string | null;
};

export type SecuritySettings = {
  cool_off_hours: number;
  trusted_contacts_max: number;
  trusted_contacts_required_to_recover: number;
  block_new_devices_during_cool_off: boolean;
};

// ── TOTP enrolment ────────────────────────────────────────────────
export type TwoFactorSetup = {
  secret: string;
  otpauth_uri: string;
  issuer: string;
  account: string;
};

export async function setupTwoFactor(): Promise<TwoFactorSetup> {
  const res = await apiFetch<{ data: TwoFactorSetup }>("/auth/2fa/setup", {
    method: "POST",
    body: JSON.stringify({}),
  });
  return res.data;
}

export async function enableTwoFactor(
  code: string,
): Promise<{ codes: string[]; status: BackupCodeStatus }> {
  const res = await apiFetch<{
    data: { codes: string[]; backup_codes: BackupCodeStatus };
  }>("/auth/2fa/enable", {
    method: "POST",
    body: JSON.stringify({ code }),
  });
  return { codes: res.data.codes, status: res.data.backup_codes };
}

export async function disableTwoFactor(input: {
  code?: string;
  backup_code?: string;
}): Promise<BackupCodeStatus> {
  const res = await apiFetch<{ data: { backup_codes: BackupCodeStatus } }>(
    "/auth/2fa",
    { method: "DELETE", body: JSON.stringify(input) },
  );
  return res.data.backup_codes;
}

// ── Backup codes ──────────────────────────────────────────────────
export async function getBackupCodeStatus(): Promise<BackupCodeStatus> {
  const res = await apiFetch<{ data: { backup_codes: BackupCodeStatus } }>(
    "/auth/2fa/backup-codes",
  );
  return res.data.backup_codes;
}

export async function generateBackupCodes(
  count?: number,
): Promise<{ codes: string[]; status: BackupCodeStatus }> {
  const res = await apiFetch<{
    data: { codes: string[]; backup_codes: BackupCodeStatus };
  }>("/auth/2fa/backup-codes", {
    method: "POST",
    body: JSON.stringify(count ? { count } : {}),
  });
  return { codes: res.data.codes, status: res.data.backup_codes };
}

export async function verifyBackupCode(input: {
  challenge_token: string;
  code: string;
  device?: string | null;
}): Promise<{ token: string; user: unknown }> {
  const res = await apiFetch<{ data: { token: string; user: unknown } }>(
    "/auth/2fa/backup-codes/verify",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data;
}

/**
 * Complete a sign-in that was interrupted by the second factor: trade the
 * short-lived `challenge_token` (returned alongside `totp_required`) plus a
 * 6-digit authenticator code for a real session. The backend accepts a
 * backup code here too — both /auth/2fa endpoints land on the same action.
 */
export async function verifyTotpChallenge(input: {
  challenge_token: string;
  code: string;
  device?: string | null;
}): Promise<{ token: string; user: unknown }> {
  const res = await apiFetch<{ data: { token: string; user: unknown } }>(
    "/auth/2fa/challenge/verify",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data;
}

// ── Password management (Task #5619) ─────────────────────────────
export async function changePassword(input: {
  current_password: string;
  password: string;
  password_confirmation: string;
}): Promise<void> {
  await apiFetch("/me/password/change", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export async function sendSetPasswordCode(): Promise<{
  sent: boolean;
  channel: "email" | "mobile";
}> {
  const res = await apiFetch<{
    data: { sent: boolean; channel: "email" | "mobile" };
  }>("/me/password/set-code", { method: "POST", body: JSON.stringify({}) });
  return res.data;
}

export async function setFirstPassword(input: {
  code: string;
  password: string;
  password_confirmation: string;
}): Promise<void> {
  await apiFetch("/me/password/set", {
    method: "POST",
    body: JSON.stringify(input),
  });
}

export async function forgotPassword(email: string): Promise<string> {
  const res = await apiFetch<{ data: { message: string } }>(
    "/auth/password/forgot",
    { method: "POST", body: JSON.stringify({ email }) },
  );
  return res.data.message;
}

// ── Settings ──────────────────────────────────────────────────────
export async function getSecuritySettings(): Promise<SecuritySettings> {
  const res = await apiFetch<{ data: { settings: SecuritySettings } }>(
    "/security/settings",
  );
  return res.data.settings;
}

export async function updateSecuritySettings(
  patch: Partial<SecuritySettings>,
): Promise<SecuritySettings> {
  const res = await apiFetch<{ data: { settings: SecuritySettings } }>(
    "/security/settings",
    { method: "PATCH", body: JSON.stringify(patch) },
  );
  return res.data.settings;
}

// ── Trusted contacts ──────────────────────────────────────────────
export async function listTrustedContacts(): Promise<TrustedContact[]> {
  const res = await apiFetch<{ data: { items: TrustedContact[] } }>(
    "/security/trusted-contacts",
  );
  return res.data.items;
}

export async function nominateTrustedContact(input: {
  contact_user_id?: number;
  handle?: string;
}): Promise<TrustedContact> {
  const res = await apiFetch<{ data: { contact: TrustedContact } }>(
    "/security/trusted-contacts",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.contact;
}

export async function revokeTrustedContact(id: number): Promise<void> {
  await apiFetch(`/security/trusted-contacts/${id}`, { method: "DELETE" });
}

export async function listTrustedContactInvitations(): Promise<
  TrustedContactInvitation[]
> {
  const res = await apiFetch<{ data: { items: TrustedContactInvitation[] } }>(
    "/security/trusted-contacts/invitations",
  );
  return res.data.items;
}

export async function acceptTrustedContactInvitation(id: number): Promise<void> {
  await apiFetch(`/security/trusted-contacts/invitations/${id}/accept`, {
    method: "POST",
  });
}

export async function declineTrustedContactInvitation(id: number): Promise<void> {
  await apiFetch(`/security/trusted-contacts/invitations/${id}/decline`, {
    method: "POST",
  });
}

// ── Recovery requests (as a contact) ──────────────────────────────
export async function listRecoveryRequests(): Promise<RecoveryRequest[]> {
  const res = await apiFetch<{ data: { items: RecoveryRequest[] } }>(
    "/security/recovery/requests",
  );
  return res.data.items;
}

export async function decideRecoveryRequest(
  id: number,
  decision: "confirmed" | "denied",
): Promise<RecoveryRequest> {
  const res = await apiFetch<{ data: { request: RecoveryRequest } }>(
    `/security/recovery/requests/${id}/confirm`,
    { method: "POST", body: JSON.stringify({ decision }) },
  );
  return res.data.request;
}

// ── Pending sensitive changes (cool-off) ──────────────────────────
export async function listPendingSensitiveChanges(): Promise<
  PendingSensitiveChange[]
> {
  const res = await apiFetch<{ data: { items: PendingSensitiveChange[] } }>(
    "/security/sensitive-changes",
  );
  return res.data.items;
}

export async function stageSensitiveChange(input: {
  kind: "email" | "password";
  new_email?: string;
  new_password?: string;
  current_password?: string;
}): Promise<PendingSensitiveChange> {
  const res = await apiFetch<{ data: { change: PendingSensitiveChange } }>(
    "/security/sensitive-changes",
    { method: "POST", body: JSON.stringify(input) },
  );
  return res.data.change;
}

export async function cancelSensitiveChange(
  id: number,
  cancellationToken?: string,
): Promise<void> {
  const qs = cancellationToken
    ? `?cancellation_token=${encodeURIComponent(cancellationToken)}`
    : "";
  await apiFetch(`/security/sensitive-changes/${id}/cancel${qs}`, {
    method: "POST",
  });
}
