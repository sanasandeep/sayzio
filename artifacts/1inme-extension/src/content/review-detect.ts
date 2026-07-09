/**
 * content-review-detect.ts — Google Maps / Trustpilot business detector.
 *
 * Injected on demand (executeScript) when the user opens the popup on a
 * business-review page. Returns a `ReviewCandidate | null` via the
 * script result channel so the popup can pre-fill the "Capture reviews"
 * panel without any message-bus boilerplate.
 *
 * Detects:
 *  • Google Maps business pages  (maps.google.com or google.com/maps)
 *  • Google Business profiles    (business.google.com)
 *  • Trustpilot business pages   (trustpilot.com/review/*)
 */

export interface ReviewCandidate {
  provider: "google" | "trustpilot";
  externalRef: string;
  name: string | null;
  logoUrl: string | null;
}

function metaContent(selector: string): string | null {
  return document.querySelector<HTMLMetaElement>(selector)?.content ?? null;
}

// ── Google Maps ──────────────────────────────────────────────────────
// The place_id appears in the URL query-string (?place_id=…) or as
// data-cid in certain elements. We try both paths, plus a JSON-LD scan.

function googlePlaceId(): string | null {
  try {
    const u = new URL(window.location.href);
    const placeId = u.searchParams.get("place_id");
    if (placeId) return placeId;

    // Maps URLs often embed the CID (different format) or place id in the path.
    // e.g. /maps/place/.../.../1s0x...%3A0x...
    const m = window.location.pathname.match(/\/place\/[^/]+\/[^/]+\/1s(0x[\da-f:]+)/i);
    if (m) return m[1];

    // data-cid on the hover card
    const cid = document.querySelector("[data-cid]")?.getAttribute("data-cid");
    if (cid) return cid;
  } catch { /* ignore */ }
  return null;
}

function detectGoogle(): ReviewCandidate | null {
  const host = window.location.hostname.replace(/^www\./, "");
  const isGoogleMaps =
    (host === "google.com" || host.endsWith(".google.com")) &&
    (window.location.pathname.startsWith("/maps/place") ||
      window.location.pathname.startsWith("/maps/search"));
  const isBusiness = host === "business.google.com";
  if (!isGoogleMaps && !isBusiness) return null;

  const ref = googlePlaceId();
  if (!ref) return null;

  const name =
    (document.querySelector('h1[data-attrid="title"]') as HTMLElement)?.innerText?.trim() ??
    (document.querySelector(".DUwDvf") as HTMLElement)?.innerText?.trim() ??
    metaContent("og:title") ??
    document.title;

  const logo = metaContent("og:image");

  return { provider: "google", externalRef: ref, name: name || null, logoUrl: logo };
}

// ── Trustpilot ───────────────────────────────────────────────────────
// URL pattern: trustpilot.com/review/<businessId>
// The businessId is typically the domain string (e.g. "example.com").

function detectTrustpilot(): ReviewCandidate | null {
  const host = window.location.hostname.replace(/^www\./, "");
  if (host !== "trustpilot.com") return null;

  const m = window.location.pathname.match(/^\/review\/([^/?#]+)/);
  if (!m) return null;

  const externalRef = decodeURIComponent(m[1]);
  const name =
    metaContent("og:title") ??
    (document.querySelector('h1[data-business-unit-title]') as HTMLElement)?.innerText?.trim() ??
    document.title;

  const logo = metaContent("og:image");

  return { provider: "trustpilot", externalRef, name: name || null, logoUrl: logo };
}

// ── Entry point ──────────────────────────────────────────────────────
const result: ReviewCandidate | null = detectGoogle() ?? detectTrustpilot() ?? null;
result;
