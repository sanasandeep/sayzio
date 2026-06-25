export interface UseCase {
  slug: string;
  eyebrow: string;
  tagline: string;
  navDesc: string;
  title: string;
  description: string;
  features: string[];
  sections: { heading: string; body: string }[];
  faqs: { question: string; answer: string }[];
}

const commonFaqs = [
  { question: "Is there really a free plan?", answer: "Yes — the Free plan is free forever, with no credit card and no trial countdown." },
  { question: "Can I use my own domain?", answer: "Yes, on paid plans. Point a CNAME record and we provision a free SSL certificate automatically." },
  { question: "How long does it take to set up?", answer: "Most people are live in under two minutes — pick a template, drop in your details, and share the link." },
  { question: "Does it work on every device?", answer: "Yes — every page is mobile-first with desktop-tuned layouts, and there's a native app for iOS and Android." },
];

export const useCases: UseCase[] = [
  {
    slug: "creators",
    eyebrow: "For creators",
    tagline: "One link that turns followers into a living.",
    navDesc: "Grow, sell and own your audience",
    title: "Sayzio for Creators",
    description:
      "The link-in-bio built for creators — sell products, take tips, grow followers you own, and see what actually lands.",
    features: [
      "Drag & drop Link in Bio builder",
      "Live audience analytics",
      "Followers & creators feed",
      "Sell products & take tips",
    ],
    sections: [
      { heading: "One link for every platform", body: "Drop a single Sayzio link in every bio and point fans to whatever matters this week — a drop, a video, a tip jar or your latest release." },
      { heading: "Built to convert, not just list", body: "Rich blocks for products, tips, embeds and forms turn casual visitors into buyers, subscribers and superfans." },
      { heading: "Own your audience", body: "Build a follower list no algorithm can throttle — reach them by digest email, push, and your own creators feed." },
      { heading: "Get paid your way", body: "Sell digital products, take tips and run memberships with low fees and fast payouts." },
      { heading: "See what actually lands", body: "Live analytics and the AI Performance Coach show you what's working and what to fix next — in one tap." },
      { heading: "Free forever to start", body: "Everything you need to launch is free. Upgrade only when you outgrow it." },
    ],
    faqs: [
      { question: "How do I get paid?", answer: "Sell products and take tips with Stripe-powered checkout; payouts land in your connected account on your schedule." },
      { question: "Do I keep my followers if I leave?", answer: "Yes — export your top followers as CSV any time. Your audience is yours, not ours." },
      ...commonFaqs,
    ],
  },
  {
    slug: "agencies",
    eyebrow: "For agencies",
    tagline: "Run every client from one tidy dashboard.",
    navDesc: "Workspaces, roles and clean reporting",
    title: "Sayzio for Agencies",
    description:
      "Manage every client from one dashboard — separate workspaces, real roles, reporting clients read, and an API to fit your stack.",
    features: [
      "Workspaces per client",
      "Roles & permissions",
      "Per-link analytics & CSV",
      "One-click social plumbing",
    ],
    sections: [
      { heading: "A clean workspace per client", body: "Keep every client's links, content, contacts and billing fully isolated — switch between them in one click." },
      { heading: "Invite the team, lock down access", body: "Owner, Admin, Editor and Viewer roles keep everyone in their lane, with audit logs on every change." },
      { heading: "Reporting clients actually read", body: "Share a styled, read-only analytics snapshot or invite the client in as a Viewer — no screenshots." },
      { heading: "Onboard in minutes", body: "One-click social connections with auto-retry mean less plumbing and more shipping." },
      { heading: "Bill the way you work", body: "Per-workspace plans and invoices let you bill each client separately and cleanly." },
      { heading: "An API to fit your stack", body: "A documented REST API and webhooks slot Sayzio into the tools you already run." },
    ],
    faqs: [
      { question: "Are clients fully separated?", answer: "Yes — each workspace is an isolated container for content, links, contacts and billing." },
      { question: "Can I bill each client separately?", answer: "Yes — every workspace has its own plan and downloadable invoices." },
      { question: "Is there an API?", answer: "Yes — a documented REST API plus webhooks on plans that include API access." },
      ...commonFaqs,
    ],
  },
  {
    slug: "coaches",
    eyebrow: "For coaches",
    tagline: "Fill your calendar while you focus on clients.",
    navDesc: "Booking, lead capture and follow-ups",
    title: "Sayzio for Coaches",
    description:
      "Turn your bio into a booking machine — capture leads, fill your calendar, follow up automatically and look like the expert.",
    features: [
      "Forms & lead capture",
      "Calendar booking links",
      "Broadcasts & follow-ups",
      "Contacts & CRM dialer",
    ],
    sections: [
      { heading: "Turn visitors into booked calls", body: "Calendar booking blocks let people grab a slot straight from your page — no back-and-forth." },
      { heading: "Capture every lead", body: "Forms with conditional logic pipe submissions straight into your contacts, ready to follow up." },
      { heading: "Follow up without busywork", body: "Schedule broadcasts and follow-ups by email and SMS to keep prospects warm on autopilot." },
      { heading: "Look like the expert", body: "Premium themes, testimonials and a verified badge build instant trust." },
      { heading: "Sell programs and sessions", body: "Take payments for packages, sessions and digital resources right on your page." },
      { heading: "Let AI handle the first reply", body: "The AI chatbot answers common questions and books calls 24/7, even while you sleep." },
    ],
    faqs: [
      { question: "Can people book me directly?", answer: "Yes — add a calendar booking block and visitors grab a slot that syncs to your real calendar." },
      { question: "Can I automate follow-up?", answer: "Yes — schedule email and SMS broadcasts and per-contact follow-ups." },
      ...commonFaqs,
    ],
  },
  {
    slug: "musicians",
    eyebrow: "For musicians",
    tagline: "Every release, every platform, one smart link.",
    navDesc: "Smart links, drops and fan growth",
    title: "Sayzio for Musicians",
    description:
      "Send fans to every platform from one smart link — drop releases on cue, sell out the room and grow a fanbase you own.",
    features: [
      "Music & video embeds",
      "Schedule drops to the minute",
      "Events, RSVPs & tickets",
      "Grow & message your fans",
    ],
    sections: [
      { heading: "Every platform, one smart link", body: "Spotify, Apple Music, YouTube, SoundCloud and more — fans tap through to wherever they listen." },
      { heading: "Drop releases on cue", body: "Schedule blocks and pages to flip live at the exact release minute, in every fan's timezone." },
      { heading: "Sell out the room", body: "Event blocks with countdowns, RSVPs, reminders and .ics downloads fill your shows." },
      { heading: "Turn listeners into a fanbase", body: "Grow followers you own, with digest emails and a creators feed for every announcement." },
      { heading: "Look the part", body: "Premium themes, video backgrounds and custom fonts make your page feel like your brand." },
      { heading: "See where fans are", body: "A live visitor map and analytics show you exactly where to tour and post next." },
    ],
    faqs: [
      { question: "Can I link to every streaming service?", answer: "Yes — add a smart link block and fans choose their platform; you can pre-save upcoming releases too." },
      { question: "Can I schedule a release?", answer: "Yes — schedule blocks or whole pages to go live at the exact drop time." },
      ...commonFaqs,
    ],
  },
  {
    slug: "small-business",
    eyebrow: "For small business",
    tagline: "Your whole storefront behind one link.",
    navDesc: "Storefront, QR and customer capture",
    title: "Sayzio for Small Business",
    description:
      "Your whole storefront behind one link — take orders, bridge offline to online with QR, and build trust fast. Free to start.",
    features: [
      "Products, payments & tips",
      "Dynamic QR for print",
      "Forms & contact capture",
      "Reviews & social proof",
    ],
    sections: [
      { heading: "A storefront without the headache", body: "Add product blocks with images, pricing, variants and checkout — no separate store to build." },
      { heading: "Take orders and bookings", body: "Accept payments, take deposits and let customers book straight from your page." },
      { heading: "Bridge offline to online", body: "Dynamic QR codes on packaging, flyers and signage send customers to the right page — repointable any time." },
      { heading: "Capture and keep customers", body: "Forms and contact capture grow a customer list you can broadcast to by email and SMS." },
      { heading: "Build trust fast", body: "Reviews pages, social proof widgets and a verified badge turn first-timers into regulars." },
      { heading: "Free to start, scale when ready", body: "Launch on the free plan and upgrade as your orders grow." },
    ],
    faqs: [
      { question: "Can customers pay or book directly?", answer: "Yes — add product and booking blocks with Stripe-powered checkout right on your page." },
      { question: "How does dynamic QR help?", answer: "Print one QR on packaging or signage and re-point it any time without reprinting." },
      ...commonFaqs,
    ],
  },
];

export function getUseCase(slug: string): UseCase | undefined {
  return useCases.find((u) => u.slug === slug);
}
