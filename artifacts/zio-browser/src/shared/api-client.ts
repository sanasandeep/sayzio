/**
 * Typed API client for the Sayzio Laravel /api/v1 REST API.
 * Follows the unified {data}/{error} envelope used throughout the platform.
 * Mirrors the mobile app's apiFetch pattern — unwraps the envelope explicitly.
 */

export interface ApiEnvelope<T> {
  data: T;
}

export interface ApiError {
  error: {
    message: string;
    code: string;
    details?: unknown;
  };
}

export interface ApiUser {
  id: number;
  name: string;
  email: string | null;
  handle: string | null;
  avatar: string | null;
  bio: string | null;
  role: string;
  status: string;
  plan_id: number | null;
  onboarded_at: string | null;
  email_verified_at: string | null;
  coin_balance?: number;
}

/** Extended profile with fields useful for form autofill (returned by GET /profile). */
export interface ApiUserProfile extends ApiUser {
  phone: string | null;
  given_name?: string | null;
  family_name?: string | null;
  organization?: string | null;
  job_title?: string | null;
  website?: string | null;
}

export interface AuthConfig {
  email: boolean;
  otp_email: boolean;
  otp_whatsapp: boolean;
  social_providers: string[];
}

export class ApiClientError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly status: number,
    public readonly details?: unknown,
  ) {
    super(message);
    this.name = 'ApiClientError';
  }
}

export interface ApiClientOptions {
  baseUrl: string;
  token?: string;
  userAgent?: string;
}

export class ApiClient {
  private baseUrl: string;
  private token: string | null;
  private userAgent: string;

  constructor(options: ApiClientOptions) {
    this.baseUrl = options.baseUrl.replace(/\/$/, '');
    this.token = options.token ?? null;
    this.userAgent = options.userAgent ?? 'SayZioBrowser/1.0';
  }

  setToken(token: string | null): void {
    this.token = token;
  }

  getToken(): string | null {
    return this.token;
  }

