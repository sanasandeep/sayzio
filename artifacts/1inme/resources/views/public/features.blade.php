@extends('public.layouts.site')

@section('title', $page->title ?? 'Features')

@php
    $categories = [
        [
            'id' => 'biolink',
            'icon' => 'fa-square-share-nodes',
            'heading' => 'Biolink & landing page builder',
            'intro' => 'Build a fully-customizable one-link landing page with a guided wizard and a deep block library, organised by sub-type so you only see what you need.',
            'features' => [
                ['Guided biolink wizard', 'Step-by-step creation flow that helps you pick a layout, profile style, and starting blocks without any design experience.'],
                ['Essentials blocks', 'Quick-add blocks for the basics: links, headings, paragraphs, dividers, and spacers to structure your page.'],
                ['Layout & profile blocks', 'Profile cards, avatars, cover images, and section layouts to anchor your identity at the top of the page.'],
                ['Media blocks', 'Embed images, image galleries, audio, video, and file downloads directly into the page.'],
                ['Engagement blocks', 'Add countdowns, FAQs, testimonials, ratings, and call-to-action buttons to keep visitors interacting.'],
                ['Commerce blocks', 'Sell products, accept payments, take tips, and showcase services right inside the biolink.'],
                ['Contact & lead blocks', 'Drop in contact forms, booking requests, and lead capture fields without leaving the builder.'],
                ['Social & embed blocks', 'Pull in social handles, feeds, maps, and third-party embeds in a single click.'],
                ['Visual customization', 'Fine-tune colors, fonts, backgrounds, button styles, and spacing for a fully on-brand look.'],
                ['Splash pages', 'Show a branded interstitial before visitors land on the main biolink to set the mood or run announcements.'],
            ],
        ],
        [
            'id' => 'links',
            'icon' => 'fa-link',
            'heading' => 'Short links & link tools',
            'intro' => 'Shorten, organise, and manage every kind of link you need to share, with project folders and lifecycle controls.',
            'features' => [
                ['Short URLs', 'Turn long URLs into clean, branded short links you can share anywhere.'],
                ['Projects', 'Group related links into project folders to keep large libraries tidy and easy to navigate.'],
                ['URL link type', 'Standard short link that redirects visitors to any web address you choose.'],
                ['File link type', 'Upload a file and share it through a short link that streams the download to visitors.'],
                ['ICS calendar link type', 'Generate calendar event links that visitors can add straight to their own calendar.'],
                ['VCF contact card link type', 'Share a downloadable contact card so people can save your details with one tap.'],
                ['Duplicate link', 'Clone an existing link and tweak it instead of rebuilding from scratch.'],
                ['Reset link', 'Wipe a link\'s analytics and start counting visits fresh whenever you need a clean baseline.'],
                ['Temporary status', 'Mark a link as temporary so it expires automatically after the date or click limit you set.'],
            ],
        ],
        [
            'id' => 'qr',
            'icon' => 'fa-qrcode',
            'heading' => 'QR code studio',
            'intro' => 'Turn any link or piece of content into a scannable, brand-styled QR code, ready for print or screen.',
            'features' => [
                ['Per-link QR codes', 'Every short link and biolink gets an instant downloadable QR code you can drop on flyers, packaging, or slides.'],
                ['Standalone QR generator', 'Generate one-off QR codes that aren\'t tied to a tracked link when you just need a quick code.'],
                ['Text QR codes', 'Encode plain text messages so a scan reveals the words on the visitor\'s device.'],
                ['Email QR codes', 'Open the visitor\'s email app pre-filled with your address, subject, and body.'],
                ['SMS QR codes', 'Pre-compose a text message with the right phone number so a scan starts the conversation.'],
                ['WiFi QR codes', 'Let guests join your WiFi by scanning, with no manual password entry.'],
                ['VCard QR codes', 'Hand out your contact card as a QR — perfect for business cards and event badges.'],
                ['Custom styling', 'Adjust colors, add a logo in the centre, and pick from styled patterns to match your brand.'],
            ],
        ],
        [
            'id' => 'analytics',
            'icon' => 'fa-chart-line',
            'heading' => 'Analytics & performance',
            'intro' => 'Understand exactly how your links and pages perform, then feed that data into your existing marketing stack.',
            'features' => [
                ['Visitor analytics', 'See visit counts, geography, devices, browsers, referrers, and trends across all your links and pages.'],
                ['Heatmaps', 'Visualise which blocks on your biolink visitors actually click and where they drop off.'],
                ['CSV export', 'Download raw analytics as CSV so you can crunch the numbers in your own spreadsheet or BI tool.'],
                ['Facebook tracking pixel', 'Drop in your Facebook Pixel ID to retarget visitors and measure ad performance.'],
                ['Google Analytics tracking', 'Connect a Google Analytics property and feed visits straight into your existing reporting.'],
                ['LinkedIn Insight tag', 'Track LinkedIn ad audiences and conversions from your biolink visitors.'],
                ['Pinterest tag', 'Attribute Pinterest-driven traffic to the right campaigns with the Pinterest tracking tag.'],
                ['TikTok Pixel', 'Send visit and conversion events to TikTok Ads Manager for retargeting and measurement.'],
            ],
        ],
        [
            'id' => 'inbox',
            'icon' => 'fa-inbox',
            'heading' => 'Inbox & messaging',
            'intro' => 'Every conversation that reaches you through 1INME lands in one place so nothing slips through the cracks.',
            'features' => [
                ['Unified inbox', 'A single inbox that pulls together every visitor message, form reply, and follow-up across all your links.'],
                ['Direct messages from visitors', 'Visitors can message you straight from your biolink and you reply right inside the inbox.'],
                ['Form submissions', 'Every contact form, lead form, and booking form submission lands in the same inbox thread.'],
            ],
        ],
        [
            'id' => 'subscribers',
            'icon' => 'fa-envelope-open-text',
            'heading' => 'Subscribers & broadcasts',
            'intro' => 'Grow your own audience list, then talk to it directly without depending on social platforms.',
            'features' => [
                ['Email list building', 'Capture email subscribers through dedicated blocks and forms on your biolink.'],
                ['SMS list building', 'Collect mobile numbers with consent so you can send time-sensitive updates by text.'],
                ['Broadcast sends', 'Compose a message once and blast it to your full email or SMS list, or to a filtered segment.'],
            ],
        ],
        [
            'id' => 'feed',
            'icon' => 'fa-rss',
            'heading' => 'Creators feed & followers',
            'intro' => 'Run your own social-style feed where supporters can follow you, without sending them off to a third-party network.',
            'features' => [
                ['Social-style creators feed', 'Post updates, photos, and announcements to a feed your audience can scroll like a social timeline.'],
                ['OTP follow via email', 'Visitors confirm with a one-time code sent to their email address, so the follow is verified.'],
                ['OTP follow via SMS', 'Visitors can also follow with a one-time code sent to their phone for verified mobile follows.'],
                ['Follow updates', 'Followers automatically get notified when you publish new posts, so they never miss an update.'],
            ],
        ],
        [
            'id' => 'buzz',
            'icon' => 'fa-bell',
            'heading' => 'Social proof / Buzz widgets',
            'intro' => 'Build trust on your biolink by showing real activity from real visitors as it happens.',
            'features' => [
                ['Floating recent-activity notifications', 'Small pop-up cards that surface recent visitors, signups, or purchases to nudge new visitors to take action.'],
            ],
        ],
        [
            'id' => 'workspaces',
            'icon' => 'fa-users',
            'heading' => 'Workspaces & team collaboration',
            'intro' => 'Work alongside teammates and clients with separate workspaces, granular roles, and clean invitations.',
            'features' => [
                ['Multi-workspace switching', 'Keep separate workspaces for different brands or clients and switch between them with one click.'],
                ['Admin role', 'Full control over the workspace, including billing, members, and every link or page.'],
                ['Editor role', 'Create and edit links, biolinks, and posts without touching billing or member management.'],
                ['Replier role', 'Read and reply to inbox messages without being able to change content or settings.'],
                ['Viewer role', 'Read-only access to analytics and content for stakeholders who only need to look in.'],
                ['Invite landing pages', 'Send a clean, branded invite page so new members can accept and onboard in seconds.'],
            ],
        ],
        [
            'id' => 'vault',
            'icon' => 'fa-vault',
            'heading' => 'Vault',
            'intro' => 'Store sensitive client information securely inside 1INME instead of scattering it across notes apps and chats.',
            'features' => [
                ['Encrypted credential storage', 'Save logins, API keys, and secret notes encrypted at rest so only authorised members can decrypt them.'],
                ['Audit logging on reveal', 'Every time a credential is revealed it gets logged with the user and timestamp for full accountability.'],
                ['Client records with notes', 'Keep structured records of each client with notes you can update over time.'],
                ['Client attachments', 'Attach contracts, briefs, and other files directly to a client record so everything stays in one place.'],
            ],
        ],
        [
            'id' => 'kanban',
            'icon' => 'fa-clipboard-check',
            'heading' => 'Kanban task boards',
            'intro' => 'Manage work without leaving 1INME using flexible boards that fit how your team actually operates.',
            'features' => [
                ['Boards with columns', 'Spin up boards with custom columns to track work through any stage you define.'],
                ['Subtasks', 'Break a card into subtasks and tick them off as the work progresses.'],
                ['Assignees', 'Assign one or more team members to a card so it\'s clear who owns what.'],
                ['Labels', 'Tag cards with colour-coded labels for quick categorisation and filtering.'],
                ['Comments', 'Discuss work in-thread on each card without bouncing to another tool.'],
                ['Attachments', 'Pin files and documents to a card so all the context lives with the task.'],
            ],
        ],
        [
            'id' => 'crm',
            'icon' => 'fa-address-book',
            'heading' => 'CRM address book & dialer',
            'intro' => 'Keep every contact you collect in a proper address book and reach out without juggling extra apps.',
            'features' => [
                ['Contacts address book', 'A central directory of every person you talk to, with rich profile details.'],
                ['Import contacts', 'Bring contacts in from CSV files so you don\'t have to retype anything.'],
                ['Export contacts', 'Download your full contact list as CSV for backups or other tools.'],
                ['Built-in dialer', 'Tap a contact to call them directly from inside 1INME without copy-pasting numbers.'],
                ['Google Contacts sync', 'Two-way sync with your Google Contacts so changes flow between both sides automatically.'],
            ],
        ],
        [
            'id' => 'calendar',
            'icon' => 'fa-calendar-days',
            'heading' => 'Calendar sync',
            'intro' => 'Keep your real calendars in the loop whenever someone books with you or RSVPs to your event link.',
            'features' => [
                ['Google Calendar sync', 'Connect a Google Calendar so 1INME events appear and update in your day-to-day schedule.'],
                ['Microsoft / Outlook sync', 'Sync with Microsoft 365 or Outlook calendars for full visibility on the Microsoft side.'],
                ['CalDAV sync', 'Use CalDAV to sync with Apple Calendar, Fastmail, and other standards-based calendars.'],
                ['RSVPs for event links', 'Create event links visitors can RSVP to, with their response captured against the event.'],
            ],
        ],
        [
            'id' => 'account',
            'icon' => 'fa-user-shield',
            'heading' => 'Account & verification',
            'intro' => 'Flexible identity tools that fit creators, agencies, and people who wear multiple hats.',
            'features' => [
                ['Verified blue-tick', 'Apply for a verified badge that proves your identity to your visitors and followers.'],
                ['Multi-identity login', 'Sign in with email, phone, or social providers and link them all to the same account.'],
                ['Account merge', 'Combine two accounts into one if you signed up twice by mistake, keeping all the content.'],
                ['Persona-based onboarding', 'Pick the persona that matches you (creator, business, agency) and get a tailored setup flow.'],
            ],
        ],
        [
            'id' => 'billing',
            'icon' => 'fa-credit-card',
            'heading' => 'Billing & plans',
            'intro' => 'Transparent subscription billing with all the extras serious customers expect.',
            'features' => [
                ['Monthly subscriptions', 'Pay month-to-month on any plan and cancel whenever you need to.'],
                ['Yearly subscriptions', 'Switch to yearly billing and save compared to the monthly rate.'],
                ['Add-ons', 'Top up your plan with add-ons for things like extra capacity without changing tiers.'],
                ['Automatic tax', 'Sales tax and VAT are calculated and added to invoices automatically based on your location.'],
                ['PDF invoices', 'Download a clean PDF invoice for every charge for your records or accountant.'],
            ],
        ],
        [
            'id' => 'referrals',
            'icon' => 'fa-gift',
            'heading' => 'Referral program',
            'intro' => 'Reward the people who tell their network about 1INME with a built-in referral system.',
            'features' => [
                ['Referral tracking', 'Every signup that comes from your referral link is tracked back to you automatically.'],
                ['Custom referral codes', 'Pick a memorable referral code instead of a long URL so it\'s easy to share by voice or in a story.'],
            ],
        ],
        [
            'id' => 'public-surfaces',
            'icon' => 'fa-globe',
            'heading' => 'Public marketing surfaces',
            'intro' => 'Discoverability features that bring new visitors to creators on 1INME without extra work.',
            'features' => [
                ['Discovery directory', 'A public directory where creators with opted-in profiles can be found by category and interest.'],
                ['Creators Feed page', 'A site-wide feed of recent creator posts that surfaces fresh activity to new visitors.'],
                ['API documentation page', 'Public API docs that show developers exactly how to build on top of the 1INME platform.'],
            ],
        ],
    ];
