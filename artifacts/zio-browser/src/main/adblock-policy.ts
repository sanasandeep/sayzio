/**
 * Main-process ad-block policy state: the single owner of every tier the
 * resolver in src/shared/adblock-policy.ts consumes.
 *
 * - Admin-mandated policy: fetched from the Sayzio API on launch + every 6 h,
 *   cached in preferences (offline-safe), ETag-aware. Unbypassable.
 * - Timed pause: in-memory (15 min / 1 hr / until restart).
 * - Per-page pause: in-memory per webContents, keyed by the URL at pause time
 *   so any navigation self-clears it.
 * - User custom allow/block lists + strength preset: preferences (SQLite),
 *   shared across all profiles and applied to private windows too.
 */
import { webContents, BrowserWindow } from 'electron';
import { getPreference, setPreference, getAllPreferences, isDbInitialized } from './db';
import { PREFERENCE_KEYS } from '../shared/db-schema';
import {
  type AdBlockState,
  type AdBlockStrength,
  type AdminAdblockPolicy,
  EMPTY_ADMIN_POLICY,
  normalizeDomain,
  parseDomainList,
  requestHostOverride,
  resolveAdBlockState,
  sanitizeAdminPolicy,
  sanitizeStrength,
} from '../shared/adblock-policy';
import { adBlockOverrideForOrigin } from './site-settings';

const DEFAULT_API_BASE = 'https://sayzio.app';
const POLICY_REFRESH_MS = 6 * 60 * 60 * 1000; // 6 hours

// ── In-memory pause state ────────────────────────────────────────────────────
/** wcId → URL that was showing when the page pause was taken. */
const _pagePauses = new Map<number, string>();
/** Epoch ms when the global timed pause expires, or null. */
let _timedPauseUntil: number | null = null;
/** Paused until the app restarts (in-memory only, so restart clears it). */
let _pausedUntilRestart = false;

// ── Cached preference-backed state ──────────────────────────────────────────
let _adminPolicy: AdminAdblockPolicy = EMPTY_ADMIN_POLICY;
let _adminEtag: string | null = null;
let _adminFetchedAt: string | null = null;
let _userAllow: string[] = [];
let _userBlock: string[] = [];
let _strength: AdBlockStrength = 'balanced';
let _globalEnabled = false;
let _refreshTimer: NodeJS.Timeout | null = null;

function safeGetPref(key: (typeof PREFERENCE_KEYS)[keyof typeof PREFERENCE_KEYS]): string | null {
  try {
    if (!isDbInitialized()) return null;
    return getPreference(key);
  } catch {
    return null;
  }
}

function safeSetPref(key: (typeof PREFERENCE_KEYS)[keyof typeof PREFERENCE_KEYS], value: string): void {
  try {
    if (isDbInitialized()) setPreference(key, value);
  } catch {
    // best-effort persistence
  }
}

/** Load all persisted policy state. Call once at startup after initDb(). */
export function initAdBlockPolicy(globalEnabled: boolean): void {
  _globalEnabled = globalEnabled;
  _strength = sanitizeStrength(safeGetPref(PREFERENCE_KEYS.ADBLOCK_STRENGTH));
  _userAllow = parseDomainList(safeGetPref(PREFERENCE_KEYS.ADBLOCK_USER_ALLOW));
  _userBlock = parseDomainList(safeGetPref(PREFERENCE_KEYS.ADBLOCK_USER_BLOCK));
  loadCachedAdminPolicy();
}

/** Restore the offline admin-policy cache from preferences. */
function loadCachedAdminPolicy(): void {
  const raw = safeGetPref(PREFERENCE_KEYS.ADBLOCK_ADMIN_POLICY);
  if (!raw) return;
  try {
    const parsed = JSON.parse(raw) as { policy?: unknown; etag?: unknown; fetched_at?: unknown };
    _adminPolicy = sanitizeAdminPolicy(parsed?.policy);
    _adminEtag = typeof parsed?.etag === 'string' ? parsed.etag : null;
    _adminFetchedAt = typeof parsed?.fetched_at === 'string' ? parsed.fetched_at : null;
  } catch {
    _adminPolicy = EMPTY_ADMIN_POLICY;
  }
}

function apiBase(): string {
  try {
    return getAllPreferences()[PREFERENCE_KEYS.SAYZIO_API_BASE_URL] ?? DEFAULT_API_BASE;
  } catch {
    return DEFAULT_API_BASE;
  }
}

/**
 * Fetch the admin policy from the Sayzio API. Silent best-effort: offline or
 * error keeps the cached copy (which loads at startup). Returns true when the
 * policy changed.
 */