  private async request<T>(
    method: string,
    path: string,
    body?: unknown,
    signal?: AbortSignal,
  ): Promise<T> {
    const url = `${this.baseUrl}/api/v1${path}`;
    const headers: Record<string, string> = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'User-Agent': this.userAgent,
      'X-App-Platform': 'desktop',
    };

    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }

    const response = await fetch(url, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal,
    });

    if (response.status === 204) {
      return undefined as unknown as T;
    }

    const json = await response.json() as ApiEnvelope<T> | ApiError;

    if (!response.ok) {
      const err = json as ApiError;
      throw new ApiClientError(
        err.error?.code ?? 'unknown_error',
        err.error?.message ?? `HTTP ${response.status}`,
        response.status,
        err.error?.details,
      );
    }

    return (json as ApiEnvelope<T>).data;
  }

  get<T>(path: string, signal?: AbortSignal): Promise<T> {
    return this.request<T>('GET', path, undefined, signal);
  }

  post<T>(path: string, body?: unknown, signal?: AbortSignal): Promise<T> {
    return this.request<T>('POST', path, body, signal);
  }

  patch<T>(path: string, body?: unknown, signal?: AbortSignal): Promise<T> {
    return this.request<T>('PATCH', path, body, signal);
  }

  put<T>(path: string, body?: unknown, signal?: AbortSignal): Promise<T> {
    return this.request<T>('PUT', path, body, signal);
  }

  delete<T>(path: string, signal?: AbortSignal): Promise<T> {
    return this.request<T>('DELETE', path, undefined, signal);
  }

  // ── Auth ──────────────────────────────────────────────────────────────────

  async getAuthConfig(): Promise<AuthConfig> {
    return this.get<AuthConfig>('/auth/config');
  }

  async login(email: string, password: string, device?: string): Promise<{ user: ApiUser; token: string }> {
    return this.post('/auth/login', { email, password, device: device ?? 'Zio Browser' });
  }

  async logout(): Promise<void> {
    return this.post('/auth/logout');
  }

  async sendOtp(identifier: string, channel: 'email' | 'whatsapp'): Promise<{ sent: boolean }> {
    return this.post('/auth/otp/send', { identifier, channel });
  }

  async verifyOtp(identifier: string, code: string, channel: 'email' | 'whatsapp'): Promise<{ user: ApiUser; token: string }> {
    return this.post('/auth/otp/verify', { identifier, code, channel });
  }

  async me(): Promise<{ user: ApiUser }> {
    return this.get('/auth/me');
  }

  // ── Browser sync ──────────────────────────────────────────────────────────

  async registerDevice(deviceInfo: BrowserDeviceInfo): Promise<{ device_id: string }> {
    return this.post('/browser/devices', deviceInfo);
  }

  async syncBookmarks(deviceId: string, items: SyncItem[]): Promise<SyncResponse> {
    return this.post(`/browser/devices/${deviceId}/bookmarks`, { items });
  }

  async syncCollections(deviceId: string, items: SyncItem[]): Promise<SyncResponse> {
    return this.post(`/browser/devices/${deviceId}/collections`, { items });
  }

  async syncHistory(deviceId: string, items: SyncItem[]): Promise<SyncResponse> {
    return this.post(`/browser/devices/${deviceId}/history`, { items });
  }

  async pullSync(deviceId: string, since?: string): Promise<SyncPullResponse> {
    const qs = since ? `?since=${encodeURIComponent(since)}` : '';
    return this.get(`/browser/devices/${deviceId}/pull${qs}`);
  }

  // ── Contacts ─────────────────────────────────────────────────────────────

  async getProfile(): Promise<{ user: ApiUserProfile }> {
    return this.get('/profile');
  }

  async createContact(data: ContactPayload): Promise<{ contact: ApiContact }> {
    return this.post('/contacts', { ...data, validate: 'strict' });
  }

  async updateContact(id: number, data: ContactPayload): Promise<{ contact: ApiContact }> {
    return this.patch(`/contacts/${id}`, data);
  }

  async getContact(id: number): Promise<{ contact: ApiContact }> {
    return this.get(`/contacts/${id}`);
  }

  async searchContacts(q: string): Promise<{ items: ApiContact[] }> {
    return this.get(`/contacts?q=${encodeURIComponent(q)}&per_page=20`);
  }

  // ── Dialer ────────────────────────────────────────────────────────────────

  async dialerSearch(q: string): Promise<DialerSearchResult> {
    return this.get(`/dialer/search?q=${encodeURIComponent(q)}`);
  }

  async dialerLookup(numberE164: string): Promise<DialerLookupResult> {
    return this.post('/dialer/lookup', { number_e164: numberE164 });
  }

  async dialerHistory(): Promise<{ recents: unknown[]; frequent: unknown[] }> {
    return this.get('/dialer/history');
  }

  // ── AI / assistant (Ask Zio) ──────────────────────────────────────────────
  // The /assistant/* endpoints mirror the web Ask Zio widget and return RAW
  // JSON payloads (ok/visitor_token/messages/...), NOT the {data} envelope
  // used by the rest of /api/v1 — so they go through rawRequest().

  private async rawRequest<T>(
    method: string,
    path: string,
    body?: unknown,
    signal?: AbortSignal,
  ): Promise<T> {
    const url = `${this.baseUrl}/api/v1${path}`;
    const headers: Record<string, string> = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      'User-Agent': this.userAgent,
      'X-App-Platform': 'desktop',
    };
    if (this.token) headers['Authorization'] = `Bearer ${this.token}`;

    const response = await fetch(url, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal,
    });

    const json = await response.json().catch(() => null) as (T & { error?: string; auth_required?: boolean }) | null;

    if (!response.ok) {
      const message = (json && typeof json.error === 'string' && json.error)
        || `HTTP ${response.status}`;
      throw new ApiClientError(
        json?.auth_required ? 'auth_required' : 'assistant_error',
        message,
        response.status,
      );
    }

    return json as T;
  }

  async assistantBootstrap(): Promise<AssistantBootstrap> {
    return this.rawRequest<AssistantBootstrap>('GET', '/assistant/bootstrap');
  }

  async assistantSession(visitorToken: string | null, page?: AssistantPage): Promise<AssistantSession> {
    return this.rawRequest<AssistantSession>('POST', '/assistant/session', {
      visitor_token: visitorToken ?? undefined,
      surface: 'app',
      page,
    });
  }

  async assistantMessage(visitorToken: string, message: string, page?: AssistantPage): Promise<AssistantTurn> {
    return this.rawRequest<AssistantTurn>('POST', '/assistant/message', {
      visitor_token: visitorToken,
      surface: 'app',
      message,
      page,
    });
  }

  /**
   * Streamed assistant reply over SSE (POST + fetch body reader).
   * Emits `token` deltas as they arrive, then `done` with the persisted
   * assistant message. Rejects (or calls onError) on failure.
   */
  async assistantStream(
    visitorToken: string,
    message: string,
    page: AssistantPage | undefined,
    handlers: AssistantStreamHandlers,
    signal?: AbortSignal,
  ): Promise<void> {
    const url = `${this.baseUrl}/api/v1/assistant/stream`;
    const headers: Record<string, string> = {
      'Accept': 'text/event-stream',
      'Content-Type': 'application/json',
      'User-Agent': this.userAgent,
      'X-App-Platform': 'desktop',
    };
    if (this.token) headers['Authorization'] = `Bearer ${this.token}`;

    const response = await fetch(url, {
      method: 'POST',
      headers,
      body: JSON.stringify({ visitor_token: visitorToken, surface: 'app', message, page }),
      signal,
    });

    const contentType = response.headers.get('content-type') ?? '';
    if (!response.ok || !contentType.includes('text/event-stream')) {
      // Auth gate / validation errors come back as JSON.
      const json = await response.json().catch(() => null) as { error?: string; auth_required?: boolean } | null;
      throw new ApiClientError(
        json?.auth_required ? 'auth_required' : 'assistant_error',
        json?.error ?? `HTTP ${response.status}`,
        response.status,
      );
    }

    if (!response.body) {
      throw new ApiClientError('assistant_error', 'Streaming not supported', response.status);
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    const dispatch = (rawFrame: string): void => {
      let event = 'message';
      const dataLines: string[] = [];
      for (const line of rawFrame.split('\n')) {
        if (line.startsWith('event:')) event = line.slice(6).trim();
        else if (line.startsWith('data:')) dataLines.push(line.slice(5).trimStart());
      }
      if (dataLines.length === 0) return;
      let payload: Record<string, unknown>;
      try {
        payload = JSON.parse(dataLines.join('\n')) as Record<string, unknown>;
      } catch {
        return;
      }
      if (event === 'token') {
        handlers.onToken?.(String((payload as { delta?: unknown }).delta ?? ''));
      } else if (event === 'done') {
        handlers.onDone?.(payload as unknown as AssistantStreamDone);
      } else if (event === 'error') {
        handlers.onError?.(payload as { error?: string; rotated?: boolean; visitor_token?: string });
      } else if (event === 'user') {
        handlers.onUser?.(payload as { user_message?: AssistantMessagePayload });
      }
    };

    for (;;) {
      const { done, value } = await reader.read();
      if (done) break;
      buffer += decoder.decode(value, { stream: true });
      let idx: number;
      while ((idx = buffer.indexOf('\n\n')) !== -1) {
        const frame = buffer.slice(0, idx);
        buffer = buffer.slice(idx + 2);
        if (frame.trim()) dispatch(frame);
      }
    }
    if (buffer.trim()) dispatch(buffer);
  }

  async getWallet(): Promise<{ balance: number; currency: string }> {
    return this.get('/wallet');
  }

  // ── Links ─────────────────────────────────────────────────────────────────

  async listLinks(params?: { type?: string; q?: string; per_page?: number }): Promise<ApiLinksPage> {
    const qs = new URLSearchParams();
    if (params?.type) qs.set('type', params.type);
    if (params?.q) qs.set('q', params.q);
    if (params?.per_page) qs.set('per_page', String(params.per_page));
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.get<ApiLinksPage>(`/links${query}`);
  }

  async createLink(data: CreateLinkPayload): Promise<{ link: ApiLink }> {
    return this.post('/links', data);
  }

  async checkAlias(alias: string, ignoreId?: number): Promise<AliasCheckResult> {
    const qs = new URLSearchParams({ alias });
    if (ignoreId !== undefined) qs.set('ignore_id', String(ignoreId));
    return this.get<AliasCheckResult>(`/links/check-alias?${qs.toString()}`);
  }

  async getLinkAnalytics(id: number, from?: string, to?: string): Promise<LinkAnalytics> {
    const qs = new URLSearchParams();
    if (from) qs.set('from', from);
    if (to) qs.set('to', to);
    const query = qs.toString() ? `?${qs.toString()}` : '';
    return this.get<LinkAnalytics>(`/links/${id}/analytics${query}`);
  }

  // ── Domains ───────────────────────────────────────────────────────────────

  async listAvailableDomains(): Promise<{ items: ApiDomain[] }> {
    return this.get<{ items: ApiDomain[] }>('/domains/available');
  }

  // ── QR codes ──────────────────────────────────────────────────────────────

  async createQrCode(data: CreateQrPayload): Promise<{ qr_code: ApiQrCode }> {
    return this.post('/qr-codes', data);
  }

  // ── Biolinks / blocks ─────────────────────────────────────────────────────

  async listBiolinks(): Promise<ApiLinksPage> {
    return this.listLinks({ type: 'biolink', per_page: 100 });
  }

  async addBiolinkBlock(linkId: number, data: AddBiolinkBlockPayload): Promise<{ block: ApiBiolinkBlock }> {
    return this.post(`/biolinks/${linkId}/blocks`, data);
  }
}

