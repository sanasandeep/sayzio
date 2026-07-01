import { LOGIN_URL } from "@/config";

/**
 * The marketing Contact page reads its contact details (email, address, phone,
 * hours, social links) from the product app's admin-editable /contact page via
 * a public JSON endpoint, so an admin edit flows through to the marketing site
 * without a redeploy.
 *
 * Base origin of the product app that serves the contact feed. Defaults to the
 * origin of the login URL (https://1in.me) and can be overridden at build time
 * with VITE_CONTACT_API_BASE (handy for local dev / staging). Mirrors
 * resolveBlogApiBase() in blog-posts.ts.
 */
function resolveContactApiBase(): string {
  const override = import.meta.env.VITE_CONTACT_API_BASE as string | undefined;
  if (override && override.trim() !== "") {
    return override.replace(/\/$/, "");
  }
  try {
    return new URL(LOGIN_URL).origin;
  } catch {
    return "https://1in.me";
  }
}

export const CONTACT_API_BASE = resolveContactApiBase();

export interface ContactSocial {
  twitter: string;
  instagram: string;
  linkedin: string;
  youtube: string;
  facebook: string;
}

export interface ContactContent {
  email: string;
  phone: string;
  address: string;
  hours: string;
  social: ContactSocial;
}

interface ContactResponse {
  data: Partial<ContactContent> & { social?: Partial<ContactSocial> };
}

/**
 * Correct brand defaults for the entity behind Sayzio — EEFind Private Limited,
 * Banjara Hills, hello@sayzio.app, and deliberately NO phone number. Used when
 * the API is unreachable so a fetch failure can never surface stale/placeholder
 * contact info (support@1inme.com, a fake phone, the wrong city) to visitors.
 * These MUST stay in sync with SitePagesContent::contactExtraDefault() on the
 * product app.
 */
export const DEFAULT_CONTACT_CONTENT: ContactContent = {
  email: "hello@sayzio.app",
  phone: "",
  address:
    "EEFind Private Limited\n8 Amrutha Nilayam, Banjara Hills\nHyderabad, Telangana 500034, India",
  hours: "Mon–Fri · 10:00 – 18:00 IST\nClosed on public holidays",
  social: {
    twitter: "",
    instagram: "",
    linkedin: "",
    youtube: "",
    facebook: "",
  },
};

/**
 * Fetch the admin-editable contact details from the product app. Any missing
 * field falls back to the correct brand default, so a partial or malformed
 * payload can never blank out a field. Throws on a non-OK response so the
 * caller can fall back to DEFAULT_CONTACT_CONTENT wholesale.
 */
export async function fetchContactContent(
  signal?: AbortSignal,
): Promise<ContactContent> {
  const res = await fetch(`${CONTACT_API_BASE}/api/v1/site/contact`, {
    signal,
    headers: { Accept: "application/json" },
  });
  if (!res.ok) {
    throw new Error(`Failed to load contact details (${res.status})`);
  }
  const json = (await res.json()) as ContactResponse;
  const data: Partial<ContactContent> & { social?: Partial<ContactSocial> } =
    json.data ?? {};
  const social: Partial<ContactSocial> = data.social ?? {};

  const clean = (value: unknown, fallback: string): string => {
    const str = typeof value === "string" ? value.trim() : "";
    return str !== "" ? str : fallback;
  };

  return {
    // Email must always resolve to a real inbox — never blank.
    email: clean(data.email, DEFAULT_CONTACT_CONTENT.email),
    // Phone is intentionally allowed to be empty (no fake number). Only a
    // non-blank server value overrides the empty default.
    phone: typeof data.phone === "string" ? data.phone.trim() : DEFAULT_CONTACT_CONTENT.phone,
    address: clean(data.address, DEFAULT_CONTACT_CONTENT.address),
    hours: typeof data.hours === "string" ? data.hours.trim() : DEFAULT_CONTACT_CONTENT.hours,
    social: {
      twitter: typeof social.twitter === "string" ? social.twitter.trim() : "",
      instagram: typeof social.instagram === "string" ? social.instagram.trim() : "",
      linkedin: typeof social.linkedin === "string" ? social.linkedin.trim() : "",
      youtube: typeof social.youtube === "string" ? social.youtube.trim() : "",
      facebook: typeof social.facebook === "string" ? social.facebook.trim() : "",
    },
  };
}
