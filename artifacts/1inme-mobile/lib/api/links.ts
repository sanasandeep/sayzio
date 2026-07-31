import { Platform } from "react-native";

import { apiFetch, getBaseUrl, MOBILE_USER_AGENT } from "@/lib/api";
import { getToken } from "@/lib/secure";

export type Link = {
  id: number;
  type: string;
  alias: string;
  title: string | null;
  long_url: string | null;
  visibility: "public" | "registered" | "followers" | "subscribers";
  is_active: boolean;
  is_verified: boolean;
  is_password_protected: boolean;
  expires_at: string | null;
  total_clicks: number;
  unique_clicks: number;
  seo_title: string | null;
  seo_description: string | null;
  seo_image: string | null;
  domain_id: number | null;
  domain: string | null;
  short_url: string;
  created_at: string | null;
  updated_at: string | null;
  settings?: Record<string, unknown> | null;
  /** True while the page's styling is locked to an applied design template. */
  design_locked?: boolean;
  design_lock?: {
    template_id: number | null;
    template_name: string | null;
    locked_at: string | null;
  } | null;
};

export type LinksMeta = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};

export type LinksList = { items: Link[]; meta: LinksMeta };

export async function listLinks(params: {
  type?: string;
  q?: string;
  page?: number;
  per_page?: number;
} = {}): Promise<LinksList> {
  const qs = new URLSearchParams();
  if (params.type) qs.set("type", params.type);
  if (params.q) qs.set("q", params.q);
  if (params.page) qs.set("page", String(params.page));
  if (params.per_page) qs.set("per_page", String(params.per_page));
  const res = await apiFetch<{ data: LinksList }>(
    `/links${qs.toString() ? `?${qs}` : ""}`,
  );
  return res.data;
}

/**
 * Download the caller's link list as a CSV, honouring the same
 * `type`/`q` filters as the list. Mirrors the web "Export CSV" action and
 * the exportMyCalendar pattern: on web we fetch a blob and trigger an
 * anchor download; on native we download to the cache with the auth header
 * and hand the file to the share sheet. Not plan-gated.
 */
export async function exportLinksCsv(
  params: { type?: string; q?: string } = {},
): Promise<void> {
  const qs = new URLSearchParams();
  if (params.type) qs.set("type", params.type);
  if (params.q) qs.set("q", params.q);

  const token = await getToken();
  const url = `${getBaseUrl()}/api/v1/links/export.csv${
    qs.toString() ? `?${qs}` : ""
  }`;
  const filename = `my-links-${new Date().toISOString().slice(0, 10)}.csv`;
  const headers: Record<string, string> = {
    "User-Agent": MOBILE_USER_AGENT,
    "X-1INME-Client": MOBILE_USER_AGENT,
  };
  if (token) headers.Authorization = `Bearer ${token}`;

  if (Platform.OS === "web") {
    const res = await fetch(url, { headers });
    if (!res.ok) throw new Error(`Export failed (${res.status}).`);
    const blob = await res.blob();
    const href = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = href;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(href);
    return;
  }

  const FileSystem = await import("expo-file-system/legacy");
  const Sharing = await import("expo-sharing");
  const target = `${FileSystem.cacheDirectory ?? ""}${filename}`;
  const dl = await FileSystem.downloadAsync(url, target, { headers });
  if (dl.status !== 200) throw new Error(`Export failed (${dl.status}).`);
  if (await Sharing.isAvailableAsync()) {
    await Sharing.shareAsync(dl.uri, {
      mimeType: "text/csv",
      dialogTitle: "Export links",
    });
  }
}

export type QuickShortenResult = {
  id: number;
  short_url: string;
  long_url: string;
  kind: "url" | "email" | "phone";
};

/**
 * Clipboard quick-shorten — one-tap create from raw clipboard content
 * (web URL, email address, phone number, or bare domain). The server
 * classifies + normalizes the destination itself, so we just pass the
 * raw string through. Mirrors the web header bolt button.
 *
 * An optional custom back-half (`alias`) is validated server-side with the
 * full alias stack (format, banned names, uniqueness, per-plan length);
 * when omitted or blank the server auto-generates one.
 */
export async function quickShorten(
  destination: string,
  opts?: { alias?: string; domain_id?: number | null },
): Promise<QuickShortenResult> {
  const alias = opts?.alias?.trim();
  const res = await apiFetch<{ data: QuickShortenResult }>(`/links/quick-shorten`, {
    method: "POST",
    body: JSON.stringify({
      destination,
      ...(alias ? { alias } : {}),
      ...(opts?.domain_id != null ? { domain_id: opts.domain_id } : {}),
    }),
  });
  return res.data;
}

export type AliasCheck = {
  status:
    | "empty"
    | "invalid"
    | "too_short"
    | "too_long"
    | "banned"
    | "taken"
    | "available";
  available: boolean | null;
  message: string;
};

export async function checkAlias(
  alias: string,
  ignoreId?: number,
  domainId?: number | null,
): Promise<AliasCheck> {
  const qs = new URLSearchParams({ alias });
  // On the edit screen, exclude the link's own current alias from the
  // "taken" check so an unchanged alias reads as available.
  if (ignoreId != null) qs.set("ignore_id", String(ignoreId));
  // Uniqueness is per-domain, so scope the verdict to the chosen host.
  if (domainId != null) qs.set("domain_id", String(domainId));
  return apiFetch<AliasCheck>(`/links/check-alias?${qs}`);
}

export async function getLink(id: number): Promise<Link> {
  const res = await apiFetch<{ data: { link: Link } }>(`/links/${id}`);
  return res.data.link;
}

export async function createLink(payload: {
  type: string;
  alias?: string;
  title?: string | null;
  long_url?: string | null;
  visibility?: Link["visibility"];
  is_active?: boolean;
  expires_at?: string | null;
  settings?: Record<string, unknown>;
  seo_title?: string | null;
  seo_description?: string | null;
  domain_id?: number | null;
}): Promise<Link> {
  const res = await apiFetch<{ data: { link: Link } }>(`/links`, {
    method: "POST",
    body: JSON.stringify(payload),
  });
  return res.data.link;
}

export async function updateLink(
  id: number,
  patch: Partial<{
    title: string | null;
    long_url: string | null;
    alias: string;
    visibility: Link["visibility"];
    is_active: boolean;
    expires_at: string | null;
    settings: Record<string, unknown>;
    seo_title: string | null;
    seo_description: string | null;
    domain_id: number | null;
  }>,
): Promise<Link> {
  const res = await apiFetch<{ data: { link: Link } }>(`/links/${id}`, {
    method: "PATCH",
    body: JSON.stringify(patch),
  });
  return res.data.link;
}

export async function deleteLink(id: number): Promise<void> {
  await apiFetch(`/links/${id}`, { method: "DELETE" });
}

export async function resetLink(id: number): Promise<Link> {
  const res = await apiFetch<{ data: { link: Link } }>(`/links/${id}/reset`, {
    method: "POST",
  });
  return res.data.link;
}

export async function duplicateLink(src: Link): Promise<Link> {
  return createLink({
    type: src.type,
    title: src.title ? `${src.title} (copy)` : null,
    long_url: src.long_url ?? undefined,
    visibility: src.visibility,
    is_active: src.is_active,
    settings: (src.settings as Record<string, unknown>) ?? {},
    seo_title: src.seo_title,
    seo_description: src.seo_description,
  });
}
