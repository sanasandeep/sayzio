// Deck spec: ordered list of slide configs.
// Each slide is rendered by a layout function in generate-deck.mjs.
// Edit copy here freely; re-run `node scripts/generate-deck.mjs` to regenerate.

const FEATURE_CATEGORIES = [
  {
    key: "biolinks",
    name: "Link in Bio & Smart Links",
    subtitle: "Bio Pages, Short Links, QR Studio, Splash Screens.",
    capabilities: [
      { title: "Link in Bio pages", body: "Drag-and-drop blocks, themes, custom domains." },
      { title: "Smart short links", body: "Branded URLs with routing rules and pixels." },
      { title: "QR Studio", body: "Logo-aware codes that update without reprint." },
      { title: "Splash screens", body: "Branded interstitials between click and destination." },
    ],
    mock: {
      title: "Top performing links — last 7 days",
      rows: [
        { label: "1inme.co/spring-drop", value: "12,480 clicks" },
        { label: "1inme.co/episode-42", value: "4,210 clicks" },
        { label: "1inme.co/coaching-call", value: "2,317 clicks" },
        { label: "1inme.co/qr-business-card", value: "1,096 scans" },
      ],
      footer: "Live link analytics with device, geo, and source breakdowns.",
    },
    useCase: {
      title: "Use case · Album launch",
      bullets: [
        "Bio page lists every streaming service with smart routing per region.",
        "QR on physical merch deep-links into the artist bio.",
        "Splash screen previews the new track before the redirect.",
        "Pixels fire to retarget visitors on Meta and TikTok.",
      ],
    },
  },
  {
    key: "ai",
    name: "AI Suite",
    subtitle: "Companions, AskCoach, Voice, Card Scanner.",
    capabilities: [
      { title: "AI Companions", body: "Always-on assistants with custom personalities." },
      { title: "AskCoach", body: "Grounded answers from your own files and CRM." },
      { title: "Voice assistant", body: "Hands-free triggers across modules." },
      { title: "Business card scanner", body: "OCR + enrichment straight to CRM." },
    ],
    mock: {
      title: "AI credits this month",
      rows: [
        { label: "AskCoach turns", value: "1,420 / 5,000" },
        { label: "Voice minutes", value: "92 / 300" },
        { label: "Card scans", value: "47 / unlimited" },
        { label: "Companion replies", value: "812 / 5,000" },
      ],
      footer: "Transparent usage, fair pooling across the workspace.",
    },
    useCase: {
      title: "Use case · Solo consultant",
      bullets: [
        "Companion drafts client follow-ups from meeting notes.",
        "AskCoach answers \"what did I promise Acme last quarter?\" instantly.",
        "Voice captures action items between meetings.",
        "Scanner files every business card the moment it's collected.",
      ],
    },
  },
  {
    key: "productivity",
    name: "Productivity & CRM",
    subtitle: "Vault, Tasks, Forms, Resume, Calendar, CRM.",
    capabilities: [
      { title: "Vault", body: "Encrypted storage for credentials, docs, and notes." },
      { title: "Tasks & workflows", body: "Lightweight project tracking and automations." },
      { title: "Forms & resume", body: "Capture leads and ship a polished resume." },
      { title: "Calendar & CRM", body: "Bookings tied to contacts and deal stages." },
    ],
    mock: {
      title: "This week — pipeline snapshot",
      rows: [
        { label: "Open deals", value: "$48,200" },
        { label: "Calls scheduled", value: "11" },
        { label: "Forms submitted", value: "37" },
        { label: "Tasks due", value: "9 of 23" },
      ],
      footer: "One workspace. Contacts, calendar, vault, and tasks share context.",
    },
    useCase: {
      title: "Use case · Boutique agency",
      bullets: [
        "Lead form auto-creates a contact and a task in CRM.",
        "Vault stores the signed contract; client gets read-only access.",
        "Calendar books the kickoff and pings the team channel.",
        "Tasks track every deliverable through to invoice.",
      ],
    },
  },
  {
    key: "social",
    name: "Social & Community",
    subtitle: "Creator Feed, Cross-Posting, Analytics.",
    capabilities: [
      { title: "Creator feed", body: "Native publishing surface for your followers." },
      { title: "Cross-posting", body: "Push once, distribute everywhere." },
      { title: "Engagement analytics", body: "See what lands across every platform." },
      { title: "Community moments", body: "Polls, AMAs, and member-only drops." },
    ],
    mock: {
      title: "Cross-post composer",
      rows: [
        { label: "Instagram", value: "scheduled · Thu 9am" },
        { label: "TikTok", value: "scheduled · Thu 9am" },
        { label: "X / Twitter", value: "scheduled · Thu 9am" },
        { label: "Sayzio feed", value: "scheduled · Thu 9am" },
      ],
      footer: "Per-network previews, hashtag swaps, and best-time picks.",
    },
    useCase: {
      title: "Use case · Podcaster",
      bullets: [
        "Episode drops to the feed, then auto-clips for Reels and Shorts.",
        "Link in Bio updates with the latest episode block.",
        "Listeners join a member-only AMA next day.",
        "Analytics roll up listens, clicks, and replies in one report.",
      ],
    },
  },
  {
    key: "mobile",
    name: "Mobile-First Tools",
    subtitle: "NFC Cards, Smart Dialer.",
    capabilities: [
      { title: "NFC smart cards", body: "Tap-to-share digital business cards." },
      { title: "Smart dialer", body: "Click-to-call with full CRM context." },
      { title: "Mobile capture", body: "Snap, scan, and save on the go." },
      { title: "Offline-first", body: "Sync once you're back online." },
    ],
    mock: {
      title: "Smart dialer · live call",
      rows: [
        { label: "Contact", value: "Jordan Reeves" },
        { label: "Last touch", value: "12 days ago" },
        { label: "Open deal", value: "Q2 expansion · $14k" },
        { label: "Suggested next step", value: "Send proposal" },
      ],
      footer: "Notes auto-save to the contact at hangup.",
    },
    useCase: {
      title: "Use case · Field sales",
      bullets: [
        "Tap to share a digital card with every prospect.",
        "Dialer surfaces the deal status before the call connects.",
        "Notes sync to CRM without typing.",
        "Cards captured today appear in the office tomorrow.",
      ],
    },
  },
  {
    key: "admin",
    name: "Admin, Workspaces & Billing",
    subtitle: "Workspaces, white label, audit logs, plans.",
    capabilities: [
      { title: "Workspaces & roles", body: "Separate brands, projects, and clients." },
      { title: "White label", body: "Your domain, your logo, your colors." },
      { title: "Audit log", body: "Every action, by every member." },
      { title: "Billing & seats", body: "Monthly or annual, scale per workspace." },
    ],
    mock: {
      title: "Workspace overview",
      rows: [
        { label: "Members", value: "12 active" },
        { label: "Brands", value: "3" },
        { label: "AI credits", value: "18,400 / 50,000" },
        { label: "Plan", value: "Studio · annual" },
      ],
      footer: "Owners can split credits and seats across brands.",
    },
    useCase: {
      title: "Use case · Multi-brand agency",
      bullets: [
        "Each client lives in its own workspace under one bill.",
        "White label keeps every dashboard on-brand for the client.",
        "Audit log answers \"who changed the Link in Bio?\" in seconds.",
        "Seats and credits move between teams without friction.",
      ],
    },
  },
  {
    key: "analytics",
    name: "Analytics & Insights",
    subtitle: "Engagement, audience, funnels.",
    capabilities: [
      { title: "Engagement analytics", body: "Clicks, scans, opens, and time on page." },
      { title: "Audience insights", body: "Geos, devices, and returning visitors." },
      { title: "Funnels & conversions", body: "From impression to outcome." },
      { title: "Exports & alerts", body: "CSV, webhooks, and threshold alerts." },
    ],
    mock: {
      title: "Funnel · April",
      rows: [
        { label: "Bio page views", value: "84,210" },
        { label: "Link clicks", value: "12,480" },
        { label: "Form submissions", value: "612" },
        { label: "Deals created", value: "47" },
      ],
      footer: "Drill into any step to see who, where, and from which source.",
    },
    useCase: {
      title: "Use case · Course creator",
      bullets: [
        "See which bio block converts best, hour by hour.",
        "Audience report reveals top 3 cities for the next live event.",
        "Funnel shows the drop between landing page and signup form.",
        "Alert fires when daily signups beat the rolling average.",
      ],
    },
  },
  {
    key: "integrations",
    name: "Integrations",
    subtitle: "Apps, webhooks, marketing pixels.",
    capabilities: [
      { title: "App integrations", body: "Native connections to popular tools." },
      { title: "Webhooks & API", body: "Build your own automations." },
      { title: "Marketing pixels", body: "Meta, TikTok, Google on every link." },
      { title: "Zapier & Make", body: "Connect to thousands of apps." },
    ],
    mock: {
      title: "Connected services",
      rows: [
        { label: "HubSpot", value: "syncing · contacts" },
        { label: "Stripe", value: "payouts last 30d" },
        { label: "Slack", value: "alerts on new lead" },
        { label: "Google Calendar", value: "two-way sync" },
      ],
      footer: "Token-scoped access with full audit trail.",
    },
    useCase: {
      title: "Use case · Operations team",
      bullets: [
        "Every new form lead lands in HubSpot with the right owner.",
        "Stripe payouts trigger a Slack message in #revenue.",
        "Zap renames the contact when the deal closes.",
        "Webhook keeps the data warehouse fresh by the hour.",
      ],
    },
  },
  {
    key: "security",
    name: "Security & Compliance",
    subtitle: "Privacy by design, account security, standards.",
    capabilities: [
      { title: "Privacy by design", body: "Data residency, deletion, and consent built in." },
      { title: "Account security", body: "MFA, passkeys, sessions, and recovery." },
      { title: "Compliance posture", body: "Aligned with SOC 2 and GDPR." },
      { title: "Workspace controls", body: "SSO, SCIM, role-based access." },
    ],
    mock: {
      title: "Workspace security checklist",
      rows: [
        { label: "MFA enforced", value: "12 / 12 members" },
        { label: "SSO", value: "active · Okta" },
        { label: "SCIM provisioning", value: "enabled" },
        { label: "Audit log retention", value: "365 days" },
      ],
      footer: "Evidence packs available for security reviews on request.",
    },
    useCase: {
      title: "Use case · Regulated team",
      bullets: [
        "SSO + SCIM keeps access tied to the source-of-truth directory.",
        "Audit log exports satisfy quarterly internal reviews.",
        "Vault-shared assets respect the same role-based access.",
        "Data deletion requests fulfilled in days, not weeks.",
      ],
    },
  },
];

