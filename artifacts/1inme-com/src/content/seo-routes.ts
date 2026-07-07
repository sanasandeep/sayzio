/**
 * Single source of truth for route-level SEO metadata on the marketing site.
 *
 * This registry drives BOTH the prerendered HTML `<head>` for each public
 * route (scripts/prerender.mjs) and `sitemap.xml` generation
 * (scripts/generate-sitemap.mjs), so titles/descriptions/paths can never
 * drift between what a crawler sees in the static HTML and what the app
 * itself renders after hydration.
 *
 * Static routes mirror the `title`/`description` (or `metaTitle`/
 * `metaDescription`) props each page already passes to `PageLayout` /
 * `LegalPage`. Dynamic routes (use cases, AI products, comparisons) are
 * derived from the same content files the pages themselves import, so
 * adding a new use case/AI product/competitor automatically gets a
 * prerendered page + sitemap entry with no extra wiring.
 *
 * Blog posts are NOT included here — they are fetched live from the
 * database-driven blog feed at build time (see scripts/prerender.mjs),
 * since that data can't be known statically.
 */
import { useCases } from "./use-cases.ts";
import { aiProducts } from "./ai-products.ts";
import { competitors } from "./compare.ts";

export interface SeoRoute {
  /** Path relative to the site root, always starting with "/". */
  path: string;
  title: string;
  description: string;
  /** Lower priority for less-central pages; defaults to 0.6. */
  priority?: number;
  /** How often the page's content is expected to change. */
  changeFrequency?: "daily" | "weekly" | "monthly" | "yearly";
}

/**
 * High-value marketing + product-education pages, in the order they should
 * be considered for crawling. Keep in sync with the routes registered in
 * `src/App.tsx`.
 */
