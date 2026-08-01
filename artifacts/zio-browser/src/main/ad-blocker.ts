/**
 * Full ad blocker for Zio Browser, powered by the @ghostery/adblocker filter
 * engine running EasyList + EasyPrivacy.
 *
 * - Bundled offline list snapshots ship with the app (build-resources/adblock)
 *   so ad blocking works with no network access.
 * - The parsed engine is cached (serialized) in userData so subsequent startups
 *   deserialize in ~20ms instead of re-parsing ~4MB of filter text.
 * - Lists refresh in the background every 24h; a failed refresh keeps the last
 *   good engine.
 * - This module owns MATCHING only. The single per-session webRequest
 *   dispatcher lives in tracker-blocker.ts and consults `matchAdRequest()` —
 *   never install a second onBeforeRequest listener for ads.
 * - Cosmetic (element-hiding) CSS comes from `getCosmeticStylesForUrl()` and is
 *   injected by the web-contents hook wired up in index.ts.
 */
import * as fs from 'fs';
import * as path from 'path';
import { app } from 'electron';
import { FiltersEngine, Request, ENGINE_VERSION } from '@ghostery/adblocker';
import { parse as parseDomain } from 'tldts-experimental';
import { getPreference, setPreference, isDbInitialized } from './db';
import { PREFERENCE_KEYS } from '../shared/db-schema';

/** Upstream list URLs used for the periodic background refresh. */
const LIST_URLS = [
  'https://easylist.to/easylist/easylist.txt',
  'https://easylist.to/easylist/easyprivacy.txt',
];

/** Refresh cadence for the background list update (24 hours). */
const REFRESH_INTERVAL_MS = 24 * 60 * 60 * 1000;

/** Electron resourceType → adblocker request type. */
const RESOURCE_TYPE_MAP: Record<string, string> = {
  mainFrame: 'main_frame',
  subFrame: 'sub_frame',
  stylesheet: 'stylesheet',
  script: 'script',
  image: 'image',
  font: 'font',
  object: 'object',
  xhr: 'xhr',
  ping: 'ping',
  cspReport: 'csp_report',
  media: 'media',
  webSocket: 'websocket',
  other: 'other',
};

/** Module state (single browser instance). */
let _engine: FiltersEngine | null = null;
let _refreshTimer: NodeJS.Timeout | null = null;
/**
 * Effective-state resolver: the layered policy module (admin policy → pauses
 * → per-site setting → user lists → global toggle) registered from index.ts.
 * Null (e.g. in unit tests) means the fallback flag below decides.
 */
let _policyResolver: ((wcId: number | undefined) => boolean) | null = null;
let _fallbackEnabled = false;

export function setAdBlockPolicyResolver(resolver: (wcId: number | undefined) => boolean): void {
  _policyResolver = resolver;
}

export function setAdBlockingEnabled(enabled: boolean): void {
  _fallbackEnabled = enabled;
}

export function isAdBlockingEnabled(): boolean {
  return _fallbackEnabled;
}

/** Whether the filter engine has finished loading. */
export function isAdBlockEngineReady(): boolean {
  return _engine !== null;
}

/**
 * Effective on/off decision for a given webContents — delegates to the
 * layered policy resolver when registered, else the plain global flag.
 */
export function isAdBlockingEffectiveForWc(wcId: number | undefined): boolean {
  if (_policyResolver) {
    try {
      return _policyResolver(wcId);
    } catch {
      // Best-effort; fall through to the global flag.
    }
  }
  return _fallbackEnabled;
}

// ── Bundled list resolution ──────────────────────────────────────────────────

/**
 * Locate the bundled offline list snapshots. Packaged builds ship them via
 * electron-builder extraResources; in dev they are read straight from
 * build-resources/adblock in the repo.
 */
function bundledListDirCandidates(): string[] {
  return app.isPackaged
    ? [path.join(process.resourcesPath, 'adblock')]
    : [
        path.join(app.getAppPath(), 'build-resources', 'adblock'),
        path.join(__dirname, '..', '..', '..', 'build-resources', 'adblock'),
      ];
}

function readBundledLists(): string | null {
  for (const dir of bundledListDirCandidates()) {
    try {
      const parts = ['easylist.txt', 'easyprivacy.txt']
        .map((f) => path.join(dir, f))
        .filter((p) => fs.existsSync(p))
        .map((p) => fs.readFileSync(p, 'utf8'));
      if (parts.length > 0) return parts.join('\n');
    } catch {
      // Try the next candidate directory.
    }
  }
  return null;
}

// ── Engine cache (serialized parsed engine in userData) ─────────────────────

/** Cache file name is keyed by the library's binary format version. */
function engineCachePath(): string {
  return path.join(app.getPath('userData'), `adblock-engine-v${ENGINE_VERSION}.bin`);
}

function saveEngineCache(engine: FiltersEngine): void {
  try {
    const tmp = `${engineCachePath()}.tmp`;
    fs.writeFileSync(tmp, Buffer.from(engine.serialize()));
    fs.renameSync(tmp, engineCachePath());
  } catch {
    // Cache is an optimization only — never let it break blocking.
  }
}