const PERSONAS = [
  {
    group: "Creators",
    name: "Artists",
    pains: ["Fans scattered across DSPs", "Merch and tour info living in 5 places", "No clean way to capture super-fans"],
    modules: ["Link in Bio", "Smart Links + Pixels", "Creator Feed", "AI Companion (tone of voice)", "Forms (mailing list)"],
    day: [
      { time: "8:00", module: "Companion", action: "Drafts a caption for tonight's drop in the artist's voice." },
      { time: "11:00", module: "Link in Bio", action: "Updates the pinned block with the new single." },
      { time: "14:00", module: "Smart Dialer", action: "Quick call with the manager — notes saved to CRM." },
      { time: "18:00", module: "Creator Feed", action: "Premieres the music video to super-fans first." },
      { time: "22:00", module: "Analytics", action: "Reviews where streams are spiking." },
    ],
    outcomes: [
      { value: "+38%", label: "click-through to streaming" },
      { value: "2.4×", label: "mailing list growth" },
      { value: "−6h", label: "saved per launch" },
    ],
  },
  {
    group: "Creators",
    name: "Musicians",
    pains: ["Multiple DSP links in every post", "Tour dates scattered across socials", "No single home for press"],
    modules: ["Link in Bio", "Smart Links (DSP routing)", "Calendar (tour)", "Vault (press kit)", "AI Companion"],
    day: [
      { time: "9:00", module: "Vault", action: "Shares the EPK with a new venue." },
      { time: "12:00", module: "Calendar", action: "Confirms a tour date and updates the bio block." },
      { time: "15:00", module: "Companion", action: "Generates city-specific announcement copy." },
      { time: "19:00", module: "Smart Links", action: "Routes US fans to Apple Music, EU to Spotify." },
      { time: "23:00", module: "Analytics", action: "Sees stream spikes by region." },
    ],
    outcomes: [
      { value: "+52%", label: "DSP conversion" },
      { value: "1 link", label: "for every venue" },
      { value: "9", label: "tools replaced" },
    ],
  },
  {
    group: "Creators",
    name: "Podcasters",
    pains: ["Episodes posted everywhere, attribution nowhere", "Sponsor links untracked", "Listener data trapped in apps"],
    modules: ["Link in Bio", "Smart Links (sponsor codes)", "Creator Feed (members)", "Forms (listener Q&A)", "Analytics"],
    day: [
      { time: "7:30", module: "Companion", action: "Generates show notes from the episode transcript." },
      { time: "10:00", module: "Smart Links", action: "Sets up trackable sponsor URLs for the new episode." },
      { time: "13:00", module: "Creator Feed", action: "Posts a member-only behind-the-scenes clip." },
      { time: "17:00", module: "Analytics", action: "Sends sponsor a weekly performance recap." },
      { time: "21:00", module: "Forms", action: "Reviews listener Q&A for next week." },
    ],
    outcomes: [
      { value: "100%", label: "sponsor link visibility" },
      { value: "+27%", label: "member conversion" },
      { value: "−4h", label: "post-production per week" },
    ],
  },
  {
    group: "Creators",
    name: "Streamers",
    pains: ["Multi-platform schedule chaos", "Donations, subs, and merch in different tools", "Hard to convert viewers to followers"],
    modules: ["Link in Bio", "Smart Links", "QR Studio", "Creator Feed", "AI Voice"],
    day: [
      { time: "12:00", module: "Link in Bio", action: "Shows today's stream schedule across platforms." },
      { time: "15:00", module: "QR Studio", action: "Branded QR overlay on the stream." },
      { time: "18:00", module: "Voice", action: "Triggers commands during the live show." },
      { time: "22:00", module: "Smart Links", action: "Reviews donation link conversions." },
      { time: "01:00", module: "Companion", action: "Generates highlights to drop tomorrow." },
    ],
    outcomes: [
      { value: "+44%", label: "viewer-to-follower lift" },
      { value: "1 page", label: "for every link in chat" },
      { value: "5", label: "tools consolidated" },
    ],
  },
  {
    group: "Creators",
    name: "Influencers",
    pains: ["Brand deal links a mess", "Reporting brands ask for is painful", "Audience trapped on platforms"],
    modules: ["Link in Bio", "Smart Links + Pixels", "Forms (rate card)", "Analytics (brand reports)", "CRM (brands)"],
    day: [
      { time: "8:00", module: "Forms", action: "New brand inquiry hits the inbox + CRM." },
      { time: "11:00", module: "Smart Links", action: "Builds a campaign URL with deep tracking." },
      { time: "14:00", module: "Analytics", action: "Brand-shareable report auto-generated." },
      { time: "18:00", module: "Creator Feed", action: "Posts the sponsored content to Sayzio audience." },
      { time: "21:00", module: "CRM", action: "Logs the next follow-up with the brand manager." },
    ],
    outcomes: [
      { value: "1 click", label: "to send brand reports" },
      { value: "+31%", label: "campaign CTR vs UTMs" },
      { value: "0", label: "spreadsheets needed" },
    ],
  },
  {
    group: "Coaches & Experts",
    name: "Coaches",
    pains: ["Booking + payment + intake split across tools", "Client notes scattered", "No place to share resources"],
    modules: ["Calendar (bookings)", "Forms (intake)", "Vault (resources)", "AI Coach", "CRM"],
    day: [
      { time: "8:00", module: "Calendar", action: "Reviews today's coaching sessions." },
      { time: "10:00", module: "AskCoach", action: "Pulls notes from last session for context." },
      { time: "13:00", module: "Vault", action: "Shares a custom worksheet with the client." },
      { time: "16:00", module: "Forms", action: "New client completes the intake form." },
      { time: "19:00", module: "CRM", action: "Logs progress and books the next session." },
    ],
    outcomes: [
      { value: "−5h/wk", label: "admin time" },
      { value: "+22%", label: "booking conversion" },
      { value: "1 link", label: "for the whole practice" },
    ],
  },
  {
    group: "Coaches & Experts",
    name: "Educators",
    pains: ["Course content fragmented", "Student questions repeat", "Hard to track engagement"],
    modules: ["Link in Bio (course hub)", "Forms (quizzes)", "AI Mind (course content)", "Creator Feed", "Analytics"],
    day: [
      { time: "8:00", module: "AI Mind", action: "Loads new course materials so AskCoach can answer." },
      { time: "11:00", module: "Link in Bio", action: "Updates the course hub with this week's module." },
      { time: "14:00", module: "Creator Feed", action: "Hosts a student Q&A in the community." },
      { time: "17:00", module: "Forms", action: "Pushes a quick quiz; results sync to CRM." },
      { time: "20:00", module: "Analytics", action: "Sees where students stall and tweaks the next lesson." },
    ],
    outcomes: [
      { value: "+34%", label: "course completion" },
      { value: "−40%", label: "repeat questions" },
      { value: "1 home", label: "for every cohort" },
    ],
  },
  {
    group: "Coaches & Experts",
    name: "Fitness Trainers",
    pains: ["Plans, payments, and progress in different apps", "Client retention drops after sign-up", "No mobile-first capture"],
    modules: ["Calendar (sessions)", "Vault (plans)", "Forms (intake)", "Smart Dialer (check-ins)", "Analytics"],
    day: [
      { time: "6:00", module: "Calendar", action: "Confirms morning client sessions." },
      { time: "9:00", module: "Vault", action: "Sends today's plan to clients securely." },
      { time: "12:00", module: "Smart Dialer", action: "Check-in calls with last week's no-shows." },
      { time: "15:00", module: "Forms", action: "Weekly progress survey to all clients." },
      { time: "18:00", module: "Analytics", action: "Sees who's drifting and re-engages." },
    ],
    outcomes: [
      { value: "+28%", label: "retention at month 3" },
      { value: "100%", label: "plans delivered on time" },
      { value: "1 phone", label: "to run the business" },
    ],
  },
  {
    group: "Sales & Field Pros",
    name: "Realtors",
    pains: ["Listing links a mess on signs and socials", "Lead capture inconsistent", "Follow-up loses to the next agent"],
    modules: ["QR Studio (signs)", "Link in Bio (listings)", "Forms (leads)", "Smart Dialer", "CRM"],
    day: [
      { time: "8:00", module: "QR Studio", action: "Refreshes QR on yard signs for the new listing." },
      { time: "11:00", module: "Link in Bio", action: "Showcases the listing as the pinned block." },
      { time: "14:00", module: "Forms", action: "Open-house attendees check in via QR + form." },
      { time: "17:00", module: "Smart Dialer", action: "Calls today's leads with deal context onscreen." },
      { time: "20:00", module: "CRM", action: "Schedules tomorrow's follow-ups automatically." },
    ],
    outcomes: [
      { value: "2.1×", label: "open-house lead capture" },
      { value: "−12h/mo", label: "admin time" },
      { value: "1 page", label: "per listing" },
    ],
  },
  {
    group: "Sales & Field Pros",
    name: "Business Developers",
    pains: ["LinkedIn + email + calendar + CRM tab juggling", "Inconsistent follow-ups", "Hard to attribute pipeline"],
    modules: ["Smart Links + Pixels", "AI Companion (drafts)", "Calendar", "CRM", "Analytics (funnels)"],
    day: [
      { time: "8:00", module: "Companion", action: "Drafts personalised outbound for today's accounts." },
      { time: "11:00", module: "Smart Links", action: "Creates trackable proposal links per prospect." },
      { time: "14:00", module: "Calendar", action: "Books discovery calls; CRM auto-updates." },
      { time: "17:00", module: "Analytics", action: "Reviews funnel: sent → opened → booked." },
      { time: "19:00", module: "CRM", action: "Tags this week's pipeline by source." },
    ],
    outcomes: [
      { value: "+42%", label: "reply rate" },
      { value: "−7h/wk", label: "tool switching" },
      { value: "100%", label: "attribution coverage" },
    ],
  },
  {
    group: "Teams & Agencies",
    name: "Teams & Agencies",
    pains: ["Per-client tool sprawl", "Brand consistency across deliverables", "Reporting that takes days"],
    modules: ["Workspaces", "White Label", "Vault (assets)", "Analytics (client reports)", "Audit Log"],
    day: [
      { time: "8:00", module: "Workspaces", action: "Switches into the client of the day." },
      { time: "11:00", module: "Vault", action: "Shares a brand kit with the new contractor." },
      { time: "14:00", module: "Analytics", action: "Sends a white-labelled report to the client." },
      { time: "17:00", module: "Audit Log", action: "Confirms who updated the campaign URL." },
      { time: "19:00", module: "Billing", action: "Reviews seat and credit usage across brands." },
    ],
    outcomes: [
      { value: "1 bill", label: "across every brand" },
      { value: "−3 days", label: "report turnaround" },
      { value: "0", label: "stray client tools" },
    ],
  },
];

// ---------- Build ordered spec ----------

const spec = [];

// 1. Cover
spec.push({
  layout: "cover",
  slug: "Cover",
  title: "Sayzio — Sectioned Deck",
  description: "Master cover for the Sayzio multi-section deck.",
  eyebrow: "Sectioned Deck · 2026",
  titleA: "One link.",
  titleB: "One identity.",
  titleC: "One platform.",
  subtitle: "A single deck for sales, product, features, personas, investors, and roadmap. Jump to the section you need.",
  headerLabel: "Sayzio",
});

// 2. TOC
spec.push({
  layout: "toc",
  slug: "TableOfContents",
  title: "Table of contents",
  subtitle: "Each section is appendix-separated by a divider slide. Jump to the divider to start the section.",
  items: [
    { name: "Sales Presentation", desc: "Problem, pitch, ROI, pricing, next steps.", range: "3 – 23" },
    { name: "Product Presentation", desc: "Web, mobile, API, journeys, integrations.", range: "24 – 44" },
    { name: "Feature Deep-Dives", desc: "9 module mini-decks for buyer questions.", range: "45 – 80" },
    { name: "Persona Decks", desc: "How Sayzio helps each role we sell into.", range: "81 – 135" },
    { name: "Investor Pitch", desc: "Vision, market, model, team, ask.", range: "136 – 156" },
    { name: "Future Roadmap", desc: "Now / Next / Later across every area.", range: "157 – 177" },
  ],
});

