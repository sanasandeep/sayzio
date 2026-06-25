export interface FeatureCategory {
  id: string;
  name: string;
  intro: string;
  items: { title: string; description: string }[];
}

export const featuresCategories: FeatureCategory[] = [
  {
    id: "link-types",
    name: "Link types — everything you can create",
    intro:
      "One dashboard, ten distinct kinds of link. Pick the format that fits the moment — from a simple short link to a full chat experience — and every one is tracked, themeable, and shareable from a single URL.",
    items: [
      { title: "Short Link", description: "Turn long URLs into clean, branded short links you can repoint anytime, with click analytics, expiry dates and click limits." },
      { title: "Link in Bio", description: "A drag-and-drop one-link landing page with a deep block library, custom themes and a guided setup wizard." },
      { title: "Conversational", description: "A chat-style page that greets visitors and guides them through your links, questions and actions one message at a time." },
      { title: "Slides", description: "A swipeable, story-style page that presents your content as full-screen slides, with background music and transitions." },
      { title: "AI Chatbot", description: "An AI-powered page that answers visitor questions about you using your own content, around the clock." },
      { title: "Restaurant Menu", description: "A digital menu with categories, photos, descriptions and prices — plus optional table-side ordering by QR code." },
      { title: "File Share", description: "Upload a file and share it through a short link that streams the download straight to your visitors." },
      { title: "Event", description: "A shareable calendar event with a downloadable .ics file visitors can add to their own calendar in one tap." },
      { title: "Contact Card", description: "A downloadable vCard so people can save your full contact details — phones, emails, socials — with one tap." },
      { title: "Reviews Page", description: "A dedicated review wall that collects and shows star ratings and feedback from your visitors." },
    ],
  },
  {
    id: "ai-suite",
    name: "AI suite",
    intro:
      "A set of AI products that plug into your 1INME — a chatbot for your Link in Bio, an agent that runs multi-step tasks, an embeddable widget for any site, and a voice assistant that picks up your calls.",
    items: [
      { title: "AI Chatbot", description: "Trained 24/7 chatbot on your Link in Bio that answers in your voice, captures leads and hands off to a human when needed." },
      { title: "AI Agent", description: "A multi-step agent that runs playbooks across your contacts, inbox and calendar — qualifying leads and following up on its own." },
      { title: "AI Widget", description: "Embeddable AI assistant for any website — answers questions, captures leads and routes the hot ones to your unified inbox." },
      { title: "AI Voice Assistant", description: "AI receptionist that picks up calls to your number, qualifies callers and books or routes them — never a missed lead." },
    ],
  },
  {
    id: "biolink",
    name: "Link in Bio & landing page builder",
    intro:
      "Build a fully-customizable one-link landing page with a guided wizard and a deep block library, organised by sub-type so you only see what you need.",
    items: [
      { title: "Guided Link in Bio wizard", description: "Step-by-step creation flow that helps you pick a layout, profile style, and starting blocks without any design experience." },
      { title: "Essentials blocks", description: "Quick-add blocks for the basics: links, headings, paragraphs, dividers, and spacers to structure your page." },
      { title: "Layout & profile blocks", description: "Profile cards, avatars, cover images, and section layouts to anchor your identity at the top of the page." },
      { title: "Media blocks", description: "Embed images, image galleries, audio, video, and file downloads directly into the page." },
      { title: "Engagement blocks", description: "Add countdowns, FAQs, testimonials, ratings, and call-to-action buttons to keep visitors interacting." },
      { title: "Commerce blocks", description: "Sell products, accept payments, take tips, and showcase services right inside the Link in Bio." },
      { title: "Contact & lead blocks", description: "Drop in contact forms, booking requests, and lead capture fields without leaving the builder." },
      { title: "Social & embed blocks", description: "Pull in social handles, feeds, maps, and third-party embeds in a single click." },
      { title: "Visual customization", description: "Fine-tune colors, fonts, backgrounds, button styles, and spacing for a fully on-brand look." },
      { title: "Splash pages", description: "Show a branded interstitial before visitors land on the main Link in Bio to set the mood or run announcements." },
    ],
  },
  {
    id: "links",
    name: "Short links & link tools",
    intro:
      "Shorten, organise, and manage every kind of link you need to share, with project folders and lifecycle controls.",
    items: [
      { title: "Short URLs", description: "Turn long URLs into clean, branded short links you can share anywhere." },
      { title: "Projects", description: "Group related links into project folders to keep large libraries tidy and easy to navigate." },
      { title: "URL link type", description: "Standard short link that redirects visitors to any web address you choose." },
      { title: "File link type", description: "Upload a file and share it through a short link that streams the download to visitors." },
      { title: "ICS calendar link type", description: "Generate calendar event links that visitors can add straight to their own calendar." },
      { title: "VCF contact card link type", description: "Share a downloadable contact card so people can save your details with one tap." },
      { title: "Duplicate link", description: "Clone an existing link and tweak it instead of rebuilding from scratch." },
      { title: "Reset link", description: "Wipe a link's analytics and start counting visits fresh whenever you need a clean baseline." },
      { title: "Temporary status", description: "Mark a link as temporary so it expires automatically after the date or click limit you set." },
    ],
  },
  {
    id: "qr",
    name: "QR code studio",
    intro:
      "Turn any link or piece of content into a scannable, brand-styled QR code, ready for print or screen.",
    items: [
      { title: "Per-link QR codes", description: "Every short link and Link in Bio gets an instant downloadable QR code you can drop on flyers, packaging, or slides." },
      { title: "Standalone QR generator", description: "Generate one-off QR codes that aren't tied to a tracked link when you just need a quick code." },
      { title: "Text QR codes", description: "Encode plain text messages so a scan reveals the words on the visitor's device." },
      { title: "Email QR codes", description: "Open the visitor's email app pre-filled with your address, subject, and body." },
      { title: "SMS QR codes", description: "Pre-compose a text message with the right phone number so a scan starts the conversation." },
      { title: "WiFi QR codes", description: "Let guests join your WiFi by scanning, with no manual password entry." },
      { title: "VCard QR codes", description: "Hand out your contact card as a QR — perfect for business cards and event badges." },
      { title: "Custom styling", description: "Adjust colors, add a logo in the centre, and pick from styled patterns to match your brand." },
    ],
  },
  {
    id: "analytics",
    name: "Analytics & performance",
    intro:
      "Understand exactly how your links and pages perform, then feed that data into your existing marketing stack.",
    items: [
      { title: "Visitor analytics", description: "See visit counts, geography, devices, browsers, referrers, and trends across all your links and pages." },
      { title: "Heatmaps", description: "Visualise which blocks on your Link in Bio visitors actually click and where they drop off." },
      { title: "CSV export", description: "Download raw analytics as CSV so you can crunch the numbers in your own spreadsheet or BI tool." },
      { title: "Facebook tracking pixel", description: "Drop in your Facebook Pixel ID to retarget visitors and measure ad performance." },
      { title: "Google Analytics tracking", description: "Connect a Google Analytics property and feed visits straight into your existing reporting." },
      { title: "LinkedIn Insight tag", description: "Track LinkedIn ad audiences and conversions from your Link in Bio visitors." },
      { title: "Pinterest tag", description: "Attribute Pinterest-driven traffic to the right campaigns with the Pinterest tracking tag." },
      { title: "TikTok Pixel", description: "Send visit and conversion events to TikTok Ads Manager for retargeting and measurement." },
    ],
  },
  {
    id: "inbox",
    name: "Inbox & messaging",
    intro:
      "Every conversation that reaches you through 1INME lands in one place so nothing slips through the cracks.",
    items: [
      { title: "Unified inbox", description: "A single inbox that pulls together every visitor message, form reply, and follow-up across all your links." },
      { title: "Direct messages from visitors", description: "Visitors can message you straight from your Link in Bio and you reply right inside the inbox." },
      { title: "Form submissions", description: "Every contact form, lead form, and booking form submission lands in the same inbox thread." },
    ],
  },
  {
    id: "subscribers",
    name: "Subscribers & broadcasts",
    intro:
      "Grow your own audience list, then talk to it directly without depending on social platforms.",
    items: [
      { title: "Email list building", description: "Capture email subscribers through dedicated blocks and forms on your Link in Bio." },
      { title: "SMS list building", description: "Collect mobile numbers with consent so you can send time-sensitive updates by text." },
      { title: "Broadcast sends", description: "Compose a message once and blast it to your full email or SMS list, or to a filtered segment." },
    ],
  },
  {
    id: "feed",
    name: "Creators feed & followers",
    intro:
      "Run your own social-style feed where supporters can follow you, without sending them off to a third-party network.",
    items: [
      { title: "Social-style creators feed", description: "Post updates, photos, and announcements to a feed your audience can scroll like a social timeline." },
      { title: "OTP follow via email", description: "Visitors confirm with a one-time code sent to their email address, so the follow is verified." },
      { title: "OTP follow via SMS", description: "Visitors can also follow with a one-time code sent to their phone for verified mobile follows." },
      { title: "Follow updates", description: "Followers automatically get notified when you publish new posts, so they never miss an update." },
    ],
  },
  {
    id: "buzz",
    name: "Social proof / Buzz widgets",
    intro: "Build trust on your Link in Bio by showing real activity from real visitors as it happens.",
    items: [
      { title: "Floating recent-activity notifications", description: "Small pop-up cards that surface recent visitors, signups, or purchases to nudge new visitors to take action." },
    ],
  },
  {
    id: "workspaces",
    name: "Workspaces & team collaboration",
    intro:
      "Work alongside teammates and clients with separate workspaces, granular roles, and clean invitations.",
    items: [
      { title: "Multi-workspace switching", description: "Keep separate workspaces for different brands or clients and switch between them with one click." },
      { title: "Admin role", description: "Full control over the workspace, including billing, members, and every link or page." },
      { title: "Editor role", description: "Create and edit links, Link in Bio pages, and posts without touching billing or member management." },
      { title: "Replier role", description: "Read and reply to inbox messages without being able to change content or settings." },
      { title: "Viewer role", description: "Read-only access to analytics and content for stakeholders who only need to look in." },
      { title: "Invite landing pages", description: "Send a clean, branded invite page so new members can accept and onboard in seconds." },
    ],
  },
  {
    id: "vault",
    name: "Vault",
    intro:
      "Store sensitive client information securely inside 1INME instead of scattering it across notes apps and chats.",
    items: [
      { title: "Encrypted credential storage", description: "Save logins, API keys, and secret notes encrypted at rest so only authorised members can decrypt them." },
      { title: "Audit logging on reveal", description: "Every time a credential is revealed it gets logged with the user and timestamp for full accountability." },
      { title: "Client records with notes", description: "Keep structured records of each client with notes you can update over time." },
      { title: "Client attachments", description: "Attach contracts, briefs, and other files directly to a client record so everything stays in one place." },
    ],
  },
  {
    id: "kanban",
    name: "Kanban task boards",
    intro: "Manage work without leaving 1INME using flexible boards that fit how your team actually operates.",
    items: [
      { title: "Boards with columns", description: "Spin up boards with custom columns to track work through any stage you define." },
      { title: "Subtasks", description: "Break a card into subtasks and tick them off as the work progresses." },
      { title: "Assignees", description: "Assign one or more team members to a card so it's clear who owns what." },
      { title: "Labels", description: "Tag cards with colour-coded labels for quick categorisation and filtering." },
      { title: "Comments", description: "Discuss work in-thread on each card without bouncing to another tool." },
      { title: "Attachments", description: "Pin files and documents to a card so all the context lives with the task." },
    ],
  },
  {
    id: "crm",
    name: "CRM address book & dialer",
    intro: "Keep every contact you collect in a proper address book and reach out without juggling extra apps.",
    items: [
      { title: "Contacts address book", description: "A central directory of every person you talk to, with rich profile details." },
      { title: "Import contacts", description: "Bring contacts in from CSV files so you don't have to retype anything." },
      { title: "Export contacts", description: "Download your full contact list as CSV for backups or other tools." },
      { title: "Built-in dialer", description: "Tap a contact to call them directly from inside 1INME without copy-pasting numbers." },
      { title: "Google Contacts sync", description: "Two-way sync with your Google Contacts so changes flow between both sides automatically." },
    ],
  },
  {
    id: "calendar",
    name: "Calendar sync",
    intro: "Keep your real calendars in the loop whenever someone books with you or RSVPs to your event link.",
    items: [
      { title: "Google Calendar sync", description: "Connect a Google Calendar so 1INME events appear and update in your day-to-day schedule." },
      { title: "Microsoft / Outlook sync", description: "Sync with Microsoft 365 or Outlook calendars for full visibility on the Microsoft side." },
      { title: "CalDAV sync", description: "Use CalDAV to sync with Apple Calendar, Fastmail, and other standards-based calendars." },
      { title: "RSVPs for event links", description: "Create event links visitors can RSVP to, with their response captured against the event." },
    ],
  },
  {
    id: "account",
    name: "Account & verification",
    intro: "Flexible identity tools that fit creators, agencies, and people who wear multiple hats.",
    items: [
      { title: "Verified blue-tick", description: "Apply for a verified badge that proves your identity to your visitors and followers." },
      { title: "Multi-identity login", description: "Sign in with email, phone, or social providers and link them all to the same account." },
      { title: "Account merge", description: "Combine two accounts into one if you signed up twice by mistake, keeping all the content." },
      { title: "Persona-based onboarding", description: "Pick the persona that matches you (creator, business, agency) and get a tailored setup flow." },
    ],
  },
  {
    id: "billing",
    name: "Billing & plans",
    intro: "Transparent subscription billing with all the extras serious customers expect.",
    items: [
      { title: "Monthly subscriptions", description: "Pay month-to-month on any plan and cancel whenever you need to." },
      { title: "Yearly subscriptions", description: "Switch to yearly billing and save compared to the monthly rate." },
      { title: "Add-ons", description: "Top up your plan with add-ons for things like extra capacity without changing tiers." },
      { title: "Automatic tax", description: "Sales tax and VAT are calculated and added to invoices automatically based on your location." },
      { title: "PDF invoices", description: "Download a clean PDF invoice for every charge for your records or accountant." },
    ],
  },
  {
    id: "referrals",
    name: "Referral program",
    intro: "Reward the people who tell their network about 1INME with a built-in referral system.",
    items: [
      { title: "Referral tracking", description: "Every signup that comes from your referral link is tracked back to you automatically." },
      { title: "Custom referral codes", description: "Pick a memorable referral code instead of a long URL so it's easy to share by voice or in a story." },
    ],
  },
  {
    id: "public-surfaces",
    name: "Public marketing surfaces",
    intro: "Discoverability features that bring new visitors to creators on 1INME without extra work.",
    items: [
      { title: "Discovery directory", description: "A public directory where creators with opted-in profiles can be found by category and interest." },
      { title: "Creators Feed page", description: "A site-wide feed of recent creator posts that surfaces fresh activity to new visitors." },
      { title: "API documentation page", description: "Public API docs that show developers exactly how to build on top of the 1INME platform." },
    ],
  },
  {
    id: "scheduling",
    name: "Scheduling & timing",
    intro:
      "Set blocks, posts and pages to publish, swap or expire on the dates and times you want — with timezone support and the option to test a send before it goes out.",
    items: [
      { title: "Schedule blocks to appear", description: "Pick a date and time for any block to publish — it stays hidden until then and goes live automatically." },
      { title: "Schedule blocks to expire", description: "Set an end date and time so seasonal or campaign blocks disappear on their own without you remembering to remove them." },
      { title: "Page-level publish scheduling", description: "Schedule a whole Link in Bio to flip from draft to live at a given moment — perfect for launches." },
      { title: "Test send for digest emails", description: "Send the daily digest to yourself first to check the layout and content before it goes out to your followers." },
      { title: "Visitor timezone awareness", description: "Schedules use the visitor's timezone where it matters, so a launch goes live everywhere at the right local moment." },
    ],
  },
  {
    id: "events",
    name: "Events & RSVPs",
    intro:
      "Run launches, drops, lives and meetups directly from your Link in Bio — with countdowns, RSVPs and reminder emails handled for you.",
    items: [
      { title: "Event blocks with countdown", description: "Drop in an event block and it shows a live countdown to the start time so visitors know exactly when it kicks off." },
      { title: "RSVP collection", description: "Collect RSVPs from visitors with optional email or phone capture, and see the attendee list in your dashboard." },
      { title: "Reminder emails before the event", description: "Attendees get an automatic reminder before the event kicks off so they actually show up." },
      { title: "Calendar file (.ics) download", description: "One-click \"Add to calendar\" downloads a standard .ics file that works with Google, Apple, Outlook and more." },
      { title: "Capacity caps & waitlists", description: "Limit how many people can RSVP and automatically push the rest onto a waitlist if a spot opens up." },
    ],
  },
  {
    id: "templates",
    name: "Templates & quick start",
    intro:
      "Skip the blank canvas. Pick a professionally designed template, fill in your details, and you're live in two minutes.",
    items: [
      { title: "Curated Link in Bio templates", description: "A growing library of mobile-first templates for creators, brands, agencies, restaurants, coaches, freelancers and more." },
      { title: "One-click template apply", description: "Tap a template and your page rebuilds in seconds — keep your existing content, swap just the look, or both." },
      { title: "Industry starter packs", description: "Pre-configured block sets for music, fitness, hospitality and other industries so you don't have to think about what to add first." },
      { title: "Linktree / Beacons importer", description: "Paste your existing Link in Bio URL and we'll pull the blocks, icons and links into a ready-to-edit starter page." },
      { title: "Save your own templates", description: "Turn any page into a reusable template so your agency or team can spin up new client pages in seconds." },
    ],
  },
  {
    id: "integrations",
    name: "Social integrations",
    intro:
      "One-click connections to every network you live on, with auto-retry, live status and notifications when something needs your attention.",
    items: [
      { title: "Instagram connection", description: "Connect your Instagram account in one tap to pull profile, posts and follower counts into your Link in Bio." },
      { title: "TikTok connection", description: "Plug in TikTok with one tap to surface your latest videos and follower count." },
      { title: "Facebook page connection", description: "Hook up a Facebook page so visitors can follow and you can pull recent posts." },
      { title: "X (Twitter) connection", description: "Connect X so your latest posts and follower count stay live on your Link in Bio." },
      { title: "LinkedIn connection", description: "Plug in your LinkedIn profile or company page for a one-tap follow surface on your Link in Bio." },
      { title: "Pinterest connection", description: "Connect Pinterest to surface your boards and pins on your Link in Bio." },
      { title: "Auto-retry on broken connections", description: "When a token expires we keep retrying with smart back-off and only ping you when we actually need you to reconnect." },
      { title: "Connection health dashboard", description: "See \"healthy / needs reconnect / paused\" for every network at a glance, with last-synced timestamps." },
    ],
  },
];
