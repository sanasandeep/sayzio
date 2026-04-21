<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\FaqItem;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Services\PathSuggester;
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
        $settings = [
            'discovery_per_page'        => (int) AppSetting::get('discovery_per_page', 24),
            'discovery_show_search'     => (bool) AppSetting::get('discovery_show_search', true),
            'creators_feed_per_page'    => (int) AppSetting::get('creators_feed_per_page', 12),
            'creators_feed_show_pinned' => (bool) AppSetting::get('creators_feed_show_pinned', true),
            'error_404_suggestions_enabled' => (bool) AppSetting::get(PathSuggester::SETTING_KEY, true),
        ];
        return view('admin.site-pages.edit', compact('page', 'faqs', 'settings'));
    }

    public function update(Request $request, string $slug)
    {
        $page = SitePage::where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'meta_description' => 'nullable|string|max:500',
            'sections' => 'array',
            'sections.*.heading' => 'nullable|string|max:200',
            'sections.*.body' => 'nullable|string|max:20000',
            'cta_label' => 'nullable|string|max:120',
            'cta_url' => ['nullable', 'string', 'max:500', 'regex:#^(/|https?://)#i'],
            'error_404_suggestions_enabled' => 'nullable|boolean',
        ]);
        if ($slug === 'error-404') {
            AppSetting::put(PathSuggester::SETTING_KEY, (bool) $request->input('error_404_suggestions_enabled', false));
        }
        $sections = collect($data['sections'] ?? [])
            ->filter(fn($s) => trim($s['heading'] ?? '') !== '' || trim($s['body'] ?? '') !== '')
            ->values()->all();
        $page->update([
            'title' => $data['title'],
            'meta_description' => $data['meta_description'] ?? null,
            'sections' => $sections,
            'cta_label' => $data['cta_label'] ?? null,
            'cta_url' => $data['cta_url'] ?? null,
        ]);
        return redirect()->route('admin.site-pages.edit', $slug)->with('success', 'Page updated.');
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
