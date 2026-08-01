/**
 * Per-site settings resolution with a tiny TTL cache.
 *
 * The tracker blocker consults the per-site content-blocker override on every
 * network request, so raw SQLite reads per request would be wasteful. This
 * module memoizes site_settings rows per origin for a few seconds and is
 * invalidated whenever the settings are edited.
 *
 * Privacy: callers are responsible for never consulting this for private
 * windows / non-persistent sessions.
 */
import { getSiteSettings, type SiteSettingsRow } from './db';

const CACHE_TTL_MS = 3000;
const cache = new Map<string, { row: SiteSettingsRow | null; ts: number }>();

function cachedRow(origin: string): SiteSettingsRow | null {
  const hit = cache.get(origin);
  const now = Date.now();
  if (hit && now - hit.ts < CACHE_TTL_MS) return hit.row;
  let row: SiteSettingsRow | null = null;
  try {
    row = getSiteSettings(origin);
  } catch {
    // DB unavailable — treat as "no per-site settings".
  }
  cache.set(origin, { row, ts: now });
  return row;
}

/** Resolve stored per-site settings for a page URL, or null when none. */
export function resolveSiteSettingsForUrl(
  url: string,
): { zoom: number | null; autoplay: string | null; popups: string | null } | null {
  try {
    const origin = new URL(url).origin;
    if (!origin.startsWith('http')) return null;
    const row = cachedRow(origin);
    return row ? { zoom: row.zoom, autoplay: row.autoplay, popups: row.popups } : null;
  } catch {
    return null;
  }
}

/**
 * Per-site content-blocker override for an origin: true/false when the user
 * chose an explicit override, null to use the global tracker-blocking flag.
 */
export function contentBlockerOverrideForOrigin(origin: string): boolean | null {
  const row = cachedRow(origin);
  if (!row || row.content_blockers === null || row.content_blockers === undefined) return null;
  return row.content_blockers === 1;
}

/**
 * Per-site ad-block override for an origin: true/false when the user chose
 * an explicit override, null to use the global ad-blocking flag.
 */
export function adBlockOverrideForOrigin(origin: string): boolean | null {
  const row = cachedRow(origin);
  if (!row || row.ad_blockers === null || row.ad_blockers === undefined) return null;
  return row.ad_blockers === 1;
}

/** Drop cached rows (one origin, or everything) after settings are edited. */
export function invalidateSiteSettingsCache(origin?: string): void {
  if (origin) cache.delete(origin);
  else cache.clear();
}
