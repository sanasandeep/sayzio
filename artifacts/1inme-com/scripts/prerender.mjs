#!/usr/bin/env node
/*
 * Prerenders route-specific `<head>` metadata (title, description, canonical,
 * Open Graph, Twitter) into a static index.html per public marketing route,
 * plus generates sitemap.xml + patches robots.txt with a Sitemap directive.
 *
 * Why: the marketing site is a plain Vite SPA (no SSR). Without this step,
 * every route ships the SAME homepage <head>, so social/AI crawlers (which
 * don't execute JS) and the first pass of search crawlers see the wrong
 * title/description/OG tags for every deep page (blog posts, /compare/*,
 * /for/*, /ai/*, etc). This script runs after `vite build` (see the
 * `postbuild` npm script) and writes one `<route>/index.html` per route into
 * the build output, each with the correct <head> baked in, while keeping the
 * same body/scripts so the SPA still hydrates and takes over navigation.
 *
 * Route inventory: `src/content/seo-routes.ts` (static pages + use
 * cases/AI products/competitors derived from their own content files) plus
 * blog posts, fetched live from the database-driven blog feed. If the blog
 * feed is unreachable at build time (e.g. offline build), blog pages are
 * skipped with a warning rather than failing the whole build.
 *
 * Usage: node scripts/prerender.mjs   (run after `vite build`)
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import path from "node:path";
import {
  getKnownSeoRoutes,
  STATIC_ROUTES,
} from "../src/content/seo-routes.ts";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(__dirname, "..");
const distPublic = path.resolve(root, "dist/public");
const templatePath = path.join(distPublic, "index.html");

const SITE_URL = (process.env.VITE_SITE_URL ?? "https://sayzio.com").replace(/\/$/, "");
const BASE_PATH = (process.env.BASE_PATH ?? "/").replace(/\/$/, "");

/*
 * Blog feed base candidates, tried in order until one responds. The Laravel
 * app serves the SAME database-driven feed on every host it's reachable at
 * (brand domains in production, the Replit workspace domain in dev — the
 * proxy routes "/" to the Laravel artifact), so any successful candidate is
 * equivalent. An explicit VITE_BLOG_API_BASE always goes first; then the
 * canonical brand domains (sayzio.app primary, 1in.me secondary — keep in
 * lockstep with the Laravel app's PlatformHosts::PLATFORM_DOMAINS); then
 * whatever domains the Replit env advertises, which covers deploy builds
 * before a custom domain is attached (previous deployment still serving on
 * *.replit.app) and dev builds (workspace preview domain).
 */
const BLOG_API_BASES = [
  process.env.VITE_BLOG_API_BASE,
  "https://sayzio.app",
  "https://1in.me",
  ...(process.env.REPLIT_DOMAINS ?? "")
    .split(",")
    .map((d) => d.trim())
    .filter(Boolean)
    .map((d) => `https://${d}`),
  process.env.REPLIT_DEV_DOMAIN ? `https://${process.env.REPLIT_DEV_DOMAIN}` : undefined,
]
  .filter(Boolean)
  .map((base) => base.replace(/\/$/, ""))
  .filter((base, i, all) => all.indexOf(base) === i);
const SITE_NAME = "Sayzio";
const DEFAULT_OG_IMAGE = "/opengraph.jpg";

function absoluteUrl(routePath) {
  const suffix = routePath === "/" ? "" : routePath;
  return `${SITE_URL}${BASE_PATH}${suffix}`;
}

function absoluteAsset(assetPath) {
  return `${SITE_URL}${BASE_PATH}${assetPath}`;
}

