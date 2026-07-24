import { describe, it, expect, vi, beforeEach } from 'vitest';
import { ApiClient, ApiClientError } from '../src/shared/api-client';
import type { ApiLink, CreateLinkPayload, LinkAnalytics, ApiDomain, CreateQrPayload, ApiLinksPage } from '../src/shared/api-client';

function mockFetch(response: { ok: boolean; status: number; body: unknown }) {
  return vi.fn().mockResolvedValue({
    ok: response.ok,
    status: response.status,
    json: async () => response.body,
  });
}

describe('ApiClient', () => {
  let client: ApiClient;
  const baseUrl = 'https://1in.me';

  beforeEach(() => {
    client = new ApiClient({ baseUrl, token: 'test-token' });
  });

  it('sets and retrieves token', () => {
    expect(client.getToken()).toBe('test-token');
    client.setToken('new-token');
    expect(client.getToken()).toBe('new-token');
  });

  it('clears token with null', () => {
    client.setToken(null);
    expect(client.getToken()).toBeNull();
  });

  it('unwraps the {data} envelope on success', async () => {
    global.fetch = mockFetch({
      ok: true,
      status: 200,
      body: { data: { id: 1, name: 'Test User' } },
    });

    const result = await client.get<{ id: number; name: string }>('/test');
    expect(result).toEqual({ id: 1, name: 'Test User' });
  });

  it('throws ApiClientError on error response', async () => {
    global.fetch = mockFetch({
      ok: false,
      status: 401,
      body: { error: { message: 'Unauthenticated', code: 'unauthenticated' } },
    });

    await expect(client.get('/protected')).rejects.toThrow(ApiClientError);
    try {
      await client.get('/protected');
    } catch (err) {
      const apiErr = err as ApiClientError;
      expect(apiErr.code).toBe('unauthenticated');
      expect(apiErr.status).toBe(401);
    }
  });

  it('includes Authorization header when token is set', async () => {
    let capturedHeaders: Record<string, string> = {};
    global.fetch = vi.fn().mockImplementation((url: string, opts: RequestInit) => {
      capturedHeaders = opts.headers as Record<string, string>;
      return Promise.resolve({ ok: true, status: 200, json: async () => ({ data: {} }) });
    });

    await client.get('/test');
    expect(capturedHeaders['Authorization']).toBe('Bearer test-token');
  });

  it('does not include Authorization header when no token', async () => {
    let capturedHeaders: Record<string, string> = {};
    global.fetch = vi.fn().mockImplementation((url: string, opts: RequestInit) => {
      capturedHeaders = opts.headers as Record<string, string>;
      return Promise.resolve({ ok: true, status: 200, json: async () => ({ data: {} }) });
    });

    client.setToken(null);
    await client.get('/test');
    expect(capturedHeaders['Authorization']).toBeUndefined();
  });

  it('sends JSON body for POST requests', async () => {
    let capturedBody = '';
    global.fetch = vi.fn().mockImplementation((url: string, opts: RequestInit) => {
      capturedBody = opts.body as string;
      return Promise.resolve({ ok: true, status: 200, json: async () => ({ data: { ok: true } }) });
    });

    await client.post('/test', { key: 'value' });
    expect(JSON.parse(capturedBody)).toEqual({ key: 'value' });
  });

  it('returns undefined for 204 No Content', async () => {
    global.fetch = mockFetch({ ok: true, status: 204, body: undefined });
    const result = await client.delete('/test');
    expect(result).toBeUndefined();
  });

  it('uses baseUrl correctly', async () => {
    let capturedUrl = '';
    global.fetch = vi.fn().mockImplementation((url: string) => {
      capturedUrl = url;
      return Promise.resolve({ ok: true, status: 200, json: async () => ({ data: {} }) });
    });

    await client.get('/auth/me');
    expect(capturedUrl).toBe('https://1in.me/api/v1/auth/me');
  });

  it('strips trailing slash from baseUrl', async () => {
    const clientWithSlash = new ApiClient({ baseUrl: 'https://1in.me/' });
    let capturedUrl = '';
    global.fetch = vi.fn().mockImplementation((url: string) => {
      capturedUrl = url;
      return Promise.resolve({ ok: true, status: 200, json: async () => ({ data: {} }) });
    });

    await clientWithSlash.get('/test');
    expect(capturedUrl).toBe('https://1in.me/api/v1/test');
  });

  it('includes X-App-Platform: desktop header', async () => {
    let capturedHeaders: Record<string, string> = {};
    global.fetch = vi.fn().mockImplementation((url: string, opts: RequestInit) => {
      capturedHeaders = opts.headers as Record<string, string>;
      return Promise.resolve({ ok: true, status: 200, json: async () => ({ data: {} }) });
    });

    await client.get('/test');
    expect(capturedHeaders['X-App-Platform']).toBe('desktop');
  });

  it('throws ApiClientError with details when available', async () => {
    global.fetch = mockFetch({
      ok: false,
      status: 422,
      body: {
        error: {
          message: 'Validation failed',
          code: 'validation_error',
          details: { email: ['The email field is required.'] },
        },
      },
    });

    try {
      await client.post('/test');
      expect.fail('Should have thrown');
    } catch (err) {
      const apiErr = err as ApiClientError;
      expect(apiErr.details).toEqual({ email: ['The email field is required.'] });
    }
  });

  // ── Link tools: new methods ──────────────────────────────────────────────

  it('listLinks returns items and meta', async () => {
    const mockPage: ApiLinksPage = {
      items: [
        { id: 1, alias: 'abc', title: 'Test', type: 'short', short_url: 'https://1in.me/abc', destination_url: 'https://example.com', created_at: '2025-01-01' },
      ],
      total: 1,
      page: 1,
      per_page: 10,
      last_page: 1,
    };
    global.fetch = mockFetch({ ok: true, status: 200, body: { data: mockPage } });

    const result = await client.listLinks({ q: 'abc' });
    expect(result.items).toHaveLength(1);
    expect(result.items[0]!.alias).toBe('abc');
    expect(result.total).toBe(1);
  });

  it('createLink posts payload and returns the new link', async () => {
    const newLink: ApiLink = {
      id: 42,
      alias: 'mylink',
      title: 'My Page',
      type: 'short',
      short_url: 'https://1in.me/mylink',
      destination_url: 'https://example.com',
      created_at: '2025-06-01',
    };
    let capturedBody = '';
    global.fetch = vi.fn().mockImplementation((_url: string, opts: RequestInit) => {
      capturedBody = opts.body as string;
      return Promise.resolve({ ok: true, status: 201, json: async () => ({ data: newLink }) });
    });

    const payload: CreateLinkPayload = {
      type: 'short',
      destination_url: 'https://example.com',
      alias: 'mylink',
      title: 'My Page',
    };
    const result = await client.createLink(payload);
    expect(result.id).toBe(42);
    expect(result.alias).toBe('mylink');
    expect(JSON.parse(capturedBody)).toMatchObject({ destination_url: 'https://example.com', alias: 'mylink' });
  });

  it('checkAlias returns available: true when alias is free', async () => {
    global.fetch = mockFetch({ ok: true, status: 200, body: { data: { available: true, alias: 'free-alias' } } });

    const result = await client.checkAlias('free-alias');
    expect(result.available).toBe(true);
    expect(result.alias).toBe('free-alias');
  });

  it('checkAlias returns available: false when alias is taken', async () => {
    global.fetch = mockFetch({
      ok: true,
      status: 200,
      body: {
        data: {
          available: false,
          alias: 'taken',
          suggestions: ['taken-1', 'taken-2'],
        },
      },
    });

    const result = await client.checkAlias('taken');
    expect(result.available).toBe(false);
    expect(result.suggestions).toContain('taken-1');
  });

  it('getLinkAnalytics returns stats with correct shape', async () => {
    const mockAnalytics: LinkAnalytics = {
      link_id: 5,
      alias: 'demo',
      total_clicks: 120,
      unique_clicks: 80,
      by_country: [{ country: 'US', clicks: 60 }, { country: 'IN', clicks: 30 }],
      by_device: [{ device_type: 'mobile', clicks: 90 }, { device_type: 'desktop', clicks: 30 }],
      by_day: [{ date: '2025-06-01', clicks: 10 }, { date: '2025-06-02', clicks: 5 }],
      window: { from: '2025-05-01', to: '2025-05-31' },
    };
    global.fetch = mockFetch({ ok: true, status: 200, body: { data: mockAnalytics } });

    const result = await client.getLinkAnalytics(5);
    expect(result.total_clicks).toBe(120);
    expect(result.by_country[0]!.country).toBe('US');
    expect(result.by_device[0]!.device_type).toBe('mobile');
    expect(result.by_day).toHaveLength(2);
  });

  it('listAvailableDomains returns domain array', async () => {
    const domains: ApiDomain[] = [
      { id: 1, name: '1in.me', is_global: true, is_verified: true },
      { id: 2, name: 'custom.com', is_global: false, is_verified: true },
    ];
    global.fetch = mockFetch({ ok: true, status: 200, body: { data: domains } });

    const result = await client.listAvailableDomains();
    expect(result).toHaveLength(2);
    expect(result[0]!.name).toBe('1in.me');
    expect(result[0]!.is_global).toBe(true);
  });

  it('createQrCode posts payload and returns QR object', async () => {
    const qrPayload: CreateQrPayload = {
      content_type: 'url',
      content: 'https://example.com',
      label: 'Test QR',
    };
    global.fetch = vi.fn().mockImplementation((_url: string, opts: RequestInit) => {
      const body = JSON.parse(opts.body as string) as CreateQrPayload;
      return Promise.resolve({
        ok: true,
        status: 201,
        json: async () => ({
          data: { id: 99, content_type: body.content_type, content: body.content, label: body.label, created_at: '2025-06-01' },
        }),
      });
    });

    const result = await client.createQrCode(qrPayload);
    expect(result.id).toBe(99);
    expect(result.content_type).toBe('url');
  });

  it('listBiolinks returns only biolink-type links', async () => {
    const mockPage: ApiLinksPage = {
      items: [
        { id: 10, alias: '@handle', title: 'My Biolink', type: 'biolink', short_url: 'https://1in.me/@handle', created_at: '2025-01-01' },
      ],
      total: 1,
      page: 1,
      per_page: 10,
      last_page: 1,
    };
    let capturedUrl = '';
    global.fetch = vi.fn().mockImplementation((url: string) => {
      capturedUrl = url;
      return Promise.resolve({ ok: true, status: 200, json: async () => ({ data: mockPage }) });
    });

    const result = await client.listBiolinks();
    expect(result.items[0]!.type).toBe('biolink');
    // Should include type=biolink filter in the query
    expect(capturedUrl).toContain('type=biolink');
  });

  it('addBiolinkBlock sends correct payload to biolinks/:id/blocks', async () => {
    let capturedUrl = '';
    let capturedBody = '';
    global.fetch = vi.fn().mockImplementation((url: string, opts: RequestInit) => {
      capturedUrl = url;
      capturedBody = opts.body as string;
      return Promise.resolve({
        ok: true,
        status: 201,
        json: async () => ({ data: { id: 7, type: 'link', label: 'Example', url: 'https://example.com' } }),
      });
    });

    const result = await client.addBiolinkBlock(10, {
      type: 'link',
      label: 'Example',
      url: 'https://example.com',
    });
    expect(capturedUrl).toContain('/api/v1/biolinks/10/blocks');
    expect(JSON.parse(capturedBody)).toMatchObject({ type: 'link', label: 'Example' });
    expect(result.type).toBe('link');
  });
});

