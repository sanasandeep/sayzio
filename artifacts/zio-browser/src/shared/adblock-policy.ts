/**
 * Pure ad-block policy resolution for Zio Browser.
 *
 * A single resolver computes the effective ad-blocking state for a page from
 * layered tiers, strongest first:
 *
 *   1. Admin-mandated policy (Sayzio-managed allow/block lists) — unbypassable
 *   2. Temporary timed pause (15 min / 1 hr / until restart)
 *   3. Per-page pause (until navigation)
 *   4. Per-site setting (the "Settings for this website" Ads override)
 *   5. User custom allow/block lists (subdomain matching)
 *   6. Global toggle + strength preset (Strict / Balanced / Off)
 *
 * This module is intentionally free of Electron/DB imports so the precedence
 * logic is unit-testable. State (pauses, preferences, admin cache) lives in
 * src/main/adblock-policy.ts.
 */

/** Strength presets. 'off' is represented by the global toggle being false. */
export type AdBlockStrength = 'strict' | 'balanced';

export const DEFAULT_ADBLOCK_STRENGTH: AdBlockStrength = 'balanced';

export function sanitizeStrength(raw: unknown): AdBlockStrength {
  return raw === 'strict' ? 'strict' : DEFAULT_ADBLOCK_STRENGTH;
}

/** Why the resolver landed on its decision (also drives the shield icon). */
export type AdBlockReason =
  | 'admin-block'   // admin forces blocking ON for this site (locked)
  | 'admin-allow'   // admin forces blocking OFF for this site (locked)
  | 'timed-pause'   // global temporary pause active
  | 'page-pause'    // paused on this page until navigation
  | 'site-setting'  // per-site Ads override
  | 'user-allow'    // user custom allow list
  | 'user-block'    // user custom block list
  | 'global';       // fell through to the global toggle / strength

export interface AdBlockState {
  /** Whether the ad-block engine should run for this page. */
  active: boolean;
  reason: AdBlockReason;
  /** True when the decision came from admin policy and cannot be changed. */
  adminLocked: boolean;
}

/** Versioned admin policy payload (as served by the Sayzio API). */
export interface AdminAdblockPolicy {
  version: number;
  allow: string[];
  block: string[];
}

export const EMPTY_ADMIN_POLICY: AdminAdblockPolicy = { version: 0, allow: [], block: [] };

