import { apiFetch } from "@/lib/api";

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
): Promise<AliasCheck> {
  const qs = new URLSearchParams({ alias });
  // On the edit screen, exclude the link's own current alias from the
  // "taken" check so an unchanged alias reads as available.
  if (ignoreId != null) qs.set("ignore_id", String(ignoreId));
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