// 3. SALES SECTION (divider + 20)
spec.push({
  layout: "divider",
  slug: "SalesDivider",
  title: "Appendix · Sales presentation.",
  subtitle: "Twenty slides to take a buyer from problem to next step.",
  description: "Section divider for the sales presentation block.",
  eyebrow: "Section 01",
  range: "Slides 4 – 23",
});

const sales = [
  {
    layout: "metrics",
    slug: "SalesProblem",
    title: "The modern professional drowns in tools.",
    subtitle: "Link in Bio, scheduler, CRM, vault, analytics — none of them talking to each other.",
    metrics: [
      { value: "9", label: "SaaS tools per active creator" },
      { value: "$214", label: "Average monthly stack cost" },
      { value: "37%", label: "Of features ever used" },
      { value: "2.4h", label: "Daily context switching" },
    ],
    note: "Internal Sayzio research, n = 1,200. Stacks vary by role.",
  },
  {
    layout: "bullets",
    slug: "SalesCost",
    title: "What fragmentation actually costs.",
    bullets: [
      { title: "Lost hours", body: "Switching tools eats the equivalent of a full workday each week." },
      { title: "Lost data", body: "Customer context lives in 5 places — none of them complete." },
      { title: "Lost brand", body: "Inconsistent fonts, colours, and links erode trust on every click." },
      { title: "Lost revenue", body: "Slow follow-ups and dropped pixels leak conversions everywhere." },
    ],
  },
  {
    layout: "cards",
    slug: "SalesPitch",
    title: "Sayzio — the everything platform.",
    subtitle: "Identity, links, AI, productivity, and analytics in one place.",
    cards: [
      { tag: "Identity", title: "One handle, one home", body: "1inme.com/you replaces every link in bio." },
      { tag: "Tools", title: "11 modules in one app", body: "From Link in Bio to billing, no integrations to wire." },
      { tag: "AI", title: "Always-on assistants", body: "Companions that know your data and tone." },
      { tag: "Insight", title: "Cross-module analytics", body: "Funnel from impression to outcome in one view." },
    ],
  },
  {
    layout: "cards",
    slug: "SalesDifferentiators",
    title: "Why Sayzio, not the next bundle.",
    cols: 4,
    cards: [
      { tag: "Native", title: "Built together", body: "Modules share identity, vault, and AI by default." },
      { tag: "Beautiful", title: "Pixel-honest", body: "Pages and dashboards that look as good as they work." },
      { tag: "Open", title: "Truly extensible", body: "API, webhooks, and pixels on every link." },
      { tag: "Fair", title: "Transparent pricing", body: "Predictable plans, fair AI credits, no surprise add-ons." },
    ],
  },
  {
    layout: "metrics",
    slug: "SalesRoi",
    title: "ROI of consolidating onto Sayzio.",
    metrics: [
      { value: "−$1,400", label: "annual stack savings (avg)" },
      { value: "+6h", label: "weekly time saved" },
      { value: "+24%", label: "lead-to-close conversion" },
      { value: "1 invoice", label: "across every workspace" },
    ],
    note: "Aggregate self-reported numbers from early-access customers, 2025 cohort.",
  },
  {
    layout: "mockup",
    slug: "SalesTimeSaved",
    title: "Where the hours come back.",
    subtitle: "Time saved per week by switching to Sayzio.",
    bullets: [
      "Bio + link updates: 1.5h → 10 minutes",
      "Lead capture + entry: 2h → automated",
      "Cross-posting: 3h → 30 minutes",
    ],
    mockTitle: "Weekly time savings",
    mock: [
      { label: "Bio + link maintenance", value: "−1.3h" },
      { label: "Lead capture + CRM entry", value: "−2.0h" },
      { label: "Cross-posting", value: "−2.5h" },
      { label: "Reporting", value: "−1.2h" },
    ],
    mockFooter: "Total: ~7 hours per week, per active user.",
  },
  {
    layout: "quote",
    slug: "SalesProofQuote",
    quote: "We replaced six tools and shaved a workday off every week. The team finally has one place to live.",
    author: "Avery K.",
    role: "Founder, indie record label · early-access customer",
  },
  {
    layout: "cards",
    slug: "SalesProofLogos",
    title: "Loved by early teams across creator, coaching, and agency.",
    subtitle: "Replace these placeholders with real logos and case studies as deals close.",
    cols: 4,
    cards: [
      { tag: "Logo", title: "[ Customer logo ]", body: "Music label · 12 seats" },
      { tag: "Logo", title: "[ Customer logo ]", body: "Coaching collective · 8 seats" },
      { tag: "Logo", title: "[ Customer logo ]", body: "Boutique agency · 22 seats" },
      { tag: "Logo", title: "[ Customer logo ]", body: "Real estate team · 14 seats" },
    ],
  },
  {
    layout: "metrics",
    slug: "SalesProofMetrics",
    title: "What customers measure after 90 days.",
    metrics: [
      { value: "−6", label: "tools removed (median)" },
      { value: "+38%", label: "link CTR" },
      { value: "+24%", label: "lead-to-close" },
      { value: "92%", label: "weekly active across team" },
    ],
    note: "Placeholder figures — swap for the latest cohort numbers before sending.",
  },
  {
    layout: "pricing",
    slug: "SalesPricingSnapshot",
    title: "Pricing, at a glance.",
    subtitle: "Pick the plan that matches the team you have today.",
    tiers: [
      { name: "Free", price: "$0", cadence: "forever", features: ["1 Link in Bio", "5 short links", "Basic AI Coach", "Vault: 25 secrets"] },
      { name: "Pro", price: "$12", cadence: "per month", features: ["Unlimited links", "5,000 AI credits", "1 custom domain", "No Sayzio branding"] },
      { name: "Studio", price: "$29", cadence: "per month", popular: true, features: ["3 workspaces · 5 seats", "20,000 AI credits", "3 domains", "Bookings + payments"] },
      { name: "Business", price: "$99", cadence: "per month", features: ["Unlimited workspaces", "White label, SSO", "SCIM + SOC 2 evidence", "Priority SLA"] },
    ],
    note: "Annual billing saves 20%. Education and non-profit pricing on request.",
  },
  {
    layout: "cards",
    slug: "SalesObjections",
    title: "What buyers ask, and how we answer.",
    subtitle: "The four objections we hear most often — and the proof points to handle them.",
    cards: [
      { tag: "\"We already use X\"", title: "Migration in days", body: "Importers for Linktree, Bitly, HubSpot, Calendly, and CSV." },
      { tag: "\"Too many features\"", title: "Modular by design", body: "Turn modules off per workspace. Pay only for what you use." },
      { tag: "\"AI is expensive\"", title: "Pooled credits", body: "Credits shared across the workspace, top-up on demand." },
      { tag: "\"Security?\"", title: "Built for trust", body: "MFA, passkeys, audit log, SOC 2 trajectory, SSO + SCIM." },
    ],
  },
  {
    layout: "cards",
    slug: "SalesCompetitors",
    title: "How Sayzio compares.",
    cols: 4,
    cards: [
      { tag: "vs Linktree", title: "Bio + everything else", body: "Links are the doorway, not the product." },
      { tag: "vs Bitly", title: "Branded + intelligent", body: "Routing rules, pixels, and bio in one place." },
      { tag: "vs HubSpot", title: "Right-sized CRM", body: "Pipeline, calendar, and forms without the bloat." },
      { tag: "vs ChatGPT", title: "Grounded in your data", body: "Companions reason over your vault, files, and CRM." },
    ],
  },
  {
    layout: "bullets",
    slug: "SalesSecurityTrust",
    title: "Built so legal and IT can say yes.",
    bullets: [
      { title: "Identity controls", body: "MFA, passkeys, SSO, SCIM provisioning." },
      { title: "Data protections", body: "Encryption in transit and at rest, regional storage options." },
      { title: "Audit and evidence", body: "Full audit log, SOC 2 evidence pack on request." },
      { title: "Privacy by design", body: "Granular consent, deletion, and export." },
    ],
  },
  {
    layout: "mockup",
    slug: "SalesProductSnapshot",
    title: "What buyers see on day one.",
    subtitle: "A single dashboard that proves the value within the first hour.",
    bullets: [
      "Live Link in Bio page with traffic flowing",
      "AI Companion already trained on uploaded files",
      "Funnel populated from imported clicks",
    ],
    mockTitle: "Onboarding day · workspace dashboard",
    mock: [
      { label: "Bio page", value: "live · 412 visits" },
      { label: "Companion", value: "trained · 22 docs" },
      { label: "CRM", value: "imported · 1,840 contacts" },
      { label: "Funnel", value: "tracking 4 steps" },
    ],
  },
  {
    layout: "cards",
    slug: "SalesUseCases",
    title: "Three buyer outcomes we underwrite.",
    cards: [
      { tag: "Outcome", title: "Faster launches", body: "Cut campaign setup from 2 days to 2 hours." },
      { tag: "Outcome", title: "Cleaner pipeline", body: "Zero-touch lead capture and follow-up." },
      { tag: "Outcome", title: "Lower stack cost", body: "Replace 6 tools with one bill." },
    ],
  },
  {
    layout: "bullets",
    slug: "SalesImplementation",
    title: "What the first 30 days look like.",
    bullets: [
      { title: "Week 1 — Setup", body: "Workspace created, brand kit applied, two integrations live." },
      { title: "Week 2 — Migrate", body: "Imports finished, redirects in place, team trained." },
      { title: "Week 3 — Launch", body: "First campaign on Sayzio with shareable analytics." },
      { title: "Week 4 — Review", body: "ROI snapshot delivered to the buying committee." },
    ],
  },
  {
    layout: "cards",
    slug: "SalesSocialProofExpanded",
    title: "Where buyers see early proof.",
    cards: [
      { tag: "Customer story", title: "[ Story title ]", body: "Result: −5 tools, +32% conversion in 60 days." },
      { tag: "Customer story", title: "[ Story title ]", body: "Result: ops team 1.4× faster on campaign setup." },
      { tag: "Customer story", title: "[ Story title ]", body: "Result: 3 brands consolidated under one bill." },
    ],
  },
  {
    layout: "metrics",
    slug: "SalesGuarantee",
    title: "Our buyer-side guarantees.",
    metrics: [
      { value: "30 days", label: "money-back guarantee" },
      { value: "<24h", label: "P1 response" },
      { value: "99.9%", label: "uptime SLA (Business)" },
      { value: "Free", label: "white-glove migration" },
    ],
  },
  {
    layout: "bullets",
    slug: "SalesNextSteps",
    title: "Recommended next steps.",
    bullets: [
      { title: "1. Book a 30-min walkthrough", body: "Tailored to your team's stack and goals." },
      { title: "2. Trial workspace", body: "We pre-load your data so day one is real." },
      { title: "3. ROI review", body: "We share an honest before/after at day 30." },
      { title: "4. Roll out", body: "Studio or Business plan with onboarding included." },
    ],
  },
  {
    layout: "closing",
    slug: "SalesCta",
    title: "Sales CTA",
    description: "Sales section call to action.",
    eyebrow: "Sales section CTA",
    titleA: "Let's pick a 30-minute slot.",
    subtitle: "Bring your stack. We'll bring a workspace pre-loaded with your data and a real ROI plan.",
    contacts: [
      { label: "Book", value: "1inme.com/sales" },
      { label: "Email", value: "sales@1inme.com" },
      { label: "Direct", value: "+1 (415) 555-0123" },
    ],
  },
];
spec.push(...sales);