// ── Shared types ─────────────────────────────────────────────────────────────

export interface BrowserDeviceInfo {
  label: string;
  platform: 'mac' | 'windows' | 'linux';
  app_version: string;
}

export interface SyncItem {
  local_id: string;
  updated_at: string;
  deleted?: boolean;
  data?: Record<string, unknown>;
}

export interface SyncResponse {
  accepted: string[];
  conflicts: string[];
  server_time: string;
}

export interface SyncPullResponse {
  bookmarks: SyncItem[];
  collections: SyncItem[];
  history: SyncItem[];
  server_time: string;
}

export interface ContactPayload {
  display_name?: string;
  given_name?: string;
  family_name?: string;
  organization?: string;
  job_title?: string;
  emails?: Array<{ value: string; label?: string }>;
  phones?: Array<{ value: string; label?: string }>;
  website?: string;
  notes?: string;
  tags?: string[];
  source_url?: string;
}

export interface ApiContact {
  id: number;
  display_name: string;
  organization: string | null;
  emails: Array<{ value: string; label: string }>;
  phones: Array<{ value: string; value_e164: string | null; label: string }>;
}

export interface DialerSearchResult {
  total: number;
  groups: Array<{
    key: string;
    label: string;
    items: unknown[];
  }>;
}

// ── Assistant (Ask Zio) types ────────────────────────────────────────────────

