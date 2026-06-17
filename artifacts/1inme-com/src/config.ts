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