// 4. PRODUCT SECTION (divider + 20)
spec.push({
  layout: "divider",
  slug: "ProductDivider",
  title: "Appendix · Product presentation.",
  subtitle: "Twenty slides to walk a prospect through the full product surface.",
  eyebrow: "Section 02",
  range: "Slides 25 – 44",
});

const product = [
  {
    layout: "cards",
    slug: "ProductOverview",
    title: "What Sayzio is, end to end.",
    subtitle: "One identity, three surfaces, eleven modules — wired together by AI and analytics.",
    cards: [
      { tag: "Surfaces", title: "Web, mobile, API", body: "Same data, same identity, same brand." },
      { tag: "Modules", title: "11 connected tools", body: "From Link in Bio to billing to CRM." },
      { tag: "Spine", title: "Identity + Vault + AI", body: "Every module shares the same brain." },
    ],
  },
  {
    layout: "mockup",
    slug: "ProductWebTour",
    title: "The web app.",
    subtitle: "The full-power workspace in your browser.",
    bullets: ["Drag-and-drop bio editor", "AI Companion side panel everywhere", "Cross-module dashboards"],
    mockTitle: "1inme.com — workspace",
    mock: [
      { label: "Sidebar", value: "Bio · Links · AI · Vault · CRM · Analytics" },
      { label: "Main", value: "Drag-and-drop editor" },
      { label: "Right rail", value: "AskCoach · context-aware" },
      { label: "Footer", value: "Workspace switcher" },
    ],
  },
  {
    layout: "mockup",
    slug: "ProductMobileTour",
    title: "The mobile app.",
    subtitle: "A native companion for iOS and Android.",
    bullets: ["NFC tap-to-share", "Smart dialer + CRM", "Voice capture between meetings"],
    mockTitle: "Sayzio — iOS / Android",
    mock: [
      { label: "Home", value: "Today · CRM · Bio quick-edit" },
      { label: "Quick action", value: "Tap card · Voice · Scan" },
      { label: "Notifications", value: "Lead, booking, mention" },
      { label: "Companion", value: "Voice + chat" },
    ],
  },
  {
    layout: "mockup",
    slug: "ProductApiSdk",
    title: "API & SDK.",
    subtitle: "Every capability is programmable.",
    bullets: ["REST + Webhooks for everything", "TypeScript SDK", "Token-scoped, audit-logged access"],
    mockTitle: "POST /v1/links",
    mock: [
      { label: "destination", value: "https://shop.example.com/spring" },
      { label: "rules", value: "[geo:US→...]" },
      { label: "pixels", value: "meta · tiktok · google" },
      { label: "splash", value: "spring-launch" },
    ],
  },
  {
    layout: "bullets",
    slug: "ProductUserJourney",
    title: "End-to-end user journey.",
    subtitle: "From first signup to first revenue, in one place.",
    bullets: [
      { title: "1. Sign up", body: "Claim your handle. Brand kit detected." },
      { title: "2. Build", body: "Bio + first short link in 10 minutes." },
      { title: "3. Convert", body: "Pixels, forms, and CRM wired automatically." },
      { title: "4. Operate", body: "Calendar, vault, and analytics tying it together." },
    ],
  },
  {
    layout: "cards",
    slug: "ProductCrossModule",
    title: "Cross-module workflows.",
    subtitle: "What happens when modules know about each other.",
    cards: [
      { tag: "Bio → CRM", title: "Form on a bio block", body: "Lead lands in CRM, tagged by source link." },
      { tag: "Smart Link → Pixel", title: "Click attribution", body: "Meta + Google + first-party in one tag." },
      { tag: "Calendar → Vault", title: "Booking auto-shares", body: "Booking sends the right doc from Vault." },
      { tag: "AI → Everything", title: "Coach uses it all", body: "AskCoach reads CRM, Vault, and notes." },
    ],
  },
  {
    layout: "mockup",
    slug: "ProductIdentitySpine",
    title: "Identity is the spine.",
    subtitle: "Your handle is the entry point. Everything else inherits brand and permissions.",
    bullets: ["1inme.com/yourhandle", "Single sign-on across modules", "Brand kit propagated everywhere"],
    mockTitle: "Identity · 1inme.com/yourhandle",
    mock: [
      { label: "Public surface", value: "Bio + Links + Forms" },
      { label: "Private surface", value: "Workspace + CRM + Vault" },
      { label: "Brand kit", value: "Colors · fonts · logo" },
      { label: "Permissions", value: "Roles · seats · audit" },
    ],
  },
  {
    layout: "cards",
    slug: "ProductSecurityTrust",
    title: "Security & trust at the platform layer.",
    cards: [
      { tag: "Identity", title: "MFA + passkeys", body: "TOTP, WebAuthn, recovery." },
      { tag: "Workspace", title: "SSO + SCIM", body: "Okta, Azure AD, Google Workspace." },
      { tag: "Data", title: "Encrypted everywhere", body: "TLS in transit, AES-256 at rest." },
      { tag: "Audit", title: "Every action logged", body: "Workspace-scoped, exportable." },
    ],
  },
  {
    layout: "mockup",
    slug: "ProductAdminWorkspace",
    title: "Admin & workspace overview.",
    subtitle: "Built for teams of every size.",
    bullets: ["Workspaces per brand or client", "Role-based access on every module", "White label per workspace"],
    mockTitle: "Workspace · settings",
    mock: [
      { label: "Members", value: "12 · 4 roles" },
      { label: "Brands", value: "3 active" },
      { label: "Domains", value: "5 verified" },
      { label: "Plan", value: "Studio · annual" },
    ],
  },
  {
    layout: "cards",
    slug: "ProductIntegrationsMap",
    title: "Integrations map.",
    subtitle: "Sayzio plugs into the tools you already use.",
    cols: 4,
    cards: [
      { tag: "CRM", title: "HubSpot · Salesforce", body: "Two-way contact + deal sync." },
      { tag: "Comms", title: "Slack · Teams", body: "Alerts, lead pings, daily digests." },
      { tag: "Calendar", title: "Google · Outlook", body: "Two-way sync, conflict-aware." },
      { tag: "Pixels", title: "Meta · TikTok · Google", body: "Fire on every link and page." },
      { tag: "Pay", title: "Stripe", body: "Bookings → payouts." },
      { tag: "Storage", title: "Drive · Dropbox", body: "Files into Vault." },
      { tag: "Workflow", title: "Zapier · Make", body: "Thousands of connectors." },
      { tag: "Build", title: "Webhooks · API", body: "Roll your own." },
    ],
  },
  {
    layout: "bullets",
    slug: "ProductOnboardingFlow",
    title: "The onboarding flow.",
    subtitle: "Time-to-value measured in hours, not weeks.",
    bullets: [
      { title: "Detect", body: "We scan your existing handles and brand kit." },
      { title: "Import", body: "Linktree, Bitly, HubSpot, CSV — pick what you have." },
      { title: "Wire", body: "Pixels, integrations, and CRM in one click each." },
      { title: "Launch", body: "First campaign live before the kickoff call ends." },
    ],
  },
  {
    layout: "mockup",
    slug: "ProductBioModule",
    title: "Link in Bio pages.",
    subtitle: "The doorway to everything else.",
    bullets: ["Drag-and-drop blocks", "Themes + custom domains", "AB tests built in"],
    mockTitle: "yourhandle · bio editor",
    mock: [
      { label: "Hero block", value: "image + headline" },
      { label: "Links", value: "8 · sortable" },
      { label: "Form block", value: "lead capture" },
      { label: "Theme", value: "Studio · custom font" },
    ],
  },
  {
    layout: "mockup",
    slug: "ProductLinksModule",
    title: "Smart short links.",
    subtitle: "Branded URLs with rules, pixels, and analytics.",
    bullets: ["Routing by geo, device, time", "Pixels in one click", "QR for every link"],
    mockTitle: "1inme.co/spring-drop",
    mock: [
      { label: "Destination", value: "shop.example.com/spring" },
      { label: "Rules", value: "US → US store" },
      { label: "Pixels", value: "Meta · TikTok · Google" },
      { label: "Splash", value: "Spring Drop" },
    ],
  },
  {
    layout: "mockup",
    slug: "ProductAiModule",
    title: "AI suite.",
    subtitle: "Companions, AskCoach, Voice, and Card Scanner.",
    bullets: ["Custom personalities", "Knowledge from your Minds", "Hands-free on mobile"],
    mockTitle: "Companion · Sienna",
    mock: [
      { label: "Personality", value: "Warm, witty, brief" },
      { label: "Mind", value: "Brand voice + product docs" },
      { label: "Tools", value: "CRM, Calendar, Vault" },
      { label: "Channels", value: "Web · iOS · Android" },
    ],
  },
  {
    layout: "mockup",
    slug: "ProductProductivityModule",
    title: "Productivity & CRM.",
    subtitle: "Vault, Tasks, Forms, Calendar, CRM, Resume.",
    bullets: ["One contact, one source of truth", "Bookings ↔ deals", "Vault under role-based access"],
    mockTitle: "Pipeline · this week",
    mock: [
      { label: "New", value: "12 contacts" },
      { label: "Qualified", value: "8 deals" },
      { label: "Closed-won", value: "$12,400" },
      { label: "Tasks", value: "9 due today" },
    ],
  },
  {
    layout: "mockup",
    slug: "ProductSocialModule",
    title: "Social & community.",
    subtitle: "Native creator feed plus cross-posting.",
    bullets: ["Members-only content", "Per-platform previews", "Best-time scheduling"],
    mockTitle: "Compose · cross-post",
    mock: [
      { label: "Networks", value: "IG · TikTok · X · Feed" },
      { label: "Schedule", value: "Thu 9:00 (best time)" },
      { label: "Analytics", value: "rolled up across platforms" },
      { label: "Members tier", value: "early access · 24h" },
    ],
  },
  {
    layout: "mockup",
    slug: "ProductMobileModule",
    title: "Mobile-first tools.",
    subtitle: "NFC cards, smart dialer, on-the-go capture.",
    bullets: ["Tap to share contact", "Click-to-call with deal context", "Card scanner to CRM"],
    mockTitle: "Mobile · today",
    mock: [
      { label: "NFC card", value: "tap-ready" },
      { label: "Dialer", value: "3 calls scheduled" },
      { label: "Scanner", value: "12 cards captured" },
      { label: "Voice", value: "8 notes synced" },
    ],
  },
  {
    layout: "mockup",
    slug: "ProductAnalyticsModule",
    title: "Analytics & insights.",
    subtitle: "From impression to outcome, across every module.",
    bullets: ["Funnel and audience views", "Workspace and brand splits", "Exports + alerts"],
    mockTitle: "Funnel · April",
    mock: [
      { label: "Bio views", value: "84,210" },
      { label: "Link clicks", value: "12,480" },
      { label: "Form submits", value: "612" },
      { label: "Deals created", value: "47" },
    ],
  },
  {
    layout: "bullets",
    slug: "ProductPlatformPromise",
    title: "The platform promise.",
    bullets: [
      { title: "Open", body: "Every module is API-addressable." },
      { title: "Composable", body: "Use only what you need; turn modules off per workspace." },
      { title: "Honest", body: "Pricing, AI usage, and storage shown plainly." },
      { title: "Yours", body: "Export everything, on every plan, anytime." },
    ],
  },
  {
    layout: "closing",
    slug: "ProductCta",
    title: "Product CTA",
    description: "Product section call to action.",
    eyebrow: "Product section CTA",
    titleA: "Want a guided demo?",
    subtitle: "We tailor every product walkthrough to the modules and integrations you actually need.",
    contacts: [
      { label: "Demo", value: "1inme.com/demo" },
      { label: "Docs", value: "docs.1inme.com" },
      { label: "Email", value: "product@1inme.com" },
    ],
  },
];
spec.push(...product);