async function fetchBlogFeedFrom(base) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 8000);
  try {
    const res = await fetch(`${base}/blogs/feed.json`, {
      signal: controller.signal,
      headers: { Accept: "application/json" },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();
    if (!Array.isArray(json?.data)) throw new Error("unexpected feed shape (no data array)");
    return json.data;
  } finally {
    clearTimeout(timeout);
  }
}

async function fetchBlogRoutes() {
  let posts = null;
  for (const base of BLOG_API_BASES) {
    try {
      posts = await fetchBlogFeedFrom(base);
      console.log(`[prerender] Blog feed loaded from ${base}/blogs/feed.json (${posts.length} post(s)).`);
      break;
    } catch (err) {
      console.warn(
        `[prerender] Blog feed unreachable at ${base}/blogs/feed.json (${err.message}) — trying next candidate.`,
      );
    }
  }
  if (posts === null) {
    console.warn(
      `[prerender] Skipping blog post prerendering — no blog feed candidate responded (tried: ${BLOG_API_BASES.join(", ")}).`,
    );
    return [];
  }
  return posts.map((post) => ({
    path: `/blog/${post.slug}`,
    title: post.metaTitle ?? post.title,
    description: post.metaDescription ?? post.excerpt ?? "",
    priority: 0.6,
    changeFrequency: "monthly",
    image: post.coverImage ?? undefined,
  }));
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function renderHead(template, route) {
  const fullTitle = escapeHtml(`${route.title} | ${SITE_NAME}`);
  const description = escapeHtml(route.description ?? "");
  const canonicalUrl = absoluteUrl(route.path);
  const image =
    route.image && /^https?:\/\//i.test(route.image)
      ? route.image
      : absoluteAsset(route.image ?? DEFAULT_OG_IMAGE);

  let html = template;
  html = html.replace(/<title>.*?<\/title>/, `<title>${fullTitle}</title>`);
  html = html.replace(
    /<meta name="description" content=".*?"\s*\/?>/,
    `<meta name="description" content="${description}" />`,
  );
  html = html.replace(
    /<link rel="canonical" href=".*?"\s*\/?>/,
    `<link rel="canonical" href="${canonicalUrl}" />`,
  );
  html = html.replace(
    /<meta property="og:title" content=".*?"\s*\/?>/,
    `<meta property="og:title" content="${fullTitle}" />`,
  );
  html = html.replace(
    /<meta property="og:description" content=".*?"\s*\/?>/,
    `<meta property="og:description" content="${description}" />`,
  );
  html = html.replace(
    /<meta property="og:url" content=".*?"\s*\/?>/,
    `<meta property="og:url" content="${canonicalUrl}" />`,
  );
  html = html.replace(
    /<meta property="og:image" content=".*?"\s*\/?>/,
    `<meta property="og:image" content="${image}" />`,
  );
  html = html.replace(
    /<meta name="twitter:title" content=".*?"\s*\/?>/,
    `<meta name="twitter:title" content="${fullTitle}" />`,
  );
  html = html.replace(
    /<meta name="twitter:description" content=".*?"\s*\/?>/,
    `<meta name="twitter:description" content="${description}" />`,
  );
  html = html.replace(
    /<meta name="twitter:image" content=".*?"\s*\/?>/,
    `<meta name="twitter:image" content="${image}" />`,
  );
  // Root-relative "./"-prefixed asset hrefs (favicons, manifest) only resolve
  // correctly from the document root. Nested route files (e.g.
  // /blog/my-post/index.html) need them rewritten to the real absolute path.
  const assetPrefix = BASE_PATH === "" ? "" : BASE_PATH;
  html = html.replace(/(href|src)="\.\//g, `$1="${assetPrefix}/`);
  return html;
}

function writeRoute(template, route) {
  const outDir =
    route.path === "/" ? distPublic : path.join(distPublic, route.path.replace(/^\//, ""));
  mkdirSync(outDir, { recursive: true });
  const outFile = path.join(outDir, "index.html");
  writeFileSync(outFile, renderHead(template, route));
}

function buildSitemap(routes) {
  const urls = routes
    .map((route) => {
      const loc = absoluteUrl(route.path);
      const changefreq = route.changeFrequency ? `\n    <changefreq>${route.changeFrequency}</changefreq>` : "";
      const priority = route.priority !== undefined ? `\n    <priority>${route.priority.toFixed(1)}</priority>` : "";
      return `  <url>\n    <loc>${loc}</loc>${changefreq}${priority}\n  </url>`;
    })
    .join("\n");
  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls}\n</urlset>\n`;
}

async function main() {
  if (!existsSync(templatePath)) {
    console.error(
      `[prerender] ${templatePath} not found — run "vite build" before this script.`,
    );
    process.exit(1);
  }
  const template = readFileSync(templatePath, "utf8");

  const blogRoutes = await fetchBlogRoutes();
  const allRoutes = [...getKnownSeoRoutes(), ...blogRoutes];

  for (const route of allRoutes) {
    writeRoute(template, route);
  }

  const sitemap = buildSitemap(allRoutes);
  const sitemapUrl = `${SITE_URL}${BASE_PATH}/sitemap.xml`;
  writeFileSync(path.join(distPublic, "sitemap.xml"), sitemap);

  // public/robots.txt (copied verbatim into dist/public by Vite) points the
  // Sitemap directive at the site root, which is correct once this artifact
  // is deployed at its own custom domain root. If this build ran with a
  // non-root BASE_PATH (e.g. this workspace's shared multi-artifact preview
  // domain), rewrite the copied robots.txt so the Sitemap line still points
  // at the sitemap this same build actually produced.
  const robotsPath = path.join(distPublic, "robots.txt");
  if (BASE_PATH !== "" && existsSync(robotsPath)) {
    const robots = readFileSync(robotsPath, "utf8");
    writeFileSync(robotsPath, robots.replace(/^Sitemap:.*$/m, `Sitemap: ${sitemapUrl}`));
  }

  console.log(
    `[prerender] Wrote ${allRoutes.length} prerendered route(s) (${STATIC_ROUTES.length} static + ${allRoutes.length - STATIC_ROUTES.length} dynamic, including ${blogRoutes.length} blog post(s)) + sitemap.xml`,
  );
}

main();
