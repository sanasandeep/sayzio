// Backlink-radar matcher. Lives in the background service worker so
// the page (content script) only ever sees its own DOM hrefs and is
// never burdened with the creator's known-properties payload.
//
// Public surface:
//   - ensureProperties()        — fetch + cache the user's known
//                                  properties (1h TTL) when missing.
//   - matchHrefs(payload)       — given a content-script harvest, run
//                                  it against the cached properties
//                                  and return the structured matches.
//   - updateBadgeForTab(tab,n)  — stamp the toolbar badge with the
//                                  match count + radar tint.
//   - rememberMatches(tabId,…)  — persist per-tab match list keyed by
//                                  tab id so the popup can render them.
//   - getMatchesForTab(tabId)   — read what was last seen on a tab.
//   - clearMatches(tabId)       — drop a tab's matches when it
//                                  navigates away or closes.

import { browser } from "../lib/browser";
import { api } from "../lib/api";
import { sha256HexPrefix } from "../lib/hash";
import {
  ExtSettings,
  PropertiesPayload,
  RadarMatch,
  TabMatchState,
  getCachedProperties,
  getSettings,
  setCachedProperties,
} from "../lib/storage";

const PROPERTIES_TTL_MS = 60 * 60 * 1000; // 1 hour
const MATCHES_KEY = "radarTabMatches";
const RADAR_BADGE_COLOR = "#6366f1";

/** Lowercased, www-stripped host. */
function normalizeHost(h: string | null | undefined): string {
  if (!h) return "";
  let host = h.trim().toLowerCase();
  if (host.startsWith("www.")) host = host.slice(4);
  const colon = host.indexOf(":");
  if (colon > 0) host = host.slice(0, colon);
  return host;
}

/** Does the page itself sit on a host the user has muted? */
export async function isPageHostDisabled(pageUrl: string, settings?: ExtSettings): Promise<boolean> {
  const s = settings ?? (await getSettings());
  if (!s.radarDisabledHosts?.length) return false;
  let host = "";
  try { host = normalizeHost(new URL(pageUrl).hostname); } catch { return false; }
  return s.radarDisabledHosts.some((h) => normalizeHost(h) === host);
}

export async function ensureProperties(force = false): Promise<PropertiesPayload | null> {
  const settings = await getSettings();
  if (!settings.token) return null;
  const cached = await getCachedProperties();
  const now = Date.now();
  if (
    !force &&
    cached &&
    now - (cached.fetched_at_ms || 0) < PROPERTIES_TTL_MS
  ) {
    return cached;
  }
  try {
    const resp = await api.properties();
    const payload: PropertiesPayload = { ...resp.properties, fetched_at_ms: now };
    await setCachedProperties(payload);
    return payload;
  } catch {
    return cached;
  }
}

/**
 * Pull every "candidate slug" out of a URL's pathname. We probe each
 * non-empty segment plus the bare last segment with extension stripped
 * — a creator with alias `summer` linked to as `/summer`, `/summer.html`,
 * or `/dl/summer` should still get matched.
 */
function extractSlugCandidates(pathname: string): string[] {
  const out = new Set<string>();
  const segs = pathname.split("/").map((s) => decodeURIComponent(s)).filter(Boolean);
  for (const s of segs) {
    out.add(s);
    const dot = s.lastIndexOf(".");
    if (dot > 0) out.add(s.slice(0, dot));
  }
  return [...out];
}

export async function matchHrefs(payload: {
  pageUrl: string;
  pageTitle: string;
  links: Array<{ href: string; anchor: string }>;
}): Promise<RadarMatch[]> {
  const props = await ensureProperties();
  if (!props) return [];

  const shortHosts = new Set(props.short_link_hosts.map(normalizeHost));
  const biolinkHosts = new Set(props.biolink_hosts.map(normalizeHost));
  const customDomainHosts = new Set(props.custom_domain_hosts.map(normalizeHost));
  const slugSet = new Set(props.slug_hashes);
  const prefixLen = props.slug_hash_prefix_len || 12;
  const usernamePath = (props.biolink_username_path || "").toLowerCase();

  let pageHost = "";
  try { pageHost = normalizeHost(new URL(payload.pageUrl).hostname); } catch { /* ignore */ }

  const out: RadarMatch[] = [];
  const seen = new Set<string>();
  for (const link of payload.links) {
    let url: URL;
    try { url = new URL(link.href); } catch { continue; }
    const host = normalizeHost(url.hostname);
    if (!host || host === pageHost) continue;

    let match: RadarMatch | null = null;

    // 1) Bio-link username path (e.g. https://1inme.com/handle/...)
    if (biolinkHosts.has(host) && usernamePath) {
      const p = url.pathname.toLowerCase();
      if (p === usernamePath || p.startsWith(usernamePath + "/")) {
        match = {
          href: link.href,
          anchor: link.anchor || "",
          matchedPropertyType: "biolink_username",
          matchedPropertyValue: usernamePath.replace(/^\//, ""),
        };
      }
    }

    // 2) Custom domain — host belongs to one of the user's verified
    //    custom domains. Counts as a backlink to that property.
    if (!match && customDomainHosts.has(host)) {
      match = {
        href: link.href,
        anchor: link.anchor || "",
        matchedPropertyType: "custom_domain",
        matchedPropertyValue: host,
      };
    }

    // 3) Short link — host is a known short-link host AND the first
    //    path segment hashes into the slug set.
    if (!match && shortHosts.has(host)) {
      const candidates = extractSlugCandidates(url.pathname);
      for (const c of candidates) {
        const h = await sha256HexPrefix(c, prefixLen);
        if (slugSet.has(h)) {
          match = {
            href: link.href,
            anchor: link.anchor || "",
            matchedPropertyType: "short_link",
            matchedPropertyValue: c,
          };
          break;
        }
      }
    }

    if (match && !seen.has(match.href)) {
      seen.add(match.href);
      out.push(match);
    }
  }
  return out;
}

export async function updateBadgeForTab(tabId: number, count: number) {
  try {
    const action = (browser as any).action || (browser as any).browserAction;
    if (!action) return;
    if (count > 0) {
      await action.setBadgeText?.({ tabId, text: String(count) });
      await action.setBadgeBackgroundColor?.({ tabId, color: RADAR_BADGE_COLOR });
      await action.setTitle?.({ tabId, title: `Sayzio — ${count} link${count === 1 ? "" : "s"} to you on this page` });
    } else {
      await action.setBadgeText?.({ tabId, text: "" });
      await action.setTitle?.({ tabId, title: "Sayzio" });
    }
  } catch { /* badge updates are best-effort */ }
}

export async function rememberMatches(tabId: number, state: TabMatchState | null) {
  const stored = (await browser.storage.local.get([MATCHES_KEY])) as Record<string, any>;
  const map: Record<string, TabMatchState> = stored[MATCHES_KEY] || {};
  if (state === null) {
    delete map[String(tabId)];
  } else {
    map[String(tabId)] = state;
  }
  await browser.storage.local.set({ [MATCHES_KEY]: map });
}

export async function getMatchesForTab(tabId: number): Promise<TabMatchState | null> {
  const stored = (await browser.storage.local.get([MATCHES_KEY])) as Record<string, any>;
  const map: Record<string, TabMatchState> = stored[MATCHES_KEY] || {};
  return map[String(tabId)] || null;
}

export async function clearMatches(tabId: number) {
  return rememberMatches(tabId, null);
}