// 5. FEATURE CATEGORY MINI-DECKS
// Top-level features divider
spec.push({
  layout: "divider",
  slug: "FeaturesDivider",
  title: "Appendix · Feature deep-dives.",
  subtitle: "Nine module mini-decks, each opened by its own appendix divider.",
  eyebrow: "Section 03",
  range: "Slides 46 – 80",
});

for (const cat of FEATURE_CATEGORIES) {
  spec.push({
    layout: "divider",
    slug: `${slugify(cat.name)}Divider`,
    title: `Feature deep-dive · ${cat.name}.`,
    subtitle: cat.subtitle,
    eyebrow: "Feature appendix",
    range: "4 slides",
    notes: `Open the ${cat.name} deep-dive. Four slides — overview, capabilities, in-product mockup, use case. Skip ahead if the buyer has already seen the module; linger if it's the one they came for.`,
  });
  spec.push({
    layout: "bullets",
    slug: `${slugify(cat.name)}Overview`,
    title: `${cat.name} — overview.`,
    subtitle: cat.subtitle,
    bullets: cat.capabilities,
    notes: `Walk the ${cat.name} capability list at a steady pace. Two sentences per item — what it does, who it helps. Invite questions before moving on; this is where buyers usually interrupt.`,
  });
  spec.push({
    layout: "cards",
    slug: `${slugify(cat.name)}Capabilities`,
    title: `${cat.name} — key capabilities.`,
    cols: 4,
    cards: cat.capabilities.map((c) => ({ tag: "Capability", title: c.title, body: c.body })),
    notes: `Four capability cards for ${cat.name}. Read the title, give one concrete example from a real customer, move on. Don't dwell — the next slide shows it in product.`,
  });
  spec.push({
    layout: "mockup",
    slug: `${slugify(cat.name)}Mockup`,
    title: `${cat.name} — in product.`,
    subtitle: "Editable mockup. Update copy and numbers in this slide file.",
    bullets: ["Replace these bullets with talking points", "Numbers below are placeholders", "Swap mock title for screenshot caption"],
    mockTitle: cat.mock.title,
    mock: cat.mock.rows,
    mockFooter: cat.mock.footer,
    notes: `Show ${cat.name} in product. Point at the rows in "${cat.mock.title}", narrate what the user is doing, and land the value with the footer caption. If you have a live demo, this is the slide to switch into the product.`,
  });
  spec.push({
    layout: "bullets",
    slug: `${slugify(cat.name)}UseCase`,
    title: cat.useCase.title,
    subtitle: "A concrete walk-through using only this category's modules.",
    bullets: cat.useCase.bullets.map((b) => ({ title: b, body: "" })),
    notes: `Walk the "${cat.useCase.title}" scenario end-to-end. Stay inside ${cat.name} — no cross-references to other modules yet. The point: this category alone delivers a complete outcome.`,
  });
}

// 6. PERSONA MINI-DECKS
spec.push({
  layout: "divider",
  slug: "PersonasDivider",
  title: "Appendix · Persona decks.",
  subtitle: "How Sayzio shows up for each role we sell into.",
  eyebrow: "Section 04",
  range: "Slides 82 – 135",
});

for (const p of PERSONAS) {
  const ns = slugify(`${p.group} ${p.name}`);
  const lower = p.name.toLowerCase();
  spec.push({
    layout: "divider",
    slug: `${ns}Divider`,
    title: `How Sayzio helps a ${lower}.`,
    subtitle: `${p.group} · 4 slides + this divider.`,
    eyebrow: "Persona appendix",
    range: "4 slides",
    notes: `Open the ${lower} mini-deck (${p.group}). Pains, stack, day in the life, outcomes — four slides. Use this when ${lower} are in the room or you're tailoring an outbound for that audience.`,
  });
  spec.push({
    layout: "bullets",
    slug: `${ns}IntroPains`,
    title: `Meet the ${lower}.`,
    subtitle: `${p.group}. Here are the top pains we hear over and over.`,
    bullets: p.pains.map((pain) => ({ title: pain, body: "" })),
    aside: {
      eyebrow: "Why now",
      title: "Their stack hit a wall.",
      body: "More tools is no longer the answer. They want one home that respects their craft.",
    },
    notes: `Read the three pains aloud and watch the audience nod — that's the buying signal. Land the "why now" aside last: more tools isn't the answer for ${lower} anymore.`,
  });
  spec.push({
    layout: "cards",
    slug: `${ns}Stack`,
    title: `The Sayzio stack for a ${lower}.`,
    subtitle: "The 4–5 modules that matter most for this persona.",
    cols: Math.min(p.modules.length, 5),
    cards: p.modules.map((m) => ({ tag: "Module", title: m, body: "" })),
    notes: `These are the ${p.modules.length} modules that matter most for ${lower}. Skip everything else for this conversation — focus is the value here. Mention each module by the customer outcome, not the feature name.`,
  });
  spec.push({
    layout: "dayInLife",
    slug: `${ns}Day`,
    title: `A day in the life — ${lower}.`,
    subtitle: "Time, module, action — all happening inside Sayzio.",
    steps: p.day,
    notes: `Walk this day chronologically from ${p.day[0].time} to ${p.day[p.day.length - 1].time}. Hammer the point: every step happens inside Sayzio — no app switching, no copy-paste, no lost context.`,
  });
  spec.push({
    layout: "metrics",
    slug: `${ns}Outcomes`,
    title: `Outcomes a ${lower} can expect.`,
    subtitle: "Placeholders pulled from early-access cohorts. Replace with your real customer numbers.",
    metrics: p.outcomes,
    note: `CTA · 1inme.com/${lower.replace(/\s+/g, "-")}`,
    notes: `Three outcomes ${lower} can expect — early-access cohort numbers, so flag them as illustrative until you have a real customer story. Close on the persona-specific CTA URL at the bottom.`,
  });
}

// 7. INVESTOR PITCH (divider + 20)
spec.push({
  layout: "divider",
  slug: "InvestorDivider",
  title: "Appendix · Investor pitch.",
  subtitle: "Twenty slides for investor conversations. Numbers are placeholders unless tagged otherwise.",
  eyebrow: "Section 05",
  range: "Slides 137 – 156",
});

