export interface Competitor {
  slug: string;
  name: string;
  tagline: string;
  badge: string;
  headline: string;
  intro: string;
  ourWins: string[];
  theirWins: string[];
}

export const competitors: Competitor[] = [
  {
    slug: "linktree",
    name: "Linktree",
    tagline: "Link in Bio page",
    badge: "Half the cost",
    headline: "A Link in Bio is the start, not the finish.",
    intro:
      "Linktree nails the simple link-in-bio. But once you want analytics that act, your own audience list, short links, QR codes and AI — you're stitching on extra tools. Sayzio puts the whole growth stack behind one link.",
    ourWins: [
      "Branded short links, dynamic QR codes and the AI Performance Coach are built in",
      "Live visitor map and click heatmaps on top of click counts",
      "Followers you actually own, with digest emails and CSV export",
      "Team workspaces, roles and per-workspace billing for agencies",
    ],
    theirWins: [
      "Instantly recognised brand name with huge install base",
      "Dead-simple if all you ever need is a list of links",
    ],
  },
  {
    slug: "bitly",
    name: "Bitly",
    tagline: "Short links & QR",
    badge: "More features",
    headline: "Short links are one block of the stack.",
    intro:
      "Bitly is a great URL shortener. But a link in a bio needs more than a redirect — it needs a page, an audience, content blocks and analytics that tell you what to do next. Sayzio does the short links and everything around them.",
    ourWins: [
      "A full drag-and-drop Link in Bio page, not just a redirect",
      "Embed video, music, products and forms right on the page",
      "Followers, creators feed and built-in messaging",
      "Free forever plan with no credit card required",
    ],
    theirWins: [
      "Enterprise-grade link management at very high volume",
      "Long-established short-link brand and integrations",
    ],
  },
  {
    slug: "beacons",
    name: "Beacons",
    tagline: "Creator bio",
    badge: "Lower price",
    headline: "More inside, for less.",
    intro:
      "Beacons is built for creators — and so is Sayzio. The difference is how much is included: short links, QR studio, an AI coach, team workspaces and a native app, all on a free-forever base.",
    ourWins: [
      "Branded short links and a full QR code studio included",
      "AI Performance Coach that turns numbers into one-tap fixes",
      "Team workspaces, roles and audit logs",
      "Native mobile app for iOS and Android",
    ],
    theirWins: [
      "Creator-first media kit and store features",
      "Familiar to existing Beacons users",
    ],
  },
  {
    slug: "carrd",
    name: "Carrd",
    tagline: "One-page sites",
    badge: "Way more inside",
    headline: "A page is nice. A growth stack is better.",
    intro:
      "Carrd builds beautiful one-page sites. But it stops at the page — no link analytics that act, no followers, no QR studio, no AI. Sayzio gives you the page plus the tools to grow what it earns.",
    ourWins: [
      "Live analytics, heatmaps and the AI Performance Coach",
      "Branded short links and dynamic QR codes",
      "Own your audience with followers and digest emails",
      "Team workspaces and per-workspace billing",
    ],
    theirWins: [
      "Pixel-level control over a custom one-page site",
      "Very low cost for a simple static page",
    ],
  },
  {
    slug: "taplink",
    name: "Taplink",
    tagline: "Insta micro-landing",
    badge: "Bigger toolkit",
    headline: "Beyond the Instagram micro-landing.",
    intro:
      "Taplink makes a tidy Instagram landing page. Sayzio does that and adds short links, a QR studio, an AI coach, followers you own and a team-ready workspace — all under one login.",
    ourWins: [
      "Short links, QR studio and UTM builder included",
      "AI Performance Coach and live visitor map",
      "Followers, creators feed and broadcasts",
      "Team workspaces with roles and permissions",
    ],
    theirWins: [
      "Quick to set up for a single Instagram bio",
      "Simple block-based editor",
    ],
  },
  {
    slug: "stan",
    name: "Stan",
    tagline: "Creator store",
    badge: "Free forever plan",
    headline: "Sell and grow — without the monthly floor.",
    intro:
      "Stan is a creator store with a subscription floor. Sayzio lets you sell digital products and take tips too — but on a free-forever base, with short links, QR codes, analytics and an AI coach included.",
    ourWins: [
      "Free forever plan with no credit card required",
      "Branded short links, QR studio and live analytics",
      "Followers you own plus digest emails and CSV export",
      "Team workspaces for agencies and collaborators",
    ],
    theirWins: [
      "Focused, opinionated creator store flow",
      "Built-in email marketing for store customers",
    ],
  },
];