@endphp

@push('head')
<style>
    html { scroll-behavior: smooth; scroll-padding-top: 5rem; }
    .feature-cat-card { transition: border-color .15s ease, transform .15s ease; }
    .feature-cat-card:hover { border-color: rgba(167,139,250,.35); }
    .feature-row { border-top: 1px solid rgba(255,255,255,.06); }
    .feature-row:first-child { border-top: 0; }
    .toc-link { transition: color .15s ease, background .15s ease; }
    .toc-link:hover { color:#a78bfa; background: rgba(167,139,250,.08); }
</style>
@endpush

@section('content')
<section class="relative pt-16 pb-10 lg:pt-24 lg:pb-14 overflow-hidden">
    <div class="absolute -top-32 -left-32 w-[500px] h-[500px] rounded-full pointer-events-none" style="background:radial-gradient(circle,rgba(124,58,237,0.18) 0%,transparent 70%);"></div>
    <div class="absolute -bottom-32 -right-32 w-[500px] h-[500px] rounded-full pointer-events-none" style="background:radial-gradient(circle,rgba(236,72,153,0.12) 0%,transparent 70%);"></div>
    <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-violet-500/10 border border-violet-400/20 text-xs text-violet-300 uppercase tracking-wider font-semibold mb-4">
            <i class="fas fa-sparkles"></i> Everything 1INME can do
        </div>
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight">{{ $page->title ?? 'Features' }}</h1>
        <p class="mt-5 text-lg text-gray-400 max-w-3xl mx-auto leading-relaxed">
            {{ $page->meta_description ?? 'A complete tour of every capability inside 1INME — from your biolink and short links to inboxes, teams, billing, and beyond. No hidden lists, nothing collapsed: the whole product, on one page.' }}
        </p>
    </div>
</section>

<section class="pb-12">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white/[0.03] border border-white/10 rounded-2xl p-5 sm:p-6">
            <div class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Jump to a category</div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                @foreach($categories as $cat)
                    <a href="#cat-{{ $cat['id'] }}" class="toc-link flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-gray-300">
                        <i class="fas {{ $cat['icon'] }} text-violet-400 w-4 text-center"></i>
                        <span class="truncate">{{ $cat['heading'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</section>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 pb-20 space-y-10">
    @foreach($categories as $i => $cat)
        <section id="cat-{{ $cat['id'] }}" class="feature-cat-card bg-white/[0.03] border border-white/10 rounded-2xl p-6 sm:p-10">
            <div class="flex items-start gap-4 mb-6">
                <div class="shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-violet-500/30 to-fuchsia-500/20 border border-violet-400/30 flex items-center justify-center">
                    <i class="fas {{ $cat['icon'] }} text-violet-300 text-lg"></i>
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-semibold uppercase tracking-wider text-violet-300/80 mb-1">Category {{ $i + 1 }}</div>
                    <h2 class="text-2xl sm:text-3xl font-bold text-white">{{ $cat['heading'] }}</h2>
                    <p class="mt-2 text-gray-400 leading-relaxed max-w-3xl">{{ $cat['intro'] }}</p>
                </div>
            </div>
            <div class="rounded-xl border border-white/5 bg-black/10 overflow-hidden">
                @foreach($cat['features'] as $feat)
                    <div class="feature-row grid grid-cols-1 md:grid-cols-3 gap-2 md:gap-6 px-5 py-4">
                        <div class="md:col-span-1">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-circle-check text-violet-400 mt-1 text-sm"></i>
                                <div class="font-semibold text-white">{{ $feat[0] }}</div>
                            </div>
                        </div>
                        <div class="md:col-span-2 text-gray-400 text-sm leading-relaxed md:pt-0.5">
                            {{ $feat[1] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</div>

<section class="pb-24">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="bg-gradient-to-br from-violet-500/15 via-fuchsia-500/10 to-transparent border border-violet-400/20 rounded-2xl p-8 sm:p-12">
            <h3 class="text-2xl sm:text-3xl font-bold text-white">Ready to put it all to work?</h3>
            <p class="mt-3 text-gray-400 max-w-2xl mx-auto">Spin up your biolink, drop in your first link, and explore every feature on this page from your dashboard.</p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                @auth
                    <a href="{{ route('user.dashboard') }}" class="px-6 py-3 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9]">Open dashboard</a>
                @else
                    <a href="{{ route('register.page') }}" class="px-6 py-3 bg-[#7c3aed] text-white rounded-full text-sm font-bold hover:bg-[#6d28d9]">Get started free</a>
                    <a href="{{ route('site.how-it-works') }}" class="px-6 py-3 border border-white/15 text-gray-200 rounded-full text-sm font-semibold hover:bg-white/5">See how it works</a>
                @endauth
            </div>
        </div>
    </div>
</section>
@endsection
