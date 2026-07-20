import { apiFetch } from "@/lib/api";

// Mobile parity for admin user-management actions that live in
// App\Modules\Api\Controllers\AdminAccessController (all auth:sanctum).
// Responses use the unified {data}/{error} envelope.
//
//   POST /admin/users/{user}/set-password   set / replace a user's password

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