const investor = [
  {
    layout: "cover",
    slug: "InvestorCover",
    title: "Investor cover",
    description: "Investor deck cover slide.",
    eyebrow: "Investor pitch · 2026",
    titleA: "Sayzio.",
    titleB: "The everything platform",
    titleC: "for the creator economy.",
    subtitle: "Series A teaser · placeholder figures throughout.",
  },
  {
    layout: "bullets",
    slug: "InvestorVision",
    title: "Vision.",
    subtitle: "Replace fragmented tooling with a single identity-first platform for every creator and team.",
    bullets: [
      { title: "Identity is the new homepage", body: "1inme.com/you replaces every link in bio." },
      { title: "AI is the new operating system", body: "Companions automate the work between modules." },
      { title: "One bill, one brand, one home", body: "From first link to last invoice." },
    ],
  },
  {
    layout: "metrics",
    slug: "InvestorProblem",
    title: "Problem.",
    metrics: [
      { value: "9", label: "tools per active creator" },
      { value: "$214", label: "stack cost / month" },
      { value: "2.4h", label: "lost daily to context switching" },
      { value: "37%", label: "of features ever used" },
    ],
    note: "Placeholder market figures. Swap for verified secondary research before sending.",
  },
  {
    layout: "metrics",
    slug: "InvestorMarket",
    title: "Market.",
    subtitle: "TAM / SAM / SOM placeholders.",
    metrics: [
      { value: "$104B", label: "TAM · creator + SMB tools" },
      { value: "$28B", label: "SAM · identity, links, CRM, AI" },
      { value: "$3.2B", label: "SOM · 5y addressable" },
      { value: "+18%", label: "CAGR" },
    ],
    note: "Replace with sourced figures (Statista / Gartner / internal) before sharing.",
  },
  {
    layout: "cards",
    slug: "InvestorSolution",
    title: "Solution.",
    subtitle: "Identity, links, AI, productivity, and analytics — one platform.",
    cards: [
      { tag: "Surface", title: "Web · Mobile · API", body: "One identity, three interfaces." },
      { tag: "Spine", title: "Identity + Vault + AI", body: "Shared by every module." },
      { tag: "Modules", title: "11 connected tools", body: "From Link in Bio to billing." },
    ],
  },
  {
    layout: "mockup",
    slug: "InvestorDemoHighlights",
    title: "Product highlights.",
    subtitle: "Three demos investors remember.",
    bullets: ["AskCoach grounded in your data", "Smart Links + Pixels + CRM in one click", "Cross-module funnel view"],
    mockTitle: "Demo storyline",
    mock: [
      { label: "Open", value: "Bio + AskCoach side by side" },
      { label: "Wire", value: "Smart Link + pixel + CRM" },
      { label: "Reveal", value: "Funnel: impression → revenue" },
      { label: "Close", value: "All in 5 minutes" },
    ],
  },
  {
    layout: "bullets",
    slug: "InvestorBusinessModel",
    title: "Business model.",
    bullets: [
      { title: "SaaS subscriptions", body: "Free → Pro → Studio → Business." },
      { title: "AI credit packs", body: "Pooled, transparent, top-up on demand." },
      { title: "White-label & enterprise", body: "Per-workspace pricing for agencies and teams." },
      { title: "Affiliate + referral", body: "Network-driven distribution flywheel." },
    ],
  },
  {
    layout: "pricing",
    slug: "InvestorPricing",
    title: "Pricing tiers.",
    tiers: [
      { name: "Free", price: "$0", cadence: "forever", features: ["Acquisition layer", "Conversion to Pro"] },
      { name: "Pro", price: "$12", cadence: "per month", features: ["Power individuals", "Highest LTV / CAC ratio"] },
      { name: "Studio", price: "$29", cadence: "per month", popular: true, features: ["Small teams", "Modal price point"] },
      { name: "Business", price: "$99+", cadence: "per month", features: ["Agencies, enterprise", "Highest ARPA"] },
    ],
  },
  {
    layout: "cards",
    slug: "InvestorGtm",
    title: "Go-to-market.",
    cards: [
      { tag: "PLG", title: "Self-serve top of funnel", body: "Free plan + viral Link in Bio pages + creator referrals." },
      { tag: "Community", title: "Creator partnerships", body: "Distribution through trusted creator networks." },
      { tag: "Sales", title: "Mid-market sales motion", body: "Inbound from PLG, expansion via white label." },
      { tag: "Affiliates", title: "Revenue-share network", body: "Recurring incentives for distribution." },
    ],
  },
  {
    layout: "metrics",
    slug: "InvestorTraction",
    title: "Traction (placeholders).",
    metrics: [
      { value: "[ x ]k", label: "registered users" },
      { value: "[ x ]k", label: "WAU" },
      { value: "[ $x ]k", label: "MRR" },
      { value: "+[x]%", label: "MoM growth" },
    ],
    note: "Replace with the latest numbers before sending. Mark as confidential.",
  },
  {
    layout: "cards",
    slug: "InvestorCompetitive",
    title: "Competitive landscape.",
    cols: 4,
    cards: [
      { tag: "Bio", title: "Linktree · Beacons", body: "Single-feature; no spine to expand." },
      { tag: "Links", title: "Bitly · Rebrandly", body: "Branded links, but no identity layer." },
      { tag: "CRM", title: "HubSpot · Pipedrive", body: "Heavy, business-only, expensive." },
      { tag: "AI", title: "ChatGPT · Notion AI", body: "General-purpose, not grounded in your stack." },
    ],
  },
  {
    layout: "bullets",
    slug: "InvestorMoat",
    title: "Moat.",
    bullets: [
      { title: "Identity gravity", body: "1inme.com/you is sticky once it's printed and shared." },
      { title: "Cross-module data", body: "AI grounded in CRM + Vault + Calendar is non-trivial to copy." },
      { title: "Network effects", body: "Bio + cross-posting + referrals reinforce each other." },
      { title: "Workspace lock-in", body: "Agencies host clients; switching is painful for everyone." },
    ],
  },
  {
    layout: "cards",
    slug: "InvestorTeam",
    title: "Team.",
    subtitle: "Replace placeholders with founder bios and pictures.",
    cols: 4,
    cards: [
      { tag: "CEO", title: "[ Founder name ]", body: "Background · prior exits / scale roles." },
      { tag: "CTO", title: "[ Founder name ]", body: "Background · platform engineering." },
      { tag: "CPO", title: "[ Founder name ]", body: "Background · creator products." },
      { tag: "GTM", title: "[ Hire ]", body: "Hiring · Series A go-to-market lead." },
    ],
  },
  {
    layout: "quarters",
    slug: "InvestorFinancials",
    title: "Financials & projections (placeholders).",
    subtitle: "Replace with the live model before sending.",
    headers: ["Metric", "Y1", "Y2", "Y3", "Y4"],
    rows: [
      { theme: "ARR", quarters: ["$1.2M", "$5.4M", "$14M", "$32M"] },
      { theme: "Gross margin", quarters: ["72%", "78%", "81%", "82%"] },
      { theme: "Net revenue retention", quarters: ["108%", "118%", "125%", "128%"] },
      { theme: "Burn multiple", quarters: ["1.8", "1.2", "0.8", "0.6"] },
    ],
  },
  {
    layout: "metrics",
    slug: "InvestorAsk",
    title: "Funding ask.",
    metrics: [
      { value: "$[ X ]M", label: "Series A" },
      { value: "[ X ]%", label: "equity" },
      { value: "[ X ]mo", label: "runway" },
      { value: "$[ Y ]M", label: "post-money cap" },
    ],
    note: "Update with the live round structure.",
  },
  {
    layout: "cards",
    slug: "InvestorUseOfFunds",
    title: "Use of funds.",
    cards: [
      { tag: "40%", title: "Product & engineering", body: "AI, integrations, API depth, mobile parity." },
      { tag: "30%", title: "Go-to-market", body: "Sales, partnerships, lifecycle marketing." },
      { tag: "20%", title: "Customer success & support", body: "White-glove onboarding for Studio + Business." },
      { tag: "10%", title: "G&A", body: "Compliance, finance, hiring engine." },
    ],
  },
  {
    layout: "timeline",
    slug: "InvestorMilestones",
    title: "Milestones.",
    subtitle: "What we'll prove with this round.",
    columns: [
      { label: "Quarter 1", title: "Pricing + PLG funnel", items: ["New pricing live", "Self-serve to Pro upgrade", "Activation up 25%"] },
      { label: "Quarter 2", title: "AI + integrations", items: ["Companions GA", "10 native integrations", "AskCoach 2.0"] },
      { label: "Quarter 3", title: "Workspaces + white label", items: ["Agency tier", "SSO + SCIM", "First 50 white-label deals"] },
      { label: "Quarter 4", title: "Mobile + enterprise", items: ["Mobile parity", "SOC 2 Type II", "First enterprise wins"] },
    ],
  },
  {
    layout: "bullets",
    slug: "InvestorExitVision",
    title: "Exit & long-term vision.",
    bullets: [
      { title: "Path 1 · Strategic", body: "Adjacent platforms (creator, CRM, comms) acquiring the identity layer." },
      { title: "Path 2 · Growth", body: "Continue compounding to a category-defining platform." },
      { title: "Path 3 · IPO", body: "Long-term scenario at $200M+ ARR with healthy margins." },
    ],
  },
  {
    layout: "metrics",
    slug: "InvestorWhyNow",
    title: "Why now.",
    metrics: [
      { value: "AI", label: "credible across every workflow" },
      { value: "Tools", label: "fatigue at all-time high" },
      { value: "Identity", label: "becoming the homepage" },
      { value: "Mobile", label: "parity finally possible" },
    ],
  },
  {
    layout: "closing",
    slug: "InvestorClose",
    title: "Investor close",
    description: "Investor pitch closing slide.",
    eyebrow: "Thank you",
    titleA: "Let's build the everything platform.",
    subtitle: "Happy to share the live model, customer references, and a deeper product session.",
    contacts: [
      { label: "Founder", value: "[ name ]" },
      { label: "Email", value: "investors@1inme.com" },
      { label: "Data room", value: "1inme.com/dataroom" },
    ],
  },
];
spec.push(...investor);

// 8. ROADMAP (divider + 20)
spec.push({
  layout: "divider",
  slug: "RoadmapDivider",
  title: "Appendix · Future roadmap.",
  subtitle: "Twenty slides on what's coming, by horizon and by area.",
  eyebrow: "Section 06",
  range: "Slides 158 – 177",
});

