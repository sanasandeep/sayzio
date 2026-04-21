<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\FaqItem;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Services\PathSuggester;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Http\Request;

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
        return view('admin.site-pages.edit', compact('page', 'faqs', 'settings', 'featuresCategories'));
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
        ];
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
        $page->update($payload);
        return redirect()->route('admin.site-pages.edit', $slug)->with('success', 'Page updated.');
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
        ]);

        $sections = SitePagesContent::normalizeFeaturesCategories(
            (array) ($data['categories'] ?? [])
        );

        $page->update([
            'title' => $data['title'],
            'meta_description' => $data['meta_description'] ?? null,
            'sections' => $sections,
        ]);

        return redirect()->route('admin.site-pages.edit', $page->slug)->with('success', 'Features page updated.');
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