export async function refreshAdminPolicy(): Promise<boolean> {
  try {
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (_adminEtag) headers['If-None-Match'] = _adminEtag;
    const resp = await fetch(`${apiBase()}/api/v1/zio-browser/adblock-policy`, { headers });
    if (resp.status === 304) {
      _adminFetchedAt = new Date().toISOString();
      persistAdminCache();
      return false;
    }
    if (!resp.ok) return false;
    const body = (await resp.json()) as { data?: unknown };
    const next = sanitizeAdminPolicy(body?.data);
    const changed = JSON.stringify(next) !== JSON.stringify(_adminPolicy);
    _adminPolicy = next;
    _adminEtag = resp.headers.get('etag');
    _adminFetchedAt = new Date().toISOString();
    persistAdminCache();
    if (changed) broadcastStateChanged();
    return changed;
  } catch {
    // Offline — keep the cached policy so mandates stay enforced.
    return false;
  }
}

function persistAdminCache(): void {
  safeSetPref(
    PREFERENCE_KEYS.ADBLOCK_ADMIN_POLICY,
    JSON.stringify({ policy: _adminPolicy, etag: _adminEtag, fetched_at: _adminFetchedAt }),
  );
}

/** Kick off the launch fetch + periodic refresh timer. */
export function startAdminPolicySync(): void {
  void refreshAdminPolicy();
  if (_refreshTimer) clearInterval(_refreshTimer);
  _refreshTimer = setInterval(() => { void refreshAdminPolicy(); }, POLICY_REFRESH_MS);
  _refreshTimer.unref?.();
}

// ── Accessors / mutators ─────────────────────────────────────────────────────

export function getAdminPolicyInfo(): { policy: AdminAdblockPolicy; fetchedAt: string | null } {
  return { policy: _adminPolicy, fetchedAt: _adminFetchedAt };
}

export function getStrength(): AdBlockStrength {
  return _strength;
}

export function setStrength(strength: AdBlockStrength): void {
  _strength = sanitizeStrength(strength);
  safeSetPref(PREFERENCE_KEYS.ADBLOCK_STRENGTH, _strength);
  broadcastStateChanged();
}

export function isGlobalAdBlockEnabled(): boolean {
  return _globalEnabled;
}

export function setGlobalAdBlockEnabled(enabled: boolean): void {
  _globalEnabled = enabled === true;
  safeSetPref(PREFERENCE_KEYS.AD_BLOCKING_ENABLED, _globalEnabled ? '1' : '0');
  broadcastStateChanged();
}

export function getUserLists(): { allow: string[]; block: string[] } {
  return { allow: [..._userAllow], block: [..._userBlock] };
}

export function addUserListDomain(kind: 'allow' | 'block', raw: string): string | null {
  const domain = normalizeDomain(raw);
  if (!domain) return null;
  const list = kind === 'allow' ? _userAllow : _userBlock;
  if (!list.includes(domain)) list.push(domain);
  // Keep the two lists disjoint: adding to one removes from the other.
  const other = kind === 'allow' ? '_userBlock' : '_userAllow';
  if (other === '_userBlock') _userBlock = _userBlock.filter(d => d !== domain);
  else _userAllow = _userAllow.filter(d => d !== domain);
  persistUserLists();
  broadcastStateChanged();
  return domain;
}

export function removeUserListDomain(kind: 'allow' | 'block', raw: string): boolean {
  const domain = normalizeDomain(raw);
  if (!domain) return false;
  const before = kind === 'allow' ? _userAllow.length : _userBlock.length;
  if (kind === 'allow') _userAllow = _userAllow.filter(d => d !== domain);
  else _userBlock = _userBlock.filter(d => d !== domain);
  const removed = (kind === 'allow' ? _userAllow.length : _userBlock.length) !== before;
  if (removed) {
    persistUserLists();
    broadcastStateChanged();
  }
  return removed;
}

function persistUserLists(): void {
  safeSetPref(PREFERENCE_KEYS.ADBLOCK_USER_ALLOW, JSON.stringify(_userAllow));
  safeSetPref(PREFERENCE_KEYS.ADBLOCK_USER_BLOCK, JSON.stringify(_userBlock));
}

// ── Pauses ───────────────────────────────────────────────────────────────────

/** Pause ad blocking on the page currently shown in this webContents. */
export function pausePage(wcId: number): boolean {
  try {
    const wc = webContents.fromId(wcId);
    if (!wc || wc.isDestroyed()) return false;
    _pagePauses.set(wcId, wc.getURL());
    broadcastStateChanged();
    return true;
  } catch {
    return false;
  }
}

/** True when this webContents still shows the URL it was paused on. */
function isPagePaused(wcId: number | undefined, currentUrl?: string): boolean {
  if (wcId === undefined) return false;
  const pausedUrl = _pagePauses.get(wcId);
  if (pausedUrl === undefined) return false;
  let url = currentUrl;
  if (url === undefined) {
    try {
      const wc = webContents.fromId(wcId);
      url = wc && !wc.isDestroyed() ? wc.getURL() : undefined;
    } catch {
      url = undefined;
    }
  }
  if (url === undefined || url !== pausedUrl) {
    // Navigated away (or gone) — the page pause self-clears.
    _pagePauses.delete(wcId);
    return false;
  }
  return true;
}

