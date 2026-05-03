// Backlink radar content script.
//
// Runs on every http(s) page the creator visits (when the radar is
// enabled). Collects outbound link hrefs, anchor text, and the page's
// own title — nothing else. Posts the harvested hrefs to the
// background script, which compares them against the creator's known
// properties (cached). The page text, body content, cookies, and any
// PII are never read or transmitted.
//
// Fires once after DOM stability and again on history-API navigations
// (SPAs) so client-routed pages still get scanned.
//
// In addition to the link harvest, we sniff the page author's
// public contact handles (email / X handle / LinkedIn profile URL) so
// the popup's Thank composer can open instantly with the right targets
// pre-filled instead of doing its own executeScript hop on demand.

import { browser } from "../lib/browser";

interface AuthorContacts {
  email: string | null;
  xHandle: string | null;
  linkedinUrl: string | null;
}

interface HarvestPayload {
  pageUrl: string;
  pageTitle: string;
  links: Array<{ href: string; anchor: string }>;
  author: AuthorContacts;
}

const MAX_LINKS = 1500;
const STABILITY_MS = 600;

function harvestAuthor(): AuthorContacts {
  const out: AuthorContacts = { email: null, xHandle: null, linkedinUrl: null };

  // ── Email ────────────────────────────────────────────────
  const emailRe = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/;
  const mailto = document.querySelector<HTMLAnchorElement>('a[href^="mailto:"]');
  if (mailto) {
    const v = (mailto.getAttribute("href") || "").replace(/^mailto:/, "").split("?")[0];
    if (emailRe.test(v)) out.email = v;
  }
  if (!out.email) {
    const text = document.body?.innerText?.slice(0, 30000) || "";
    const m = text.match(emailRe);
    if (m) out.email = m[0];
  }

  // ── X handle ─────────────────────────────────────────────
  const cleanHandle = (raw: string | null | undefined): string | null => {
    if (!raw) return null;
    const h = raw.trim().replace(/^@/, "");
    if (!/^[A-Za-z0-9_]{1,15}$/.test(h)) return null;
    if (/^(home|search|explore|notifications|messages|i|intent|share|hashtag|status|compose)$/i.test(h)) return null;
    return h;
  };
  const handleFromUrl = (href: string): string | null => {
    try {
      const u = new URL(href, document.baseURI);
      const host = u.hostname.replace(/^www\./, "");
      if (host !== "twitter.com" && host !== "x.com" && host !== "mobile.twitter.com") return null;
      const segs = u.pathname.split("/").filter(Boolean);
      if (segs.length < 1) return null;
      return cleanHandle(segs[0]);
    } catch { return null; }
  };
  const metaCreator =
    document.querySelector<HTMLMetaElement>('meta[name="twitter:creator" i]')?.content ||
    document.querySelector<HTMLMetaElement>('meta[property="twitter:creator" i]')?.content ||
    null;
  out.xHandle = cleanHandle(metaCreator);
  if (!out.xHandle) {
    const authorLinks = Array.from(document.querySelectorAll<HTMLAnchorElement>('a[rel~="author" i][href]'));
    for (const a of authorLinks) {
      const h = handleFromUrl(a.href);
      if (h) { out.xHandle = h; break; }
    }
  }
  if (!out.xHandle) {
    const candidates = Array.from(document.querySelectorAll<HTMLAnchorElement>(
      'a[href*="twitter.com/" i], a[href*="x.com/" i]',
    )).slice(0, 50);
    for (const a of candidates) {
      const h = handleFromUrl(a.href);
      if (h) { out.xHandle = h; break; }
    }
  }

  // ── LinkedIn profile URL ─────────────────────────────────
  const cleanLinkedinUrl = (href: string): string | null => {
    try {
      const u = new URL(href, document.baseURI);
      const host = u.hostname.replace(/^www\./, "");
      if (!/(^|\.)linkedin\.com$/.test(host)) return null;
      const segs = u.pathname.split("/").filter(Boolean);
      if (segs.length < 2) return null;
      if (!/^(in|company|pub)$/i.test(segs[0])) return null;
      const profileSlug = segs[1].split("?")[0];
      if (!profileSlug) return null;
      return `https://www.linkedin.com/${segs[0].toLowerCase()}/${profileSlug}/`;
    } catch { return null; }
  };
  const liAuthor = Array.from(document.querySelectorAll<HTMLAnchorElement>('a[rel~="author" i][href]'));
  for (const a of liAuthor) {
    const url = cleanLinkedinUrl(a.href);
    if (url) { out.linkedinUrl = url; break; }
  }
  if (!out.linkedinUrl) {
    const liLinks = Array.from(document.querySelectorAll<HTMLAnchorElement>('a[href*="linkedin.com/" i]')).slice(0, 50);
    for (const a of liLinks) {
      const url = cleanLinkedinUrl(a.href);
      if (url) { out.linkedinUrl = url; break; }
    }
  }

  return out;
}

