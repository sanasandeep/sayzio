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
  user: {
    id: number;
    name: string;
    email: string;
    handle?: string | null;
    capabilities?: { link_smart_rules?: boolean; max_smart_rules?: number };
  };
  token: string;
}

/**
 * Single smart-routing rule the popup builder produces. Mirrors the
 * shape sanitizeSmartRules() expects on the server. `id` is optional —
 * the server mints stable ones for new rules.
 */
export type SmartRule =
  | { id?: string; type: "device";   match: string[]; url: string; label?: string }
  | { id?: string; type: "country";  match: string[]; url: string; label?: string }
  | { id?: string; type: "language"; match: string[]; url: string; label?: string }
  | { id?: string; type: "time";     from: string; to: string; tz: string; url: string; label?: string };

export interface DomainOption {
  id: number;
  domain: string;
  is_global?: boolean;
  is_verified?: boolean;
  is_primary?: boolean;
}

export interface AvailableDomainsResult {
  items: DomainOption[];
  primary_domain_id: number | null;
  default_host: string;
  can_manage?: boolean;
}

export interface AliasCheckResult {
  status: "empty" | "invalid" | "too_short" | "too_long" | "banned" | "taken" | "available";
  available: boolean | null;
  message: string;
  suggestions?: string[];
}

export interface LinkSummary {
  id: number;
  alias: string;
  title?: string | null;
  long_url?: string | null;
  short_url?: string;
  is_smart?: boolean;
  smart_rules_count?: number;
  auto_pixel?: boolean;
  pixel_fires?: { count: number; providers: string[] };
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

  // Quick shorten — server-side normalization of the raw destination
  // (web URL, bare domain, email → mailto:, phone → tel:; anything else
  // becomes a shareable text page) so the extension's classification can
  // never drift from web/mobile. Optional custom back-half (alias)
  // validated with the full create-flow rules.
  quickShorten: (destination: string, alias?: string, workspaceId?: number | null, domainId?: number | null) =>
    request<{ id: number; short_url: string; long_url: string | null; kind: "url" | "email" | "phone" | "text" }>(
      "/links/quick-shorten",
      {
        method: "POST",
        body: {
          destination,
          alias: alias || undefined,
          workspace_id: workspaceId ?? undefined,
          domain_id: domainId ?? undefined,
        },
      },
    ),

  // Live custom back-half availability check (same endpoint the mobile
  // app uses; verdicts match exactly what quickShorten enforces on save).
  // Alias uniqueness is per-domain, so pass the target domain when the
  // user picked a branded one — the verdict depends on it.
  checkAlias: (alias: string, domainId?: number | null) =>
    request<AliasCheckResult>(
      `/links/check-alias?alias=${encodeURIComponent(alias)}${domainId ? `&domain_id=${domainId}` : ""}`,
    ),

  // Domains the user can attach a link to (their own verified domains +
  // admin-provisioned global domains) — feeds the popup domain picker.
  availableDomains: () =>
    request<AvailableDomainsResult>("/domains/available"),

  updateLink: (linkId: number, patch: Record<string, unknown>) =>
    request<{ link: { id: number; alias: string; auto_pixel?: boolean } }>(`/links/${linkId}`, {
      method: "PATCH",
      body: patch,
    }),

