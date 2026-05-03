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

  createShortLink: (longUrl: string, title?: string, workspaceId?: number | null) =>
    request<{ link: { id: number; alias: string; long_url: string; short_url?: string } }>("/links", {
      method: "POST",
      body: {
        type: "short",
        long_url: longUrl,
        title: title || undefined,
        workspace_id: workspaceId ?? undefined,
      },
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
};

export { request };
