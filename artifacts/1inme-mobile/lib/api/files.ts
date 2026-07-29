import { apiFetch, getBaseUrl, MOBILE_USER_AGENT } from "@/lib/api";
import { getToken } from "@/lib/secure";

// Sayzio Files vault client (Task #5956 — mobile photo-sticker add flow).
// GET /me/files mirrors the web vault list (system-generated `context`
// files excluded); POST /me/files/upload goes through the shared
// UserFile::createFromUpload pipeline so quota / mime limits match web.

export type VaultFile = {
  id: number;
  type: string;
  original_name: string;
  mime_type: string;
  url: string;
  url_path: string;
  size_human: string;
  created_at: string | null;
};

export async function listVaultFiles(args?: {
  type?: "image" | "video" | "audio" | "document";
  page?: number;
  perPage?: number;
  q?: string;
}): Promise<{
  files: VaultFile[];
  pagination: { current_page: number; last_page: number; total: number };
}> {
  const params = new URLSearchParams();
  if (args?.type) params.set("type", args.type);
  if (args?.page) params.set("page", String(args.page));
  if (args?.perPage) params.set("per_page", String(args.perPage));
  if (args?.q) params.set("q", args.q);
  const qs = params.toString();
  const res = await apiFetch<{
    data: {
      files: VaultFile[];
      pagination: { current_page: number; last_page: number; total: number };
    };
  }>(`/me/files${qs ? `?${qs}` : ""}`);
  return res.data;
}

// apiFetch is JSON-only, so the multipart upload hand-rolls its own fetch
// with RN FormData ({uri, name, type} entry shape; never set Content-Type —
// RN fills in the multipart boundary itself).
export async function uploadVaultFile(args: {
  uri: string;
  name?: string;
  mime?: string;
}): Promise<VaultFile> {
  const token = await getToken();
  const fd = new FormData();
  const mime = args.mime || guessImageMime(args.uri) || "image/jpeg";
  const ext = extFromMime(mime);
  fd.append("file", {
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment
    // @ts-ignore – RN-specific FormData entry shape.
    uri: args.uri,
    name: args.name || `upload.${ext}`,
    type: mime,
  } as unknown as Blob);

  const headers: Record<string, string> = {
    Accept: "application/json",
    "User-Agent": MOBILE_USER_AGENT,
    "X-1INME-Client": MOBILE_USER_AGENT,
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  const res = await fetch(`${getBaseUrl()}/api/v1/me/files/upload`, {
    method: "POST",
    body: fd as unknown as BodyInit,
    headers,
  });
  const text = await res.text();
  const body = text ? safeJson(text) : null;
  if (!res.ok) {
    const nested =
      body && typeof body.error === "object" && body.error !== null
        ? (body.error as Record<string, unknown>)
        : null;
    const message =
      (nested && typeof nested.message === "string"
        ? (nested.message as string)
        : null) ||
      (body && typeof body.message === "string"
        ? (body.message as string)
        : null) ||
      `Upload failed (${res.status})`;
    // Carry code + details through so plan-gated rejections (402 storage
    // quota with a recommended_plan hint) are recognizable by
    // lib/upgradePrompt.ts's isPlanLockedError / upgradeHintFromError.
    const code =
      nested && typeof nested.code === "string"
        ? (nested.code as string)
        : undefined;
    const details =
      nested &&
      typeof nested.details === "object" &&
      nested.details !== null &&
      !Array.isArray(nested.details)
        ? (nested.details as Record<string, unknown>)
        : undefined;
    throw { status: res.status, message, code, details };
  }
  return (body as { data: { file: VaultFile } }).data.file;
}

// Task #6028 — server-side import of a curated platform asset. The
// asset CDN (CloudFront) serves no CORS headers, so the WEB build can't
// browser-fetch the blob to re-upload it; instead the server reads the
// S3 object itself (key allow-listed to the assets/<folder>/ prefixes)
// and vault-writes it. Used by the stock-sticker pick on ALL platforms
// (native could download+upload, but one shared path can't drift).
export async function importPlatformAsset(args: {
  key: string;
}): Promise<VaultFile> {
  const res = await apiFetch<{ data: { file: VaultFile } }>(
    "/me/files/import-platform-asset",
    {
      method: "POST",
      body: JSON.stringify({ key: args.key }),
    },
  );
  return res.data.file;
}

function guessImageMime(uri: string): string | null {
  const ext = uri.split("?")[0].split(".").pop()?.toLowerCase();
  switch (ext) {
    case "jpg":
    case "jpeg":
      return "image/jpeg";
    case "png":
      return "image/png";
    case "gif":
      return "image/gif";
    case "webp":
      return "image/webp";
    case "svg":
      return "image/svg+xml";
    default:
      return null;
  }
}

function extFromMime(mime: string): string {
  switch (mime) {
    case "image/png":
      return "png";
    case "image/gif":
      return "gif";
    case "image/webp":
      return "webp";
    case "image/svg+xml":
      return "svg";
    default:
      return "jpg";
  }
}

function safeJson(text: string): Record<string, unknown> | null {
  try {
    return JSON.parse(text) as Record<string, unknown>;
  } catch {
    return null;
  }
}
