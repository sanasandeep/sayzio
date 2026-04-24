<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\ContactMessage;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Models\SitePageRevision;
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
                    'meta_description' => 'A complete tour of every capability inside 1INME — biolinks, short links, QR codes, analytics, inboxes, teams, billing, and more.',
                    'sections' => SitePagesContent::featuresCategoriesDefault(),
                ]
            );
            $categories = SitePagesContent::normalizeFeaturesCategories(
                is_array($page->sections) ? $page->sections : []
            );
            if (empty($categories)) {
                $categories = SitePagesContent::featuresCategoriesDefault();
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
                default              => null,
            };
            $unlockedOn = [];
            if ($featureKey) {
                $plans = \App\Modules\Admin\Models\Plan::active()->ordered()->get();
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
            ->where('type', 'biolink')
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

        $data = $request->validate([
            'name'    => 'required|string|max:120',
            'email'   => 'required|email|max:190',
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:5000',
            'website' => 'nullable|max:0', // honeypot
        ], $customRuleMessages);

        // Honeypot tripped — silently succeed.
        if (!empty($request->input('website'))) {
            return back()->with('success', $successMessage);
        }

        $key = 'contact:' . ($request->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['message' => 'Too many submissions — please try again in a few minutes.'])->withInput();
        }
        RateLimiter::hit($key, 600);

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
                        $m->to($recipient)->subject('[1INME Contact] ' . $msg->subject);
                    }
                );
            } catch (\Throwable $e) {
                \Log::warning('Contact email failed: ' . $e->getMessage());
            }
        }

        return back()->with('success', $successMessage);
    }
}
