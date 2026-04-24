<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\FaqItem;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Models\SitePageRevision;
use App\Modules\Common\Services\PathSuggester;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SitePageController extends Controller
{
    public function index()
    {
        $pages = SitePage::orderBy('id')->get();
        return view('admin.site-pages.index', [
            'pages' => $pages,
            'recipient' => AppSetting::get('contact_recipient_email', ''),
        ]);
    }

    public function edit(string $slug)
    {
        $page = SitePage::where('slug', $slug)->firstOrFail();
        $faqs = $slug === 'faqs' ? FaqItem::where('page_slug', 'faqs')->orderBy('sort_order')->orderBy('id')->get() : collect();
        $revisions = SitePageRevision::where('site_page_id', $page->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();
        if ($slug === 'features') {
            $current = is_array($page->sections) ? $page->sections : [];
            $featuresCategories = SitePagesContent::normalizeFeaturesCategories($current);
            if (empty($featuresCategories)) {
                $featuresCategories = SitePagesContent::featuresCategoriesDefault();
            }
        } else {
            $featuresCategories = [];
        }
        $settings = [
            'discovery_per_page'        => (int) AppSetting::get('discovery_per_page', 24),
            'discovery_show_search'     => (bool) AppSetting::get('discovery_show_search', true),
            'creators_feed_per_page'    => (int) AppSetting::get('creators_feed_per_page', 12),
            'creators_feed_show_pinned' => (bool) AppSetting::get('creators_feed_show_pinned', true),
            'error_404_suggestions_enabled' => (bool) AppSetting::get(PathSuggester::SETTING_KEY, true),
        ];
        // Lightweight blog data for the per-page "Related blog posts" block.
        $blogCategories = collect();
        $blogPosts      = collect();
        try {
            $blogCategories = \App\Modules\Common\Models\BlogCategory::orderBy('name')->get(['id', 'name']);
            $blogPosts      = \App\Modules\Common\Models\BlogPost::published()
                ->orderByDesc('published_at')->take(50)->get(['id', 'title', 'slug']);
        } catch (\Throwable $e) {
            // blogs migration not yet run
        }
        return view('admin.site-pages.edit', compact(
            'page', 'faqs', 'settings', 'featuresCategories', 'revisions',
            'blogCategories', 'blogPosts'
        ));
    }

    public function update(Request $request, string $slug)
    {
        $page = SitePage::where('slug', $slug)->firstOrFail();

        if ($slug === 'features') {
            return $this->updateFeatures($request, $page);
        }

        $rules = [
            'title' => 'required|string|max:200',
            'meta_description' => 'nullable|string|max:500',
            'intro' => 'nullable|string|max:2000',
            'last_updated_at' => 'nullable|date',
            'show_toc' => 'nullable|boolean',
            'sections' => 'array',
            'sections.*.id' => 'nullable|string|max:80',
            'sections.*.heading' => 'nullable|string|max:200',
            'sections.*.body' => 'nullable|string|max:20000',
            'sections.*.visible' => 'nullable|boolean',
            'cta_label' => 'nullable|string|max:120',
            'cta_url' => ['nullable', 'string', 'max:500', 'regex:#^(/|https?://)#i'],
            'error_404_suggestions_enabled' => 'nullable|boolean',
            'extra' => 'nullable|array',
            'extra.blog_block.enabled'     => 'nullable|boolean',
            'extra.blog_block.heading'     => 'nullable|string|max:200',
            'extra.blog_block.category_id' => 'nullable|integer|exists:blog_categories,id',
            'extra.blog_block.post_ids'    => 'nullable|array|max:6',
            'extra.blog_block.post_ids.*'  => 'integer|exists:blog_posts,id',
            'extra.blog_block.limit'       => 'nullable|integer|min:1|max:6',
        ];
        if ($slug === 'about') {
            // Hero block (badge, side image, location card, three stats).
            $rules['extra.hero']                          = 'nullable|array';
            $rules['extra.hero.badge_label']              = 'nullable|string|max:60';
            $rules['extra.hero.badge_icon']               = 'nullable|string|max:60';
            $rules['extra.hero.side_image']               = ['nullable', 'string', 'max:1000', 'regex:#^(/|https?://)#i'];
            $rules['extra.hero.side_image_alt']           = 'nullable|string|max:200';
            $rules['extra.hero.location_title']           = 'nullable|string|max:120';
            $rules['extra.hero.location_subtitle']        = 'nullable|string|max:120';
            $rules['extra.hero.location_icon']            = 'nullable|string|max:60';
            $rules['extra.hero.stats']                    = 'nullable|array|max:6';
            $rules['extra.hero.stats.*.value']            = 'nullable|string|max:40';
            $rules['extra.hero.stats.*.suffix']           = 'nullable|string|max:10';
            $rules['extra.hero.stats.*.label']            = 'nullable|string|max:120';
            $rules['extra.hero.stats.*.visible']          = 'nullable|boolean';
            // Values section (heading + repeatable cards).
            $rules['extra.values']                        = 'nullable|array';
            $rules['extra.values.heading']                = 'nullable|string|max:200';
            $rules['extra.values.subheading']             = 'nullable|string|max:500';
            $rules['extra.values.cards']                  = 'nullable|array|max:8';
            $rules['extra.values.cards.*.icon']           = 'nullable|string|max:60';
            $rules['extra.values.cards.*.title']          = 'nullable|string|max:200';
            $rules['extra.values.cards.*.desc']           = 'nullable|string|max:500';
            // Story images: office, values, team band.
            $rules['extra.story_images']                  = 'nullable|array';
            $rules['extra.story_images.office.url']       = ['nullable', 'string', 'max:1000', 'regex:#^(/|https?://)#i'];
            $rules['extra.story_images.office.alt']       = 'nullable|string|max:200';
            $rules['extra.story_images.values.url']       = ['nullable', 'string', 'max:1000', 'regex:#^(/|https?://)#i'];
            $rules['extra.story_images.values.alt']       = 'nullable|string|max:200';
            $rules['extra.story_images.team_band.url']    = ['nullable', 'string', 'max:1000', 'regex:#^(/|https?://)#i'];
            $rules['extra.story_images.team_band.alt']    = 'nullable|string|max:200';
            // Lower-section titles / subtitles.
            $rules['extra.section_titles']                = 'nullable|array';
            $rules['extra.section_titles.founder']             = 'nullable|string|max:200';
            $rules['extra.section_titles.co_founders']         = 'nullable|string|max:200';
            $rules['extra.section_titles.team_title']          = 'nullable|string|max:200';
            $rules['extra.section_titles.team_subtitle']       = 'nullable|string|max:300';
            $rules['extra.section_titles.milestones_title']    = 'nullable|string|max:200';
            $rules['extra.section_titles.milestones_subtitle'] = 'nullable|string|max:300';
            // Render order for the lower /about sections (drag-to-reorder).
            // The list is canonicalised in normalizeAboutExtra(): unknown
            // and duplicate slugs are dropped, missing slugs are appended.
            $rules['extra.section_order']   = 'nullable|array|max:7';
            $rules['extra.section_order.*'] = ['nullable', 'string', 'in:' . implode(',', SitePagesContent::aboutLowerSectionSlugs())];
            // Per-section visibility map (slug => bool). The map is
            // canonicalised in normalizeAboutExtra(): unknown keys are
            // dropped and missing slugs default to visible (true).
            $rules['extra.section_visibility'] = 'nullable|array';
            foreach (SitePagesContent::aboutLowerSectionSlugs() as $visSlug) {
                $rules['extra.section_visibility.' . $visSlug] = 'nullable|boolean';
            }
            // Bottom call-to-action block.
            $rules['extra.cta']                           = 'nullable|array';
            $rules['extra.cta.heading']                   = 'nullable|string|max:200';
            $rules['extra.cta.body']                      = 'nullable|string|max:1000';
            $rules['extra.cta.primary_label']             = 'nullable|string|max:120';
            $rules['extra.cta.primary_url']               = ['nullable', 'string', 'max:500', 'regex:#^(/|https?://)#i'];
            $rules['extra.cta.secondary_label']           = 'nullable|string|max:120';
            $rules['extra.cta.secondary_url']             = ['nullable', 'string', 'max:500', 'regex:#^(/|https?://)#i'];

            $rules['extra.founder']                       = 'nullable|array';
            $rules['extra.founder.name']                  = 'nullable|string|max:120';
            $rules['extra.founder.role']                  = 'nullable|string|max:120';
            $rules['extra.founder.photo']                 = 'nullable|string|max:1000';
            $rules['extra.founder.bio']                   = 'nullable|string|max:2000';
            $rules['extra.founder.links.twitter']         = ['nullable', 'string', 'max:500', 'regex:#^https?://#i'];
            $rules['extra.founder.links.linkedin']        = ['nullable', 'string', 'max:500', 'regex:#^https?://#i'];
            $rules['extra.co_founders']                   = 'nullable|array|max:20';
            $rules['extra.co_founders.*.name']            = 'nullable|string|max:120';
            $rules['extra.co_founders.*.role']            = 'nullable|string|max:120';
            $rules['extra.co_founders.*.photo']           = 'nullable|string|max:1000';
            $rules['extra.co_founders.*.bio']             = 'nullable|string|max:2000';
            $rules['extra.co_founders.*.links.twitter']   = ['nullable', 'string', 'max:500', 'regex:#^https?://#i'];
            $rules['extra.co_founders.*.links.linkedin']  = ['nullable', 'string', 'max:500', 'regex:#^https?://#i'];
            $rules['extra.team']                          = 'nullable|array|max:60';
            $rules['extra.team.*.name']                   = 'nullable|string|max:120';
            $rules['extra.team.*.role']                   = 'nullable|string|max:120';
            $rules['extra.team.*.photo']                  = 'nullable|string|max:1000';
            $rules['extra.team.*.bio']                    = 'nullable|string|max:2000';
            $rules['extra.team.*.links.twitter']          = ['nullable', 'string', 'max:500', 'regex:#^https?://#i'];
            $rules['extra.team.*.links.linkedin']         = ['nullable', 'string', 'max:500', 'regex:#^https?://#i'];
            $rules['extra.milestones']                    = 'nullable|array|max:50';
            $rules['extra.milestones.*.date']             = 'nullable|string|max:40';
            $rules['extra.milestones.*.title']            = 'nullable|string|max:200';
            $rules['extra.milestones.*.description']      = 'nullable|string|max:1000';
        }
        if ($slug === 'contact') {
            $rules['extra.address']            = 'nullable|string|max:1000';
            $rules['extra.email']              = 'nullable|email|max:190';
            $rules['extra.phone']              = 'nullable|string|max:60';
            $rules['extra.hours']              = 'nullable|string|max:500';
            $rules['extra.social.twitter']     = ['nullable', 'string', 'max:500', 'regex:#^https?://#i'];
            $rules['extra.social.instagram']   = ['nullable', 'string', 'max:500', 'regex:#^https?://#i'];
            $rules['extra.social.linkedin']    = ['nullable', 'string', 'max:500', 'regex:#^https?://#i'];
            $rules['extra.social.youtube']     = ['nullable', 'string', 'max:500', 'regex:#^https?://#i'];
            $rules['extra.social.facebook']    = ['nullable', 'string', 'max:500', 'regex:#^https?://#i'];
            $rules['extra.map.lat']            = 'nullable|numeric|between:-90,90';
            $rules['extra.map.lng']            = 'nullable|numeric|between:-180,180';
            $rules['extra.map.zoom']           = 'nullable|integer|between:1,19';
            $rules['extra.map.label']          = 'nullable|string|max:200';
            // Hero pill / availability / language line / side image / floating card.
            $rules['extra.hero.badge_label']             = 'nullable|string|max:60';
            $rules['extra.hero.badge_icon']              = 'nullable|string|max:60';
            $rules['extra.hero.availability_text']       = 'nullable|string|max:200';
            $rules['extra.hero.availability_icon']       = 'nullable|string|max:60';
            $rules['extra.hero.languages']               = 'nullable|string|max:200';
            $rules['extra.hero.side_image']              = ['nullable', 'string', 'max:1000', 'regex:#^(/|https?://)#i'];
            $rules['extra.hero.side_image_alt']          = 'nullable|string|max:200';
            $rules['extra.hero.floating_card.title']     = 'nullable|string|max:120';
            $rules['extra.hero.floating_card.subtitle']  = 'nullable|string|max:120';
            $rules['extra.hero.floating_card.icon']      = 'nullable|string|max:60';
            // Contact-details heading.
            $rules['extra.details_heading']              = 'nullable|string|max:200';
            // Three feature cards between the map and the form.
            $rules['extra.feature_cards']                = 'nullable|array|max:6';
            $rules['extra.feature_cards.*.icon']         = 'nullable|string|max:60';
            $rules['extra.feature_cards.*.title']        = 'nullable|string|max:200';
            $rules['extra.feature_cards.*.desc']         = 'nullable|string|max:500';
            // Office image next to the form.
            $rules['extra.office_image.url']             = ['nullable', 'string', 'max:1000', 'regex:#^(/|https?://)#i'];
            $rules['extra.office_image.alt']             = 'nullable|string|max:200';
            // Form copy (heading, optional intro, labels, placeholders, submit).
            $rules['extra.form.heading']                 = 'nullable|string|max:200';
            $rules['extra.form.intro']                   = 'nullable|string|max:500';
            $rules['extra.form.name_label']              = 'nullable|string|max:80';
            $rules['extra.form.name_placeholder']        = 'nullable|string|max:200';
            $rules['extra.form.email_label']             = 'nullable|string|max:80';
            $rules['extra.form.email_placeholder']       = 'nullable|string|max:200';
            $rules['extra.form.subject_label']           = 'nullable|string|max:80';
            $rules['extra.form.subject_placeholder']     = 'nullable|string|max:200';
            $rules['extra.form.message_label']           = 'nullable|string|max:80';
            $rules['extra.form.message_placeholder']     = 'nullable|string|max:200';
            $rules['extra.form.submit_label']            = 'nullable|string|max:80';
        }
        if ($slug === 'services') {
            $rules['sections.*.tagline']   = 'nullable|string|max:200';
            $rules['sections.*.icon']      = 'nullable|string|max:60';
            $rules['sections.*.tint']      = 'nullable|string|max:120';
            $rules['sections.*.bullets']   = 'nullable|string|max:4000';
            $rules['sections.*.cta_label'] = 'nullable|string|max:120';
            $rules['sections.*.cta_url']   = ['nullable', 'string', 'max:500', 'regex:#^(/|https?://)#i'];
        }
        $data = $request->validate($rules);
        if ($slug === 'error-404') {
            AppSetting::put(PathSuggester::SETTING_KEY, (bool) $request->input('error_404_suggestions_enabled', false));
        }
        $sections = collect($data['sections'] ?? [])
            ->filter(fn($s) => trim($s['heading'] ?? '') !== '' || trim($s['body'] ?? '') !== '')
            ->map(function ($s) use ($slug) {
                if ($slug === 'services') {
                    $bulletsRaw = (string) ($s['bullets'] ?? '');
                    $bullets = array_values(array_filter(
                        array_map('trim', preg_split('/\r?\n/', $bulletsRaw)),
                        fn($b) => $b !== ''
                    ));
                    return [
                        'heading'   => trim((string) ($s['heading'] ?? '')),
                        'tagline'   => trim((string) ($s['tagline'] ?? '')),
                        'body'      => (string) ($s['body'] ?? ''),
                        'icon'      => trim((string) ($s['icon'] ?? '')),
                        'tint'      => trim((string) ($s['tint'] ?? '')),
                        'bullets'   => $bullets,
                        'cta_label' => trim((string) ($s['cta_label'] ?? '')),
                        'cta_url'   => trim((string) ($s['cta_url'] ?? '')),
                    ];
                }
                return [
                    'id'      => trim((string) ($s['id'] ?? '')),
                    'heading' => (string) ($s['heading'] ?? ''),
                    'body'    => (string) ($s['body'] ?? ''),
                    'visible' => array_key_exists('visible', $s) ? (bool) $s['visible'] : true,
                ];
            })
            ->values()->all();
        // The services page uses a specialized section schema (tagline, icon,
        // bullets, CTA, etc.) that normalizeSections would strip — only
        // normalize for the generic/policy editor.
        if ($slug !== 'services') {
            $sections = SitePagesContent::normalizeSections($sections);
        }

        $isPolicy = in_array($slug, SitePagesContent::policySlugs(), true);
        $payload = [
            'title' => $data['title'],
            'meta_description' => $data['meta_description'] ?? null,
            'sections' => $sections,
            'cta_label' => $data['cta_label'] ?? null,
            'cta_url' => $data['cta_url'] ?? null,
        ];
        if ($isPolicy) {
            $payload['intro'] = $data['intro'] ?? null;
            $payload['last_updated_at'] = $data['last_updated_at'] ?? null;
            $payload['show_toc'] = (bool) $request->input('show_toc', false);
        }
        if ($slug === 'about') {
            $payload['extra'] = SitePagesContent::normalizeAboutExtra((array) ($data['extra'] ?? []));
        } elseif ($slug === 'contact') {
            $payload['extra'] = SitePagesContent::normalizeContactExtra((array) ($data['extra'] ?? []));
        }
        // Persist the optional Related-blog-posts block on any site page.
        // Lives under extra.blog_block so it composes with about/contact extras.
        $blogBlock = $request->input('extra.blog_block');
        if (is_array($blogBlock)) {
            $existing = is_array($payload['extra'] ?? null)
                ? $payload['extra']
                : (is_array($page->extra) ? $page->extra : []);
            $existing['blog_block'] = [
                'enabled'     => (bool) ($blogBlock['enabled'] ?? false),
                'heading'     => trim((string) ($blogBlock['heading'] ?? '')) ?: null,
                'category_id' => isset($blogBlock['category_id']) && $blogBlock['category_id'] !== ''
                                  ? (int) $blogBlock['category_id'] : null,
                'post_ids'    => array_values(array_filter(array_map('intval', (array) ($blogBlock['post_ids'] ?? [])))),
                'limit'       => max(1, min(6, (int) ($blogBlock['limit'] ?? 3))),
            ];
            $payload['extra'] = $existing;
        }
        $previous = $this->captureState($page);
        $page->update($payload);
        $this->snapshotPrevious($page->fresh(), $previous, $this->captureState($page->fresh()));
        return redirect()->route('admin.site-pages.edit', $slug)->with('success', 'Page updated.');
    }

    /**
     * Capture the savable state of a SitePage as an associative array so
     * we can compare it against the post-save state to build a revision
     * summary.
     */
    private function captureState(SitePage $page): array
    {
        return [
            'title'            => (string) $page->title,
            'meta_description' => (string) ($page->meta_description ?? ''),
            'intro'            => (string) ($page->intro ?? ''),
            'last_updated_at'  => $page->last_updated_at ? $page->last_updated_at->toDateString() : null,
            'show_toc'         => (bool) ($page->show_toc ?? true),
            'sections'         => is_array($page->sections) ? $page->sections : [],
            'extra'            => is_array($page->extra) ? $page->extra : null,
            'cta_label'        => (string) ($page->cta_label ?? ''),
            'cta_url'          => (string) ($page->cta_url ?? ''),
        ];
    }

    /**
     * Persist the previous state of the page as a new revision row,
     * tagged with the editor who triggered the replacing save and a
     * short summary describing what changed. Called on every save so
     * the audit trail is complete.
     */
    private function snapshotPrevious(SitePage $page, array $previous, array $newState): void
    {
        [$id, $type, $name] = $this->currentEditor();
        SitePageRevision::snapshot($page, $previous, $newState, $id, $type, $name);
    }

    /**
     * Resolve the editor of the current request. Admin pages run under
     * the `admin` guard; we fall back to the default web guard so the
     * helper still records meaningful identity if the editor was
     * authenticated as a regular user (e.g. via an impersonation flow).
     */
    private function currentEditor(): array
    {
        if ($admin = Auth::guard('admin')->user()) {
            return [(int) $admin->getKey(), 'admin', (string) ($admin->name ?? $admin->email ?? '')];
        }
        if ($user = Auth::user()) {
            return [(int) $user->getKey(), 'user', (string) ($user->name ?? $user->email ?? '')];
        }
        return [null, null, null];
    }

    /**
     * Save the /features page using its category-structured sections
     * (id/icon/heading/intro/features). Each category keeps its own
     * ordered list of feature rows, preserving the "no merging / no
     * collapsing" rule the public page relies on.
     */
    private function updateFeatures(Request $request, SitePage $page)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'meta_description' => 'nullable|string|max:500',
            'categories' => 'array',
            'categories.*.id' => 'nullable|string|max:80|regex:/^[a-z0-9\-]*$/i',
            'categories.*.icon' => 'nullable|string|max:80',
            'categories.*.heading' => 'nullable|string|max:200',
            'categories.*.intro' => 'nullable|string|max:2000',
            'categories.*.features' => 'array',
            'categories.*.features.*.name' => 'nullable|string|max:200',
            'categories.*.features.*.description' => 'nullable|string|max:2000',
            'categories.*.features.*.link' => ['nullable', 'string', 'max:500', 'regex:#^(/|https?://)#i'],
        ]);

        $sections = SitePagesContent::normalizeFeaturesCategories(
            (array) ($data['categories'] ?? [])
        );

        $previous = $this->captureState($page);
        $page->update([
            'title' => $data['title'],
            'meta_description' => $data['meta_description'] ?? null,
            'sections' => $sections,
        ]);
        $this->snapshotPrevious($page->fresh(), $previous, $this->captureState($page->fresh()));

        return redirect()->route('admin.site-pages.edit', $page->slug)->with('success', 'Features page updated.');
    }

    /**
     * Show a single revision side-by-side with the page's current state
     * so an admin can preview what was different before restoring.
     */
    public function showRevision(string $slug, SitePageRevision $revision)
    {
        $page = SitePage::where('slug', $slug)->firstOrFail();
        abort_unless($revision->site_page_id === $page->id, 404);
        return view('admin.site-pages.revision', [
            'page'     => $page,
            'revision' => $revision,
        ]);
    }

    /**
     * Restore a prior revision: copy its content back onto the page and
     * record the act as a new snapshot so the history stays linear.
     */
    public function restoreRevision(string $slug, SitePageRevision $revision)
    {
        $page = SitePage::where('slug', $slug)->firstOrFail();
        abort_unless($revision->site_page_id === $page->id, 404);

        $previous = $this->captureState($page);
        $page->update([
            'title'            => $revision->title ?? $page->title,
            'meta_description' => $revision->meta_description,
            'intro'            => $revision->intro,
            'last_updated_at'  => $revision->last_updated_at?->toDateString(),
            'show_toc'         => (bool) $revision->show_toc,
            'sections'         => is_array($revision->sections) ? $revision->sections : [],
            'extra'            => is_array($revision->extra) ? $revision->extra : null,
            'cta_label'        => $revision->cta_label,
            'cta_url'          => $revision->cta_url,
        ]);

        [$id, $type, $name] = $this->currentEditor();
        $newState = $this->captureState($page->fresh());
        $rev = SitePageRevision::snapshot($page->fresh(), $previous, $newState, $id, $type, $name);
        $rev->update(['summary' => 'Restored revision #' . $revision->id . '. ' . $rev->summary]);

        return redirect()->route('admin.site-pages.edit', $slug)->with('success', 'Revision restored.');
    }

    public function updateDiscoverySettings(Request $request)
    {
        $data = $request->validate([
            'discovery_per_page'    => 'required|integer|min:4|max:60',
            'discovery_show_search' => 'nullable|boolean',
        ]);
        AppSetting::put('discovery_per_page', (int) $data['discovery_per_page']);
        AppSetting::put('discovery_show_search', (bool) ($data['discovery_show_search'] ?? false));
        return back()->with('success', 'Discovery settings saved.');
    }

    public function updateCreatorsFeedSettings(Request $request)
    {
        $data = $request->validate([
            'creators_feed_per_page'    => 'required|integer|min:4|max:60',
            'creators_feed_show_pinned' => 'nullable|boolean',
        ]);
        AppSetting::put('creators_feed_per_page', (int) $data['creators_feed_per_page']);
        AppSetting::put('creators_feed_show_pinned', (bool) ($data['creators_feed_show_pinned'] ?? false));
        return back()->with('success', 'Creators feed settings saved.');
    }

    public function updateContactRecipient(Request $request)
    {
        $data = $request->validate([
            'contact_recipient_email' => 'nullable|email|max:190',
        ]);
        AppSetting::put('contact_recipient_email', $data['contact_recipient_email'] ?? null);
        return back()->with('success', 'Contact recipient updated.');
    }

    public function storeFaq(Request $request)
    {
        $data = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string|max:5000',
        ]);
        $next = (FaqItem::where('page_slug', 'faqs')->max('sort_order') ?? 0) + 1;
        FaqItem::create([
            'page_slug' => 'faqs',
            'question' => $data['question'],
            'answer' => $data['answer'],
            'sort_order' => $next,
        ]);
        return back()->with('success', 'FAQ added.');
    }

    public function updateFaq(Request $request, FaqItem $faq)
    {
        $data = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string|max:5000',
            'sort_order' => 'nullable|integer',
        ]);
        $faq->update($data);
        return back()->with('success', 'FAQ updated.');
    }

    public function destroyFaq(FaqItem $faq)
    {
        $faq->delete();
        return back()->with('success', 'FAQ removed.');
    }
}