export const STATIC_ROUTES: SeoRoute[] = [
  {
    path: "/",
    title: "The AI-first marketing toolkit",
    description:
      "Sayzio is the AI-first marketing toolkit that redefines how creators and businesses market themselves — AI pages, an AI Performance Coach, an on-brand AI assistant and more. Free forever, no card required.",
    priority: 1,
    changeFrequency: "daily",
  },
  {
    path: "/features",
    title: "Features",
    description:
      "Every tool you need to create, share, track and grow — Link in Bio pages, short links, QR codes, analytics, AI, forms, inbox, teams and more, in one platform.",
    priority: 0.9,
    changeFrequency: "weekly",
  },
  {
    path: "/pricing",
    title: "Pricing",
    description: "Plans for steady use, coins for one-off boosts — all in one place.",
    priority: 0.9,
    changeFrequency: "weekly",
  },
  {
    path: "/how-it-works",
    title: "How it works",
    description:
      "Four tiny steps from 'I have an idea' to 'share my link'. No card, no setup call, no fuss.",
    priority: 0.7,
  },
  {
    path: "/analytics",
    title: "Analytics & AI Coach",
    description:
      "Numbers that move — live visitor maps, click heatmaps, per-block CTR and an AI Performance Coach that turns data into one-tap fixes.",
    priority: 0.7,
  },
  {
    path: "/integrations",
    title: "Integrations",
    description:
      "One-click connections to every network you live on — with auto-retry, live status and notifications when something needs your attention.",
    priority: 0.6,
  },
  {
    path: "/domains",
    title: "Domains & links",
    description:
      "Branded short links, dynamic QR codes and custom domains — repointable any time, with free SSL and smart routing.",
    priority: 0.6,
  },
  {
    path: "/workspace-team",
    title: "Workspaces & teams",
    description:
      "Projects, roles, shared brand kits and an audit log — everything a team needs to ship on brand from one workspace.",
    priority: 0.6,
  },
  {
    path: "/api-docs",
    title: "Developer API",
    description:
      "Build on top of Sayzio — a clean REST API with bearer-token auth, webhooks, usage metering and full mobile parity.",
    priority: 0.6,
  },
  {
    path: "/resume-builder",
    title: "Résumé & Portfolio Builder",
    description:
      "Drag-and-drop sections, AI-polished bullet points and 20+ recruiter-tested templates — a public portfolio link and pixel-perfect PDF export.",
    priority: 0.6,
  },
  {
    path: "/discovery",
    title: "Discover Link in Bio pages",
    description:
      "Browse public Sayzio Link in Bio pages — find creators, brands and businesses sharing their work.",
    priority: 0.5,
    changeFrequency: "daily",
  },
  {
    path: "/creators-feed",
    title: "Creators feed",
    description:
      "The latest posts from creators on Sayzio — updates, drops, news and behind-the-scenes from people building in public.",
    priority: 0.5,
    changeFrequency: "daily",
  },
  {
    path: "/buzz",
    title: "Buzz — social proof widgets",
    description:
      "Build trust on your Link in Bio by showing real activity from real visitors as it happens — recent signups, purchases, live counts and reviews.",
    priority: 0.6,
  },
  {
    path: "/services",
    title: "Use cases — Sayzio for everyone",
    description:
      "Whoever you are, Sayzio is the all-in-one link, monetization and growth stack. See how creators, agencies, coaches, musicians and small businesses use it.",
    priority: 0.7,
  },
  {
    path: "/compare",
    title: "Compare Sayzio",
    description:
      "See how Sayzio stacks up against Linktree, Bitly, Beacons, Carrd, Taplink and Stan — the whole growth stack behind one link.",
    priority: 0.7,
  },
  {
    path: "/about",
    title: "About Sayzio",
    description:
      "We help creators, freelancers, agencies and small businesses turn one link into a complete online presence.",
    priority: 0.5,
  },
  {
    path: "/contact",
    title: "Contact",
    description: "We love hearing from you. Reach the Sayzio team — we reply within one business day.",
    priority: 0.4,
  },
  {
    path: "/faq",
    title: "FAQ",
    description:
      "Answers to common questions about Sayzio — getting started, Link in Bio pages, short links, QR codes, analytics, teams, billing, domains, security, integrations and more.",
    priority: 0.6,
  },
  {
    path: "/terms",
    title: "Terms & Conditions",
    description:
      "The terms governing your use of Sayzio — your account, what you can publish, billing, intellectual property, governing law and how we end the relationship.",
    priority: 0.3,
    changeFrequency: "yearly",
  },
  {
    path: "/privacy",
    title: "Privacy Policy",
    description:
      "How Sayzio collects, uses, stores and shares your data, including AI features, analytics, sub-processors and international transfers.",
    priority: 0.3,
    changeFrequency: "yearly",
  },
  {
    path: "/refunds",
    title: "Refunds Policy",
    description:
      "Sayzio's refund window, renewal handling, add-on/overage policy and how to request a refund.",
    priority: 0.3,
    changeFrequency: "yearly",
  },
  {
    path: "/gdpr",
    title: "GDPR Policy",
    description:
      "How Sayzio meets GDPR obligations — lawful bases, your rights, sub-processors, international transfers and breach notification.",
    priority: 0.3,
    changeFrequency: "yearly",
  },
  {
    path: "/cookies",
    title: "Cookie Policy",
    description:
      "The cookies Sayzio uses — strictly necessary, functional, analytics and optional marketing pixels — and how to control them.",
    priority: 0.3,
    changeFrequency: "yearly",
  },
  {
    path: "/blog",
    title: "Blog",
    description:
      "Stories, product thinking, and tips from the Sayzio team on Link in Bio pages, analytics, and growing your audience.",
    priority: 0.7,
    changeFrequency: "daily",
  },
  {
    path: "/changelog",
    title: "Changelog",
    description: "Everything new in Sayzio — features, improvements, and fixes shipped week after week.",
    priority: 0.5,
    changeFrequency: "weekly",
  },
];

/** `/for/:slug` use-case pages, one per entry in `src/content/use-cases.ts`. */
export const USE_CASE_ROUTES: SeoRoute[] = useCases.map((useCase) => ({
  path: `/for/${useCase.slug}`,
  title: useCase.title,
  description: useCase.description,
  priority: 0.6,
}));

/** `/ai/:slug` AI Suite product pages, one per entry in `src/content/ai-products.ts`. */
export const AI_PRODUCT_ROUTES: SeoRoute[] = aiProducts.map((product) => ({
  path: `/ai/${product.slug}`,
  title: `${product.title} — Sayzio AI`,
  description: product.description,
  priority: 0.6,
}));

/** `/compare/:slug` competitor comparison pages, one per entry in `src/content/compare.ts`. */
export const COMPARE_ROUTES: SeoRoute[] = competitors.map((competitor) => ({
  path: `/compare/${competitor.slug}`,
  title: `Sayzio vs ${competitor.name}`,
  description: competitor.intro,
  priority: 0.6,
}));

/**
 * All statically-knowable SEO routes (everything except blog posts, whose
 * slugs only exist in the live database and are fetched separately).
 */
export function getKnownSeoRoutes(): SeoRoute[] {
  return [...STATIC_ROUTES, ...USE_CASE_ROUTES, ...AI_PRODUCT_ROUTES, ...COMPARE_ROUTES];
}
