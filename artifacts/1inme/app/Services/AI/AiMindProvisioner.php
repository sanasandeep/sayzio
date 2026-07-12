<?php

namespace App\Services\AI;

use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindSource;
use App\Modules\User\Models\User;

/**
 * Boot helpers for the AI Mind feature.
 *
 *   ensurePlatformDefault()  — ensures the global "Sayzio Default Mind"
 *                              exists with the seed knowledge sources.
 *                              Idempotent — safe to call from a console
 *                              command, an admin button, or a migration.
 *
 *   ensureForUser($user)     — placeholder for future per-user seeding.
 *                              The platform default is shared (read-only)
 *                              with every account, so for now this is a
 *                              no-op kept for symmetry with other
 *                              provisioners (e.g. PersonalTaskBoard).
 */
class AiMindProvisioner
{
    public const PLATFORM_NAME = 'Sayzio Default Mind';

    /**
     * Live public snapshots auto-attached to the platform default Mind so
     * the Site Assistant always reflects current public pricing + the
     * feature catalogue. Read live at query time (see AiMindFeatureAdapter
     * ::publicSnapshot) — no re-crawl needed when prices/features change.
     *
     * @var array<int,string>
     */
    protected const PLATFORM_FEATURE_KEYS = ['pricing', 'features'];

    /**
     * Stable identity keys for the two code-defined static text sources on
     * the platform Mind. Stored in each source's `meta['managed_key']` so we
     * can find, adopt (legacy installs), and re-sync them regardless of any
     * title edit — without creating duplicates.
     */
    protected const STATIC_KEY_ABOUT = 'about';
    protected const STATIC_KEY_FAQ   = 'faq';

    /**
     * @param array<int,int> $dispatchedSourceIds Out-param: filled with the ids
     *        of any source freshly (re-)queued and dispatched for ingestion by
     *        this call. Lets callers (e.g. the admin reseed) avoid dispatching
     *        the SAME source a second time in the same action.
     * @return AiMind The platform-managed mind.
     */
    public static function ensurePlatformDefault(array &$dispatchedSourceIds = []): AiMind
    {
        $dispatchedSourceIds = [];

        $mind = AiMind::whereNull('user_id')
            ->where('is_default', true)
            ->first();

        if (!$mind) {
            $mind = AiMind::create([
                'user_id'     => null,
                'name'        => self::PLATFORM_NAME,
                'description' => 'Built-in knowledge about Sayzio — features, how-to, FAQs. Auto-attached to every account.',
                'is_default'  => true,
            ]);
        }

        // Idempotently sync the code-defined static text sources (About + FAQ):
        // create them on first run, and re-ingest ONLY when the code body has
        // drifted from what's stored — so edits to the product overview / FAQ
        // answers reach existing installs on the next provision/reseed.
        $dispatchedSourceIds = array_merge($dispatchedSourceIds, self::ensureStaticTextSources($mind));

        // Idempotently attach the live public pricing + feature snapshots.
        // Done unconditionally (not just on first creation) so existing
        // installs pick them up on the next provision/reseed.
        $dispatchedSourceIds = array_merge($dispatchedSourceIds, self::ensurePublicFeatureSources($mind));

        $mind->recountStats();
        return $mind;
    }

    /**
     * Code-defined static text sources for the platform Mind, keyed by their
     * stable {@see AiMindSource::$meta}['managed_key']. Each entry carries the
     * current code body so drift can be detected and re-ingested.
     *
     * @return array<string,array{type:string,title:string,body:string}>
     */
    protected static function staticTextSourceDefinitions(): array
    {
        return [
            self::STATIC_KEY_ABOUT => [
                'type'  => AiMindSource::TYPE_TEXT,
                'title' => 'About Sayzio',
                'body'  => self::aboutText(),
            ],
            self::STATIC_KEY_FAQ => [
                'type'  => AiMindSource::TYPE_FAQ,
                'title' => 'Common questions',
                'body'  => json_encode(self::seedFaqs(), JSON_UNESCAPED_UNICODE),
            ],
        ];
    }

    /**
     * Create-or-refresh the platform Mind's static About + FAQ sources from
     * the code constants. Idempotent: an unchanged body is a no-op (no
     * re-embed); only genuine drift — or a first-time create — updates the
     * body, re-queues, and dispatches ingestion.
     *
     * Legacy installs (seeded before `managed_key` existed) are located by
     * their original type+title, adopted (tagged with the key), and only
     * re-ingested if their body actually differs from the current code.
     *
     * @return array<int,int> ids of sources (re-)queued and dispatched here.
     */
    protected static function ensureStaticTextSources(AiMind $mind): array
    {
        $dispatched = [];

        foreach (self::staticTextSourceDefinitions() as $key => $def) {
            $source = AiMindSource::where('mind_id', $mind->id)
                ->where('meta->managed_key', $key)
                ->first();

            $isLegacyAdopt = false;
            if (!$source) {
                // Adopt a pre-existing untagged source seeded by an older
                // build so we refresh it in place rather than duplicating it.
                $source = AiMindSource::where('mind_id', $mind->id)
                    ->where('type', $def['type'])
                    ->where('title', $def['title'])
                    ->first();
                $isLegacyAdopt = (bool) $source;
            }

            $bodyMatches = $source && $source->body === $def['body'];

            // Already tagged and current — nothing to do.
            if ($source && $bodyMatches && !$isLegacyAdopt) {
                continue;
            }

            if (!$source) {
                $source = new AiMindSource();
                $source->mind_id = $mind->id;
            }

            $meta = $source->meta ?? [];
            $meta['managed_key'] = $key;
            $source->meta  = $meta;
            $source->type  = $def['type'];
            $source->title = $def['title'];

            // Legacy source whose body already matches code: just tag it so
            // future runs are true no-ops — no needless re-embed.
            if ($bodyMatches && $isLegacyAdopt) {
                $source->save();
                continue;
            }

            $source->body   = $def['body'];
            $source->status = AiMindSource::STATUS_QUEUED;
            $source->save();

            \App\Jobs\IngestAiMindSourceJob::dispatch($source->id);
            $dispatched[] = $source->id;
        }

        return $dispatched;
    }