/** Normalize a user/admin-entered domain: lowercase host, no scheme/path/www. */
export function normalizeDomain(raw: unknown): string | null {
  if (typeof raw !== 'string') return null;
  let s = raw.trim().toLowerCase();
  if (!s) return null;
  // Strip scheme and path if a full URL was pasted.
  s = s.replace(/^[a-z][a-z0-9+.-]*:\/\//, '');
  s = s.split('/')[0].split('?')[0].split('#')[0];
  // Strip port and leading www.
  s = s.split(':')[0];
  if (s.startsWith('www.')) s = s.slice(4);
  // Basic hostname sanity: labels of [a-z0-9-], at least one dot, no spaces.
  if (!/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/.test(s)) return null;
  if (s.length > 253) return null;
  return s;
}

/** Parse a JSON preference value into a clean, deduped domain list. */
export function parseDomainList(rawJson: string | null | undefined): string[] {
  if (!rawJson) return [];
  try {
    const arr = JSON.parse(rawJson) as unknown;
    if (!Array.isArray(arr)) return [];
    const out: string[] = [];
    for (const item of arr) {
      const d = normalizeDomain(item);
      if (d && !out.includes(d)) out.push(d);
    }
    return out;
  } catch {
    return [];
  }
}

/** True when `host` equals `domain` or is a subdomain of it. */
export function hostMatchesDomain(host: string, domain: string): boolean {
  if (!host || !domain) return false;
  return host === domain || host.endsWith(`.${domain}`);
}

/** True when `host` matches any entry in the list (subdomain matching). */
export function hostMatchesList(host: string | null, list: readonly string[]): boolean {
  if (!host) return false;
  const h = host.startsWith('www.') ? host.slice(4) : host;
  for (const d of list) {
    if (hostMatchesDomain(h, d) || hostMatchesDomain(host, d)) return true;
  }
  return false;
}

/** Validate + sanitize an admin policy payload (from the API or offline cache). */
export function sanitizeAdminPolicy(raw: unknown): AdminAdblockPolicy {
  if (!raw || typeof raw !== 'object') return EMPTY_ADMIN_POLICY;
  const obj = raw as Record<string, unknown>;
  const version = typeof obj.version === 'number' && Number.isFinite(obj.version) && obj.version >= 0
    ? Math.floor(obj.version) : 0;
  const clean = (v: unknown): string[] => {
    if (!Array.isArray(v)) return [];
    const out: string[] = [];
    for (const item of v) {
      const d = normalizeDomain(item);
      if (d && !out.includes(d)) out.push(d);
    }
    return out;
  };
  return { version, allow: clean(obj.allow), block: clean(obj.block) };
}

export interface ResolveInput {
  /** Page hostname (null when unknown/non-http — resolver falls to global). */
  host: string | null;
  adminPolicy: AdminAdblockPolicy;
  /** Global timed pause currently active (timed or until-restart). */
  timedPauseActive: boolean;
  /** This specific page is paused until navigation. */
  pagePaused: boolean;
  /** Per-site Ads override (true/false) or null. Private windows pass null. */
  siteOverride: boolean | null;
  userAllow: readonly string[];
  userBlock: readonly string[];
  /** Global ad-blocking toggle (strength Off = false). */
  globalEnabled: boolean;
}

/** Resolve the effective ad-block state for a page from all tiers. */
export function resolveAdBlockState(input: ResolveInput): AdBlockState {
  const { host } = input;
  // Tier 1 — admin policy is unbypassable, block beats allow.
  if (host && hostMatchesList(host, input.adminPolicy.block)) {
    return { active: true, reason: 'admin-block', adminLocked: true };
  }
  if (host && hostMatchesList(host, input.adminPolicy.allow)) {
    return { active: false, reason: 'admin-allow', adminLocked: true };
  }
  // Tier 2 — global timed pause.
  if (input.timedPauseActive) {
    return { active: false, reason: 'timed-pause', adminLocked: false };
  }
  // Tier 3 — per-page pause (until navigation).
  if (input.pagePaused) {
    return { active: false, reason: 'page-pause', adminLocked: false };
  }
  // Tier 4 — per-site Ads override.
  if (input.siteOverride !== null) {
    return { active: input.siteOverride, reason: 'site-setting', adminLocked: false };
  }
  // Tier 5 — user custom lists (block beats allow when both match).
  if (host && hostMatchesList(host, input.userBlock)) {
    return { active: true, reason: 'user-block', adminLocked: false };
  }
  if (host && hostMatchesList(host, input.userAllow)) {
    return { active: false, reason: 'user-allow', adminLocked: false };
  }
  // Tier 6 — global toggle / strength.
  return { active: input.globalEnabled, reason: 'global', adminLocked: false };
}

/**
 * Request-level override for an individual outgoing request host:
 * admin block → 'block', admin allow → 'allow', then user block/allow.
 * Returns null when no list matches (fall through to engine matching).
 * These apply in every session (profiles AND private windows), regardless of
 * the global toggle.
 */
export function requestHostOverride(
  host: string,
  adminPolicy: AdminAdblockPolicy,
  userAllow: readonly string[],
  userBlock: readonly string[],
): 'allow' | 'block' | null {
  if (hostMatchesList(host, adminPolicy.block)) return 'block';
  if (hostMatchesList(host, adminPolicy.allow)) return 'allow';
  if (hostMatchesList(host, userBlock)) return 'block';
  if (hostMatchesList(host, userAllow)) return 'allow';
  return null;
}
