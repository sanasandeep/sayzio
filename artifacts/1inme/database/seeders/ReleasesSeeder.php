<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\Release;
use App\Modules\Admin\Support\VersionRegistry;
use Illuminate\Database\Seeder;

/**
 * Backfill the releases changelog with each surface's currently declared
 * version and real, curated release notes describing what that surface
 * actually ships today (drawn from docs/features.md and the knowledge base).
 *
 * Idempotent and never clobbers admin work:
 *   - firstOrCreate by surface+version — existing rows are never duplicated;
 *   - curated notes are applied only to rows this seeder owns
 *     (source = seed) whose notes are still an untouched seeder default
 *     (the legacy "Initial recorded release …" placeholder or a previous
 *     curated snapshot, tracked by the marker line below). `manual` and
 *     `github` entries, and any admin-edited notes, are left alone.
 */
class ReleasesSeeder extends Seeder
{
    /** Fallback declared versions (as of July 2026). */
    private const FALLBACK_VERSIONS = [
        'web'         => '1.0.0',
        'marketing'   => '0.1.0',
        'mobile'      => '1.0.0',
        'dialer'      => '1.0.0',
        'zio_browser' => '0.3.1',
        'extension'   => '0.1.0',
        'api_server'  => '0.0.0',
        'docs'        => '1.0.0',
    ];

    /**
     * Prior curated snapshots this seeder has shipped in the past. When the
     * curated copy changes, append the outgoing text of each surface here so
     * re-runs can still recognise untouched rows and refresh them. Rows whose
     * notes match none of these (and none of the current curated texts, and
     * no legacy placeholder) are treated as admin-edited and left alone.
     *
     * @var list<string>
     */
    private const LEGACY_CURATED_NOTES = [];

    public function run(): void
    {
        $snapshot = VersionRegistry::snapshot();
        $declared = is_array($snapshot['surfaces'] ?? null) ? $snapshot['surfaces'] : [];

        foreach (self::FALLBACK_VERSIONS as $surface => $fallback) {
            $version = $declared[$surface] ?? null;
            $version = is_string($version) && $version !== '' ? $version : $fallback;

            $notes = self::curatedNotes($surface);

            $release = Release::firstOrCreate(
                ['surface' => $surface, 'version' => $version],
                [
                    'released_at' => now()->toDateString(),
                    'notes'       => $notes,
                    'source'      => 'seed',
                ]
            );

            if (!$release->wasRecentlyCreated
                && $release->source === 'seed'
                && self::isUntouchedSeedNotes($release->notes)
                && $release->notes !== $notes) {
                $release->update(['notes' => $notes]);
            }
        }

        // Sweep: older seeded rows (e.g. a previously declared version that has
        // since moved on) may still carry the legacy placeholder. Give them the
        // surface's curated notes too — still only rows this seeder owns and
        // whose notes were never edited by an admin.
        Release::where('source', 'seed')->get()->each(function (Release $release) {
            if (!self::isUntouchedSeedNotes($release->notes)) {
                return;
            }
            $notes = self::curatedNotes($release->surface);
            if ($release->notes !== $notes) {
                $release->update(['notes' => $notes]);
            }
        });
    }

