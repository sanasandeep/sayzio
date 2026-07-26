/**
 * Pure utility functions for detecting and working with Sayzio-hosted links.
 * No Electron or browser dependencies — fully testable.
 */

export interface OwnLinkDetection {
  /** The alias/back-half of the detected link */
  alias: string;
  /** The platform domain that hosted the link (e.g. "1in.me") */
  host: string;
  /**
   * Best-guess type based on URL shape:
   *  - "biolink"  — path starts with @ (/@handle public profile)
   *  - "short"    — plain short-link path (no @)
   *  - "unknown"  — could not determine
   */
  guessedType: 'biolink' | 'short' | 'unknown';
}

/**
 * Platform domains known to host Sayzio short links and biolinks.
 * Custom user domains are handled separately via the `extraHosts` parameter.
 */
export const PLATFORM_DOMAINS = [
  'sayzio.app',
  '1in.me',
  'sayzio.com',
  'www.sayzio.com',
] as const;

/**
 * Detect whether `url` is a Sayzio-hosted link (short link or biolink).
 *
 * Returns an `OwnLinkDetection` when the URL's host is a known platform
 * domain (or one of `extraHosts`), `null` otherwise.
 *
 * This is intentionally a *host-only* check — it cannot know whether the
 * alias belongs to the signed-in user without a server round-trip. Callers
 * that need ownership confirmation should follow up with the API.
 */
export function detectSayzioLink(
  url: string,
  extraHosts: string[] = [],
): OwnLinkDetection | null {
  let parsed: URL;
  try {
    parsed = new URL(url);
  } catch {
    return null;
  }

  if (parsed.protocol !== 'https:' && parsed.protocol !== 'http:') {
    return null;
  }

  const host = parsed.hostname.toLowerCase();
  const knownHosts = new Set<string>([
    ...(PLATFORM_DOMAINS as readonly string[]),
    ...extraHosts.map(h => h.toLowerCase()),
  ]);

  if (!knownHosts.has(host)) {
    return null;
  }

  // Strip the leading slash and any trailing slash
  const path = parsed.pathname.replace(/^\/+|\/+$/g, '');
  if (!path) {
    // Bare domain root — not a specific link
    return null;
  }

  // /@handle → biolink public profile
  if (path.startsWith('@')) {
    return {
      alias: path.slice(1), // strip the @
      host,
      guessedType: 'biolink',
    };
  }

  // Top-level reserved paths that are not links
  const RESERVED = new Set([
    'login', 'register', 'user', 'admin', 'api', 'pricing',
    'features', 'about', 'contact', 'blog', 'demos', 'creators',
    'discovery', 'assistant', 'upgrade', 'sitemap.xml', 'robots.txt',
  ]);

  // Only the first path segment matters for alias matching
  const firstSegment = path.split('/')[0] ?? '';
  if (!firstSegment || RESERVED.has(firstSegment)) {
    return null;
  }

  // Valid alias characters: alphanumeric and hyphens/underscores
  if (!/^[a-zA-Z0-9_-]{2,}$/.test(firstSegment)) {
    return null;
  }

  return {
    alias: firstSegment,
    host,
    guessedType: 'short',
  };
}

/**
 * Build the canonical short URL for a given alias + host.
 * Uses https:// always.
 */
export function buildShortUrl(alias: string, host: string): string {
  return `https://${host}/${alias}`;
}

/**
 * Suggest an alias from a page title by:
 * 1. Lower-casing and stripping non-alphanumeric chars
 * 2. Replacing spaces/hyphens with a single hyphen
 * 3. Truncating to 30 characters
 *
 * Falls back to an empty string (server will auto-generate).
 */
export function suggestAlias(title: string): string {
  return title
    .toLowerCase()
    .replace(/[^a-z0-9\s-]/g, '')
    .trim()
    .replace(/[\s-]+/g, '-')
    .slice(0, 30)
    .replace(/^-+|-+$/g, '');
}

/**
 * Quick inline QR image URL using the free `api.qrserver.com` service.
 * Used for immediate preview before (optionally) persisting via the Sayzio API.
 */
export function quickQrImageUrl(data: string, size = 200): string {
  return `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(data)}&margin=10`;
}