const roadmap = [
  {
    layout: "bullets",
    slug: "RoadmapIntro",
    title: "How we plan the roadmap.",
    bullets: [
      { title: "Now", body: "Shipping this quarter. Visible in the product." },
      { title: "Next", body: "In active build. Scoped, validated, dated to the quarter." },
      { title: "Later", body: "On the horizon. Direction set, details flexible." },
      { title: "Always", body: "Performance, reliability, security, accessibility." },
    ],
  },
  {
    layout: "timeline",
    slug: "RoadmapHorizons",
    title: "Now / Next / Later — at a glance.",
    columns: [
      { label: "Now", title: "Shipping this quarter", items: ["Companions 2.0", "Smart Links rules editor v2", "Mobile NFC card v2", "Workspace audit log exports"] },
      { label: "Next", title: "Active build", items: ["AskCoach 3.0 · multi-mind", "AI Voice GA", "Bio Pages AB testing", "Stripe Connect for bookings"] },
      { label: "Later", title: "Direction set", items: ["Marketplace for templates", "International payments", "Public Companion API", "On-device inference"] },
    ],
  },
  {
    layout: "timeline",
    slug: "RoadmapAi",
    title: "Roadmap · AI.",
    columns: [
      { label: "Now", title: "Companions 2.0", items: ["Workspace-shared", "Tools: CRM + Calendar", "Browser side panel"] },
      { label: "Next", title: "AskCoach 3.0", items: ["Multi-mind reasoning", "Citations everywhere", "Voice → answer round-trip"] },
      { label: "Later", title: "Open AI surface", items: ["Public Companion API", "Custom tools by partners", "On-device inference"] },
    ],
  },
  {
    layout: "timeline",
    slug: "RoadmapBio",
    title: "Roadmap · Link in Bio & Smart Links.",
    columns: [
      { label: "Now", title: "Editor v3", items: ["AB testing on blocks", "Block library refresh", "Faster live preview"] },
      { label: "Next", title: "Smart routing v3", items: ["Visual rule editor", "Per-rule analytics", "ML-suggested rules"] },
      { label: "Later", title: "Programmable bios", items: ["Composable blocks", "Marketplace for blocks", "Bio API"] },
    ],
  },
  {
    layout: "timeline",
    slug: "RoadmapProductivity",
    title: "Roadmap · Productivity & CRM.",
    columns: [
      { label: "Now", title: "Pipeline 2.0", items: ["Custom stages", "Bulk actions", "Saved views"] },
      { label: "Next", title: "Vault sharing 2.0", items: ["Time-bound shares", "Per-asset audit trail", "External recipient flows"] },
      { label: "Later", title: "Forms 2.0", items: ["Conditional logic", "Multi-step", "Embeddable widgets"] },
    ],
  },
  {
    layout: "timeline",
    slug: "RoadmapSocial",
    title: "Roadmap · Social & community.",
    columns: [
      { label: "Now", title: "Cross-post v2", items: ["Per-network previews", "Hashtag swap", "Best-time picks"] },
      { label: "Next", title: "Members tier", items: ["Paid memberships", "Members-only feeds", "Private AMAs"] },
      { label: "Later", title: "Community moments", items: ["Live drops", "Polls + reactions", "Creator collabs"] },
    ],
  },
  {
    layout: "timeline",
    slug: "RoadmapMobile",
    title: "Roadmap · Mobile.",
    columns: [
      { label: "Now", title: "NFC v2", items: ["Re-programmable", "Multi-card per account", "Apple Wallet pass"] },
      { label: "Next", title: "Smart Dialer 2.0", items: ["Two-way call sync", "Live transcription", "Power dialer"] },
      { label: "Later", title: "Mobile parity", items: ["Bio editor on mobile", "Companion voice always-on", "Offline-first sync"] },
    ],
  },
  {
    layout: "timeline",
    slug: "RoadmapPlatformApi",
    title: "Roadmap · Platform & API.",
    columns: [
      { label: "Now", title: "Webhook v2", items: ["Granular topics", "Replay & debug", "Signed payloads"] },
      { label: "Next", title: "API v2", items: ["GraphQL surface", "Per-module SDKs", "Sandbox tokens"] },
      { label: "Later", title: "Composable apps", items: ["In-product app store", "Partner-built modules", "Customer-built workflows"] },
    ],
  },
  {
    layout: "timeline",
    slug: "RoadmapEnterprise",
    title: "Roadmap · Enterprise.",
    columns: [
      { label: "Now", title: "SSO + SCIM", items: ["Okta · Azure AD · Google", "Just-in-time provisioning", "Group sync"] },
      { label: "Next", title: "Workspace federation", items: ["Multi-org structures", "Shared brand kits", "Cross-workspace audit"] },
      { label: "Later", title: "Custom contracts", items: ["Data residency choice", "BYO key", "Custom audit retention"] },
    ],
  },
  {
    layout: "timeline",
    slug: "RoadmapCompliance",
    title: "Roadmap · Security & compliance.",
    columns: [
      { label: "Now", title: "SOC 2 Type II", items: ["Active audit", "Evidence pack on request", "Quarterly pentests"] },
      { label: "Next", title: "ISO 27001", items: ["Scope finalised", "Gap analysis underway", "Target: end of year"] },
      { label: "Later", title: "Regional", items: ["EU residency", "UK data zone", "APAC roadmap"] },
    ],
  },
  {
    layout: "timeline",
    slug: "RoadmapInternationalization",
    title: "Roadmap · Internationalization.",
    columns: [
      { label: "Now", title: "Localized UI", items: ["EN · ES · PT · FR · DE", "Date / number locales", "RTL ready"] },
      { label: "Next", title: "Local payments", items: ["EU SEPA", "BR Pix", "MX SPEI"] },
      { label: "Later", title: "Local content", items: ["Region-specific templates", "Local currency analytics", "In-region support"] },
    ],
  },
  {
    layout: "timeline",
    slug: "RoadmapMarketplace",
    title: "Roadmap · Marketplace.",
    columns: [
      { label: "Now", title: "Templates v1", items: ["Bio templates", "Form templates", "Companion personalities"] },
      { label: "Next", title: "Paid templates", items: ["Creator payouts", "Reviews + ratings", "Featured collections"] },
      { label: "Later", title: "Apps marketplace", items: ["Partner-built apps", "Revenue share", "Certified integrations"] },
    ],
  },
  {
    layout: "timeline",
    slug: "RoadmapAnalytics",
    title: "Roadmap · Analytics.",
    columns: [
      { label: "Now", title: "Funnels v2", items: ["Drag-and-drop steps", "Per-step audience", "Saved + scheduled"] },
      { label: "Next", title: "Cohorts", items: ["Returning users", "Behavioural cohorts", "Cohort exports"] },
      { label: "Later", title: "Data pipeline", items: ["Warehouse exports", "BI partner connectors", "Custom dashboards"] },
    ],
  },
  {
    layout: "timeline",
    slug: "RoadmapBrand",
    title: "Roadmap · Brand & design system.",
    columns: [
      { label: "Now", title: "Brand kits", items: ["Per-workspace kits", "Logo + color tokens", "Font hosting"] },
      { label: "Next", title: "Theme studio", items: ["Visual theme editor", "Bio + email parity", "Dark / light variants"] },
      { label: "Later", title: "Design API", items: ["Programmatic theming", "Tokens via API", "Partner-built themes"] },
    ],
  },
  {
    layout: "timeline",
    slug: "RoadmapIntegrations",
    title: "Roadmap · Integrations.",
    columns: [
      { label: "Now", title: "Top 20", items: ["HubSpot", "Salesforce", "Slack", "Stripe"] },
      { label: "Next", title: "Top 50", items: ["Notion", "Airtable", "Mailchimp", "Webflow"] },
      { label: "Later", title: "Long tail", items: ["Hundreds via Zapier", "Custom Make scenarios", "Embedded iframes"] },
    ],
  },
  {
    layout: "quarters",
    slug: "RoadmapQuarterlyTimeline",
    title: "Quarterly timeline.",
    subtitle: "What ships when, across the platform.",
    headers: ["Theme", "Q1", "Q2", "Q3", "Q4"],
    rows: [
      { theme: "AI", quarters: ["Companions 2.0", "AskCoach 3.0", "Voice GA", "Open Companion API"] },
      { theme: "Bio + Links", quarters: ["Editor v3", "Routing v3", "Bio AB tests", "Programmable bios"] },
      { theme: "Productivity", quarters: ["Pipeline 2.0", "Vault sharing 2.0", "Forms 2.0", "Workflows 2.0"] },
      { theme: "Mobile", quarters: ["NFC v2", "Dialer 2.0", "Mobile bio editor", "Offline-first"] },
      { theme: "Enterprise", quarters: ["SSO + SCIM", "Workspace federation", "SOC 2 Type II", "Data residency"] },
      { theme: "Marketplace", quarters: ["Templates v1", "Paid templates", "Apps · alpha", "Apps · GA"] },
    ],
  },
  {
    layout: "cards",
    slug: "RoadmapPrinciples",
    title: "How we choose what to ship.",
    cards: [
      { tag: "Honest", title: "Visible roadmap", body: "Public Now / Next / Later." },
      { tag: "Open", title: "Customer-driven", body: "Quarterly votes from active workspaces." },
      { tag: "Realistic", title: "Quarter granularity", body: "We commit to quarters, not weeks." },
      { tag: "Bold", title: "Big bets named", body: "Marketplace, API, mobile parity, enterprise." },
    ],
  },
  {
    layout: "metrics",
    slug: "RoadmapInvestmentSplit",
    title: "Where the engineering hours go.",
    metrics: [
      { value: "40%", label: "AI + integrations" },
      { value: "25%", label: "core modules" },
      { value: "20%", label: "platform + API" },
      { value: "15%", label: "performance + reliability" },
    ],
  },
  {
    layout: "bullets",
    slug: "RoadmapCustomerInfluence",
    title: "How customers shape the roadmap.",
    bullets: [
      { title: "Public requests", body: "1inme.com/roadmap — vote and comment." },
      { title: "Quarterly council", body: "Customer council reviews the next quarter." },
      { title: "Beta program", body: "Early access weeks before GA." },
      { title: "Office hours", body: "Weekly product office hours, open to all paid plans." },
    ],
  },
  {
    layout: "closing",
    slug: "RoadmapShape",
    title: "Roadmap close",
    description: "Roadmap closing slide.",
    eyebrow: "Shape it with us",
    titleA: "Help us build what's next.",
    subtitle: "Vote on the public roadmap, join the customer council, or talk to product directly.",
    contacts: [
      { label: "Roadmap", value: "1inme.com/roadmap" },
      { label: "Council", value: "council@1inme.com" },
      { label: "Office hours", value: "1inme.com/office-hours" },
    ],
  },
];
spec.push(...roadmap);

// Helper used in this file too
function slugify(s) {
  return s
    .replace(/[^a-zA-Z0-9 ]/g, "")
    .split(/\s+/)
    .filter(Boolean)
    .map((w) => w[0].toUpperCase() + w.slice(1).toLowerCase())
    .join("")
    .slice(0, 28);
}

