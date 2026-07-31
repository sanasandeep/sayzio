/**
 * "On Sayzio" site detection — debounced, per-host cached public lookup used
 * by ChromeBar's address-bar badge.
 *
 * Privacy gate (in priority order):
 *   1. Never in private windows.
 *   2. Only for signed-in users (who already have a first-party relationship
 *      with Sayzio).
 * The badge state resets to null the moment either gate flips mid-session
 * (sign-out, window turning private) — both are effect dependencies — and a
 * pending debounced lookup is cancelled on any URL change or unmount.
 */
import { useState, useEffect } from 'react';
import { ApiClient } from '../../shared/api-client';
import type { SiteResolveResult } from '../../shared/api-client';

const BASE_URL = 'https://sayzio.app';

/** Debounce before hitting the public resolver (ms). */
export const SITE_RESOLVE_DEBOUNCE_MS = 800;

// Per-host cache for the "On Sayzio" site resolver (session-lifetime).
const siteResolveCache = new Map<string, SiteResolveResult>();

/** Test-only: reset the per-host cache between test cases. */
export function clearSiteResolveCache(): void {
  siteResolveCache.clear();
}

/** Extract a lookup-worthy hostname from a tab URL, or null to skip. */
export function hostForSiteResolve(url: string | undefined | null): string | null {
  if (!url || !/^https?:\/\//i.test(url)) return null;
  try {
    const host = new URL(url).hostname.toLowerCase().replace(/^www\./, '');
    if (!host.includes('.')) return null;
    // Sayzio's own hosts don't need a lookup.
    if (host === 'sayzio.app' || host.endsWith('.sayzio.app')) return null;
    return host;
  } catch {
    return null;
  }
}

export interface UseSiteResolveOptions {
  /** Current tab URL (badge subject). */
  url: string | undefined | null;
  /** True when this window is an incognito/private window. */
  isPrivate: boolean;
  /** Signed-in API token, or null when signed out. */
  token: string | null;
  /**
   * Override the network lookup (tests). Defaults to the public
   * ApiClient.resolveSite endpoint.
   */
  resolver?: (host: string) => Promise<SiteResolveResult>;
}

function defaultResolver(host: string): Promise<SiteResolveResult> {
  const client = new ApiClient({ baseUrl: BASE_URL });
  return client.resolveSite(host);
}

export function useSiteResolve({
  url,
  isPrivate,
  token,
  resolver,
}: UseSiteResolveOptions): SiteResolveResult | null {
  const [siteResolve, setSiteResolve] = useState<SiteResolveResult | null>(null);

  useEffect(() => {
    // Privacy: never in private windows, and only for signed-in users.
    // isPrivate/token are deps, so flipping either mid-visit clears the
    // badge immediately (and cancels any pending debounced lookup via the
    // previous run's cleanup).
    if (isPrivate || !token) { setSiteResolve(null); return; }
    const host = hostForSiteResolve(url);
    if (!host) { setSiteResolve(null); return; }
    const cached = siteResolveCache.get(host);
    if (cached) { setSiteResolve(cached); return; }
    setSiteResolve(null);
    let cancelled = false;
    const resolve = resolver ?? defaultResolver;
    const timer = setTimeout(() => {
      resolve(host)
        .then((res) => {
          siteResolveCache.set(host, res);
          if (!cancelled) setSiteResolve(res);
        })
        .catch(() => { /* Silent — indicator only. */ });
    }, SITE_RESOLVE_DEBOUNCE_MS);
    return () => { cancelled = true; clearTimeout(timer); };
  }, [url, isPrivate, token, resolver]);

  return siteResolve;
}
