/**
 * Single source of truth for the destination product-app URLs.
 *
 * The marketing site (1inme.com) has no auth of its own — every
 * "Log in" / "Sign up" / "Get started" / primary CTA must send the
 * visitor to the real product app at 1in.me.
 *
 * To change where CTAs point, edit the default below or set the
 * VITE_LOGIN_URL / VITE_SIGNUP_URL environment variables at build time.
 */

const DEFAULT_LOGIN_URL = "https://1in.me/login";

export const LOGIN_URL: string =
  import.meta.env.VITE_LOGIN_URL ?? DEFAULT_LOGIN_URL;

export const SIGNUP_URL: string =
  import.meta.env.VITE_SIGNUP_URL ?? LOGIN_URL;