// ---------- Speaker notes (live presentation talk-track) ----------
// Keyed by slide slug. Notes for the dynamic feature/persona mini-decks
// are attached inline above (inside their generation loops).
const NOTES = {
  // Cover + TOC
  Cover:
    "Open the deck. One identity, one platform — that's the whole pitch in a sentence. Tell the room which section you'll spend most of the time in, then jump straight there from the table of contents.",
  TableOfContents:
    "Six sections, appendix-separated by dividers. Tell the audience to interrupt and pick whichever section matters most — the dividers act as bookmarks, so you can jump without losing your place.",

  // Top-level section dividers
  SalesDivider:
    "Shift into the sales pitch. Twenty slides from problem to next step — feel free to skip the proof slides if the buyer is already convinced. Set expectations: this section ends with a concrete CTA.",
  ProductDivider:
    "Shift into the product walkthrough. Twenty slides covering web, mobile, API, every module, and the platform layer. Tell the audience to flag the modules they want to go deep on.",
  FeaturesDivider:
    "Open the feature deep-dives. Nine module mini-decks, each four slides. Only present the ones the buyer asked about — the rest are appendix material.",
  PersonasDivider:
    "Open the persona decks. Twelve roles, each with pains, stack, day in the life, and outcomes. Pick the one closest to the room; the others are there for follow-up sends.",
  InvestorDivider:
    "Shift into the investor pitch. Twenty slides — vision, market, model, traction, ask. Anything tagged 'placeholder' must be replaced with live numbers before sending this section out.",
  RoadmapDivider:
    "Shift into the roadmap section. Twenty slides organised as Now / Next / Later, broken down by area and rolled up by quarter. Be honest about what's GA versus directional.",

  // Sales section (20)
  SalesProblem:
    "Open with the numbers behind the pain. Read the nine-tools stat first, let it sit, then move through cost, usage, and lost hours. The point: this is real money and real time bleeding out today, not a future risk.",
  SalesCost:
    "Translate the numbers into business cost. Hours, data, brand, revenue — frame each as something the buyer's team will feel this quarter, not abstractly someday. Pause after 'lost revenue' and ask which one stings most.",
  SalesPitch:
    "Pivot to the Sayzio answer. Identity, tools, AI, insight — four cards, one platform. Don't list features yet; sell the shape: one home instead of nine browser tabs.",
  SalesDifferentiators:
    "Pre-empt the 'why not just use a bundle?' objection. Native, beautiful, open, fair — these are the four things bundles structurally can't fake. Mention a competitor by name only if the buyer brings them up.",
  SalesRoi:
    "Make the ROI math obvious: about $1,400 saved annually, six hours back per week, conversion lift, single invoice. Anchor the buyer to a payback window measured in weeks, not quarters.",
  SalesTimeSaved:
    "Walk the time-savings table left to right. Bio maintenance, lead capture, cross-posting, reporting — call out the biggest win for the buyer's role first. Land the seven-hours-per-week footer total.",
  SalesProofQuote:
    "Read the quote slowly. Pause on 'shaved a workday off every week'. Mention this is a real early-access customer and offer to set up a reference call after this meeting.",
  SalesProofLogos:
    "If logos are still placeholders, acknowledge it and skip ahead. Otherwise, point to the closest analogue to the buyer in the room — same size, same vertical — and offer to introduce them.",
  SalesProofMetrics:
    "Frame these as 90-day expectations, not promises. Six tools removed and a 24% lift in lead-to-close are the lines that move buying committees — repeat them twice.",
  SalesPricingSnapshot:
    "Anchor on Studio first — most teams land here. Free is for evaluation, Pro is for power individuals, Business is for compliance and scale. Call out the 20% annual discount before someone asks.",
  SalesObjections:
    "Run the four objections proactively. The buyer is already thinking them; saying them out loud builds trust. Keep each answer to one sentence and move on.",
  SalesCompetitors:
    "Position relative to the specific tool the buyer mentioned in discovery. We're additive to most of these and replacing one or two — be honest about which is which.",
  SalesSecurityTrust:
    "This is the slide for IT and legal — slow down here if either is in the room. MFA, SSO, SCIM, audit, SOC 2 trajectory. Offer the evidence pack on request.",
  SalesProductSnapshot:
    "Show what the buyer's workspace looks like on day one — not in three months. Live bio page, trained companion, imported pipeline, working funnel. The point: time-to-value is hours.",
  SalesUseCases:
    "Three outcomes we underwrite. Pick the one that matches the buyer's stated goal from discovery and spend most of the slide there. Skim the other two unless asked.",
  SalesImplementation:
    "Walk the four-week plan. The point is predictability: by the end of week four the ROI snapshot is in their inbox, not promised vaguely for 'soon'.",
  SalesSocialProofExpanded:
    "Customer stories — replace placeholders with the closest real match before sending. Lead each card with the result, then the customer name. If you don't have a story, leave the card off.",
  SalesGuarantee:
    "These four risk-removers are designed to make 'yes' the safe answer. 30-day money-back is the headline; mention white-glove migration is included free on Studio and up.",
  SalesNextSteps:
    "Tell the buyer exactly what happens next. The four-step ladder gives them an easy first 'yes' — start with the 30-minute walkthrough and book it before you leave the call.",
  SalesCta:
    "Close. Pick a slot together on screen; don't leave with 'we'll send a calendar link' — that's where deals stall. Capture the email and book the next meeting live.",

  // Product section (20)
  ProductOverview:
    "Set up the product walkthrough. Three surfaces, eleven modules, identity + vault + AI as the spine. Audience leaves this slide knowing the shape of the platform.",
  ProductWebTour:
    "Tour the web app: sidebar, drag-and-drop editor, AskCoach side panel, workspace switcher in the footer. Emphasise this is the full-power surface — everything works here first.",
  ProductMobileTour:
    "Now the mobile app. NFC tap-to-share, smart dialer with deal context, voice capture between meetings, push notifications for leads and bookings. Designed for field work.",
  ProductApiSdk:
    "Show developers we're serious. Every capability is a REST endpoint with a webhook; SDK in TypeScript. Token-scoped and audit-logged. The mock is a real /v1/links call.",
  ProductUserJourney:
    "Walk signup → build → convert → operate. Time-to-value sits between steps two and three; brand kit detection at signup is the moment most prospects say 'oh, this is different'.",
  ProductCrossModule:
    "Show what happens when modules know about each other. Bio → CRM, Smart Link → Pixel, Calendar → Vault, AI → everything. This is the slide that justifies the 'platform' word.",
  ProductIdentitySpine:
    "Identity is what holds it together. Handle, SSO, brand kit, permissions — every other module inherits from here. Public surface vs. private surface is the mental model to leave them with.",
  ProductSecurityTrust:
    "Re-state the security posture for a technical audience. MFA + passkeys, SSO + SCIM, encryption everywhere, full audit log. Same evidence pack as the sales conversation; offer it on request.",
  ProductAdminWorkspace:
    "Show the admin surface. Workspaces per brand or client, role-based access on every module, white label per workspace, plan and seat management in one place.",
  ProductIntegrationsMap:
    "Eight categories, top-of-mind tools per category. The point: Sayzio doesn't pretend to be your CRM if you already have HubSpot — it sits beside it, two-way synced.",
  ProductOnboardingFlow:
    "Detect, import, wire, launch. Time-to-value measured in hours, not weeks. We bring the data so day one is real, not a blank workspace.",
  ProductBioModule:
    "Open the module deep-dives with bio — the doorway to everything. Drag-and-drop blocks, themes, custom domains, AB tests built in. This is the module most prospects already know.",
  ProductLinksModule:
    "Smart links: routing rules, pixels, QR for every link. The intelligent layer between the click and the destination. Show the rules editor if you have time.",
  ProductAiModule:
    "Companions, AskCoach, Voice, Card Scanner. Custom personalities, grounded in your Minds, hands-free on mobile. The differentiator vs. ChatGPT is grounding — say it explicitly.",
  ProductProductivityModule:
    "One contact, one source of truth. Bookings tied to deals, vault under role-based access, tasks rolling up to the workspace dashboard. The CRM you wish HubSpot was for a small team.",
  ProductSocialModule:
    "Native creator feed plus cross-posting. Members-only content, per-platform previews, best-time scheduling. Two surfaces in one — owned audience and rented audience.",
  ProductMobileModule:
    "Mobile-first tools: NFC card, dialer with deal context on screen, scanner straight to CRM, voice notes that sync. Built for people who close deals away from a desk.",
  ProductAnalyticsModule:
    "Funnels and audience views across every module. Workspace and brand splits. Exports and threshold alerts. The slide that proves we're a platform, not a bundle.",
  ProductPlatformPromise:
    "Open, composable, honest, yours. The four principles we won't break — even when it costs us. Read each one slowly; this is how prospects remember the brand.",
  ProductCta:
    "Close the product section. Offer a tailored demo on the modules they actually care about. Capture the next slot before leaving the call — same playbook as the sales close.",

  // Investor section (20)
  InvestorCover:
    "Open the investor deck. State the thesis in one breath: identity-first everything platform for the creator economy. This is a Series A teaser — set expectations for figures up front.",
  InvestorVision:
    "Three sentences for the vision. Identity is the new homepage. AI is the new operating system. One bill, one brand, one home. Don't elaborate; the next slides do that.",
  InvestorProblem:
    "Land the problem in numbers. Nine tools, $214 a month, 2.4 hours lost daily, 37% of features used. Note explicitly that these are placeholder market figures until the live research is in.",
  InvestorMarket:
    "TAM / SAM / SOM. Be honest these are placeholders unless tagged otherwise — savvy investors will check. Replace with sourced figures (Statista, Gartner, internal) before sending.",
  InvestorSolution:
    "Three cards for the solution: surface, spine, modules. Resist the urge to go module-by-module here — the next sections do that. Land the 'one platform' shape and move on.",
  InvestorDemoHighlights:
    "Tease the demo. Three moments investors remember: AskCoach grounded in your data, Smart Link wiring in one click, the cross-module funnel reveal. Five minutes total.",
  InvestorBusinessModel:
    "Subscriptions, AI credit packs, white-label, affiliate. Four lines of revenue, tilted toward recurring SaaS. AI credits are the wedge into top-up revenue without surprise bills.",
  InvestorPricing:
    "Pricing tiers framed by investor narrative: acquisition (Free), highest LTV/CAC (Pro), modal price point (Studio), highest ARPA (Business). Studio is where most teams land.",
  InvestorGtm:
    "PLG + community + sales + affiliates. A distribution flywheel, not a single channel. Each one feeds the next: Link in Bio pages go viral, creators refer creators, agencies bring teams.",
  InvestorTraction:
    "Replace placeholders with the latest numbers before sending. Mark the slide as confidential when you do. Lead with MoM growth — investors care about velocity more than absolutes early.",
  InvestorCompetitive:
    "Map ourselves against bio, links, CRM, AI. We're the only one with all four under a single identity layer. Avoid bashing competitors — frame us as the integration of their categories.",
  InvestorMoat:
    "Four moats: identity gravity, cross-module data, network effects, workspace lock-in. Each compounds the others — that's the slide-five-minutes-later answer for the partner meeting.",
  InvestorTeam:
    "Replace founder placeholders with bios and pictures before sending. Lead with prior outcomes, not titles. Investors back people first; product second.",
  InvestorFinancials:
    "Walk across rows: ARR, gross margin, NRR, burn multiple. Replace with the live model before sending. Investors will pull this into their own spreadsheet — make sure it ties out.",
  InvestorAsk:
    "Ask, equity, runway, post-money cap. Update with the live round structure before the meeting. Be ready to defend each number with a sentence.",
  InvestorUseOfFunds:
    "40% product, 30% GTM, 20% customer success, 10% G&A. This is a build-and-distribute round, not a fix-and-survive one. Be specific about the first three engineering hires.",
  InvestorMilestones:
    "Quarterly milestones for the round — what we'll prove with this money. Pricing in Q1, AI in Q2, workspaces in Q3, mobile + enterprise in Q4. Each is dated and falsifiable.",
  InvestorExitVision:
    "Three exit paths: strategic, growth, IPO. Don't oversell IPO — lead with credible strategic interest from adjacent platforms. Investors prefer optionality over a single bet.",
  InvestorWhyNow:
    "Four reasons this works in 2026 and didn't in 2022: AI credibility across workflows, tool fatigue at all-time highs, identity becoming the homepage, mobile parity finally possible.",
  InvestorClose:
    "Close the investor deck. Offer the live model, customer references, and a deeper product session. Confirm the next meeting on the call before hanging up.",

  // Roadmap section (20)
  RoadmapIntro:
    "Frame how we plan: Now / Next / Later / Always. Set the expectation that quarters are the granularity, not weeks — that's a feature, not a bug.",
  RoadmapHorizons:
    "The full company roadmap on one slide. Now is shipping this quarter, Next is in active build, Later has direction set with details flexible. This is the slide most prospects screenshot.",
  RoadmapAi:
    "AI roadmap: Companions 2.0 now, AskCoach 3.0 next, an open Companion API later. Be honest about what's GA vs. directional — don't promise on-device inference yet.",
  RoadmapBio:
    "Bio + Smart Links roadmap. Editor v3 now, visual routing v3 next, programmable bios later. The marketplace for blocks is the long-tail bet here.",
  RoadmapProductivity:
    "Productivity & CRM roadmap. Pipeline 2.0 now, Vault sharing 2.0 next, Forms 2.0 later. This is where teams expand seats once they're on Studio.",
  RoadmapSocial:
    "Social & community roadmap: cross-post v2 today, paid memberships next, community moments (live drops, polls, collabs) later. The members tier is the monetisation hook.",
  RoadmapMobile:
    "Mobile roadmap: NFC v2 now, Smart Dialer 2.0 next, full mobile parity later. Apple Wallet pass is the headline announcement for this quarter.",
  RoadmapPlatformApi:
    "Platform & API roadmap. Webhook v2 with replay and signed payloads, GraphQL surface, in-product app store with partner-built modules. This is the developer-relations narrative.",
  RoadmapEnterprise:
    "Enterprise roadmap: SSO + SCIM now (Okta, Azure AD, Google), workspace federation next, custom contracts (data residency, BYO key) later. The slide for the IT buyer.",
  RoadmapCompliance:
    "Security & compliance roadmap. SOC 2 Type II audit is active, ISO 27001 next with end-of-year target, regional data zones (EU, UK, APAC) later.",
  RoadmapInternationalization:
    "i18n roadmap: localized UI in five languages today, local payments (SEPA, Pix, SPEI) next, region-specific content later. RTL is already supported.",
  RoadmapMarketplace:
    "Marketplace roadmap: free templates today, paid templates with creator payouts next, full apps marketplace with revenue share later. This is the long-term platform play.",
  RoadmapAnalytics:
    "Analytics roadmap: Funnels v2 with drag-and-drop steps, behavioural cohorts next, warehouse exports and BI partner connectors later.",
  RoadmapBrand:
    "Brand & design system roadmap: per-workspace brand kits today, visual theme studio next, programmatic Design API later. The white-label foundation.",
  RoadmapIntegrations:
    "Integrations roadmap: top 20 native today, top 50 next, the long tail covered via Zapier and Make. We won't build every connector ourselves — that's a feature.",
  RoadmapQuarterlyTimeline:
    "Read this matrix across the rows, not down the columns. Investors and IT buyers care about pace per area — six themes shipping every quarter is the headline.",
  RoadmapPrinciples:
    "Four principles for choosing what to ship: honest, open, realistic, bold. We commit to quarters, not weeks. Customers vote on the public roadmap — show them the URL.",
  RoadmapInvestmentSplit:
    "Where engineering hours go: 40% AI + integrations, 25% core modules, 20% platform + API, 15% performance and reliability. The split investors expect from a Series A platform.",
  RoadmapCustomerInfluence:
    "Four ways customers shape the roadmap: public requests, quarterly council, beta program, weekly office hours. The point: this isn't a closed roadmap — they have a seat at the table.",
  RoadmapShape:
    "Close the roadmap section. Invite them to vote on the public roadmap, join the customer council, or talk to product directly. One CTA — pick the one that fits the room.",
};

for (const s of spec) {
  if (!s.notes && NOTES[s.slug]) s.notes = NOTES[s.slug];
}

export { spec };
