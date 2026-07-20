import { apiFetch } from "@/lib/api";

// Mobile parity for admin user-management actions that live in
// App\Modules\Api\Controllers\AdminAccessController (all auth:sanctum).
// Responses use the unified {data}/{error} envelope.
//
//   GET  /admin/context                     admin capabilities for this token
//   POST /admin/users/{user}/set-password   set / replace a user's password

export type AdminCapabilities = {
  set_user_password?: boolean;
  [key: string]: boolean | undefined;
};

/**
 * Per-action capability map for the signed-in user's linked back-office
 * admin account. Returns an all-false map (never throws a 403) when the
 * caller has no active admin account, so UI gating can key off it directly.
 */
export async function getAdminCapabilities(): Promise<AdminCapabilities> {
  const r = await apiFetch<{
    data: { has_admin_access: boolean; can: AdminCapabilities };
  }>("/admin/context");
  return r.data.can ?? {};
}

/**
 * Set (or replace) the password for a user account.
 * The calling admin must hold the `users.edit` permission;
 * protected accounts are rejected with 403.
 */
export async function setUserPassword(
  userId: number,
  password: string,
): Promise<{ message: string }> {
  const r = await apiFetch<{ data: { message: string } }>(
    `/admin/users/${userId}/set-password`,
    { method: "POST", body: JSON.stringify({ password }) },
  );
  return r.data;
}
