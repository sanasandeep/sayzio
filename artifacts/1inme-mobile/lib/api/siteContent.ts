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

/**
 * Admin-editable /contact card details, read at runtime from the product
 * app's public `/api/v1/site/contact` endpoint. Mirrors the web /contact
 * page's "Contact details" card (address, support email, phone, business
 * hours, social links, map) so the mobile Contact screen stays in sync when
 * an admin edits the copy — no app rebuild. The caller renders nothing extra
 * when the fetch fails or returns nothing (offline / endpoint unavailable).
 */
export interface ContactSocial {
  twitter: string;
  instagram: string;
  linkedin: string;
  youtube: string;
  facebook: string;
}

export interface ContactMap {
  lat: number;
  lng: number;
  zoom: number;
  label: string;
}

export interface ContactContent {
  title: string;
  address: string;
  email: string;
  phone: string;
  hours: string;
  social: ContactSocial;
  map: ContactMap;
}

interface ContactResponse {
  data?: {
    title?: string;
    address?: string;
    email?: string;
    phone?: string;
    hours?: string;
    social?: Partial<ContactSocial>;
    map?: Partial<ContactMap>;
  };
}

let contactCache: ContactContent | null = null;
let contactInflight: Promise<ContactContent | null> | null = null;

export async function fetchContactContent(): Promise<ContactContent | null> {
  if (contactCache) return contactCache;
  if (contactInflight) return contactInflight;

  contactInflight = (async () => {
    try {
      const res = await fetch(`${getBaseUrl()}/api/v1/site/contact`, {
        headers: { Accept: "application/json" },
      });
      if (!res.ok) return null;
      const json = (await res.json()) as ContactResponse;
      const data = json?.data;
      if (!data) return null;

      const s = data.social ?? {};
      const m = data.map ?? {};

      const content: ContactContent = {
        title: (data.title ?? "").trim() || "Contact us",
        address: (data.address ?? "").trim(),
        email: (data.email ?? "").trim(),
        phone: (data.phone ?? "").trim(),
        hours: (data.hours ?? "").trim(),
        social: {
          twitter: (s.twitter ?? "").trim(),
          instagram: (s.instagram ?? "").trim(),
          linkedin: (s.linkedin ?? "").trim(),
          youtube: (s.youtube ?? "").trim(),
          facebook: (s.facebook ?? "").trim(),
        },
        map: {
          lat: Number.isFinite(Number(m.lat)) ? Number(m.lat) : 17.385,
          lng: Number.isFinite(Number(m.lng)) ? Number(m.lng) : 78.4867,
          zoom: Number.isFinite(Number(m.zoom)) ? Number(m.zoom) : 12,
          label: (m.label ?? "").trim(),
        },
      };

      // A payload with no usable details isn't worth swapping in; let the
      // caller keep the screen free of an empty card.
      const hasDetail =
        content.address !== "" ||
        content.email !== "" ||
        content.phone !== "" ||
        content.hours !== "" ||
        Object.values(content.social).some((u) => u !== "");
      if (!hasDetail) return null;

      contactCache = content;
      return content;
    } catch {
      return null;
    } finally {
      contactInflight = null;
    }
  })();

  return contactInflight;
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
