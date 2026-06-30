<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\ContactMessage;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Models\SitePageRevision;
use App\Modules\Common\Support\ComparisonContent;
use App\Modules\Common\Support\SitePagesContent;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class SitePageController extends Controller
{
    public function show(string $slug, ?Request $request = null)
    {
        $request = $request ?? request();

        if ($slug === 'features') {
            $page = SitePage::firstOrCreate(
                ['slug' => 'features'],
                [
                    'title' => 'Features',
                    'meta_description' => 'A complete tour of every capability inside Sayzio — all 10 link types (short links, Link in Bio pages, conversational, slides, AI chatbot, restaurant menus, file shares, events, contact cards, reviews), plus QR codes, analytics, inboxes, teams, billing, and more.',
                    'sections' => SitePagesContent::featuresCategoriesDefault(),
                ]
            );
            $categories = SitePagesContent::normalizeFeaturesCategories(
                is_array($page->sections) ? $page->sections : []
            );
            if (empty($categories)) {
                $categories = SitePagesContent::featuresCategoriesDefault();
            } else {
                // Append any default categories whose `id` isn't already
                // in the stored sections so new categories surface even
                // on instances with admin-edited content.
                $existingIds = [];
                foreach ($categories as $c) {
                    $cid = (string) ($c['id'] ?? '');
                    if ($cid !== '') $existingIds[$cid] = true;
                }
                foreach (SitePagesContent::featuresCategoriesDefault() as $defaultCat) {
                    $defaultId = (string) ($defaultCat['id'] ?? '');
                    if ($defaultId === '' || isset($existingIds[$defaultId])) {
                        continue;
                    }
                    $categories[] = $defaultCat;
                }
            }
            return view('public.features', ['page' => $page, 'categories' => $categories]);
        }

        $page = SitePage::where('slug', $slug)->firstOrFail();

        if ($slug === 'faqs') {
            // Categorised structure lives in code; admin-edited Q/A in
            // the DB. We merge: DB answers override code answers when
            // the question text matches; any DB-only questions
            // (renamed or freshly added by an admin) appear at the end
            // under a dedicated "More from the team" group so they
            // never disappear.
            $dbItems = $page->faqs();
            $dbByQuestion = [];
            foreach ($dbItems as $row) {
                $key = mb_strtolower(trim((string) $row->question));
                if ($key === '') continue;
                $dbByQuestion[$key] = [
                    'q' => (string) $row->question,
                    'a' => (string) $row->answer,
                ];
            }
            $usedKeys = [];
            $groups = [];
            foreach (SitePagesContent::homepageFaqs() as $cat => $items) {
                $rows = [];
                foreach ($items as $pair) {
                    $key = mb_strtolower(trim((string) $pair[0]));
                    $usedKeys[$key] = true;
                    $rows[] = [
                        'q' => $pair[0],
                        'a' => $dbByQuestion[$key]['a'] ?? $pair[1],
                        'anchor' => \Illuminate\Support\Str::slug($pair[0]),
                    ];
                }
                $groups[$cat] = $rows;
            }
            $extras = [];
            foreach ($dbByQuestion as $key => $row) {
                if (isset($usedKeys[$key])) continue;
                if (trim($row['a']) === '') continue;
                $extras[] = [
                    'q' => $row['q'],
                    'a' => $row['a'],
                    'anchor' => \Illuminate\Support\Str::slug($row['q']),
                ];
            }
            if (!empty($extras)) {
                $groups['More from the team'] = $extras;
            }
            return view('public.faqs', ['page' => $page, 'groups' => $groups]);
        }
        if ($slug === 'contact') {
            $extra = is_array($page->extra) && !empty($page->extra)
                ? SitePagesContent::normalizeContactExtra($page->extra)
                : SitePagesContent::contactExtraDefault();
            return view('public.contact', ['page' => $page, 'extra' => $extra]);
        }
        if ($slug === 'about') {
            $extra = is_array($page->extra) && !empty($page->extra)
                ? SitePagesContent::normalizeAboutExtra($page->extra)
                : SitePagesContent::aboutExtraDefault();
            return view('public.about', ['page' => $page, 'extra' => $extra]);
        }
        if ($slug === 'discovery') {
            return $this->showDiscovery($page, $request);
        }
        if ($slug === 'creators-feed') {
            return $this->showCreatorsFeed($page, $request);
        }
        if ($slug === 'services') {
            return view('public.services', [
                'page' => $page,
                'useCases' => $this->normaliseServicesSections($page->sections ?? []),
            ]);
        }
        if (in_array($slug, SitePagesContent::policySlugs(), true)) {
            $hasHistory = SitePageRevision::where('site_page_id', $page->id)->exists();
            // Resolve company-identity tokens (e.g. {{app_name}}) in the SEO
            // title/description too, not just the body — these feed the
            // <title>/<meta description> via MarketingSeo. Mutating the model
            // in-memory only; the row keeps its tokenised template.
            $page->title = \App\Modules\Common\Support\CompanyIdentity::substitute((string) $page->title);
            $page->meta_description = \App\Modules\Common\Support\CompanyIdentity::substitute((string) $page->meta_description);
            return view('public.policy', ['page' => $page, 'hasHistory' => $hasHistory]);
        }
        if ($slug === 'how-it-works') {
            return view('public.how-it-works', ['page' => $page]);
        }
        if ($slug === 'workspace-team') {
            return view('public.workspace-team', ['page' => $page]);
        }
        if ($slug === 'buzz') {
            return view('public.buzz', ['page' => $page]);
        }
        if (in_array($slug, SitePagesContent::aiProductSlugs(), true)) {
            $featureKey = match ($slug) {
                'ai-chatbot'         => 'ai_chatbot',
                'ai-agent'           => 'ai_agent',
                'ai-widget'          => 'ai_widget',
                'ai-voice-assistant' => 'ai_voice_assistant',
                'whatsapp-agent'     => 'whatsapp_agent',
                default              => null,
            };
            $unlockedOn = [];
            if ($featureKey) {
                $plans = \App\Modules\Admin\Models\Plan::active()->public()->ordered()->get();
                $map = \App\Modules\Common\Support\PremiumFeatures::unlocksByFeature($plans);
                $slugsUnlocked = $map[$featureKey] ?? [];
                foreach ($plans as $p) {
                    if (in_array($p->slug, $slugsUnlocked, true)) {
                        $unlockedOn[] = $p->name;
                    }
                }
            }
            $faqs = SitePagesContent::aiProductFaqs($slug);
            $testimonials = (array) \App\Modules\Admin\Models\AppSetting::get('marketing_features_testimonials', []);
            if (empty($testimonials)) {
                $testimonials = SitePagesContent::testimonialsDefault();
            }
            return view('public.ai-product', [
                'page'         => $page,
                'aiSlug'       => $slug,
                'unlockedOn'   => $unlockedOn,
                'faqs'         => $faqs,
                'testimonials' => $testimonials,
            ]);
        }
        return view('public.page', ['page' => $page]);
    }

    /**
     * /demos — public gallery linking every live "explainer" biolink page,
     * one per marketing headline link type (slugs `demo-type-*`, seeded by
     * LinkTypeExplainerSeeder). Copy stays in step with the home "What you
     * can create" showcase: the card name/icon/description come from the
     * same Features-page `link-types` category that powers the showcase, and
     * we only surface a card when its demo page actually exists.
     */
    public function demos()
    {
        // Showcase copy source (admin-editable). Passing the saved features
        // sections — or an empty array — lets the helper fall back to the
        // built-in 10 link types when nothing has been customised.
        $featuresPage = SitePage::where('slug', 'features')->first();
        $sections = is_array($featuresPage?->sections) ? $featuresPage->sections : [];
        $linkTypes = SitePagesContent::featuresLinkTypesFromSections($sections);

        // Live explainer pages, keyed by alias so we only link real pages.
        $demoLinks = Link::query()
            ->where('type', 'biolink')
            ->where('is_active', true)
            ->where('alias', 'like', 'demo-type-%')
            ->get(['id', 'alias', 'title'])
            ->keyBy('alias');

        $cards = [];
        $seen = [];
        // Preserve the showcase order, dropping any link type whose demo
        // page has not been seeded.
        foreach ($linkTypes as $lt) {
            $name = trim((string) ($lt['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $alias = 'demo-type-' . \Illuminate\Support\Str::slug($name);
            if (! $demoLinks->has($alias)) {
                continue;
            }
            $seen[$alias] = true;
            $cards[] = [
                'name'        => $name,
                'icon'        => trim((string) ($lt['icon'] ?? '')) ?: 'fa-link',
                'description' => trim((string) ($lt['description'] ?? '')),
                'url'         => url('/' . $alias),
            ];
        }
        // Surface any seeded demo whose name no longer matches a showcase row
        // (e.g. an admin renamed the showcase entry) so no page is hidden.
        foreach ($demoLinks as $alias => $link) {
            if (isset($seen[$alias])) {
                continue;
            }
            $slug = \Illuminate\Support\Str::after($alias, 'demo-type-');
            $cards[] = [
                'name'        => $link->title ?: \Illuminate\Support\Str::headline(str_replace('-', ' ', $slug)),
                'icon'        => 'fa-link',
                'description' => '',
                'url'         => url('/' . $alias),
            ];
        }

        return view('public.demos', [
            'seoKey'           => 'demos',
            'cards'            => $cards,
            'shareTitle'       => 'See what you can build with Sayzio',
            'shareDescription' => 'A live gallery of every kind of link Sayzio can create — short links, Link in Bio pages, conversational pages, slides, AI chatbots, restaurant menus, file shares, events, contact cards and reviews pages.',
        ]);
    }

    /**
     * /compare — index of every available competitor comparison.
     */
    public function compareIndex()
    {
        $competitors = ComparisonContent::index();

        return view('public.compare.index', [
            'seoKey'           => 'compare-index',
            'competitors'      => $competitors,
            'total'            => ComparisonContent::totalFeatures(),
            'ourScore'         => ComparisonContent::scores()['ours'] ?? 0,
            'shareTitle'       => 'Compare Sayzio vs Linktree, Beacons, Bitly & more',
            'shareDescription' => 'See how Sayzio stacks up against the tools you already use across '
                . ComparisonContent::totalFeatures()
                . ' features — Link in Bio pages, short links, QR codes, analytics, monetisation and more.',
        ]);
    }

    /**
     * /compare/{competitor} — dedicated head-to-head landing page.
     * 404s on unknown competitors.
     */
    public function compareShow(string $competitor)
    {
        $data = ComparisonContent::competitor($competitor);
        abort_if($data === null, 404);

        return view('public.compare.show', [
            'seoKey'           => 'compare-' . $competitor,
            'competitor'       => $data,
            'shareTitle'       => ComparisonContent::shareTitle($data),
            'shareDescription' => ComparisonContent::shareDescription($data),
            'shareType'        => 'article',
        ]);
    }

    /**
     * Dedicated "Sayzio for X" use-case landing page. Mirrors the AI product
     * slug pattern: the route constrains $persona to a known slug, the
     * editable copy lives on the "for-{persona}" SitePage row, and the
     * hero chrome / feature anchors / FAQ come from SitePagesContent.
     */
    public function useCase(string $persona)
    {
        abort_unless(in_array($persona, SitePagesContent::useCaseSlugs(), true), 404);

        $pageSlug = 'for-' . $persona;
        $defaults = SitePagesContent::useCasesDefault()[$pageSlug] ?? [];
        $page = SitePage::firstOrCreate(
            ['slug' => $pageSlug],
            [
                'title'            => $defaults['title'] ?? ('Sayzio for ' . ucfirst($persona)),
                'meta_description' => $defaults['meta_description'] ?? null,
                'sections'         => $defaults['sections'] ?? [],
                'cta_label'        => $defaults['cta_label'] ?? null,
                'cta_url'          => $defaults['cta_url'] ?? null,
                'extra'            => ['use_case' => SitePagesContent::useCaseExtraDefault($persona)],
            ]
        );

        // Hero chrome, featured features and the FAQ are admin-editable: read
        // them from the SitePage's extra.use_case payload, falling back to the
        // code defaults whenever a field (or the whole payload) is absent so
        // rows seeded before extra.use_case existed still render correctly.
        $metaDefaults = SitePagesContent::useCaseMeta()[$persona] ?? [];
        $extra = is_array($page->extra) ? $page->extra : [];
        $uc = is_array($extra['use_case'] ?? null) ? $extra['use_case'] : [];
        $meta = [
            'eyebrow'  => $uc['eyebrow']  ?? ($metaDefaults['eyebrow']  ?? ''),
            'tagline'  => $uc['tagline']  ?? ($metaDefaults['tagline']  ?? ''),
            'icon'     => $uc['icon']     ?? ($metaDefaults['icon']     ?? 'fa-star'),
            'accent'   => $uc['accent']   ?? ($metaDefaults['accent']   ?? '#7c3aed'),
            'nav_desc' => $uc['nav_desc'] ?? ($metaDefaults['nav_desc'] ?? ''),
            'features' => isset($uc['features']) && is_array($uc['features'])
                ? $uc['features']
                : ($metaDefaults['features'] ?? []),
        ];
        $faqs = isset($uc['faqs']) && is_array($uc['faqs'])
            ? $uc['faqs']
            : SitePagesContent::useCaseFaqs($persona);

        $testimonials = (array) AppSetting::get('marketing_features_testimonials', []);
        if (empty($testimonials)) {
            $testimonials = SitePagesContent::testimonialsDefault();
        }

        return view('public.use-case', [
            'page'         => $page,
            'persona'      => $persona,
            'meta'         => $meta,
            'faqs'         => $faqs,
            'testimonials' => $testimonials,
        ]);
    }

    /**
     * Public-facing change history for a policy page. Shows the date and
     * a short summary of each saved revision so visitors can see how the
     * page has changed over time.
     */
    public function history(string $slug)
    {
        abort_unless(in_array($slug, SitePagesContent::policySlugs(), true), 404);
        $page = SitePage::where('slug', $slug)->firstOrFail();
        $revisions = SitePageRevision::where('site_page_id', $page->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'site_page_id', 'title', 'summary', 'created_at']);
        return view('public.policy-history', ['page' => $page, 'revisions' => $revisions]);
    }

    /**
     * Coerce the editable JSON sections for the /services page into the
     * shape the public Blade view expects, supplying safe defaults for
     * any missing optional fields.
     */
    private function normaliseServicesSections(array $sections): array
    {
        $defaultTints = [
            'from-violet-500/30 to-fuchsia-500/10',
            'from-sky-500/30 to-violet-500/10',
            'from-fuchsia-500/30 to-pink-500/10',
            'from-amber-500/30 to-violet-500/10',
            'from-emerald-500/30 to-sky-500/10',
            'from-rose-500/30 to-violet-500/10',
        ];

        $out = [];
        foreach (array_values($sections) as $i => $s) {
            $title = trim((string) ($s['heading'] ?? ''));
            if ($title === '') {
                continue;
            }
            $bullets = $s['bullets'] ?? [];
            if (is_string($bullets)) {
                $bullets = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $bullets))));
            }
            $bullets = array_values(array_filter(array_map(fn($b) => trim((string) $b), (array) $bullets), fn($b) => $b !== ''));
            $out[] = [
                'title'     => $title,
                'tagline'   => trim((string) ($s['tagline'] ?? '')),
                'desc'      => trim((string) ($s['body'] ?? '')),
                'icon'      => trim((string) ($s['icon'] ?? '')) ?: 'fa-circle-dot',
                'tint'      => trim((string) ($s['tint'] ?? '')) ?: $defaultTints[$i % count($defaultTints)],
                'bullets'   => $bullets,
                'cta_label' => trim((string) ($s['cta_label'] ?? '')) ?: 'Get started',
                'cta_url'   => trim((string) ($s['cta_url'] ?? '')) ?: '/register',
            ];
        }
        return $out;
    }

    private function showDiscovery(SitePage $page, Request $request)
    {
        $perPage = max(4, min(60, (int) AppSetting::get('discovery_per_page', 24)));
        $showSearch = (bool) AppSetting::get('discovery_show_search', true);

        $q = trim((string) $request->query('q', ''));

        // Public biolinks: active + owned by a discoverable user.
        $query = Link::query()
            ->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)
            ->where('is_active', true)
            ->whereHas('user', fn($u) => $u->where('discoverable', true))
            ->with(['user:id,name,handle,bio,avatar,followers_count']);

        if ($showSearch && $q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('title', 'ilike', $like)
                    ->orWhere('alias', 'ilike', $like)
                    ->orWhereHas('user', function ($uq) use ($like) {
                        $uq->where('name', 'ilike', $like)
                            ->orWhere('handle', 'ilike', $like)
                            ->orWhere('bio', 'ilike', $like);
                    });
            });
        }

        $biolinks = $query->orderByDesc('updated_at')->paginate($perPage)->withQueryString();

        return view('public.discovery', [
            'page'       => $page,
            'biolinks'   => $biolinks,
            'q'          => $q,
            'showSearch' => $showSearch,
        ]);
    }

    private function showCreatorsFeed(SitePage $page, Request $request)
    {
        $perPage = max(4, min(60, (int) AppSetting::get('creators_feed_per_page', 12)));
        $showPinned = (bool) AppSetting::get('creators_feed_show_pinned', true);

        $posts = CreatorPost::query()
            ->published()
            ->whereHas('user', fn($u) => $u->where('discoverable', true))
            ->with(['user:id,name,handle,avatar'])
            ->when($showPinned, fn($q) => $q->orderByDesc('pinned_at'))
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('public.creators-feed', [
            'page'       => $page,
            'posts'      => $posts,
            'showPinned' => $showPinned,
        ]);
    }

    public function submitContact(Request $request)
    {
        // Pull admin-editable post-submit copy out of the contact page's
        // `extra` payload (task #782). Each value is optional — blank means
        // "use the literal default" so the page keeps working before any
        // admin saves the contact editor and after they wipe a field.
        $contactPage = SitePage::where('slug', 'contact')->first();
        $extra       = is_array($contactPage?->extra) ? $contactPage->extra : [];
        $messages    = is_array($extra['messages'] ?? null) ? $extra['messages'] : [];

        $defaultSuccess = 'Thanks! Your message has been sent. We will reply within one business day.';
        $successMessage = trim((string) ($messages['success'] ?? '')) !== ''
            ? trim((string) $messages['success'])
            : $defaultSuccess;

        // Map admin-supplied required-field error wording onto Laravel's
        // rule-message dictionary. We only register an override when the
        // admin actually typed something so blank fields keep Laravel's
        // built-in ":attribute is required" phrasing.
        $customRuleMessages = [];
        foreach ([
            'name'    => 'name_required',
            'email'   => 'email_required',
            'subject' => 'subject_required',
            'message' => 'message_required',
        ] as $field => $key) {
            $val = trim((string) ($messages[$key] ?? ''));
            if ($val !== '') {
                $customRuleMessages[$field . '.required'] = $val;
            }
        }
        // Same idea for the "email must be a valid email address" rule —
        // blank means keep Laravel's built-in phrasing.
        $emailInvalid = trim((string) ($messages['email_invalid'] ?? ''));
        if ($emailInvalid !== '') {
            $customRuleMessages['email.email'] = $emailInvalid;
        }

        $data = $request->validate([
            'name'    => 'required|string|max:120',
            'email'   => 'required|email|max:190',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:5000',
            'topic'   => 'nullable|string|max:50',
            'website' => 'nullable|max:0', // honeypot
        ], $customRuleMessages);

        // Honeypot tripped — silently succeed.
        if (!empty($request->input('website'))) {
            return back()->with('success', $successMessage);
        }

        $key = 'contact:' . ($request->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $defaultRateLimited = 'Too many submissions — please try again in a few minutes.';
            $rateLimited = trim((string) ($messages['rate_limited'] ?? '')) !== ''
                ? trim((string) $messages['rate_limited'])
                : $defaultRateLimited;
            return back()->withErrors(['message' => $rateLimited])->withInput();
        }
        RateLimiter::hit($key, 600);

        // "Badge request" topic from a signed-in visitor opens a real badge
        // request (Task #2910) so it lands in the admin review queue rather
        // than the plain contact inbox. Anonymous visitors fall through to a
        // normal contact message (we can't attach a badge to no account).
        if (($data['topic'] ?? '') === 'badge_request' && auth()->check()) {
            $result = app(\App\Modules\User\Services\BadgeRequestService::class)
                ->submit(auth()->user(), null, $data['subject'], $data['message']);

            return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
        }

        $msg = ContactMessage::create([
            'name'    => $data['name'],
            'email'   => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'ip'      => $request->ip(),
            'status'  => 'new',
        ]);

        $recipient = AppSetting::get('contact_recipient_email');
        if ($recipient) {
            try {
                Mail::raw(
                    "New contact message from {$msg->name} <{$msg->email}>\n\nSubject: {$msg->subject}\n\n{$msg->message}",
                    function ($m) use ($recipient, $msg) {
                        $m->to($recipient)->subject('[Sayzio Contact] ' . $msg->subject);
                    }
                );
            } catch (\Throwable $e) {
                \Log::warning('Contact email failed: ' . $e->getMessage());
            }
        }

        return back()->with('success', $successMessage);
    }
}
