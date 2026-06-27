import { apiFetch } from "@/lib/api";

// Mirrors the web Cloud File Library (Google Drive / Dropbox / OneDrive).
// All requests go through the standard `{data}` API envelope and honour the
// workspace files.view / files.create / files.delete permissions server-side.

export type CloudProviderInfo = {
  provider: string;
  label: string;
  icon: string;
  configured: boolean;
};

export type CloudConnection = {
  id: number;
  provider: string;
  provider_label: string;
  account_label?: string | null;
  account_email?: string | null;
  is_broken: boolean;
  last_error?: string | null;
  expires_soon: boolean;
  last_synced_at?: string | null;
};

export type CloudConnectionsState = {
  providers: CloudProviderInfo[];
  connections: CloudConnection[];
};

export type CloudFolder = { id: string; name: string };

export type CloudRemoteFile = {
  id: string;
  name: string;
  mime?: string | null;
  size?: number | null;
  link: string;
  thumbnail_url?: string | null;
};

export type CloudBrowseResult = {
  folders: CloudFolder[];
  files: CloudRemoteFile[];
  cursor?: string | null;
};

export type CloudLibraryFile = {
  id: number;
  name: string;
  link: string;
  mime?: string | null;
  size: number;
  human_size: string;
  provider: string;
  provider_icon: string;
  provider_label: string;
  thumbnail_url?: string | null;
  connection_id?: number | null;
  added_by?: string | null;
  added_at?: string | null;
};

export type CloudLibraryPage = {
  files: CloudLibraryFile[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export type CloudAttachment = {
  id: number;
  cloud_file_id: number;
  name?: string | null;
  link?: string | null;
  provider?: string | null;
  provider_icon?: string | null;
  provider_label?: string | null;
  human_size?: string | null;
};

export type CloudAttachTargetType = "post" | "task_card" | "inbox_reply";

export async function getCloudConnections(): Promise<CloudConnectionsState> {
  const res = await apiFetch<{ data: CloudConnectionsState }>(
    "/cloud-files/connections",
  );
  return res.data;
}

/**
 * Begin the OAuth connect flow. Returns the provider authorize URL for the
 * app to open in an in-app browser. The `return` deep link is whitelisted
 * server-side (sayzio://cloud-oauth).
 */
export async function startCloudConnect(
  provider: string,
): Promise<{ authorize_url: string }> {
  const res = await apiFetch<{ data: { authorize_url: string } }>(
    `/cloud-files/${provider}/connect`,
    {
      method: "POST",
      body: JSON.stringify({ return: "sayzio://cloud-oauth" }),
    },
  );
  return res.data;
}

export async function disconnectCloud(id: number): Promise<void> {
  await apiFetch(`/cloud-files/connections/${id}`, { method: "DELETE" });
}

export async function browseCloud(
  connectionId: number,
  opts: { folder?: string | null; search?: string | null; cursor?: string | null } = {},
): Promise<CloudBrowseResult> {
  const qs = new URLSearchParams();
  if (opts.folder) qs.set("folder", opts.folder);
  if (opts.search) qs.set("search", opts.search);
  if (opts.cursor) qs.set("cursor", opts.cursor);
  const suffix = qs.toString() ? `?${qs.toString()}` : "";
  const res = await apiFetch<{ data: CloudBrowseResult }>(
    `/cloud-files/picker/${connectionId}${suffix}`,
  );
  return res.data;
}

export async function getCloudLibrary(
  opts: { provider?: string | null; q?: string | null; page?: number } = {},
): Promise<CloudLibraryPage> {
  const qs = new URLSearchParams();
  if (opts.provider) qs.set("provider", opts.provider);
  if (opts.q) qs.set("q", opts.q);
  if (opts.page) qs.set("page", String(opts.page));
  const suffix = qs.toString() ? `?${qs.toString()}` : "";
  const res = await apiFetch<{ data: CloudLibraryPage }>(
    `/cloud-files${suffix}`,
  );
  return res.data;
}

export async function saveToCloudLibrary(payload: {
  connection_id: number;
  items: CloudRemoteFile[];
  parent_folder_path?: string | null;
}): Promise<{ added: number }> {
  const res = await apiFetch<{ data: { added: number } }>("/cloud-files", {
    method: "POST",
    body: JSON.stringify({
      connection_id: payload.connection_id,
      parent_folder_path: payload.parent_folder_path ?? null,
      items: payload.items.map((f) => ({
        remote_id: f.id,
        name: f.name,
        mime: f.mime ?? null,
        size: f.size ?? 0,
        link: f.link,
        thumbnail_url: f.thumbnail_url ?? null,
      })),
    }),
  });
  return res.data;
}

export async function removeFromCloudLibrary(id: number): Promise<void> {
  await apiFetch(`/cloud-files/${id}`, { method: "DELETE" });
}

export async function getCloudAttachments(
  targetType: CloudAttachTargetType,
  targetId: number,
): Promise<CloudAttachment[]> {
  const qs = new URLSearchParams({
    target_type: targetType,
    target_id: String(targetId),
  });
  const res = await apiFetch<{ data: { attachments: CloudAttachment[] } }>(
    `/cloud-files/attachments?${qs.toString()}`,
  );
  return res.data.attachments;
}

export async function attachCloudFiles(payload: {
  target_type: CloudAttachTargetType;
  target_id: number;
  cloud_file_ids: number[];
}): Promise<CloudAttachment[]> {
  const res = await apiFetch<{ data: { attachments: CloudAttachment[] } }>(
    "/cloud-files/attach",
    { method: "POST", body: JSON.stringify(payload) },
  );
  return res.data.attachments;
}

export async function detachCloudFile(attachmentId: number): Promise<void> {
  await apiFetch(`/cloud-files/attach/${attachmentId}`, { method: "DELETE" });
}