export interface CompareGroup {
  category: string;
  features: string[];
}

export const compareGroups: CompareGroup[] = [
  {
    category: "Link in Bio & pages",
    features: [
      "Drag-and-drop Link in Bio builder",
      "Multiple bio pages per account",
      "Embed video, music & forms",
      "Custom themes & fonts",
      "Premium template library",
      "Custom domains",
    ],
  },
  {
    category: "Links, QR & control",
    features: [
      "Branded short links",
      "Dynamic QR codes",
      "QR styling, logos & colors",
      "Bulk link import",
      "Password-protected links",
      "Link expiry & scheduling",
      "Geo & device targeting",
    ],
  },
  {
    category: "Analytics & tracking",
    features: [
      "Built-in click analytics",
      "Live visitor map",
      "Click heatmap",
      "UTM builder",
      "Marketing pixels (Meta, TikTok, Google)",
      "SEO & social previews",
      "Stats CSV export",
    ],
  },
  {
    category: "More page types",
    features: [
      "Lead-capture forms",
      "Paid forms",
      "Reviews pages",
      "Restaurant menu & table QR ordering",
      "Resume / portfolio pages",
      "Events & RSVPs",
      "Followable calendars (Google sync)",
    ],
  },
  {
    category: "Growth & AI",
    features: [
      "AI Performance coach",
      "Knowledge Bases, AI Agents & Chat Widgets",
      "AI Agent (multi-step automation)",
      "Site Assistant widget",
      "AI Voice Assistant",
      "Card & brochure scanner",
      "AI resume tools",
      "Scheduled posts",
      "A/B testing",
    ],
  },
  {
    category: "Monetization",
    features: ["Tip jar / donations", "Sell digital products", "Coin / wallet rewards"],
  },
  {
    category: "Branding & developer",
    features: [
      "White-label branding",
      'Remove "powered by" badge',
      "Custom HTML / JS",
      "Public API access",
    ],
  },
  {
    category: "Team & workflow",
    features: ["Team workspaces", "Direct messaging", "Roles & permissions"],
  },
  {
    category: "Plans & access",
    features: ["Free forever (no credit card)", "Native mobile app"],
  },
];

/**
 * Per-feature support matrix. `true` = supported. Sayzio ("ours") supports
 * everything; rivals support a subset. Keyed by the feature label.
 */
