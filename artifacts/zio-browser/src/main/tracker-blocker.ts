/**
 * Tracker & ad blocker for Zio Browser.
 * Enforces a bundled static blocklist via the Electron session webRequest API.
 * Tracks per-tab blocked request counts and broadcasts updates to the renderer.
 */
import type { Session, BrowserWindow } from 'electron';
import { getPreference, setPreference, isDbInitialized } from './db';
import { PREFERENCE_KEYS } from '../shared/db-schema';

/**
 * Compact, bundled blocklist of well-known tracker and ad-network hostnames.
 * No external subscriptions — this list is baked into the binary.
 * Default behaviour: tracker blocking is OFF by default (user must enable it).
 */
export const TRACKER_DOMAINS = new Set([
  // Google Analytics / Ads
  'google-analytics.com', 'googletagmanager.com', 'googletagservices.com',
  'googlesyndication.com', 'doubleclick.net', 'adservice.google.com',
  'pagead2.googlesyndication.com', 'stats.g.doubleclick.net',
  // Meta / Facebook
  'connect.facebook.net', 'pixel.facebook.com', 'an.facebook.com',
  // Twitter / X
  'ads-twitter.com', 'analytics.twitter.com', 'platform.twitter.com',
  // Amazon
  'amazon-adsystem.com', 'assoc-amazon.com', 'fls-na.amazon.com',
  // Microsoft
  'bat.bing.com', 'clarity.ms', 'c.clarity.ms',
  // LinkedIn
  'snap.licdn.com', 'px.ads.linkedin.com',
  // Pinterest
  'ct.pinterest.com', 'tr.pinterest.com',
  // TikTok
  'analytics.tiktok.com',
  // Snapchat
  'tr.snapchat.com',
  // Outbrain / Taboola
  'outbrain.com', 'widgets.outbrain.com', 'taboola.com', 'trc.taboola.com',
  // AdRoll / Criteo
  'adroll.com', 'd.adroll.com', 'criteo.com', 'static.criteo.net',
  // Programmatic ad networks
  'pubmatic.com', 'rubiconproject.com', 'openx.net', 'openx.com',
  'adsrvr.org', 'bidswitch.net', 'advertising.com',
  'casalemedia.com', 'contextweb.com', 'conversantmedia.com',
  'emxdgt.com', 'lijit.com', 'lkqd.net', 'media.net',
  'moatads.com', 'servedby-buysellads.com', 'sharethrough.com',
  'sovrn.com', 'spotxchange.com', 'tribalfusion.com', 'turn.com',
  'undertone.com', 'unrulymedia.com', 'valueclick.net',
  'xaxis.com', 'yieldmo.com', 'zedo.com', '33across.com',
  // Analytics / session recording
  'quantserve.com', 'scorecardresearch.com', 'comscore.com',
  'chartbeat.com', 'chartbeat.net', 'hotjar.com', 'static.hotjar.com',
  'script.hotjar.com', 'mouseflow.com', 'fullstory.com',
  'logrocket.com', 'logrocket.io', 'rec.siterecording.com',
  // Product analytics
  'mixpanel.com', 'api.mixpanel.com', 'amplitude.com', 'api.amplitude.com',
  'segment.com', 'api.segment.io', 'segment.io',
  // Tracking pixels / affiliates
  'pixel.parsely.com', 'p.typekit.net', 'rfihub.com', 'rlcdn.com',
  'zeotap.com', 'akstat.io',
]);

/** State shared across the module (single browser instance). */
let _enabled = false;
const _blockedCounts: Map<string, number> = new Map();
let _mainWin: BrowserWindow | null = null;
let _getTabId: (wcId: number) => string | null = () => null;
const _installedSessions = new WeakSet<Session>();

// ── Persistent weekly tracker stats ──────────────────────────────────────────
// Shape stored in the TRACKER_STATS preference (JSON):
//   { "2026-07-25": { "doubleclick.net": 12, ... }, ... }
// Pruned to the last 7 days. Writes are buffered so a busy page does not
// hammer SQLite — flush after 20 buffered events or 5 s, whichever first.
type TrackerStatsMap = Record<string, Record<string, number>>;

let _statsLoaded = false;
let _stats: TrackerStatsMap = {};
let _pendingEvents = 0;
let _flushTimer: NodeJS.Timeout | null = null;

function todayKey(): string {
  return new Date().toISOString().slice(0, 10);
}

function pruneStats(stats: TrackerStatsMap): TrackerStatsMap {
  const cutoff = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
  const out: TrackerStatsMap = {};
  for (const [day, hosts] of Object.entries(stats)) {
    if (day >= cutoff) out[day] = hosts;
  }
  return out;
}

function loadStats(): void {
  if (_statsLoaded) return;
  _statsLoaded = true;
  if (!isDbInitialized()) return;
  try {
    const raw = getPreference(PREFERENCE_KEYS.TRACKER_STATS);
    if (raw) _stats = pruneStats(JSON.parse(raw) as TrackerStatsMap);
  } catch {
    _stats = {};
  }
}

