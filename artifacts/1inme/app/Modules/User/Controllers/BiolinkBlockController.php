<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class BiolinkBlockController extends Controller
{
    public function editor(Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);
        $blocks = $link->biolinkBlocks;
        $blockTypes = BiolinkBlock::TYPES;
        $blockCategories = BiolinkBlock::CATEGORIES;
        return view('user.links.biolink-editor', compact('link', 'blocks', 'blockTypes', 'blockCategories'));
    }

    public function store(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);

        $validated = $request->validate([
            'type' => 'required|string|in:' . implode(',', array_keys(BiolinkBlock::TYPES)),
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $maxSort = $link->biolinkBlocks()->max('sort_order') ?? -1;

        $block = $link->biolinkBlocks()->create([
            'type' => $validated['type'],
            'settings' => $validated['settings'] ?? $this->getDefaultSettings($validated['type']),
            'sort_order' => $maxSort + 1,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'block' => $block]);
        }

        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Block added.');
    }

    public function update(Request $request, Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== auth()->id() || $block->link_id !== $link->id, 403);

        $validated = $request->validate([
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $settings = $validated['settings'] ?? $block->settings;
        $settings = $this->sanitizeSettings($block->type, $settings);

        $block->update([
            'settings' => $settings,
            'is_active' => $validated['is_active'] ?? $block->is_active,
            'start_date' => $validated['start_date'] ?? $block->start_date,
            'end_date' => $validated['end_date'] ?? $block->end_date,
        ]);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'block' => $block->fresh()]);
        }

        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Block updated.');
    }

    public function destroy(Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== auth()->id() || $block->link_id !== $link->id, 403);

        $block->delete();

        if (request()->ajax()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Block deleted.');
    }

    public function reorder(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);

        $validated = $request->validate([
            'blocks' => 'required|array',
            'blocks.*' => 'integer|exists:biolink_blocks,id',
        ]);

        foreach ($validated['blocks'] as $index => $blockId) {
            BiolinkBlock::where('id', $blockId)
                ->where('link_id', $link->id)
                ->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }

    public function toggleActive(Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== auth()->id() || $block->link_id !== $link->id, 403);

        $block->update(['is_active' => !$block->is_active]);

        if (request()->ajax()) {
            return response()->json(['success' => true, 'is_active' => $block->is_active]);
        }

        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Block visibility toggled.');
    }

    public function updatePageSettings(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);

        $validated = $request->validate([
            'biolink_title' => 'nullable|string|max:100',
            'biolink_description' => 'nullable|string|max:500',
            'background_type' => 'nullable|string|in:color,gradient,image',
            'background_color' => 'nullable|string|max:20',
            'background_gradient' => 'nullable|string|max:200',
            'background_image' => 'nullable|image|max:5120',
            'font_family' => 'nullable|string|max:100',
            'font_color' => 'nullable|string|max:20',
            'button_style' => 'nullable|string|in:rounded,pill,square,outline,shadow',
            'button_color' => 'nullable|string|max:20',
            'button_text_color' => 'nullable|string|max:20',
            'verified_badge' => 'boolean',
            'branding_hidden' => 'boolean',
        ]);

        $settings = $link->settings ?? [];
        $settings['biolink'] = array_merge($settings['biolink'] ?? [], $validated);

        if ($request->hasFile('background_image')) {
            $path = $request->file('background_image')->store('biolink-backgrounds', 'public');
            $settings['biolink']['background_image'] = Storage::disk('public')->url($path);
        }

        $link->update(['settings' => $settings]);

        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Page settings updated.');
    }

    private function sanitizeSettings(string $type, array $settings): array
    {
        $urlFields = ['url', 'link', 'thumbnail'];
        foreach ($urlFields as $field) {
            if (isset($settings[$field]) && $settings[$field] !== '') {
                $url = $settings[$field];
                if (!preg_match('/^https?:\/\//i', $url)) {
                    $settings[$field] = '';
                }
            }
        }

        if (isset($settings['platforms']) && is_array($settings['platforms'])) {
            foreach ($settings['platforms'] as &$platform) {
                if (isset($platform['url']) && !preg_match('/^https?:\/\//i', $platform['url'])) {
                    $platform['url'] = '';
                }
            }
        }

        if ($type === 'custom_html' && isset($settings['html'])) {
            $settings['html'] = strip_tags(
                $settings['html'],
                '<p><br><a><strong><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6><span><div><img><iframe><table><tr><td><th><thead><tbody><hr><blockquote><pre><code>'
            );
        }

        return $settings;
    }

    private function getDefaultSettings(string $type): array
    {
        return match ($type) {
            'link' => ['url' => '', 'text' => 'My Link', 'icon' => '', 'thumbnail' => ''],
            'heading' => ['text' => 'Heading', 'size' => 'h2', 'align' => 'center'],
            'paragraph' => ['text' => 'Your text here...', 'align' => 'center'],
            'image' => ['url' => '', 'alt' => '', 'link' => '', 'full_width' => false],
            'video' => ['url' => '', 'autoplay' => false],
            'audio' => ['url' => '', 'title' => ''],
            'divider' => ['style' => 'solid', 'color' => 'rgba(255,255,255,0.1)'],
            'spacer' => ['height' => 20],
            'avatar' => ['url' => '', 'size' => 96, 'rounded' => true],
            'socials' => ['platforms' => []],
            'faq' => ['items' => [['question' => 'Question?', 'answer' => 'Answer.']]],
            'email_collector' => ['title' => 'Subscribe', 'placeholder' => 'Your email', 'button_text' => 'Subscribe'],
            'map' => ['address' => '', 'zoom' => 14],
            'custom_html' => ['html' => ''],
            'youtube' => ['video_id' => '', 'autoplay' => false],
            'spotify' => ['url' => '', 'type' => 'track'],
            'countdown' => ['target_date' => '', 'title' => 'Coming Soon'],
            'cta_button' => ['text' => 'Click Here', 'url' => '', 'color' => '#7c3aed', 'text_color' => '#ffffff', 'size' => 'lg'],
            default => [],
        };
    }
}
