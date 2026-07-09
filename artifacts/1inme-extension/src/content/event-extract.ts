/**
 * content-event-extract.ts — structured event data extractor.
 *
 * Injected on demand (executeScript) when the user triggers
 * "Add to calendar" from the popup. Reads JSON-LD `Event` objects and
 * HTML microdata — identical to what Google Calendar's import looks for.
 *
 * Returns a plain `EventCandidate | null` via the script result channel
 * (no message bus needed).
 */

export interface EventCandidate {
  title: string;
  description: string | null;
  location: string | null;
  startDate: string | null;
  endDate: string | null;
  url: string;
  imageUrl: string | null;
  source: "json-ld" | "microdata" | "og" | "title";
}

// ── JSON-LD ──────────────────────────────────────────────────────────

function parseDateStr(raw: unknown): string | null {
  if (!raw || typeof raw !== "string") return null;
  // Try to get an ISO string; if it already is one, return as-is.
  try {
    const d = new Date(raw);
    if (isNaN(d.getTime())) return null;
    return d.toISOString();
  } catch {
    return null;
  }
}

function firstFromLdValue(v: unknown): string | null {
  if (typeof v === "string") return v;
  if (Array.isArray(v)) return firstFromLdValue(v[0]);
  if (v && typeof v === "object") {
    const o = v as Record<string, unknown>;
    return firstFromLdValue(o["@value"] ?? o.name ?? o.description ?? null);
  }
  return null;
}

function extractFromJsonLd(): EventCandidate | null {
  const scripts = document.querySelectorAll<HTMLScriptElement>('script[type="application/ld+json"]');
  for (const script of Array.from(scripts)) {
    let data: unknown;
    try { data = JSON.parse(script.textContent || ""); } catch { continue; }
    const candidates: unknown[] = [];
    if (Array.isArray(data)) {
      candidates.push(...data);
    } else if (data && typeof data === "object") {
      const d = data as Record<string, unknown>;
      if (d["@graph"] && Array.isArray(d["@graph"])) candidates.push(...d["@graph"]);
      else candidates.push(data);
    }
    for (const item of candidates) {
      if (!item || typeof item !== "object") continue;
      const o = item as Record<string, unknown>;
      const type = o["@type"];
      const typeStr = Array.isArray(type) ? type[0] : type;
      if (typeof typeStr !== "string") continue;
      if (typeStr !== "Event" && !typeStr.endsWith("Event")) continue;

      const title = firstFromLdValue(o.name) ?? firstFromLdValue(o.headline);
      if (!title) continue;

      let location: string | null = null;
      if (o.location) {
        const loc = o.location as Record<string, unknown>;
        location = firstFromLdValue(loc.name ?? loc.address) ??
          (typeof o.location === "string" ? o.location : null);
      }

      const imageUrl = firstFromLdValue(
        Array.isArray(o.image) ? o.image[0] : o.image,
      );

      return {
        title,
        description: firstFromLdValue(o.description),
        location,
        startDate: parseDateStr(o.startDate),
        endDate: parseDateStr(o.endDate),
        url: firstFromLdValue(o.url) ?? location ?? window.location.href,
        imageUrl: imageUrl ?? null,
        source: "json-ld",
      };
    }
  }
  return null;
}

// ── Microdata ────────────────────────────────────────────────────────

function propValue(scope: Element, name: string): string | null {
  const el = scope.querySelector(`[itemprop="${name}"]`);
  if (!el) return null;
  if (el.tagName === "META") return el.getAttribute("content");
  if (el.tagName === "TIME") return el.getAttribute("datetime") ?? el.textContent?.trim() ?? null;
  if (el.tagName === "IMG") return el.getAttribute("src");
  if (el.tagName === "A") return el.getAttribute("href");
  return el.textContent?.trim() ?? null;
}

function extractFromMicrodata(): EventCandidate | null {
  const scopes = document.querySelectorAll('[itemtype*="schema.org/Event"]');
  for (const scope of Array.from(scopes)) {
    const title = propValue(scope, "name");
    if (!title) continue;
    const rawLoc = propValue(scope, "location");
    return {
      title,
      description: propValue(scope, "description"),
      location: rawLoc,
      startDate: parseDateStr(propValue(scope, "startDate")),
      endDate: parseDateStr(propValue(scope, "endDate")),
      url: propValue(scope, "url") ?? window.location.href,
      imageUrl: propValue(scope, "image"),
      source: "microdata",
    };
  }
  return null;
}

// ── OG / meta fallback ───────────────────────────────────────────────

function metaContent(name: string): string | null {
  return (
    document.querySelector<HTMLMetaElement>(`meta[property="${name}"]`)?.content ??
    document.querySelector<HTMLMetaElement>(`meta[name="${name}"]`)?.content ??
    null
  );
}

function extractFromOg(): EventCandidate | null {
  const type = metaContent("og:type");
  if (type !== "event") return null;
  const title = metaContent("og:title") ?? document.title;
  if (!title) return null;
  return {
    title,
    description: metaContent("og:description"),
    location: null,
    startDate: null,
    endDate: null,
    url: metaContent("og:url") ?? window.location.href,
    imageUrl: metaContent("og:image"),
    source: "og",
  };
}

// ── Title fallback ───────────────────────────────────────────────────

function extractFromTitle(): EventCandidate {
  return {
    title: document.title || window.location.hostname,
    description: metaContent("description") ?? metaContent("og:description"),
    location: null,
    startDate: null,
    endDate: null,
    url: window.location.href,
    imageUrl: metaContent("og:image"),
    source: "title",
  };
}

// ── Entry point ──────────────────────────────────────────────────────

const result: EventCandidate =
  extractFromJsonLd() ??
  extractFromMicrodata() ??
  extractFromOg() ??
  extractFromTitle();

result;