    /**
     * Attach the public pricing + feature-catalogue snapshots to the given
     * Mind if not already present. Idempotent and safe to call repeatedly
     * (provisioning, admin reseed, migrations). Feature sources are
     * answered live at query time, so ingestion just marks them ready.
     *
     * @return array<int,int> ids of sources created and dispatched here.
     */
    protected static function ensurePublicFeatureSources(AiMind $mind): array
    {
        $dispatched = [];

        foreach (self::PLATFORM_FEATURE_KEYS as $key) {
            $exists = AiMindSource::where('mind_id', $mind->id)
                ->where('type', AiMindSource::TYPE_FEATURE)
                ->where('feature_key', $key)
                ->exists();
            if ($exists) continue;

            $source = AiMindSource::create([
                'mind_id'     => $mind->id,
                'type'        => AiMindSource::TYPE_FEATURE,
                'title'       => 'Sayzio — ' . \App\Services\AI\AiMindFeatureAdapter::label($key),
                'feature_key' => $key,
                'status'      => AiMindSource::STATUS_QUEUED,
            ]);
            \App\Jobs\IngestAiMindSourceJob::dispatch($source->id);
            $dispatched[] = $source->id;
        }

        return $dispatched;
    }

    public static function ensureForUser(User $user): void
    {
        // Per-user "My Mind" is created lazily — when the user opens
        // the Mind dashboard for the first time we create one starter
        // mind so they have something concrete to add sources to.
        if (!AiMind::where('user_id', $user->id)->exists()) {
            AiMind::create([
                'user_id'     => $user->id,
                'name'        => 'My Mind',
                'description' => 'Your personal knowledge base. Add text, documents, FAQs, and links to teach your AI persona.',
            ]);
        }
        // Ensure the platform default exists too so "All Minds" never
        // shows an empty list to a newly-signed-up user.
        self::ensurePlatformDefault();
    }

    protected static function aboutText(): string
    {
        return <<<'TXT'
Sayzio is a creator platform that lets people centralize their online presence into a single biolink page, run short links, and track audience growth.

Core features:
- Biolinks: a customizable mobile-first profile that hosts your links, posts, payments, and forms in one place.
- Short Links and File/Event Links: branded URLs with click analytics, alias rotation, and bot filtering.
- Analytics: per-link visitor stats, retention windows, and a Performance Coach that suggests improvements.
- Wallet & AI Credits: an in-app coin wallet that converts to AI credits used by every AI feature on the platform.
- Inbox & Forms: collect leads and DMs through forms attached to biolinks; replies live in a unified inbox.
- Followers & Posts: creators can publish updates and grow a follower list with email digests.
- Vault: encrypted client/credentials storage for working with collaborators.
- Tasks: lightweight personal kanban tied to your account.
- Domains, QR codes, NFC writes: bring-your-own domain and offline-to-online tools.
- Subscriptions, Plans, Referrals: paid plans with referral rewards and team workspaces.
TXT;
    }

    /** @return array<int,array{q:string,a:string}> */
    protected static function seedFaqs(): array
    {
        return [
            ['q' => 'How do I create a biolink?',
             'a' => 'Open the dashboard, click "Create Link" → "Biolink", and follow the wizard. You can pick a template, set an alias, and add blocks (links, posts, payments, forms).'],
            ['q' => 'How are AI credits different from wallet coins?',
             'a' => 'Wallet coins are general-purpose Sayzio currency. AI credits are spent only by AI features (Mind, Persona, Companion, Coach). Buy credits from "AI Credits" in the dashboard — wallet coins convert to credits at the admin-set rate.'],
            ['q' => 'What does the Performance Coach do?',
             'a' => 'The Performance Coach scores each link daily on visit volume, dwell time, and CTR, and suggests fixes (rename, add a CTA, pause if dead). Snapshots build a 30-day trend you can revisit.'],
            ['q' => 'Can I use my own domain?',
             'a' => 'Yes — go to Domains, add your domain, point its CNAME to the value shown, and we will issue an SSL cert automatically.'],
            ['q' => 'What is the Vault?',
             'a' => 'The Vault stores encrypted client + credential records (logins, API keys, etc.) for sharing with team members. Secrets are encrypted with a per-workspace key and never appear in plain text in the UI.'],
        ];
    }
}