export interface AssistantPage {
  route?: string;
  path?: string;
  title?: string;
  url?: string;
}

export interface AssistantBootstrap {
  enabled: boolean;
  surface?: string;
  brand_name?: string;
  greeting?: string;
  starter_prompts?: string[];
  input_placeholder?: string;
  auth_required?: boolean;
}

export interface AssistantMessagePayload {
  id: number;
  role: 'user' | 'assistant';
  content: string;
  blocks?: unknown[] | null;
  citations?: unknown[];
  created_at?: string;
}

export interface AssistantSession {
  ok: boolean;
  is_disabled?: boolean;
  error?: string;
  conversation_id?: number;
  visitor_token: string;
  rotated?: boolean;
  greeting?: string;
  starter_prompts?: string[];
  messages: AssistantMessagePayload[];
}

export interface AssistantTurn {
  ok?: boolean;
  error?: string;
  rotated?: boolean;
  visitor_token?: string;
  user_message?: AssistantMessagePayload;
  assistant_message?: AssistantMessagePayload;
  handed_off?: boolean;
}

export interface AssistantStreamDone {
  assistant_message?: AssistantMessagePayload;
  handed_off?: boolean;
  conversation_id?: number;
}

export interface AssistantStreamHandlers {
  onToken?: (delta: string) => void;
  onUser?: (payload: { user_message?: AssistantMessagePayload }) => void;
  onDone?: (payload: AssistantStreamDone) => void;
  onError?: (payload: { error?: string; rotated?: boolean; visitor_token?: string }) => void;
}