  recentLinks: (perPage = 10) =>
    request<{ items: LinkSummary[] }>(`/links?per_page=${perPage}`),

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
    // Optional optimistic-concurrency token: pass the server ts the client
    // last saw. Server returns a 409 ApiError (with the current payload in
    // `payload.error.details`) if its stored ts has moved on since.
    expectedUpdatedAtMs?: number | null,
  ) => {
    const body: Record<string, unknown> = { templates, updated_at_ms: updatedAtMs };
    if (expectedUpdatedAtMs !== undefined && expectedUpdatedAtMs !== null) {
      body.expected_updated_at_ms = expectedUpdatedAtMs;
    }
    return request<ThankTemplatesPayload>(
      `/me/thank-templates${workspaceId ? `?workspace_id=${workspaceId}` : ""}`,
      { method: "PUT", body },
    );
  },

  // --- Smart links ---------------------------------------------------
  createSmartLink: (longUrl: string, rules: SmartRule[], title?: string, workspaceId?: number | null) =>
    request<{ link: LinkSummary }>("/links/smart", {
      method: "POST",
      body: {
        long_url: longUrl,
        title: title || undefined,
        workspace_id: workspaceId ?? undefined,
        rules,
      },
    }),

  getRules: (linkId: number) =>
    request<{ link_id: number; rules: SmartRule[]; max: number }>(`/links/${linkId}/rules`),

  putRules: (linkId: number, rules: SmartRule[]) =>
    request<{ link_id: number; rules: SmartRule[]; max: number }>(`/links/${linkId}/rules`, {
      method: "PUT",
      body: { rules },
    }),

  // ── Notifications ─────────────────────────────────────────────────
  getNotifications: (opts: { perPage?: number; page?: number } = {}) => {
    const q = new URLSearchParams();
    if (opts.perPage) q.set("per_page", String(opts.perPage));
    if (opts.page) q.set("page", String(opts.page));
    return request<{ items: NotificationItem[]; meta: { total: number; unread: number } }>(
      `/notifications${q.toString() ? `?${q}` : ""}`,
    );
  },

  markNotificationRead: (id: number) =>
    request<null>(`/notifications/${id}/read`, { method: "POST" }),

  markAllNotificationsRead: () =>
    request<null>("/notifications/read-all", { method: "POST" }),

  // ── Dialer ────────────────────────────────────────────────────────
  dialerLookup: (numberE164: string) =>
    request<{
      contact: { id: number; name: string; organization?: string | null } | null;
      biolink: { alias: string; short_url?: string } | null;
      activity: unknown[];
      is_spam: boolean;
      is_blocked: boolean;
      is_favorite: boolean;
    }>("/dialer/lookup", { method: "POST", body: { number_e164: numberE164 } }),

  // ── Biolinks (for the "Add to existing bio-link" picker) ──────────
  getBiolinks: (perPage = 30) =>
    request<{ items: Array<{ id: number; alias: string; title?: string | null; short_url?: string }> }>(
      `/links?type=biolink&per_page=${perPage}`,
    ),

  // ── QR Studio ─────────────────────────────────────────────────────
  getQrCatalog: () =>
    request<{
      presets: Array<{ id: string; name: string; design: Record<string, unknown> }>;
      dots: unknown[];
      outer_eyes: unknown[];
      inner_eyes: unknown[];
      frames: unknown[];
      fonts: unknown[];
      types: unknown[];
      default_design: Record<string, unknown>;
    }>("/qr-codes/catalog"),

  createQrCode: (
    name: string,
    type: string,
    payload?: Record<string, unknown>,
    linkId?: number,
    design?: Record<string, unknown>,
  ) =>
    request<{ qr_code: { id: number; name: string; encoded?: string; svg?: string; short_url?: string } }>("/qr-codes", {
      method: "POST",
      body: {
        name,
        type,
        payload: payload ?? undefined,
        link_id: linkId ?? undefined,
        design: design ?? undefined,
      },
    }),

  // ── Calendars ─────────────────────────────────────────────────────
  getCalendars: () =>
    request<{ items: Array<{ id: number; name: string; color?: string | null; is_default?: boolean }> }>("/calendars"),

  createCalendarEvent: (calendarId: number, event: {
    title: string;
    description?: string;
    location?: string;
    start_date?: string;
    end_date?: string;
    url?: string;
  }) =>
    request<{ event: { id: number; title: string; start_date?: string } }>(
      `/calendars/${calendarId}/events`,
      { method: "POST", body: event },
    ),

  // ── AI Biolink Builder ────────────────────────────────────────────
  aiBiolinkIntake: (linkId: number) =>
    request<{ intake: Record<string, unknown>; credit_cost: number; plan_allowed: boolean }>(
      `/links/${linkId}/ai-builder`,
    ),

  aiBiolinkGenerate: (linkId: number, body: {
    prompt?: string;
    context_url?: string;
    images?: string[];
  }) =>
    request<{ link: { id: number; alias: string } }>(
      `/links/${linkId}/ai-builder/generate`,
      { method: "POST", body },
    ),

  // ── Review capture (extension endpoint) ──────────────────────────
  captureReviewSource: (
    provider: "google" | "trustpilot",
    externalRef: string,
    name?: string,
  ) =>
    request<{ connection_id: number; provider: string; status: string; preview: boolean }>(
      "/me/reviews/capture-source",
      { method: "POST", body: { provider, external_ref: externalRef, name: name ?? undefined } },
    ),

  // ── Pending thank-yous queue (synced per workspace) ───────────────
  getPendingThanks: (workspaceId?: number | null) =>
    request<PendingThanksPayload>(
      `/me/pending-thanks${workspaceId ? `?workspace_id=${workspaceId}` : ""}`,
    ),

  savePendingThanks: (
    items: Array<{
      id: string;
      templateId: string;
      channel: "email" | "x" | "linkedin";
      subject: string;
      body: string;
      recipient: string | null;
      pageUrl: string;
      matchedUrl: string;
      anchor: string;
      createdAt: number;
    }>,
    workspaceId?: number | null,
    updatedAtMs?: number,
  ) =>
    request<PendingThanksPayload>(
      `/me/pending-thanks${workspaceId ? `?workspace_id=${workspaceId}` : ""}`,
      { method: "PUT", body: { items, updated_at_ms: updatedAtMs } },
    ),

  // ── Mailbox AI reply draft ─────────────────────────────────────────
  listKnowledgeBases: () =>
    request<{
      mine: Array<{ id: number; name: string }>;
      platform: { id: number; name: string } | null;
    }>("/ai/minds"),

  draftMailboxReply: (payload: {
    thread: {
      subject: string;
      participants: string[];
      messages: Array<{ role: "inbound" | "outbound"; sender: string; body: string }>;
    };
    knowledge_base_ids?: number[];
    include_links?: boolean;
    instruction?: string;
  }) =>
    request<MailboxDraftResult>("/mailbox/draft-reply", {
      method: "POST",
      body: payload,
    }),

  // ── Quick analytics (7-day click count for a link) ────────────────
  getLinkClickCount: (linkId: number) =>
    request<{
      totals: { clicks: number; unique_clicks?: number };
      daily?: Array<{ date: string; clicks: number }>;
    }>(`/links/${linkId}/analytics?days=7&breakdown=daily`),

  // ── Forms ─────────────────────────────────────────────────────────
  getForms: (perPage = 30) =>
    request<{
      items: Array<{
        id: number;
        title: string;
        alias?: string | null;
        short_url?: string | null;
        submissions_count?: number;
      }>;
    }>(`/forms?per_page=${perPage}`),

  // ── Universal search (backed by the dialer search endpoint) ───────
  dialerSearch: (q: string) =>
    request<{
      groups: Array<{
        label: string;
        items: Array<{
          id?: number | string;
          display_name?: string;
          name?: string;
          title?: string;
          alias?: string;
          handle?: string;
          type?: string;
          organization?: string;
          short_url?: string;
          action_url?: string;
          copy_url?: string;
        }>;
      }>;
      total: number;
    }>(`/dialer/search?q=${encodeURIComponent(q)}&per_page=30`),

  // ── Image save from URL (extension-specific endpoint) ─────────────
  saveImageFromUrl: (imageUrl: string, filename?: string) =>
    request<{ file: { id: number; name: string; url: string } }>(
      "/me/files/fetch-url",
      {
        method: "POST",
        body: { url: imageUrl, filename: filename ?? undefined },
      },
    ),

  // ── Contacts list (for the contact-note picker) ───────────────────
  listContacts: (perPage = 20) =>
    request<{
      items: Array<{ id: number; display_name: string; emails?: any[]; phones?: any[] }>;
    }>(`/contacts?per_page=${perPage}`),

  // ── Contact note append ────────────────────────────────────────────
  appendContactNote: (contactId: number, note: string) =>
    request<{ contact: { id: number; display_name: string; notes?: string | null } }>(
      `/contacts/${contactId}`,
      { method: "PATCH", body: { notes_append: note } },
    ),

  // ── Link health check (checks alias status via the links endpoint) ─
  checkLinksHealth: (aliases: string[]) =>
    request<{
      items: Array<{ alias: string; is_active: boolean; is_expired: boolean; status: "ok" | "inactive" | "expired" }>;
    }>(
      "/me/links/health",
      { method: "POST", body: { aliases } },
    ),
};

export interface NotificationItem {
  id: number;
  type: string;
  data: Record<string, unknown>;
  read_at: string | null;
  created_at: string;
  message?: string | null;
}

export interface PendingThanksPayload {
  workspace_id: number;
  items: Array<{
    id: string;
    templateId: string;
    channel: "email" | "x" | "linkedin";
    subject: string;
    body: string;
    recipient: string | null;
    pageUrl: string;
    matchedUrl: string;
    anchor: string;
    createdAt: number;
  }>;
  updated_at_ms: number | null;
  max: number;
}

// Pull the current server payload out of a 409 conflict thrown by
// saveThankTemplates. The Laravel helper wraps it as
// `{ error: { code, message, details: <ThankTemplatesPayload> } }`.
export function extractThankTemplatesConflict(
  err: unknown,
): ThankTemplatesPayload | null {
  if (!(err instanceof ApiError) || err.status !== 409) return null;
  const payload = err.payload as { error?: { details?: ThankTemplatesPayload } } | null;
  return payload?.error?.details ?? null;
}

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

export interface MailboxDraftResult {
  draft: string;
  citations: Array<{ id: number; name: string }>;
  credits_spent: number;
  model: string;
}

export { request };
