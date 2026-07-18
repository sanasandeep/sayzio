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

  async createContact(data: ContactPayload): Promise<{ contact: ApiContact }> {
    return this.post('/contacts', { ...data, validate: 'strict' });
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

  // ── AI / assistant ────────────────────────────────────────────────────────

  async assistantMessage(sessionId: string, message: string, context?: string): Promise<{ reply: string; coins_used?: number }> {
    return this.post('/assistant/message', {
      session_id: sessionId,
      message,
      context,
    });
  }

  async assistantSession(context?: string): Promise<{ session_id: string }> {
    return this.post('/assistant/session', { context });
  }

  async getWallet(): Promise<{ balance: number; currency: string }> {
    return this.get('/wallet');
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

export interface DialerLookupResult {
  number_e164: string;
  is_spam: boolean;
  is_blocked: boolean;
  contact: { id: number; display_name: string } | null;
  biolink: { handle: string; url: string } | null;
}
