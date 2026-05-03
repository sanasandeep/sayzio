import { clearAuth, getSettings } from "./storage";

export class ApiError extends Error {
  status: number;
  code?: string;
  payload?: unknown;
  constructor(status: number, message: string, code?: string, payload?: unknown) {
    super(message);
    this.status = status;
    this.code = code;
    this.payload = payload;
  }
}

interface RequestOptions {
  method?: string;
  body?: unknown;
  auth?: boolean;
}

async function request<T = any>(path: string, opts: RequestOptions = {}): Promise<T> {
  const { apiBaseUrl, token } = await getSettings();
  const headers: Record<string, string> = {
    Accept: "application/json",
  };
  if (opts.body !== undefined) headers["Content-Type"] = "application/json";
  if (opts.auth !== false && token) headers["Authorization"] = `Bearer ${token}`;

  const resp = await fetch(`${apiBaseUrl}${path}`, {
    method: opts.method || "GET",
    headers,
    body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
  });

  let data: any = null;
  const text = await resp.text();
  if (text) {
    try { data = JSON.parse(text); } catch { data = { message: text }; }
  }

  if (!resp.ok) {
    if (resp.status === 401 && opts.auth !== false) {
      await clearAuth();
    }
    const msg = data?.message || data?.error?.message || `Request failed (${resp.status})`;
    const code = data?.error?.code || data?.code;
    throw new ApiError(resp.status, msg, code, data);
  }
  return (data?.data ?? data) as T;
}

export interface WorkspacePixels {
  workspace_id: number;
  meta_id: string | null;
  tiktok_id: string | null;
  google_id: string | null;
  google_label: string | null;
  configured: string[];
  has_any: boolean;
}

export interface LoginResult {
  user: { id: number; name: string; email: string; handle?: string | null };
  token: string;
}