/** Pause globally for N minutes, or until restart when minutes is null. */
export function pauseTimed(minutes: number | null): void {
  if (minutes === null) {
    _pausedUntilRestart = true;
    _timedPauseUntil = null;
  } else {
    const mins = Math.max(1, Math.min(24 * 60, Math.floor(minutes)));
    _timedPauseUntil = Date.now() + mins * 60_000;
    _pausedUntilRestart = false;
  }
  broadcastStateChanged();
}

/** Clear every user-level pause (timed, until-restart, and all page pauses). */
export function resumeAdBlocking(): void {
  _timedPauseUntil = null;
  _pausedUntilRestart = false;
  _pagePauses.clear();
  broadcastStateChanged();
}

export function isTimedPauseActive(now: number = Date.now()): boolean {
  if (_pausedUntilRestart) return true;
  if (_timedPauseUntil === null) return false;
  if (now >= _timedPauseUntil) {
    _timedPauseUntil = null;
    return false;
  }
  return true;
}

export function getPauseInfo(): { timedPauseUntil: number | null; pausedUntilRestart: boolean } {
  // Report expiry lazily so an elapsed pause never shows as active.
  if (!isTimedPauseActive()) return { timedPauseUntil: null, pausedUntilRestart: false };
  return { timedPauseUntil: _timedPauseUntil, pausedUntilRestart: _pausedUntilRestart };
}

// ── Resolution ───────────────────────────────────────────────────────────────

function hostFromUrl(url: string | undefined): string | null {
  if (!url) return null;
  try {
    const u = new URL(url);
    if (!u.protocol.startsWith('http')) return null;
    return u.hostname;
  } catch {
    return null;
  }
}

/**
 * Effective ad-block state for a page shown in a webContents. Private windows
 * (non-persistent sessions) skip the per-site setting tier but still honor
 * admin policy, pauses, user lists, and the global toggle.
 */
export function getStateForWc(wcId: number | undefined): AdBlockState {
  let host: string | null = null;
  let siteOverride: boolean | null = null;
  let url: string | undefined;
  if (wcId !== undefined) {
    try {
      const wc = webContents.fromId(wcId);
      if (wc && !wc.isDestroyed()) {
        url = wc.getURL();
        host = hostFromUrl(url);
        if (host && wc.session.isPersistent()) {
          try {
            siteOverride = adBlockOverrideForOrigin(new URL(url).origin);
          } catch {
            siteOverride = null;
          }
        }
      }
    } catch {
      // fall through with null host
    }
  }
  return resolveAdBlockState({
    host,
    adminPolicy: _adminPolicy,
    timedPauseActive: isTimedPauseActive(),
    pagePaused: isPagePaused(wcId, url),
    siteOverride,
    userAllow: _userAllow,
    userBlock: _userBlock,
    globalEnabled: _globalEnabled,
  });
}

/** Whether the engine should evaluate requests issued by this webContents. */
export function isAdBlockActiveForWc(wcId: number | undefined): boolean {
  return getStateForWc(wcId).active;
}

/**
 * Per-request host override (admin allow/block, then user allow/block).
 * Applies in every session — including private windows — and even when the
 * global toggle is off.
 */
export function overrideForRequestHost(host: string): 'allow' | 'block' | null {
  return requestHostOverride(host, _adminPolicy, _userAllow, _userBlock);
}

/** True when the page host is admin-controlled (per-site UI must lock). */
export function isHostAdminControlled(host: string | null): boolean {
  if (!host) return false;
  return overrideForRequestHostAdminOnly(host) !== null;
}

function overrideForRequestHostAdminOnly(host: string): 'allow' | 'block' | null {
  return requestHostOverride(host, _adminPolicy, [], []);
}

// ── Renderer notifications ───────────────────────────────────────────────────

function broadcastStateChanged(): void {
  try {
    for (const win of BrowserWindow.getAllWindows()) {
      if (!win.isDestroyed()) win.webContents.send('adblock:state-changed');
    }
  } catch {
    // best-effort UI refresh only
  }
}

/** Test-only: reset all in-memory state. */
export function __resetAdBlockPolicyForTests(): void {
  _pagePauses.clear();
  _timedPauseUntil = null;
  _pausedUntilRestart = false;
  _adminPolicy = EMPTY_ADMIN_POLICY;
  _adminEtag = null;
  _adminFetchedAt = null;
  _userAllow = [];
  _userBlock = [];
  _strength = 'balanced';
  _globalEnabled = false;
}
