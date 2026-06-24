import { apiFetch } from "@/lib/api";
import type { AuthUser } from "@/contexts/AuthContext";

// Bearer-token parity for the web back-office admin tooling: the admin <->
// user dashboard switch, the per-user role / admin-access assignment panel
// and user impersonation. The operator's authority comes from their
// email-linked back-office Admin record, so a signed-in mobile user with an
// active admin account reaches these with the same token — no re-login. Each
// endpoint is gated server-side behind the matching admin-guard permission
// (403 otherwise).

export type AdminCapabilities = {
  view_users: boolean;
  manage_roles: boolean;
  grant_admin: boolean;
  revoke_admin: boolean;
  impersonate: boolean;
  // Protected-accounts list: read needs `view_protected` (users.view),
  // add/remove needs `manage_protected` (super-admin only).
  view_protected: boolean;
  manage_protected: boolean;
};

export type AdminContext = {
  has_admin_access: boolean;
  admin: {
    id: number;
    name: string;
    role: { name: string; slug: string; is_super_admin: boolean } | null;
  } | null;
  can: AdminCapabilities;
};

export type AdminUserRow = {
  id: number;
  name: string;
  email: string;
  handle: string | null;
  avatar: string | null;
  status: string | null;
  plan: string | null;
  is_admin: boolean;
  admin_status: string | null;
  // On the canonical never-delete/suspend list — the UI hides destructive
  // controls; the server refuses regardless (defense in depth).
  is_protected: boolean;
};

export type AdminUsersPage = {
  users: AdminUserRow[];
  page: number;
  has_more: boolean;
  total: number;
};

export type RolePermission = { name: string; slug: string };

export type AssignableRole = {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  assigned: boolean;
  permissions: RolePermission[];
};

export type AdminRoleOption = {
  id: number;
  name: string;
  slug: string;
  is_super_admin: boolean;
  permissions_count: number;
  permissions: RolePermission[];
};

export type UserRolesPanel = {
  user: { id: number; name: string; email: string; is_protected: boolean };
  roles: AssignableRole[];
  admin_account: {
    id: number;
    status: string;
    role: { id: number; name: string; slug: string } | null;
  } | null;
  admin_roles: AdminRoleOption[];
  can_grant_admin: boolean;
  can_revoke_admin: boolean;
};

export type ImpersonationGrant = {
  token: string;
  user: AuthUser;
};

export async function getAdminContext(): Promise<AdminContext> {
  const res = await apiFetch<{ data: AdminContext }>("/admin/context");
  return res.data;
}

export async function listAdminUsers(
  search = "",
  page = 1,
): Promise<AdminUsersPage> {
  const params = new URLSearchParams();
  if (search.trim()) params.set("search", search.trim());
  if (page > 1) params.set("page", String(page));
  const qs = params.toString();
  const res = await apiFetch<{ data: AdminUsersPage }>(
    `/admin/users${qs ? `?${qs}` : ""}`,
  );
  return res.data;
}

export async function getUserRoles(userId: number): Promise<UserRolesPanel> {
  const res = await apiFetch<{ data: UserRolesPanel }>(
    `/admin/users/${userId}/roles`,
  );
  return res.data;
}

export async function updateUserRoles(
  userId: number,
  roleIds: number[],
): Promise<UserRolesPanel> {
  const res = await apiFetch<{ data: UserRolesPanel }>(
    `/admin/users/${userId}/roles`,
    { method: "PUT", body: JSON.stringify({ role_ids: roleIds }) },
  );
  return res.data;
}

export async function grantAdminAccess(
  userId: number,
  roleId: number,
): Promise<UserRolesPanel> {
  const res = await apiFetch<{ data: UserRolesPanel }>(
    `/admin/users/${userId}/admin-access`,
    { method: "POST", body: JSON.stringify({ role_id: roleId }) },
  );
  return res.data;
}

export async function revokeAdminAccess(
  userId: number,
): Promise<UserRolesPanel> {
  const res = await apiFetch<{ data: UserRolesPanel }>(
    `/admin/users/${userId}/admin-access`,
    { method: "DELETE" },
  );
  return res.data;
}

export async function impersonateUser(
  userId: number,
): Promise<ImpersonationGrant> {
  const res = await apiFetch<{ data: ImpersonationGrant }>(
    `/admin/users/${userId}/impersonate`,
    { method: "POST" },
  );
  return res.data;
}

// ── Protected accounts ────────────────────────────────────────────
// The canonical list of accounts that can never be deleted or suspended
// (mirrors the web back-office page). Staff with `users.view` may read it;
// only a super-admin may add/remove entries. The hard-locked seeds
// (super-admin + demo) can never be removed.

export type ProtectedAccount = {
  id: number;
  email: string;
  label: string | null;
  locked: boolean;
};

export type ProtectedAccountsList = {
  accounts: ProtectedAccount[];
  can_manage: boolean;
};

export async function listProtectedAccounts(): Promise<ProtectedAccountsList> {
  const res = await apiFetch<{ data: ProtectedAccountsList }>(
    "/admin/protected-accounts",
  );
  return res.data;
}

export async function addProtectedAccount(
  email: string,
  label?: string,
): Promise<ProtectedAccountsList> {
  const res = await apiFetch<{ data: ProtectedAccountsList }>(
    "/admin/protected-accounts",
    {
      method: "POST",
      body: JSON.stringify({ email, label: label?.trim() || undefined }),
    },
  );
  return res.data;
}

export async function removeProtectedAccount(
  id: number,
): Promise<ProtectedAccountsList> {
  const res = await apiFetch<{ data: ProtectedAccountsList }>(
    `/admin/protected-accounts/${id}`,
    { method: "DELETE" },
  );
  return res.data;
}