export const featureSupport: Record<string, Record<string, boolean>> = {
  "Drag-and-drop Link in Bio builder": { ours: true, linktree: true, bitly: false, beacons: true, carrd: true, taplink: true, stan: true },
  "Multiple bio pages per account": { ours: true, linktree: false, bitly: false, beacons: false, carrd: true, taplink: false, stan: false },
  "Embed video, music & forms": { ours: true, linktree: true, bitly: false, beacons: true, carrd: true, taplink: true, stan: true },
  "Custom themes & fonts": { ours: true, linktree: true, bitly: false, beacons: true, carrd: true, taplink: true, stan: true },
  "Custom domains": { ours: true, linktree: true, bitly: true, beacons: true, carrd: true, taplink: false, stan: false },
  "Branded short links": { ours: true, linktree: false, bitly: true, beacons: false, carrd: false, taplink: false, stan: false },
  "Dynamic QR codes": { ours: true, linktree: true, bitly: true, beacons: false, carrd: false, taplink: true, stan: false },
  "QR styling, logos & colors": { ours: true, linktree: false, bitly: true, beacons: false, carrd: false, taplink: false, stan: false },
  "Bulk link import": { ours: true, linktree: false, bitly: true, beacons: true, carrd: false, taplink: false, stan: false },
  "Built-in click analytics": { ours: true, linktree: true, bitly: true, beacons: true, carrd: false, taplink: true, stan: true },
  "Live visitor map": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "Click heatmap": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "UTM builder": { ours: true, linktree: false, bitly: true, beacons: false, carrd: false, taplink: false, stan: false },
  "AI Performance coach": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "Knowledge Bases, AI Agents & Chat Widgets": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "Site Assistant widget": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "AI Voice Assistant": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "Card & brochure scanner": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "AI resume tools": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "Scheduled posts": { ours: true, linktree: false, bitly: false, beacons: true, carrd: false, taplink: false, stan: true },
  "A/B testing": { ours: true, linktree: false, bitly: true, beacons: false, carrd: false, taplink: false, stan: false },
  "Tip jar / donations": { ours: true, linktree: true, bitly: false, beacons: true, carrd: false, taplink: true, stan: true },
  "Sell digital products": { ours: true, linktree: true, bitly: false, beacons: true, carrd: false, taplink: true, stan: true },
  "Coin / wallet rewards": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "Team workspaces": { ours: true, linktree: false, bitly: true, beacons: false, carrd: false, taplink: false, stan: false },
  "Direct messaging": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "Roles & permissions": { ours: true, linktree: false, bitly: true, beacons: false, carrd: false, taplink: false, stan: false },
  "Free forever (no credit card)": { ours: true, linktree: true, bitly: true, beacons: true, carrd: true, taplink: true, stan: false },
  "Native mobile app": { ours: true, linktree: true, bitly: true, beacons: true, carrd: false, taplink: false, stan: true },
  "Premium template library": { ours: true, linktree: true, bitly: false, beacons: true, carrd: true, taplink: true, stan: true },
  "Password-protected links": { ours: true, linktree: false, bitly: true, beacons: false, carrd: false, taplink: false, stan: false },
  "Link expiry & scheduling": { ours: true, linktree: true, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "Geo & device targeting": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "Marketing pixels (Meta, TikTok, Google)": { ours: true, linktree: true, bitly: false, beacons: true, carrd: false, taplink: true, stan: true },
  "SEO & social previews": { ours: true, linktree: true, bitly: false, beacons: true, carrd: true, taplink: true, stan: true },
  "Stats CSV export": { ours: true, linktree: true, bitly: true, beacons: true, carrd: false, taplink: true, stan: false },
  "Lead-capture forms": { ours: true, linktree: true, bitly: false, beacons: true, carrd: true, taplink: true, stan: true },
  "Paid forms": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "Reviews pages": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "Restaurant menu & table QR ordering": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "Resume / portfolio pages": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "Events & RSVPs": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "Followable calendars (Google sync)": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "AI Agent (multi-step automation)": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  "White-label branding": { ours: true, linktree: false, bitly: false, beacons: false, carrd: false, taplink: false, stan: false },
  'Remove "powered by" badge': { ours: true, linktree: true, bitly: true, beacons: true, carrd: true, taplink: true, stan: true },
  "Custom HTML / JS": { ours: true, linktree: false, bitly: false, beacons: false, carrd: true, taplink: false, stan: false },
  "Public API access": { ours: true, linktree: false, bitly: true, beacons: false, carrd: false, taplink: false, stan: false },
};

export const totalFeatures = compareGroups.reduce((n, g) => n + g.features.length, 0);

export function getCompetitor(slug: string): Competitor | undefined {
  return competitors.find((c) => c.slug === slug);
}

export const migrationSteps = [
  { title: "Create your free Sayzio", body: "Sign up with an email or phone number — no credit card, no trial clock." },
  { title: "Rebuild or import your links", body: "Recreate your page with drag-and-drop blocks, or bulk-import your existing links." },
  { title: "Point your link & go live", body: "Aim your custom domain or Link in Bio at Sayzio — your audience never notices the move." },
];
