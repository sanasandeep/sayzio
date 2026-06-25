/**
 * Single source of truth for the destination product-app URLs.
 *
 * The marketing site (1inme.com) has no auth or billing of its own — every
 * "Log in" / "Sign up" / "Get started" / "Upgrade" / pricing CTA must send
 * the visitor to the real product app at 1in.me.
 *
 * To change where CTAs point, edit the defaults below or set the
 * VITE_LOGIN_URL / VITE_SIGNUP_URL / VITE_PRICING_URL environment
 * variables at build time.
 */

const DEFAULT_LOGIN_URL = "https://1in.me/login";
const DEFAULT_PRICING_URL = "https://1in.me/pricing";

export const LOGIN_URL: string =
  import.meta.env.VITE_LOGIN_URL ?? DEFAULT_LOGIN_URL;

export const SIGNUP_URL: string =
  import.meta.env.VITE_SIGNUP_URL ?? LOGIN_URL;

/**
 * Public pricing page on the real product app. Every pricing / plan /
 * "Get started" / "Upgrade" pay CTA on the marketing site points here.
 */
export const PRICING_URL: string =
  import.meta.env.VITE_PRICING_URL ?? DEFAULT_PRICING_URL;

/**
 * Official Sayzio social media profiles, surfaced in the marketing footer.
 * `icon` names the lucide-react component to render; "threads" has no lucide
 * icon, so the footer renders an inline brand SVG for it.
 */
export interface SocialLink {
  label: string;
  handle: string;
  url: string;
  icon: "instagram" | "threads" | "x" | "facebook" | "youtube" | "linkedin";
}

export const SOCIAL_LINKS: SocialLink[] = [
  {
    label: "Instagram",
    handle: "@1in.me",
    url: "https://instagram.com/1in.me",
    icon: "instagram",
  },
  {
    label: "Threads",
    handle: "@1in.me",
    url: "https://www.threads.net/@1in.me",
    icon: "threads",
  },
  {
    label: "X",
    handle: "@1INMEOfficial",
    url: "https://x.com/1INMEOfficial",
    icon: "x",
  },
  {
    label: "Facebook",
    handle: "@1INMEOfficial",
    url: "https://facebook.com/1INMEOfficial",
    icon: "facebook",
  },
  {
    label: "YouTube",
    handle: "@1INMEOfficial",
    url: "https://youtube.com/@1INMEOfficial",
    icon: "youtube",
  },
  {
    label: "LinkedIn",
    handle: "/company/1INMEOfficial",
    url: "https://linkedin.com/company/1INMEOfficial",
    icon: "linkedin",
  },
];