function loadEngineCache(): FiltersEngine | null {
  try {
    const p = engineCachePath();
    if (!fs.existsSync(p)) return null;
    return FiltersEngine.deserialize(fs.readFileSync(p));
  } catch {
    return null;
  }
}

// ── Initialization + background refresh ─────────────────────────────────────

/**
 * Initialize the ad blocker: load the engine (cached → bundled lists) and
 * start the periodic background list refresh. Safe to call once at startup;
 * loading happens off the critical path.
 */
let _initialized = false;

export function initAdBlocker(initialEnabled: boolean): void {
  _fallbackEnabled = initialEnabled;
  if (_initialized) return;
  _initialized = true;

  // Engine load is deferred a tick so it never delays window creation.
  setImmediate(() => {
    _engine = loadEngineCache();
    if (!_engine) {
      const lists = readBundledLists();
      if (lists) {
        try {
          _engine = FiltersEngine.parse(lists, { enableCompression: false });
          saveEngineCache(_engine);
        } catch (err) {
          console.error('Ad blocker: failed to parse bundled lists:', err);
        }
      } else {
        console.error('Ad blocker: bundled filter lists not found');
      }
    }
    // Refresh soon after startup if the lists are stale, then periodically.
    scheduleRefresh();
  });
}

function lastRefreshAt(): number {
  if (!isDbInitialized()) return 0;
  try {
    const raw = getPreference(PREFERENCE_KEYS.ADBLOCK_LISTS_UPDATED_AT);
    const t = raw ? Date.parse(raw) : NaN;
    return Number.isFinite(t) ? t : 0;
  } catch {
    return 0;
  }
}

function scheduleRefresh(): void {
  if (_refreshTimer) return;
  const elapsed = Date.now() - lastRefreshAt();
  const firstDelay = Math.max(30_000, REFRESH_INTERVAL_MS - elapsed);
  const tick = (): void => {
    void refreshLists().finally(() => {
      _refreshTimer = setTimeout(tick, REFRESH_INTERVAL_MS);
      _refreshTimer.unref?.();
    });
  };
  _refreshTimer = setTimeout(tick, firstDelay);
  _refreshTimer.unref?.();
}

/**
 * Fetch fresh copies of the lists and rebuild the engine. Any failure keeps
 * the last good engine untouched. Exported for tests.
 */
export async function refreshLists(): Promise<boolean> {
  try {
    const texts = await Promise.all(LIST_URLS.map(async (url) => {
      const resp = await fetch(url, { signal: AbortSignal.timeout(60_000) });
      if (!resp.ok) throw new Error(`HTTP ${resp.status} for ${url}`);
      return resp.text();
    }));
    const fresh = FiltersEngine.parse(texts.join('\n'), { enableCompression: false });
    _engine = fresh;
    saveEngineCache(fresh);
    if (isDbInitialized()) {
      try { setPreference(PREFERENCE_KEYS.ADBLOCK_LISTS_UPDATED_AT, new Date().toISOString()); } catch { /* best-effort */ }
    }
    return true;
  } catch {
    // Keep the last good engine (cached or bundled).
    return false;
  }
}

// ── Request matching (called from the tracker-blocker dispatcher) ───────────

export interface AdMatchDetails {
  url: string;
  resourceType: string;
  referrer: string;
}

/**
 * Decide whether a network request is an ad/tracking request per the filter
 * engine. Top-level navigations are never blocked — cancelling a mainFrame
 * load would blank the page instead of just removing an ad.
 */
export function matchAdRequest(details: AdMatchDetails): boolean {
  const engine = _engine;
  if (!engine) return false;
  if (details.resourceType === 'mainFrame') return false;
  try {
    const request = Request.fromRawDetails({
      url: details.url,
      type: (RESOURCE_TYPE_MAP[details.resourceType] ?? 'other') as never,
      sourceUrl: details.referrer || undefined,
    });
    return engine.match(request).match;
  } catch {
    return false;
  }
}

// ── Cosmetic (element-hiding) filtering ──────────────────────────────────────

/**
 * Return the element-hiding CSS for a page URL, or null when there is nothing
 * to inject. Only http(s) documents get cosmetics.
 */
export function getCosmeticStylesForUrl(url: string): string | null {
  const engine = _engine;
  if (!engine) return null;
  if (!url.startsWith('http://') && !url.startsWith('https://')) return null;
  try {
    const parsed = new URL(url);
    const { styles } = engine.getCosmeticsFilters({
      url,
      hostname: parsed.hostname,
      domain: parseDomain(parsed.hostname).domain ?? parsed.hostname,
      // Network-safe subset: CSS hiding only, no scriptlet injection.
      getInjectionRules: false,
      getExtendedRules: false,
      getRulesFromDOM: false,
      getRulesFromHostname: true,
    });
    return styles && styles.length > 0 ? styles : null;
  } catch {
    return null;
  }
}

/** Test-only: replace the live engine with one parsed from raw filter text. */
export function __setEngineFromTextForTests(text: string): void {
  _engine = FiltersEngine.parse(text, { enableCompression: false });
}
