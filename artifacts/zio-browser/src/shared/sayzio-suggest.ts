/**
 * Omnibox "jump to Sayzio" suggestion logic — pure, testable, no React.
 *
 * Privacy & correctness rules enforced here:
 * - Remote lookups only for handle-like queries (no spaces/dots/URLs).
 * - Callers must gate on eligibility (never in private windows, only signed in).
 * - Lookups fail silently (both false) on any network/API error.
 * - 'taken' alias status means an existing link; invalid/reserved/banned do not.
 */

export interface SayzioExistsResult {
  link: boolean;
  profile: boolean;
}

/** Minimal API surface needed for the existence lookups (subset of ApiClient). */
export interface SayzioLookupClient {
  checkAlias(alias: string): Promise<{ status: string }>;
  creatorProfileMini(handle: string): Promise<{ profile_published: boolean }>;
}

// Handle-like queries eligible for the Sayzio existence lookups: letters,
// digits, dash, underscore only (no spaces, no dots — those parse as URLs or
// multi-word searches). 2–63 chars.
export const SAYZIO_HANDLE_PATTERN = /^[a-z0-9][a-z0-9_-]{1,62}$/i;

/**
 * Whether a query may trigger a remote Sayzio lookup at all. Mirrors the
 * site-resolve privacy gate: never in private windows, only when signed in,
 * and only for handle-like queries.
 */
export function isSayzioSuggestEligible(
  query: string,
  opts: { isPrivate: boolean; token: string | null | undefined },
): boolean {
  return !opts.isPrivate && !!opts.token && SAYZIO_HANDLE_PATTERN.test(query);
}

/**
 * Live-check Sayzio for an exact link alias and creator handle match.
 * Fails silently (both false) on any network/API error. Results are cached
 * (keyed by lowercased query) in the provided cache so backspacing/retyping
 * the same handle doesn't re-hit the API; a cache hit skips the network.
 */
export async function checkSayzioExists(
  q: string,
  client: SayzioLookupClient,
  cache: Map<string, SayzioExistsResult>,
): Promise<SayzioExistsResult> {
  const key = q.toLowerCase();
  const cached = cache.get(key);
  if (cached) return cached;
  const [link, profile] = await Promise.all([
    // Inverted alias-availability check: status 'taken' means the alias
    // exists (invalid/reserved/banned do NOT count as existing links).
    client.checkAlias(q).then(r => r.status === 'taken').catch(() => false),
    client.creatorProfileMini(q).then(r => !!r.profile_published).catch(() => false),
  ]);
  const result: SayzioExistsResult = { link, profile };
  cache.set(key, result);
  return result;
}
