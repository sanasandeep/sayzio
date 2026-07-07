import { useEffect } from "react";
import { useLocation } from "wouter";
import { SITE_URL } from "@/config";

interface SEOProps {
  title: string;
  description: string;
  /** Absolute or root-relative image URL for Open Graph/Twitter cards. */
  image?: string;
}

const DEFAULT_OG_IMAGE = "/opengraph.jpg";

function setMeta(selector: string, attr: string, value: string) {
  let el = document.head.querySelector(selector);
  if (!el) {
    el = document.createElement("meta");
    const attrName = selector.includes("property=") ? "property" : "name";
    const match = selector.match(/["']([^"']+)["']/);
    if (match) el.setAttribute(attrName, match[1]);
    document.head.appendChild(el);
  }
  el.setAttribute(attr, value);
}

function resolveAbsoluteUrl(pathOrUrl: string): string {
  if (/^https?:\/\//i.test(pathOrUrl)) return pathOrUrl;
  const base = import.meta.env.BASE_URL.replace(/\/$/, "");
  return `${SITE_URL}${base}${pathOrUrl}`;
}

/**
 * Sets document title/description/canonical/Open Graph/Twitter tags on
 * client-side navigation. This keeps tags correct when a user navigates
 * between routes without a full page load (hydration case).
 *
 * The FIRST response for each route already has correct tags baked in by
 * `scripts/prerender.mjs` at build time — this component only needs to keep
 * things in sync afterwards, so crawlers that don't execute JS still see the
 * right metadata immediately.
 */
export function SEO({ title, description, image = DEFAULT_OG_IMAGE }: SEOProps) {
  const [pathname] = useLocation();

  useEffect(() => {
    const fullTitle = `${title} | Sayzio`;
    document.title = fullTitle;

    setMeta('meta[name="description"]', "content", description);
    setMeta('meta[property="og:title"]', "content", fullTitle);
    setMeta('meta[property="og:description"]', "content", description);
    setMeta('meta[property="og:image"]', "content", resolveAbsoluteUrl(image));

    const base = import.meta.env.BASE_URL.replace(/\/$/, "");
    const canonicalUrl = `${SITE_URL}${base}${pathname === "/" ? "" : pathname}`;
    setMeta('meta[property="og:url"]', "content", canonicalUrl);
    setMeta('meta[name="twitter:title"]', "content", fullTitle);
    setMeta('meta[name="twitter:description"]', "content", description);
    setMeta('meta[name="twitter:image"]', "content", resolveAbsoluteUrl(image));

    let canonical = document.head.querySelector('link[rel="canonical"]');
    if (!canonical) {
      canonical = document.createElement("link");
      canonical.setAttribute("rel", "canonical");
      document.head.appendChild(canonical);
    }
    canonical.setAttribute("href", canonicalUrl);
  }, [title, description, image, pathname]);

  return null;
}
