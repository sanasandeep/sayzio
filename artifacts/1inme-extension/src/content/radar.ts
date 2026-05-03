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

import { browser } from "../lib/browser";

interface HarvestPayload {
  pageUrl: string;
  pageTitle: string;
  links: Array<{ href: string; anchor: string }>;
}

const MAX_LINKS = 1500;
const STABILITY_MS = 600;

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
  return { pageUrl: location.href, pageTitle: (document.title || "").slice(0, 500), links };
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