    /**
     * True when the row's notes are still whatever a past run of this seeder
     * wrote (legacy placeholder, a current curated snapshot, a prior curated
     * snapshot listed in LEGACY_CURATED_NOTES, or an early marker-stamped
     * snapshot) — i.e. an admin has never edited them.
     */
    private static function isUntouchedSeedNotes(?string $notes): bool
    {
        $notes = (string) $notes;

        if ($notes === '') {
            return true;
        }
        if (str_starts_with($notes, 'Initial recorded release for ')) {
            return true;
        }
        // A short-lived early revision stamped notes with an HTML-comment
        // marker; treat those as seeder-owned too.
        if (preg_match('/<!-- seeded-notes:v\d+ -->\s*$/', $notes)) {
            return true;
        }

        $normalized = trim($notes);

        foreach (array_keys(self::FALLBACK_VERSIONS) as $surface) {
            if ($normalized === trim(self::curatedNotes($surface))) {
                return true;
            }
        }

        foreach (self::LEGACY_CURATED_NOTES as $legacy) {
            if ($normalized === trim($legacy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Real per-surface release notes describing what each surface contains
     * today. Content is curated from docs/features.md, docs/knowledge-base.md
     * and the shipping feature set as of July 2026.
     */
    public static function curatedNotes(string $surface): string
    {
        return match ($surface) {
            'web' => <<<'MD'
### Web app 1.0 — the full Sayzio platform

- Links: short links, biolinks (mini-websites), QR codes, files, events, vCards, WiFi/SMS links, resumes/portfolios, restaurant menus, store fronts, paid pages, calendars and the conversational family (scripted walk-throughs, swipeable slides, AI Companion pages).
- Biolink editor: drag-and-drop blocks with per-block styling and display rules, global themes with overrides, card containers, device preview, SEO/OG/PWA settings, plan-gated custom branding and CSS/JS, plus the AI biolink builder and persona onboarding wizard.
- Analytics: click and session tracking with a geographic heatmap, per-plan retention and CSV export; scaled async tracking pipeline with rollups.
- Growth & audience: email/WhatsApp subscribers, forms (21 field types with paid-form pricing), reviews (native + Google/Trustpilot import), social-proof widgets, contacts with Google sync and the Zio Dialer's universal finder.
- Monetization: plans with smart upgrade recommendations, coin wallet, client invoicing (PDF/refunds/tax), creator payouts via five hosted-onboarding providers, and the product storefront block.
- Platform: workspaces, custom domains (user + shared global), Sanctum REST API with usage metering, admin control planes (integrations hub, mail/SMTP, banned names, schema health, adult-content moderation) and Ask Zio in-app assistant.
MD,
            'docs' => <<<'MD'
### Docs — initial documentation set

- `features.md`: exhaustive catalog of every shipping feature, grouped by surface and capability.
- `knowledge-base.md`: end-user help guide covering link creation, the biolink editor, analytics, billing and account management.
- `api.md`: REST API reference for `/api/v1` — auth, envelopes, rate limits and every endpoint with request/response examples.
- `chatbot-training.md`: customer-facing Ask Zio training corpus kept in sync with the knowledge base.
- `claude-training.md`: comprehensive technical training reference with cross-links into the API docs (guarded for anchor drift).
- Docs ship with every deploy; the hub's Docs row tracks the newest docs file timestamp from the committed version snapshot.
MD,
            'marketing' => <<<'MD'
### Marketing site — initial public release

- Home page with the "What you can create" showcase (10 headline link types, kept in sync with the Features catalog) and the claim-your-handle hero handoff into sign-up.
- Features hub grouped by category, plus a public `/demos` gallery of seeded explainer pages for every major link type.
- Pricing page with the plan comparison matrix, coin packages, competitor comparison, and the smart plan recommender shared with the in-app upgrade page.
- Creators directory with verified badges and an 18+ visibility gate.
- Company/legal pages (terms, privacy, about) driven by the Company Identity settings; dark/light theme support with reduced-motion-aware animations.
MD,
            'mobile' => <<<'MD'
### Mobile app 1.0 — full-parity Expo release

- Auth: email/OTP login-or-signup, Google sign-in, and 2FA challenge handling; staged first-run setup wizard (persona → template → optional WhatsApp) mirroring web onboarding.
- Links: create/edit every major link type, My Links list with filters and CSV share-sheet export, QR codes with scan analytics.
- Biolink editor: native block create/edit with inline block settings, drag reorder, live preview, themes and per-block styling parity with web.
- Ordering surfaces: Restaurant Menu (tables, live orders dashboard, estimated bill) and Store (order requests dashboard) with full REST parity.
- Resume builder, Paid Pages (native template tokens), Reviews moderation, Contacts & Dialer with the grouped universal finder, analytics dashboards, notifications and the in-app inbox.
- Admin-on-mobile: mail/SMTP settings, schema-health view with one-click column repair.
MD,
            'dialer' => <<<'MD'
### Zio Dialer 1.0 — standalone dialer release

- Number-pad dialer with T9 smart-dial and a keypad toggle between the T9 digit grid and an alphanumeric keyboard.
- Grouped universal finder: one search across contacts (names/orgs), people on Sayzio, your links and biolink aliases, followed accounts and workspaces — visibility-gated and grouped by category with verified / on-Sayzio filter chips.
- Phone-number → biolink resolution via linked identifiers, with silent biolink auto-attach (and detach memory).
- Two-way Google Contacts sync (incremental, scheduled every 30 minutes) and bulk CSV/vCard import with parse → preview → confirm.
- Native caller-ID overlay support at ring time; `tel:` / `mailto:` actions only.
MD,
            'zio_browser' => <<<'MD'
### Zio Browser 0.3.x — profiles & device lab

- Multi-profile browsing: each profile gets an isolated session (cookies, storage, logins) with quick switching.
- Device lab for responsive testing with per-device emulation.
- Private windows with hardened lifecycle and privacy guards; internal about-pages with canonical URLs.
- Chrome overlay improvements (ref-counted show/hide) and window-lifecycle fixes.
- Auto-update feed: releases publish to GitHub and surface in this hub automatically.
MD,
            'extension' => <<<'MD'
### Zio Extension — initial release

- One-click shorten of the current page into a Sayzio short link, including "Shorten as A/B test" to spin up weighted variants.
- Quick access to your recent links with copy-to-clipboard.
- Feeds the Backlinks radar so shares of your links around the web are tracked.
- Signs in with your Sayzio account; respects workspace context.
MD,
            'api_server' => <<<'MD'
### API Server (Node) — initial service release

- Express 5 + TypeScript service in the pnpm monorepo, deployable behind the shared proxy at `/api`.
- Health probe endpoint and a public contact-message intake route backed by PostgreSQL via Drizzle ORM.
- Contract-first: OpenAPI spec with generated Zod validation and typed clients (Orval codegen).
- Dev-only Laravel proxy fallback for Replit previews (not used in production routing).
MD,
            default => 'Initial recorded release for ' . Release::label($surface) . '.',
        };
    }
}
