/**
 * Sayzio web-session bridge.
 *
 * The browser authenticates against the Sayzio API with a Sanctum bearer
 * token, but web pages loaded in tabs use ordinary cookie sessions — so
 * sayzio.app rendered "logged out" even when the browser itself was signed
 * in. This module exchanges the stored token for a one-time signed login
 * URL (POST /api/v1/auth/browser-session) and fetches it INSIDE the target
 * profile partition's cookie jar, establishing a matching web session
 * without ever navigating a visible tab.
 *
 * Private windows are unaffected: they use in-memory sessions passed
 * directly to the TabManager, never a persist: partition string, and no
 * caller seeds them.
 */
import { session } from 'electron';
import { retrieveToken } from './auth-store';
import { getAllPreferences } from './db';

const DEFAULT_API_BASE = 'https://sayzio.app';

/** Partitions already seeded since app start (or since the last login). */
const seededPartitions = new Set<string>();

function apiBase(): string {
  try {
    return getAllPreferences()['sayzio_api_base_url'] ?? DEFAULT_API_BASE;
  } catch {
    return DEFAULT_API_BASE;
  }
}

/** Forget seed state — call on logout or when a new token is stored. */
export function resetSayzioSessionSeeds(): void {
  seededPartitions.clear();
}

/**
 * Establish a Sayzio web session in the given persist: partition, once per
 * app run. Silent best-effort: any failure just leaves the tab logged out
 * (the site's own login page still works).
 */
export async function seedSayzioWebSession(partition: string): Promise<void> {
  if (!partition || !partition.startsWith('persist:')) return;
  await seedInto(partition, () => session.fromPartition(partition));
}

/**
 * Seed the DEFAULT session — used by the dashboard-mode WebContentsView,
 * which runs in session.defaultSession rather than a profile partition.
 */
export async function seedSayzioDefaultSession(): Promise<void> {
  await seedInto('__default__', () => session.defaultSession);
}

async function seedInto(key: string, getSession: () => Electron.Session): Promise<void> {
  if (seededPartitions.has(key)) return;
  const token = retrieveToken();
  if (!token) return;

  // Mark early so concurrent callers (switch + warm) don't double-fire;
  // rolled back on failure so a later attempt can retry.
  seededPartitions.add(key);
  try {
    const resp = await fetch(`${apiBase()}/api/v1/auth/browser-session`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
        'X-App-Platform': 'desktop',
      },
    });
    if (!resp.ok) throw new Error(`browser-session exchange failed: ${resp.status}`);
    const json = (await resp.json()) as { data?: { login_url?: string } };
    const loginUrl = json?.data?.login_url;
    if (!loginUrl) throw new Error('browser-session exchange returned no login_url');

    // Fetch the one-time login URL with the target session's own cookie
    // store so the Laravel session cookie lands where the pages will read it.
    const ses = getSession();
    const login = await ses.fetch(loginUrl, { credentials: 'include', redirect: 'follow' });
    if (!login.ok) throw new Error(`session login fetch failed: ${login.status}`);
  } catch {
    seededPartitions.delete(key);
  }
}
