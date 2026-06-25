import { getBaseUrl } from "@/lib/api";

import type { EefindBlock, InfoSection } from "@/components/InfoPage";

/**
 * Admin-editable /about marketing copy, read at runtime from the product
 * app's public `/api/v1/site/about` endpoint. This keeps the mobile About
 * screen's EEFind parent-company block (and the narrative sections) in sync
 * with the web /about page — when an admin edits the copy, stats, address or
 * contact details on the web, the same content flows to mobile with no app
 * rebuild. The caller falls back to the bundled static copy when the fetch
 * fails or returns nothing (offline / endpoint unavailable).
 */
export interface AboutContent {
  title: string;
  sections: InfoSection[];
  eefind: EefindBlock;
}

interface AboutStatRaw {
  value?: string;
  suffix?: string;
  label?: string;
}

interface AboutEefindRaw {
  eyebrow?: string;
  heading?: string;
  body?: string;
  stats?: AboutStatRaw[];
  address?: string;
  email?: string;
  whatsapp?: string;
  website?: string;
  website_url?: string;
}

interface AboutResponse {
  data?: {
    title?: string;
    sections?: { heading?: string; body?: string }[];
    eefind?: AboutEefindRaw;
  };
}

let cache: AboutContent | null = null;
let inflight: Promise<AboutContent | null> | null = null;

/**
 * Format a stat value the way the web count-up renders it: a numeric value
 * gets thousands separators (e.g. "4000" → "4,000") and the suffix appended
 * (e.g. "+"), so "4,000+" matches the web. Non-numeric values pass through.
 */
function formatStatValue(value: string, suffix: string): string {
  const trimmed = (value ?? "").trim();
  const num = Number(trimmed.replace(/,/g, ""));
  const base =
    trimmed !== "" && Number.isFinite(num) && /^[0-9,]+$/.test(trimmed)
      ? num.toLocaleString("en-US")
      : trimmed;
  return `${base}${(suffix ?? "").trim()}`;
}

export async function fetchAboutContent(): Promise<AboutContent | null> {
  if (cache) return cache;
  if (inflight) return inflight;

  inflight = (async () => {
    try {
      const res = await fetch(`${getBaseUrl()}/api/v1/site/about`, {
        headers: { Accept: "application/json" },
      });
      if (!res.ok) return null;
      const json = (await res.json()) as AboutResponse;
      const data = json?.data;
      if (!data) return null;

      const sections: InfoSection[] = Array.isArray(data.sections)
        ? data.sections
            .map((s) => ({
              heading: (s?.heading ?? "").trim() || undefined,
              body: (s?.body ?? "").trim(),
            }))
            .filter((s) => s.body !== "")
        : [];

      const ee = data.eefind;
      if (!ee) return null;

      const eefind: EefindBlock = {
        eyebrow: (ee.eyebrow ?? "").trim(),
        heading: (ee.heading ?? "").trim(),
        body: (ee.body ?? "").trim(),
        stats: Array.isArray(ee.stats)
          ? ee.stats
              .map((st) => ({
                value: formatStatValue(st?.value ?? "", st?.suffix ?? ""),
                label: (st?.label ?? "").trim(),
              }))
              .filter((st) => st.value !== "" || st.label !== "")
          : [],
        address: (ee.address ?? "").trim(),
        email: (ee.email ?? "").trim(),
        whatsapp: (ee.whatsapp ?? "").trim(),
        website: (ee.website ?? "").trim(),
        websiteUrl: (ee.website_url ?? "").trim(),
      };

      // A payload with no usable EEFind heading/body isn't worth swapping in;
      // let the caller keep its static fallback.
      if (eefind.heading === "" && eefind.body === "") return null;

      const content: AboutContent = {
        title: (data.title ?? "").trim() || "About Sayzio",
        sections,
        eefind,
      };
      cache = content;
      return content;
    } catch {
      return null;
    } finally {
      inflight = null;
    }
  })();

  return inflight;
}
