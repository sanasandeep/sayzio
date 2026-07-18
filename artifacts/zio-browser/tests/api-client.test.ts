import { describe, it, expect, vi, beforeEach } from 'vitest';
import { ApiClient, ApiClientError } from '../src/shared/api-client';

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
});
