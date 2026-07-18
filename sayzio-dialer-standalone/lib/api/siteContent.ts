import { getBaseUrl } from "@/lib/api";

import type {
  EefindBlock,
  FounderBlock,
  InfoSection,
} from "@/components/InfoPage";

/**
 * Admin-editable /about marketing copy, read at runtime from the product
 * app's public `/api/v1/site/about` endpoint. This keeps the mobile About
 * screen's EEFind parent-company block (and the narrative sections) in sync
 * with the web /about page — when an admin edits the copy, stats, address or
 * contact details on the web, the same content flows to mobile with no app
 * rebuild. The caller falls back to the bundled static copy when the fetch
 * fails or returns nothing (offline / endpoint unavailable).
 */
export interface AboutHeroStat {
  value: string;
  label: string;
}

export interface AboutContent {
  title: string;
  sections: InfoSection[];
  eefind: EefindBlock;
  /**
   * Admin-editable hero stat row (the animated count-up counters). Omitted when
   * the endpoint returns no usable stats, so the caller keeps its bundled
   * fallback values.
   */
  heroStats?: AboutHeroStat[];
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

interface AboutFounderRaw {
  eyebrow?: string;
  name?: string;
  role?: string;
  bio?: string;
  photo?: string;
}

interface AboutResponse {
  data?: {
    title?: string;
    sections?: { heading?: string; body?: string }[];
    founder?: AboutFounderRaw;
    hero?: { stats?: AboutStatRaw[] };
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
 * an admin edits the copy — no app rebuild. When the fetch fails or returns
 * nothing (offline / endpoint unavailable) the caller still renders the
 * correct brand details via {@see DEFAULT_CONTACT_CONTENT} (never a blank
 * card, never a fake phone).
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

/**
 * The correct brand contact details, used as the first-paint value and the
 * fallback when the endpoint is unreachable (offline / server down). Kept in
 * lockstep with the product app's {@see SitePagesContent::contactExtraDefault()}
 * so a fetch failure can
 * never blank out a field or surface a fake phone number: Sayzio is a product
 * of EEFind Private Limited (Banjara Hills, Hyderabad), the public inbox is
 * hello@sayzio.app, and there is deliberately NO phone number.
 */
export const DEFAULT_CONTACT_CONTENT: ContactContent = {
  title: "Contact us",
  address:
    "EEFind Private Limited\n8 Amrutha Nilayam, Banjara Hills\nHyderabad, Telangana 500034, India",
  email: "hello@sayzio.app",
  phone: "",
  hours: "Mon–Fri · 10:00 – 18:00 IST\nClosed on public holidays",
  social: {
    twitter: "https://x.com/1INMEOfficial",
    instagram: "https://instagram.com/1in.me",
    linkedin: "https://linkedin.com/company/1INMEOfficial",
    youtube: "",
    facebook: "",
  },
  map: {
    lat: 17.4139,
    lng: 78.4483,
    zoom: 14,
    label: "EEFind Private Limited · Banjara Hills, Hyderabad",
  },
};

let contactCache: ContactContent | null = null;
let contactInflight: Promise<ContactContent> | null = null;

/**
 * Fetch the admin-editable contact details from the product app. Always
 * resolves to a renderable {@see ContactContent}: any missing field falls back
 * to the correct brand default, and a non-OK/empty/failed request resolves to
 * {@see DEFAULT_CONTACT_CONTENT} wholesale — so the mobile Contact screen never
 * shows a blank card or a fake phone. Only successful fetches are cached, so a
 * transient failure can be retried on the next mount.
 */
export async function fetchContactContent(): Promise<ContactContent> {
  if (contactCache) return contactCache;
  if (contactInflight) return contactInflight;

  contactInflight = (async () => {
    try {
      const res = await fetch(`${getBaseUrl()}/api/v1/site/contact`, {
        headers: { Accept: "application/json" },
      });
      if (!res.ok) return DEFAULT_CONTACT_CONTENT;
      const json = (await res.json()) as ContactResponse;
      const data = json?.data;
      if (!data) return DEFAULT_CONTACT_CONTENT;

      const s = data.social ?? {};
      const m = data.map ?? {};

      // A blank server value for a field that must always resolve (title,
      // address, email, hours) falls back to the brand default; a non-blank
      // server value overrides it.
      const clean = (value: unknown, fallback: string): string => {
        const str = typeof value === "string" ? value.trim() : "";
        return str !== "" ? str : fallback;
      };

      const content: ContactContent = {
        title: clean(data.title, DEFAULT_CONTACT_CONTENT.title),
        address: clean(data.address, DEFAULT_CONTACT_CONTENT.address),
        email: clean(data.email, DEFAULT_CONTACT_CONTENT.email),
        // Phone is intentionally allowed to be empty (no fake number). Only a
        // server-provided string is honored; a missing key keeps the default.
        phone:
          typeof data.phone === "string"
            ? data.phone.trim()
            : DEFAULT_CONTACT_CONTENT.phone,
        hours: clean(data.hours, DEFAULT_CONTACT_CONTENT.hours),
        // Social links are authoritative from the server on a successful fetch:
        // an admin who clears one should see it gone, so blanks stay blank.
        social: {
          twitter: (s.twitter ?? "").trim(),
          instagram: (s.instagram ?? "").trim(),
          linkedin: (s.linkedin ?? "").trim(),
          youtube: (s.youtube ?? "").trim(),
          facebook: (s.facebook ?? "").trim(),
        },
        map: {
          lat: Number.isFinite(Number(m.lat))
            ? Number(m.lat)
            : DEFAULT_CONTACT_CONTENT.map.lat,
          lng: Number.isFinite(Number(m.lng))
            ? Number(m.lng)
            : DEFAULT_CONTACT_CONTENT.map.lng,
          zoom: Number.isFinite(Number(m.zoom))
            ? Number(m.zoom)
            : DEFAULT_CONTACT_CONTENT.map.zoom,
          label: clean(m.label, DEFAULT_CONTACT_CONTENT.map.label),
        },
      };

      contactCache = content;
      return content;
    } catch {
      return DEFAULT_CONTACT_CONTENT;
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

      const fo = data.founder;
      const founder: FounderBlock | undefined =
        fo && (fo.name ?? "").trim() !== ""
          ? {
              eyebrow: (fo.eyebrow ?? "").trim(),
              name: (fo.name ?? "").trim(),
              role: (fo.role ?? "").trim(),
              bio: (fo.bio ?? "").trim(),
              photo: (fo.photo ?? "").trim() || undefined,
            }
          : undefined;

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

      // Hero stat row (the animated count-up counters). Only kept when the
      // server sends usable rows; otherwise omitted so the caller keeps its
      // bundled fallback values.
      const heroStats = Array.isArray(data.hero?.stats)
        ? data.hero.stats
            .map((st) => ({
              value: formatStatValue(st?.value ?? "", st?.suffix ?? ""),
              label: (st?.label ?? "").trim(),
            }))
            .filter((st) => st.value !== "" || st.label !== "")
        : [];

      // A payload with no usable EEFind heading/body isn't worth swapping in;
      // let the caller keep its static fallback.
      if (eefind.heading === "" && eefind.body === "") return null;

      const content: AboutContent = {
        title: (data.title ?? "").trim() || "About Sayzio",
        sections,
        eefind,
        ...(heroStats.length > 0 ? { heroStats } : {}),
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