function flushStats(): void {
  if (_flushTimer) { clearTimeout(_flushTimer); _flushTimer = null; }
  if (_pendingEvents === 0) return;
  _pendingEvents = 0;
  if (!isDbInitialized()) return;
  try {
    _stats = pruneStats(_stats);
    setPreference(PREFERENCE_KEYS.TRACKER_STATS, JSON.stringify(_stats));
  } catch {
    // Stats are best-effort; never let persistence break blocking.
  }
}

function recordBlockedTracker(trackerHost: string): void {
  loadStats();
  const day = todayKey();
  const dayMap = _stats[day] ?? (_stats[day] = {});
  dayMap[trackerHost] = (dayMap[trackerHost] ?? 0) + 1;
  _pendingEvents += 1;
  if (_pendingEvents >= 20) {
    flushStats();
  } else if (!_flushTimer) {
    _flushTimer = setTimeout(flushStats, 5000);
    _flushTimer.unref?.();
  }
}

/** Aggregated stats for the Privacy Dashboard (last 7 days). */
export function getTrackerStats(): {
  weekTotal: number;
  todayTotal: number;
  byDay: Array<{ day: string; count: number }>;
  topTrackers: Array<{ host: string; count: number }>;
} {
  loadStats();
  flushStats();
  const stats = pruneStats(_stats);
  const byDay: Array<{ day: string; count: number }> = [];
  const hostTotals: Record<string, number> = {};
  let weekTotal = 0;
  // Emit the last 7 calendar days, oldest → newest, including zero days.
  for (let i = 6; i >= 0; i--) {
    const day = new Date(Date.now() - i * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
    const hosts = stats[day] ?? {};
    let count = 0;
    for (const [host, n] of Object.entries(hosts)) {
      count += n;
      hostTotals[host] = (hostTotals[host] ?? 0) + n;
    }
    weekTotal += count;
    byDay.push({ day, count });
  }
  const topTrackers = Object.entries(hostTotals)
    .map(([host, count]) => ({ host, count }))
    .sort((a, b) => b.count - a.count)
    .slice(0, 8);
  return {
    weekTotal,
    todayTotal: byDay[byDay.length - 1]?.count ?? 0,
    byDay,
    topTrackers,
  };
}

/**
 * Call once at startup to wire up the webRequest handler.
 * The handler is installed permanently on the session; the `_enabled` flag
 * short-circuits it when blocking is off so there is no overhead.
 */
export function setupTrackerBlocking(
  sess: Session,
  win: BrowserWindow,
  initialEnabled: boolean,
  getTabIdForWcId: (wcId: number) => string | null,
): void {
  _mainWin = win;
  _getTabId = getTabIdForWcId;
  _enabled = initialEnabled;
  installTrackerHooks(sess);
}

/**
 * Idempotently install the blocking webRequest hook on a session. Call this
 * for every session that carries tab traffic (default session + each profile
 * partition) so tracker blocking applies everywhere.
 */
export function installTrackerHooks(sess: Session): void {
  if (_installedSessions.has(sess)) return;
  _installedSessions.add(sess);

  sess.webRequest.onBeforeRequest((details, callback) => {
    if (!_enabled) {
      callback({ cancel: false });
      return;
    }

    let blocked = false;
    try {
      const parsed = new URL(details.url);
      // Strip leading "www." for matching
      const raw = parsed.hostname;
      const host = raw.startsWith('www.') ? raw.slice(4) : raw;

      // Exact hostname match, or the request hostname is a subdomain of a listed domain
      if (TRACKER_DOMAINS.has(host) || TRACKER_DOMAINS.has(raw)) {
        blocked = true;
      } else {
        for (const domain of TRACKER_DOMAINS) {
          if (host.endsWith(`.${domain}`)) {
            blocked = true;
            break;
          }
        }
      }
    } catch {
      blocked = false;
    }

    if (blocked) {
      try {
        const raw = new URL(details.url).hostname;
        recordBlockedTracker(raw.startsWith('www.') ? raw.slice(4) : raw);
      } catch {
        // best-effort stats only
      }
      const wcId = details.webContentsId;
      if (wcId !== undefined) {
        const tabId = _getTabId(wcId);
        if (tabId) {
          _blockedCounts.set(tabId, (_blockedCounts.get(tabId) ?? 0) + 1);
          _mainWin?.webContents.send('tracker:blocked-count', tabId, _blockedCounts.get(tabId) ?? 0);
        }
      }
      callback({ cancel: true });
    } else {
      callback({ cancel: false });
    }
  });
}

/** Enable or disable tracker blocking at runtime. */
export function setTrackerBlockingEnabled(enabled: boolean): void {
  _enabled = enabled;
}

export function isTrackerBlockingEnabled(): boolean {
  return _enabled;
}

/** Get the blocked request count for a specific tab. */
export function getBlockedCount(tabId: string): number {
  return _blockedCounts.get(tabId) ?? 0;
}

/** Reset the blocked count for a tab (call on navigation). */
export function resetBlockedCount(tabId: string): void {
  _blockedCounts.delete(tabId);
  _mainWin?.webContents.send('tracker:blocked-count', tabId, 0);
}