export interface DialerLookupResult {
  number_e164: string;
  is_spam: boolean;
  is_blocked: boolean;
  contact: { id: number; display_name: string } | null;
  biolink: { handle: string; url: string } | null;
}

// ── Links ─────────────────────────────────────────────────────────────────────

export interface ApiLink {
  id: number;
  type: string;
  alias: string;
  title: string | null;
  long_url: string | null;
  short_url: string;
  total_clicks: number;
  unique_clicks: number;
  is_active: boolean;
  visibility: string;
  domain_id: number | null;
  created_at: string | null;
}

export interface ApiLinksPage {
  items: ApiLink[];
  meta: {
    current_page: number;
    per_page: number;
    total: number;
    last_page: number;
  };
}

export interface CreateLinkPayload {
  type: 'short' | 'biolink' | 'qr' | string;
  alias?: string;
  title?: string;
  long_url?: string;
  domain_id?: number | null;
  visibility?: 'public' | 'registered' | 'followers' | 'subscribers';
  is_active?: boolean;
  settings?: Record<string, unknown>;
}

export interface AliasCheckResult {
  status: 'available' | 'taken' | 'invalid' | 'reserved';
  available: boolean;
  message: string;
  suggestions?: string[];
}

export interface LinkAnalytics {
  link_id: number;
  alias: string;
  total_clicks: number;
  unique_clicks: number;
  window: { from: string; to: string };
  by_day: Array<{ date: string; clicks: number }>;
  by_country: Array<{ country: string; clicks: number }>;
  by_device: Array<{ device_type: string; clicks: number }>;
  by_referrer: Array<{ referrer_host: string; clicks: number }>;
}

// ── Domains ───────────────────────────────────────────────────────────────────

export interface ApiDomain {
  id: number;
  host: string;
  is_verified: boolean;
  is_active: boolean;
  is_global: boolean;
  is_primary: boolean;
}

// ── QR codes ─────────────────────────────────────────────────────────────────

export interface CreateQrPayload {
  name: string;
  type: string;
  link_id?: number | null;
  payload?: Record<string, unknown>;
  design?: Record<string, unknown>;
}

export interface ApiQrCode {
  id: number;
  name: string;
  type: string;
  link_id: number | null;
  payload: Record<string, unknown>;
  design: Record<string, unknown>;
  encoded: string;
  preview_url: string | null;
  created_at: string | null;
}

// ── Biolink blocks ────────────────────────────────────────────────────────────

export interface AddBiolinkBlockPayload {
  type: string;
  settings?: Record<string, unknown>;
  sort_order?: number;
  is_active?: boolean;
}

export interface ApiBiolinkBlock {
  id: number;
  link_id: number;
  type: string;
  sort_order: number;
  is_active: boolean;
  settings: Record<string, unknown>;
  created_at: string | null;
}
