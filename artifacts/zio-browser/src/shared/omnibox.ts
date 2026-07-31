/**
 * Omnibox URL/search parsing logic.
 * Pure functions — no Electron or browser dependencies — fully testable.
 */

export type OmniboxResultKind = 'url' | 'search' | 'localhost' | 'ip' | 'file';

export interface OmniboxResult {
  kind: OmniboxResultKind;
  /** Final URL to navigate to */
  navigateUrl: string;
  /** Display text for the address bar */
  displayText: string;
}

export interface SearchEngineConfig {
  name: string;
  searchTemplate: string;
}

export const DEFAULT_SEARCH_ENGINE: SearchEngineConfig = {
  name: 'Google',
  searchTemplate: 'https://www.google.com/search?q={query}',
};

export const SEARCH_ENGINES: Record<string, SearchEngineConfig> = {
  google: DEFAULT_SEARCH_ENGINE,
  bing: {
    name: 'Bing',
    searchTemplate: 'https://www.bing.com/search?q={query}',
  },
  duckduckgo: {
    name: 'DuckDuckGo',
    searchTemplate: 'https://duckduckgo.com/?q={query}',
  },
  brave: {
    name: 'Brave Search',
    searchTemplate: 'https://search.brave.com/search?q={query}',
  },
};

const SCHEME_PATTERN = /^[a-z][a-z0-9+\-.]*:\/\//i;
const IP_PATTERN = /^(?:\d{1,3}\.){3}\d{1,3}(:\d+)?(\/.*)?$/;
const LOCALHOST_PATTERN = /^localhost(:\d+)?(\/.*)?$/i;
const DOMAIN_PATTERN = /^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z]{2,})+/i;
const FILE_PATTERN = /^(\/|~\/|[A-Za-z]:[/\\])/;

/**
 * Parse raw omnibox input into a navigation URL or search query.
 *
 * Rules (in order):
 * 1. Has a scheme (http://, https://, ftp://, file://, etc.) → URL
 * 2. Matches localhost/127.0.0.1 pattern → URL with http://
 * 3. Matches bare IP address → URL with http://
 * 4. Matches filesystem path → file:// URL
 * 5. Looks like a domain (has at least one dot after a valid host segment) → URL with https://
 * 6. Everything else → search query
 */
export function parseOmniboxInput(
  raw: string,
  searchEngine: SearchEngineConfig = DEFAULT_SEARCH_ENGINE,
): OmniboxResult {
  const trimmed = raw.trim();

  if (!trimmed) {
    return {
      kind: 'search',
      navigateUrl: buildSearchUrl('', searchEngine),
      displayText: '',
    };
  }

  // Explicit scheme
  if (SCHEME_PATTERN.test(trimmed)) {
    return {
      kind: trimmed.startsWith('file:') ? 'file' : 'url',
      navigateUrl: trimmed,
      displayText: trimmed,
    };
  }

  // Localhost
  if (LOCALHOST_PATTERN.test(trimmed)) {
    const url = `http://${trimmed}`;
    return { kind: 'localhost', navigateUrl: url, displayText: url };
  }

  // IP address
  if (IP_PATTERN.test(trimmed)) {
    const url = `http://${trimmed}`;
    return { kind: 'ip', navigateUrl: url, displayText: url };
  }

  // Filesystem path
  if (FILE_PATTERN.test(trimmed)) {
    const url = `file://${trimmed.startsWith('~') ? trimmed.replace('~', process.env['HOME'] ?? '~') : trimmed}`;
    return { kind: 'file', navigateUrl: url, displayText: url };
  }

  // Domain-like (contains a dot in the right position and a valid TLD)
  if (looksLikeDomain(trimmed)) {
    const url = `https://${trimmed}`;
    return { kind: 'url', navigateUrl: url, displayText: url };
  }

  // Search query
  return {
    kind: 'search',
    navigateUrl: buildSearchUrl(trimmed, searchEngine),
    displayText: trimmed,
  };
}

function looksLikeDomain(s: string): boolean {
  // Must match the domain pattern and NOT contain spaces
  if (/\s/.test(s)) return false;
  // Must not look like a search (common short single-word queries don't have dots)
  if (!s.includes('.')) return false;
  return DOMAIN_PATTERN.test(s);
}

function buildSearchUrl(query: string, engine: SearchEngineConfig): string {
  // Use + encoding (application/x-www-form-urlencoded) — matches real browser search bar behaviour
  const encoded = encodeURIComponent(query).replace(/%20/g, '+');
  return engine.searchTemplate.replace('{query}', encoded);
}

/**
 * Strip the scheme and trailing slash for display in the address bar.
 * Returns the cleaned display URL.
 */
export function formatDisplayUrl(url: string): string {
  try {
    const u = new URL(url);
    let display = u.host + u.pathname;
    // Strip trailing slash on root path only when there is no query/hash — keeps
    // "example.com/?q=foo" intact so the display remains meaningful.
    if (display.endsWith('/') && u.pathname === '/' && !u.search && !u.hash) {
      display = u.host;
    }
    if (u.search) display += u.search;
    if (u.hash) display += u.hash;
    return display;
  } catch {
    return url;
  }
}

/**
 * Detect the likely search engine used from a URL, and extract the raw query.
 * Used to populate the address bar when the user navigates to a search results page.
 */
export function extractSearchQuery(url: string): string | null {
  try {
    const u = new URL(url);
    // Google, DuckDuckGo, Brave use `q`; Bing uses `q`
    const q = u.searchParams.get('q');
    if (q) return q;
  } catch {
    // ignore
  }
  return null;
}

/** MIME types Chromium can render natively as text in a tab. */
const VIEWABLE_TEXT_MIME_TYPES = new Set([
  'text/plain',
  'text/markdown',
  'text/csv',
  'application/json',
  'text/json',
  'application/ld+json',
]);

/** File extensions treated as text viewable in a tab. */
const VIEWABLE_TEXT_EXTENSION = /\.(txt|md|markdown|json|csv|log)$/i;

/**
 * True when a download is text-based and viewable in a browser tab with
 * Chromium's native text rendering: plain text, Markdown, JSON, CSV, or log
 * files — matched by MIME type or file extension. Binary formats (including
 * .doc/.docx) are excluded and keep the OS-app open behavior.
 */
export function isViewableTextDownload(filename: string, mimeType?: string | null): boolean {
  if (mimeType) {
    const base = mimeType.split(';')[0]?.trim().toLowerCase();
    if (base && VIEWABLE_TEXT_MIME_TYPES.has(base)) return true;
  }
  return VIEWABLE_TEXT_EXTENSION.test(filename.trim());
}

/** @deprecated Use {@link isViewableTextDownload}. Kept for backwards compatibility. */
export const isPlainTextDownload = isViewableTextDownload;

/**
 * Normalize a URL for history deduplication — strips fragments and normalizes
 * trailing slashes.
 */
export function normalizeUrlForHistory(url: string): string {
  try {
    const u = new URL(url);
    u.hash = '';
    return u.toString().replace(/\/$/, '');
  } catch {
    return url;
  }
}