describe('2FA login challenge', () => {
  const baseUrl = 'https://1in.me';
  let client: ApiClient;

  beforeEach(() => {
    client = new ApiClient({ baseUrl });
  });

  it('login surfaces totp_required with challenge_token in details', async () => {
    global.fetch = mockFetch({
      ok: false,
      status: 403,
      body: {
        error: {
          message: 'Two-factor authentication required',
          code: 'totp_required',
          details: { challenge_token: 'chal-123' },
        },
      },
    });

    try {
      await client.login('user@example.com', 'secret');
      expect.unreachable('login should have thrown');
    } catch (err) {
      const apiErr = err as ApiClientError;
      expect(apiErr).toBeInstanceOf(ApiClientError);
      expect(apiErr.code).toBe('totp_required');
      expect((apiErr.details as { challenge_token: string }).challenge_token).toBe('chal-123');
    }
  });

  it('verifyTotpChallenge posts token + code to the challenge endpoint and returns user/token', async () => {
    let capturedUrl = '';
    let capturedBody: Record<string, unknown> = {};
    global.fetch = vi.fn().mockImplementation((url: string, opts: RequestInit) => {
      capturedUrl = url;
      capturedBody = JSON.parse(opts.body as string) as Record<string, unknown>;
      return Promise.resolve({
        ok: true,
        status: 200,
        json: async () => ({ data: { user: { id: 7, name: 'TOTP User' }, token: 'tok-2fa' } }),
      });
    });

    const result = await client.verifyTotpChallenge('chal-123', '654321');
    expect(capturedUrl).toBe('https://1in.me/api/v1/auth/2fa/challenge/verify');
    expect(capturedBody).toEqual({ challenge_token: 'chal-123', code: '654321', device: 'Zio Browser' });
    expect(result.token).toBe('tok-2fa');
    expect(result.user.id).toBe(7);
  });

  it('verifyBackupCode posts to the backup-codes endpoint', async () => {
    let capturedUrl = '';
    let capturedBody: Record<string, unknown> = {};
    global.fetch = vi.fn().mockImplementation((url: string, opts: RequestInit) => {
      capturedUrl = url;
      capturedBody = JSON.parse(opts.body as string) as Record<string, unknown>;
      return Promise.resolve({
        ok: true,
        status: 200,
        json: async () => ({ data: { user: { id: 7, name: 'TOTP User' }, token: 'tok-recovery' } }),
      });
    });

    const result = await client.verifyBackupCode('chal-123', 'RECOVERY-CODE-1');
    expect(capturedUrl).toBe('https://1in.me/api/v1/auth/2fa/backup-codes/verify');
    expect(capturedBody).toEqual({ challenge_token: 'chal-123', code: 'RECOVERY-CODE-1', device: 'Zio Browser' });
    expect(result.token).toBe('tok-recovery');
  });

  it('wrong 2FA code throws a retriable ApiClientError (not 410)', async () => {
    global.fetch = mockFetch({
      ok: false,
      status: 422,
      body: { error: { message: 'Invalid authentication code', code: 'invalid_code' } },
    });

    try {
      await client.verifyTotpChallenge('chal-123', '000000');
      expect.unreachable('should have thrown');
    } catch (err) {
      const apiErr = err as ApiClientError;
      expect(apiErr.code).toBe('invalid_code');
      expect(apiErr.status).toBe(422);
    }
  });

  it('expired challenge returns 410 so the UI restarts the flow', async () => {
    global.fetch = mockFetch({
      ok: false,
      status: 410,
      body: { error: { message: 'Challenge expired', code: 'challenge_expired' } },
    });

    await expect(client.verifyTotpChallenge('chal-old', '123456')).rejects.toMatchObject({ status: 410 });
  });

  it('non-2FA login is unaffected and returns user/token directly', async () => {
    global.fetch = mockFetch({
      ok: true,
      status: 200,
      body: { data: { user: { id: 1, name: 'Plain User' }, token: 'tok-plain' } },
    });

    const result = await client.login('plain@example.com', 'secret');
    expect(result.token).toBe('tok-plain');
    expect(result.user.id).toBe(1);
  });
});