export const api = {
  login: (email: string, password: string) =>
    request<LoginResult>("/auth/login", {
      method: "POST",
      body: { email, password, device: "browser-extension" },
      auth: false,
    }),

  me: () => request<{ user: LoginResult["user"] }>("/auth/me"),

  logout: () => request("/auth/logout", { method: "POST" }),

  workspaces: () => request<{ items?: Array<{ id: number; name: string }> } | Array<{ id: number; name: string }>>("/workspaces"),

  createShortLink: (
    longUrl: string,
    title?: string,
    workspaceId?: number | null,
    autoPixel?: boolean,
  ) =>
    request<{ link: { id: number; alias: string; long_url: string; short_url?: string; auto_pixel?: boolean } }>("/links", {
      method: "POST",
      body: {
        type: "short",
        long_url: longUrl,
        title: title || undefined,
        workspace_id: workspaceId ?? undefined,
        auto_pixel: autoPixel,
      },
    }),

  updateLink: (linkId: number, patch: Record<string, unknown>) =>
    request<{ link: { id: number; alias: string; auto_pixel?: boolean } }>(`/links/${linkId}`, {
      method: "PATCH",
      body: patch,
    }),

  recentLinks: (perPage = 10) =>
    request<{ items: Array<{ id: number; alias: string; title: string | null; long_url: string | null; short_url?: string; auto_pixel?: boolean; pixel_fires?: { count: number; providers: string[] } }> }>(
      `/links?per_page=${perPage}`,
    ),

  getWorkspacePixels: (workspaceId?: number | null) =>
    request<{ pixels: WorkspacePixels }>(`/workspace/pixels${workspaceId ? `?workspace_id=${workspaceId}` : ""}`),

  saveWorkspacePixels: (pixels: Partial<WorkspacePixels>, workspaceId?: number | null) =>
    request<{ pixels: WorkspacePixels }>(`/workspace/pixels${workspaceId ? `?workspace_id=${workspaceId}` : ""}`, {
      method: "PUT",
      body: pixels,
    }),

  createBiolink: (title: string, seoDescription?: string, seoImage?: string, workspaceId?: number | null) =>
    request<{ link: { id: number; alias: string } }>("/links", {
      method: "POST",
      body: {
        type: "biolink",
        title,
        seo_title: title,
        seo_description: seoDescription,
        workspace_id: workspaceId ?? undefined,
        settings: seoImage ? { seo_image: seoImage } : {},
      },
    }),

  addBlock: (linkId: number, type: string, settings: Record<string, unknown>, sortOrder?: number) =>
    request<{ block: { id: number } }>(`/links/${linkId}/blocks`, {
      method: "POST",
      body: { type, settings, sort_order: sortOrder, is_active: true },
    }),

  // ── A/B test endpoints ──────────────────────────────────────────────
  createAbTest: (
    title: string | undefined,
    variants: Array<{ label?: string; url: string; weight: number }>,
    workspaceId?: number | null,
  ) =>
    request<{
      link: { id: number; alias: string; long_url: string; short_url?: string };
      variants: AbVariantsPayload;
    }>("/links/ab", {
      method: "POST",
      body: {
        title: title || undefined,
        workspace_id: workspaceId ?? undefined,
        variants,
      },
    }),

  listAbTests: () =>
    request<{ items: Array<{ link: { id: number; alias: string; short_url?: string; title?: string }; variants: AbVariantsPayload }> }>(
      "/links/ab",
    ),

  getAbTest: (linkId: number) =>
    request<{ link: { id: number; alias: string; short_url?: string }; variants: AbVariantsPayload }>(
      `/links/${linkId}/ab`,
    ),

  declareAbWinner: (linkId: number, variantId: number) =>
    request<{ link: { id: number; alias: string }; variants: AbVariantsPayload }>(
      `/links/${linkId}/ab/declare-winner`,
      { method: "POST", body: { variant_id: variantId } },
    ),

  validateContact: (payload: Record<string, unknown>) =>
    request<{
      ok: boolean;
      errors: Record<string, string[]>;
      normalized: Record<string, any>;
      duplicate_of: number | null;
    }>("/contacts/validate", { method: "POST", body: payload }),

  createContact: (payload: Record<string, unknown>, strict = false) =>
    request<{ contact: { id: number; display_name: string }; duplicate_of: number | null }>(
      "/contacts",
      { method: "POST", body: { ...payload, ...(strict ? { validate: "strict" } : {}) } },
    ),

  mergeContact: (id: number, payload: Record<string, unknown>) =>
    request<{ contact: { id: number; display_name: string } }>(`/contacts/${id}/merge`, {
      method: "POST",
      body: payload,
    }),

  getContact: (id: number) =>
    request<{ contact: { id: number; display_name: string; emails: any[]; phones: any[]; organization?: string | null } }>(`/contacts/${id}`),

  // ── Backlink radar ────────────────────────────────────────────────
  properties: () =>
    request<{
      properties: {
        short_link_hosts: string[];
        biolink_username_path: string;
        biolink_hosts: string[];
        custom_domain_hosts: string[];
        slug_hash_prefix_len: number;
        slug_hash_algo: string;
        slug_hashes: string[];
        cached_at: string;
        cache_ttl_seconds: number;
      };
    }>("/me/properties"),

  saveBacklink: (body: {
    page_url: string;
    page_title?: string;
    anchor_text?: string;
    matched_url: string;
    matched_property_type: "short_link" | "biolink_username" | "custom_domain";
    matched_property_value?: string;
  }) =>
    request<{ backlink: BacklinkRow }>("/backlinks", { method: "POST", body }),

  listBacklinks: (params: { days?: number; property_type?: string; per_page?: number } = {}) => {
    const q = new URLSearchParams();
    if (params.days) q.set("days", String(params.days));
    if (params.property_type) q.set("property_type", params.property_type);
    if (params.per_page) q.set("per_page", String(params.per_page));
    const qs = q.toString();
    return request<{ items: BacklinkRow[]; meta: { total: number } }>(
      `/backlinks${qs ? `?${qs}` : ""}`,
    );
  },

  deleteBacklink: (id: number) =>
    request<null>(`/backlinks/${id}`, { method: "DELETE" }),

  // ── Thank-you templates (synced per workspace) ─────────────────────
  getThankTemplates: (workspaceId?: number | null) =>
    request<ThankTemplatesPayload>(
      `/me/thank-templates${workspaceId ? `?workspace_id=${workspaceId}` : ""}`,
    ),

  saveThankTemplates: (
    templates: Array<{ id: string; name: string; channel: "email" | "x" | "linkedin"; subject: string; body: string }>,
    workspaceId?: number | null,
    updatedAtMs?: number,
  ) =>
    request<ThankTemplatesPayload>(
      `/me/thank-templates${workspaceId ? `?workspace_id=${workspaceId}` : ""}`,
      { method: "PUT", body: { templates, updated_at_ms: updatedAtMs } },
    ),
};

export interface ThankTemplatesPayload {
  workspace_id: number;
  templates: Array<{ id: string; name: string; channel: "email" | "x" | "linkedin"; subject: string; body: string }>;
  updated_at_ms: number | null;
  max: number;
}

export interface AbVariantsPayload {
  enabled: boolean;
  winner_variant_id: number | null;
  leader_variant_id: number | null;
  variants: Array<{
    id: number;
    label: string | null;
    url: string;
    weight: number;
    visitors: number;
    clicks: number;
    is_winner: boolean;
  }>;
}

export interface BacklinkRow {
  id: number;
  page_url: string;
  page_host: string;
  page_title: string | null;
  anchor_text: string | null;
  matched_url: string;
  matched_property_type: "short_link" | "biolink_username" | "custom_domain";
  matched_property_value: string | null;
  first_seen_at: string | null;
}

export { request };