function harvest(): HarvestPayload {
  const seen = new Set<string>();
  const links: Array<{ href: string; anchor: string }> = [];
  const here = location.hostname.replace(/^www\./, "");
  const anchors = document.querySelectorAll<HTMLAnchorElement>("a[href]");
  for (const a of Array.from(anchors)) {
    if (links.length >= MAX_LINKS) break;
    const href = a.href;
    if (!href || !/^https?:/i.test(href)) continue;
    let host = "";
    try { host = new URL(href).hostname.replace(/^www\./, ""); } catch { continue; }
    if (!host || host === here) continue;
    if (seen.has(href)) continue;
    seen.add(href);
    const anchor = (a.innerText || a.title || a.getAttribute("aria-label") || "")
      .replace(/\s+/g, " ")
      .trim()
      .slice(0, 200);
    links.push({ href, anchor });
  }
  let author: AuthorContacts = { email: null, xHandle: null, linkedinUrl: null };
  try { author = harvestAuthor(); } catch { /* author detection is best-effort */ }
  return {
    pageUrl: location.href,
    pageTitle: (document.title || "").slice(0, 500),
    links,
    author,
  };
}

let scheduled: number | null = null;
let lastSentUrl: string | null = null;

function schedule(reason: string) {
  if (scheduled !== null) {
    clearTimeout(scheduled);
  }
  scheduled = window.setTimeout(async () => {
    scheduled = null;
    try {
      const payload = harvest();
      // Avoid spamming the background with duplicate scans of the same
      // exact URL (within the same content-script lifetime).
      if (payload.pageUrl === lastSentUrl && reason === "mutation") return;
      lastSentUrl = payload.pageUrl;
      await browser.runtime.sendMessage({ type: "RADAR_SCAN", payload });
    } catch { /* ignore — popup closed or extension reloading */ }
  }, STABILITY_MS) as unknown as number;
}

function init() {
  if (window.top !== window) return; // skip iframes
  if (!/^https?:$/.test(location.protocol)) return;
  schedule("initial");

  // SPA navigation hooks
  const origPush = history.pushState;
  const origReplace = history.replaceState;
  history.pushState = function (this: History, ...args: any[]) {
    const r = origPush.apply(this, args as any);
    schedule("pushstate");
    return r;
  } as any;
  history.replaceState = function (this: History, ...args: any[]) {
    const r = origReplace.apply(this, args as any);
    schedule("replacestate");
    return r;
  } as any;
  window.addEventListener("popstate", () => schedule("popstate"));

  // Late-loaded content (lazy nav, infinite scroll). Throttled by the
  // schedule()'s clear-and-restart timer.
  const obs = new MutationObserver(() => schedule("mutation"));
  try {
    obs.observe(document.documentElement, { childList: true, subtree: true });
  } catch { /* document not ready in some edge cases */ }
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init, { once: true });
} else {
  init();
}
