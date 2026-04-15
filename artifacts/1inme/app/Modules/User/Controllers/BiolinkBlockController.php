<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BiolinkBlockController extends Controller
{
    public function editor(Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);
        $blocks = $link->biolinkBlocks()->whereNull('parent_id')->orderBy('sort_order')->get();
        $blocks->load('children');
        $blockTypes = BiolinkBlock::TYPES;
        $blockCategories = BiolinkBlock::CATEGORIES;
        return view('user.links.biolink-editor', compact('link', 'blocks', 'blockTypes', 'blockCategories'));
    }

    public function settings(Link $link)
    {
        return redirect()->route('user.links.settings.appearance', $link);
    }

    public function settingsAppearance(Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);
        $bgTemplates = \App\Modules\Admin\Models\BgTemplate::active()->get();
        return view('user.links.settings.appearance', compact('link', 'bgTemplates'));
    }

    public function settingsLayout(Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);
        return view('user.links.settings.layout', compact('link'));
    }

    public function settingsBlockTheme(Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);
        return view('user.links.settings.block-theme', compact('link'));
    }

    public function settingsAdvanced(Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);
        return view('user.links.settings.advanced', compact('link'));
    }

    public function store(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);

        $validated = $request->validate([
            'type' => 'required|string|in:' . implode(',', array_keys(BiolinkBlock::TYPES)),
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
            'parent_id' => 'nullable|integer|exists:biolink_blocks,id',
        ]);

        $parentId = $validated['parent_id'] ?? null;
        if ($parentId) {
            $parentBlock = BiolinkBlock::where('id', $parentId)->where('link_id', $link->id)->where('type', 'card')->firstOrFail();
            $maxSort = $parentBlock->children()->max('sort_order') ?? -1;
        } else {
            $maxSort = $link->biolinkBlocks()->whereNull('parent_id')->max('sort_order') ?? -1;
        }

        $settings = $validated['settings'] ?? $this->getDefaultSettings($validated['type']);
        $settings = $this->sanitizeSettings($validated['type'], $settings);

        $block = $link->biolinkBlocks()->create([
            'type' => $validated['type'],
            'settings' => $settings,
            'sort_order' => $maxSort + 1,
            'is_active' => $validated['is_active'] ?? true,
            'parent_id' => $parentId,
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
            'style' => 'nullable|array',
            'is_active' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'visibility' => 'nullable|array',
        ]);

        $settings = $validated['settings'] ?? $block->settings;
        $settings = $this->sanitizeSettings($block->type, $settings);

        $settings['_visibility'] = $this->sanitizeVisibility($validated['visibility'] ?? ($block->settings['_visibility'] ?? []));
        $existingStyle = $block->settings['_style'] ?? [];
        $incomingStyle = $validated['style'] ?? [];
        $settings['_style'] = $this->sanitizeBlockStyle(array_merge($existingStyle, $incomingStyle));

        $block->update([
            'settings' => $settings,
            'is_active' => $validated['is_active'] ?? $block->is_active,
            'start_date' => $validated['start_date'] ?? $block->start_date,
            'end_date' => $validated['end_date'] ?? $block->end_date,
        ]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'block' => $block->fresh()]);
        }

        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Block updated.');
    }

    public function editForm(Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== auth()->id() || $block->link_id !== $link->id, 403);
        $blockTypes = BiolinkBlock::TYPES;
        $html = view('user.links.partials.block-edit-form-ajax', compact('link', 'block', 'blockTypes'))->render();
        return response()->json(['html' => $html]);
    }

    public function destroy(Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== auth()->id() || $block->link_id !== $link->id, 403);
        $block->delete();

        if (request()->ajax() || request()->wantsJson()) {
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

    public function moveBlock(Request $request, Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== auth()->id() || $block->link_id !== $link->id, 403);

        $validated = $request->validate([
            'parent_id' => 'nullable|integer|exists:biolink_blocks,id',
        ]);

        $newParentId = $validated['parent_id'] ?? null;

        if ($block->type === 'card' && $newParentId) {
            return response()->json(['success' => false, 'error' => 'Cannot move a card container inside another card.'], 422);
        }

        $oldParentId = $block->parent_id;

        if ($newParentId) {
            $parent = BiolinkBlock::where('id', $newParentId)
                ->where('link_id', $link->id)
                ->where('type', 'card')
                ->firstOrFail();
            $maxSort = $parent->children()->max('sort_order') ?? -1;
        } else {
            $maxSort = $link->biolinkBlocks()->whereNull('parent_id')->max('sort_order') ?? -1;
        }

        $block->update([
            'parent_id' => $newParentId,
            'sort_order' => $maxSort + 1,
        ]);

        if ($oldParentId) {
            $siblings = BiolinkBlock::where('parent_id', $oldParentId)
                ->where('link_id', $link->id)
                ->orderBy('sort_order')
                ->get();
            foreach ($siblings as $i => $sib) {
                $sib->update(['sort_order' => $i]);
            }
        } else {
            $siblings = $link->biolinkBlocks()
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->get();
            foreach ($siblings as $i => $sib) {
                $sib->update(['sort_order' => $i]);
            }
        }

        return response()->json(['success' => true, 'block' => $block->fresh()]);
    }

    public function toggleActive(Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== auth()->id() || $block->link_id !== $link->id, 403);
        $block->update(['is_active' => !$block->is_active]);

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => true, 'block' => $block->fresh()]);
        }

        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Block visibility toggled.');
    }

    public function updatePageSettings(Request $request, Link $link)
    {
        abort_if($link->user_id !== auth()->id() || $link->type !== 'biolink', 403);

        $validated = $request->validate([
            'biolink_title' => 'nullable|string|max:100',
            'biolink_description' => 'nullable|string|max:500',
            'background_type' => 'nullable|string|in:color,gradient,image,slideshow,video,template',
            'background_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'background_gradient' => 'nullable|string|max:500',
            'background_image' => 'nullable|image|max:5120',
            'gradient_colors' => 'nullable|string|max:2000',
            'gradient_angle' => 'nullable|integer|min:0|max:360',
            'gradient_type' => 'nullable|string|in:linear,radial,conic',
            'slideshow_images' => 'nullable|array|max:10',
            'slideshow_images.*' => 'image|max:5120',
            'slideshow_interval' => 'nullable|integer|min:1|max:30',
            'video_url' => 'nullable|string|max:500',
            'video_file' => 'nullable|mimes:mp4,webm|max:51200',
            'bg_template_id' => 'nullable|integer|exists:bg_templates,id',
            'bg_attachment' => 'nullable|string|in:fixed,scroll',
            'bg_fallback_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'bg_fallback_image' => 'nullable|image|max:5120',
            'bg_blur' => 'nullable|integer|min:0|max:100',
            'bg_overlay_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'bg_overlay_opacity' => 'nullable|integer|min:0|max:100',
            'font_family' => 'nullable|string|max:100',
            'font_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'button_style' => 'nullable|string|in:rounded,pill,square,outline,shadow',
            'button_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'button_text_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'verified_badge' => 'boolean',
            'branding_hidden' => 'boolean',
            'block_theme' => 'nullable|array',
            'custom_branding_text' => 'nullable|string|max:100',
            'custom_branding_url' => 'nullable|string|max:500',
            'custom_branding_logo' => 'nullable|string|max:500',
            'favicon_url' => 'nullable|string|max:500',
            'favicon_upload' => 'nullable|image|max:1024',
            'custom_css' => 'nullable|string|max:10000',
            'custom_js_head' => 'nullable|string|max:10000',
            'custom_js_body' => 'nullable|string|max:10000',
            'layout' => 'nullable|array',
            'meta' => 'nullable|array',
            'meta.seo_title' => 'nullable|string|max:70',
            'meta.seo_description' => 'nullable|string|max:320',
            'meta.keywords' => 'nullable|string|max:500',
            'meta.author' => 'nullable|string|max:100',
            'meta.language' => 'nullable|string|max:5',
            'meta.canonical_url' => 'nullable|url|max:500',
            'meta.robots' => ['nullable', 'string', Rule::in(['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'])],
            'meta.rating' => 'nullable|string|in:general,mature,restricted',
            'og' => 'nullable|array',
            'og.title' => 'nullable|string|max:100',
            'og.description' => 'nullable|string|max:300',
            'og.type' => 'nullable|string|in:website,profile,article,product',
            'og.site_name' => 'nullable|string|max:100',
            'og.image_url' => 'nullable|url|max:500',
            'og_image_upload' => 'nullable|image|max:2048',
            'twitter' => 'nullable|array',
            'twitter.card' => 'nullable|string|in:summary_large_image,summary,app,player',
            'twitter.site' => 'nullable|string|max:50',
            'twitter.title' => 'nullable|string|max:100',
            'twitter.description' => 'nullable|string|max:200',
            'favicons' => 'nullable|array',
            'favicons.apple_touch_icon' => 'nullable|url|max:500',
            'favicons.icon_512' => 'nullable|url|max:500',
            'apple_touch_upload' => 'nullable|image|max:1024',
            'icon_512_upload' => 'nullable|image|max:2048',
            'manifest' => 'nullable|array',
            'manifest.enabled' => 'boolean',
            'manifest.name' => 'nullable|string|max:100',
            'manifest.short_name' => 'nullable|string|max:25',
            'manifest.description' => 'nullable|string|max:300',
            'manifest.display' => 'nullable|string|in:standalone,fullscreen,minimal-ui,browser',
            'manifest.orientation' => 'nullable|string|in:any,portrait,landscape',
            'manifest.theme_color' => 'nullable|string|max:20',
            'manifest.background_color' => 'nullable|string|max:20',
            'manifest.start_url' => 'nullable|string|max:200',
            'manifest.categories' => 'nullable|string|max:200',
        ]);

        $user = auth()->user();
        $settings = $link->settings ?? [];
        $blockTheme = $validated['block_theme'] ?? null;
        $layoutInput = $validated['layout'] ?? null;
        $metaInput = $validated['meta'] ?? null;
        $ogInput = $validated['og'] ?? null;
        $twitterInput = $validated['twitter'] ?? null;
        $faviconsInput = $validated['favicons'] ?? null;
        $manifestInput = $validated['manifest'] ?? null;
        $slideshowFiles = $request->file('slideshow_images');
        $videoFile = $request->file('video_file');
        $fallbackImageFile = $request->file('bg_fallback_image');
        unset($validated['block_theme'], $validated['layout'], $validated['meta'], $validated['og'], $validated['twitter'], $validated['favicons'], $validated['manifest'], $validated['og_image_upload'], $validated['apple_touch_upload'], $validated['icon_512_upload'], $validated['slideshow_images'], $validated['video_file'], $validated['bg_fallback_image']);

        if (!$user->getPlanFeature('custom_branding', false)) {
            unset($validated['custom_branding_text'], $validated['custom_branding_url'], $validated['custom_branding_logo']);
        }
        if (!$user->getPlanFeature('custom_favicon', false)) {
            unset($validated['favicon_url']);
            $request->files->remove('favicon_upload');
            $faviconsInput = null;
            $request->files->remove('apple_touch_upload');
            $request->files->remove('icon_512_upload');
        }
        if (!$user->getPlanFeature('custom_code', false)) {
            unset($validated['custom_css'], $validated['custom_js_head'], $validated['custom_js_body']);
        }

        unset($validated['favicon_upload']);
        $settings['biolink'] = array_merge($settings['biolink'] ?? [], $validated);

        if ($blockTheme !== null) {
            $settings['biolink']['block_theme'] = $this->sanitizeBlockStyle($blockTheme);
            $settings['biolink']['block_theme']['apply_to_all'] = !empty($blockTheme['apply_to_all']);
        }

        if ($layoutInput !== null) {
            $settings['biolink']['layout'] = $this->sanitizeLayout($layoutInput);
        }

        $nullifyEmpty = fn(array $arr) => array_map(fn($v) => is_string($v) && trim($v) === '' ? null : (is_string($v) ? trim($v) : $v), $arr);

        if ($metaInput !== null) {
            $settings['biolink']['meta'] = $nullifyEmpty($metaInput);
        }

        if ($ogInput !== null) {
            $settings['biolink']['og'] = $nullifyEmpty($ogInput);
        }

        if ($twitterInput !== null) {
            $settings['biolink']['twitter'] = $nullifyEmpty($twitterInput);
        }

        if ($manifestInput !== null) {
            $settings['biolink']['manifest'] = $nullifyEmpty($manifestInput);
            $settings['biolink']['manifest']['enabled'] = !empty($manifestInput['enabled']);
        }

        if ($faviconsInput !== null) {
            $existingFavicons = $settings['biolink']['favicons'] ?? [];
            foreach ($faviconsInput as $k => $v) {
                if (!empty(trim($v))) {
                    $existingFavicons[$k] = $this->sanitizeUrl(trim($v));
                }
            }
            $settings['biolink']['favicons'] = $existingFavicons;
        }

        if ($request->hasFile('background_image')) {
            $path = $request->file('background_image')->store('biolink-backgrounds', 'public');
            $settings['biolink']['background_image'] = Storage::disk('public')->url($path);
        }

        if (!empty($validated['gradient_colors'])) {
            $decoded = json_decode($validated['gradient_colors'], true);
            if (is_array($decoded)) {
                $settings['biolink']['gradient_colors'] = $decoded;
            }
        }

        if ($slideshowFiles && is_array($slideshowFiles)) {
            $existingSlides = $settings['biolink']['slideshow_images'] ?? [];
            foreach ($slideshowFiles as $file) {
                $path = $file->store('biolink-backgrounds', 'public');
                $existingSlides[] = Storage::disk('public')->url($path);
            }
            $settings['biolink']['slideshow_images'] = array_slice($existingSlides, 0, 10);
        }

        if ($videoFile) {
            $path = $videoFile->store('biolink-videos', 'public');
            $settings['biolink']['video_file'] = Storage::disk('public')->url($path);
        }

        if ($fallbackImageFile) {
            $path = $fallbackImageFile->store('biolink-backgrounds', 'public');
            $settings['biolink']['bg_fallback_image'] = Storage::disk('public')->url($path);
        }

        if ($request->has('remove_slideshow_images')) {
            $removeIndexes = array_map('intval', (array) $request->input('remove_slideshow_images', []));
            $existing = $settings['biolink']['slideshow_images'] ?? [];
            $settings['biolink']['slideshow_images'] = array_values(array_diff_key($existing, array_flip($removeIndexes)));
        }

        $faviconValue = null;
        if ($request->hasFile('favicon_upload') && $user->getPlanFeature('custom_favicon', false)) {
            $path = $request->file('favicon_upload')->store('biolink-favicons', 'public');
            $faviconValue = Storage::disk('public')->url($path);
            $settings['biolink']['favicon_url'] = $faviconValue;
        } elseif (!empty($validated['favicon_url']) && $user->getPlanFeature('custom_favicon', false)) {
            $faviconValue = $this->sanitizeUrl($validated['favicon_url']);
            $settings['biolink']['favicon_url'] = $faviconValue;
        }

        if ($request->hasFile('apple_touch_upload') && $user->getPlanFeature('custom_favicon', false)) {
            $path = $request->file('apple_touch_upload')->store('biolink-favicons', 'public');
            $settings['biolink']['favicons']['apple_touch_icon'] = Storage::disk('public')->url($path);
        }

        if ($request->hasFile('icon_512_upload') && $user->getPlanFeature('custom_favicon', false)) {
            $path = $request->file('icon_512_upload')->store('biolink-favicons', 'public');
            $settings['biolink']['favicons']['icon_512'] = Storage::disk('public')->url($path);
        }

        if ($request->hasFile('og_image_upload')) {
            $path = $request->file('og_image_upload')->store('seo-images', 'public');
            $ogImageUrl = Storage::disk('public')->url($path);
            $settings['biolink']['og']['image_url'] = $ogImageUrl;
        }

        $updateData = ['settings' => $settings];
        if ($faviconValue !== null) {
            $updateData['favicon'] = $faviconValue;
        }

        if ($metaInput !== null) {
            if (!empty($metaInput['seo_title'])) {
                $updateData['seo_title'] = trim($metaInput['seo_title']);
            }
            if (!empty($metaInput['seo_description'])) {
                $updateData['seo_description'] = trim($metaInput['seo_description']);
            }
        }

        $ogImageFinal = $settings['biolink']['og']['image_url'] ?? null;
        if ($ogImageFinal) {
            $updateData['seo_image'] = $ogImageFinal;
        }

        if (!empty($settings['biolink']['custom_branding_url'])) {
            $settings['biolink']['custom_branding_url'] = $this->sanitizeUrl($settings['biolink']['custom_branding_url']);
        }
        if (!empty($settings['biolink']['custom_branding_logo'])) {
            $settings['biolink']['custom_branding_logo'] = $this->sanitizeUrl($settings['biolink']['custom_branding_logo']);
        }
        $updateData['settings'] = $settings;

        $link->update($updateData);

        $referer = $request->headers->get('referer', '');
        if (str_contains($referer, '/settings/layout')) {
            return redirect()->route('user.links.settings.layout', $link)->with('success', 'Page settings updated.');
        } elseif (str_contains($referer, '/settings/block-theme')) {
            return redirect()->route('user.links.settings.block-theme', $link)->with('success', 'Page settings updated.');
        } elseif (str_contains($referer, '/settings/advanced')) {
            return redirect()->route('user.links.settings.advanced', $link)->with('success', 'Page settings updated.');
        }
        return redirect()->route('user.links.settings.appearance', $link)->with('success', 'Page settings updated.');
    }

    private function sanitizeLayout(array $input): array
    {
        $bounds = [
            'max_width_phone' => [280, 600],
            'max_width_tablet' => [320, 900],
            'max_width_desktop' => [400, 1200],
            'page_padding_top' => [0, 200],
            'page_padding_bottom' => [0, 200],
            'page_padding_x' => [0, 100],
            'block_gap' => [0, 100],
            'block_padding' => [0, 60],
        ];
        $result = [];
        foreach ($bounds as $key => [$min, $max]) {
            if (isset($input[$key]) && $input[$key] !== '' && is_numeric($input[$key])) {
                $result[$key] = max($min, min($max, (int) $input[$key]));
            }
        }
        return $result;
    }

    private function sanitizeUrl(?string $url): string
    {
        if (empty($url)) return '';
        return preg_match('/^https?:\/\//i', $url) ? $url : '';
    }

    private function sanitizeHtml(string $html): string
    {
        $html = strip_tags(
            $html,
            '<p><br><a><strong><em><u><ul><ol><li><h1><h2><h3><h4><h5><h6><span><div><img><table><tr><td><th><thead><tbody><hr><blockquote><pre><code>'
        );
        $html = preg_replace('/\s+on\w+\s*=/i', ' data-removed=', $html);
        $html = preg_replace('/javascript\s*:/i', '', $html);
        return $html;
    }

    private function sanitizeSettings(string $type, array $settings): array
    {
        $urlFields = ['url', 'link', 'thumbnail', 'image', 'image_url', 'video_url',
                       'audio_url', 'file_url', 'embed_url', 'logo_url', 'cover',
                       'website', 'avatar'];
        foreach ($urlFields as $field) {
            if (isset($settings[$field]) && $settings[$field] !== '') {
                $settings[$field] = $this->sanitizeUrl($settings[$field]);
            }
        }

        if (isset($settings['platforms']) && is_array($settings['platforms'])) {
            foreach ($settings['platforms'] as &$platform) {
                if (isset($platform['url'])) {
                    $platform['url'] = $this->sanitizeUrl($platform['url']);
                }
            }
        }

        if (isset($settings['images']) && is_array($settings['images'])) {
            $settings['images'] = array_values(array_filter($settings['images'], function ($img) {
                $url = is_array($img) ? ($img['url'] ?? '') : $img;
                return empty($url) || preg_match('/^https?:\/\//i', $url);
            }));
        }

        if (isset($settings['items']) && is_array($settings['items'])) {
            foreach ($settings['items'] as &$item) {
                if (isset($item['url'])) $item['url'] = $this->sanitizeUrl($item['url']);
                if (isset($item['image'])) $item['image'] = $this->sanitizeUrl($item['image']);
                if (isset($item['avatar'])) $item['avatar'] = $this->sanitizeUrl($item['avatar']);
            }
        }

        if (isset($settings['cards']) && is_array($settings['cards'])) {
            foreach ($settings['cards'] as &$card) {
                if (isset($card['url'])) $card['url'] = $this->sanitizeUrl($card['url']);
                if (isset($card['image'])) $card['image'] = $this->sanitizeUrl($card['image']);
            }
        }

        if (isset($settings['groups']) && is_array($settings['groups'])) {
            foreach ($settings['groups'] as &$group) {
                if (isset($group['platforms']) && is_array($group['platforms'])) {
                    foreach ($group['platforms'] as &$platform) {
                        if (isset($platform['url'])) $platform['url'] = $this->sanitizeUrl($platform['url']);
                    }
                }
            }
        }

        if (isset($settings['socials']) && is_array($settings['socials'])) {
            foreach ($settings['socials'] as &$social) {
                if (isset($social['url'])) $social['url'] = $this->sanitizeUrl($social['url']);
            }
        }

        if (in_array($type, ['custom_html', 'paragraph_rich']) && isset($settings['html'])) {
            $settings['html'] = $this->sanitizeHtml($settings['html']);
        }

        if (isset($settings['_image_style']) && is_array($settings['_image_style'])) {
            $settings['_image_style'] = $this->sanitizeImageStyle($settings['_image_style']);
        }

        if (isset($settings['_link']) && is_array($settings['_link'])) {
            $settings['_link'] = $this->sanitizeLinkSettings($settings['_link']);
        }

        return $settings;
    }

    private function getDefaultSettings(string $type): array
    {
        return match ($type) {
            'link' => ['url' => '', 'text' => 'My Link', 'icon' => '', 'thumbnail' => ''],
            'link_big' => ['url' => '', 'text' => 'My Link', 'description' => '', 'icon' => '', 'thumbnail' => '', 'bg_color' => '#7c3aed'],
            'heading' => ['text' => 'Heading', 'size' => 'h2', 'align' => 'center'],
            'heading_gradient' => ['text' => 'Gradient Heading', 'size' => 'h2', 'align' => 'center', 'from_color' => '#7c3aed', 'to_color' => '#ec4899'],
            'heading_logo' => ['text' => 'Brand Name', 'logo_url' => '', 'size' => 'h2', 'align' => 'center'],
            'heading_morph' => ['text' => 'Morph Text', 'size' => 'h1', 'align' => 'center'],
            'paragraph' => ['text' => 'Your text here...', 'align' => 'center'],
            'paragraph_rich' => ['html' => '<p>Your rich text content here...</p>'],
            'divider' => ['style' => 'solid', 'color' => 'rgba(255,255,255,0.1)'],
            'list' => ['items' => ['Item 1', 'Item 2', 'Item 3'], 'icon' => 'fa-check'],
            'list_numbered' => ['items' => ['First item', 'Second item', 'Third item']],
            'list_pricing' => ['items' => [['name' => 'Feature', 'price' => '$10', 'included' => true]]],
            'alert' => ['text' => 'Important notice!', 'type' => 'info', 'icon' => 'fa-info-circle'],
            'badge' => ['text' => 'New', 'color' => '#7c3aed', 'text_color' => '#ffffff'],

            'image' => ['url' => '', 'alt' => '', 'link' => ''],
            'image_grid' => ['images' => [], 'columns' => 3, 'gap' => 4],
            'image_slider' => ['images' => [], 'autoplay' => true, 'interval' => 3000],
            'image_slider_v2' => ['images' => [], 'autoplay' => true, 'effect' => 'fade'],
            'header_video' => ['url' => '', 'autoplay' => true, 'muted' => true, 'loop' => true],
            'video' => ['url' => '', 'autoplay' => false],
            'audio' => ['url' => '', 'title' => ''],
            'pdf_document' => ['url' => '', 'title' => 'Document'],
            'powerpoint' => ['url' => '', 'title' => 'Presentation'],
            'excel' => ['url' => '', 'title' => 'Spreadsheet'],

            'socials' => ['platforms' => []],
            'socials_multi' => ['groups' => [['label' => 'Personal', 'platforms' => []]]],
            'socials_custom' => ['platforms' => [], 'style' => 'rounded', 'size' => 'md'],
            'instagram_media' => ['url' => ''],
            'tiktok_video' => ['url' => ''],
            'tiktok_profile' => ['username' => ''],
            'twitter_profile' => ['username' => ''],
            'twitter_tweet' => ['url' => ''],
            'twitter_video' => ['url' => ''],
            'pinterest_profile' => ['username' => ''],
            'snapchat' => ['username' => ''],
            'rss_feed' => ['url' => '', 'count' => 5],

            'spotify' => ['url' => '', 'type' => 'track'],
            'apple_music' => ['url' => '', 'type' => 'album'],
            'soundcloud' => ['url' => ''],
            'tidal' => ['url' => ''],
            'mixcloud' => ['url' => ''],
            'anchor_fm' => ['url' => ''],

            'youtube' => ['video_id' => '', 'autoplay' => false],
            'youtube_feed' => ['channel_id' => '', 'count' => 3],
            'vimeo' => ['video_id' => ''],
            'twitch' => ['channel' => ''],
            'kick' => ['channel' => ''],
            'rumble_video' => ['url' => ''],
            'vk_video' => ['url' => ''],

            'email_collector' => ['title' => 'Subscribe', 'placeholder' => 'Your email', 'button_text' => 'Subscribe'],
            'phone_collector' => ['title' => 'Call Us', 'placeholder' => 'Your phone', 'button_text' => 'Submit'],
            'contact_form' => ['title' => 'Contact Us', 'fields' => ['name', 'email', 'message'], 'button_text' => 'Send'],
            'whatsapp_widget' => ['phone' => '', 'message' => 'Hi!', 'button_text' => 'Chat on WhatsApp'],
            'whatsapp_item' => ['phone' => '', 'name' => '', 'message' => '', 'avatar' => ''],

            'faq' => ['items' => [['question' => 'Question?', 'answer' => 'Answer.']]],
            'faq_v2' => ['items' => [['question' => 'Question?', 'answer' => 'Answer.', 'icon' => '']], 'style' => 'bordered'],
            'poll' => ['question' => 'What do you prefer?', 'options' => ['Option A', 'Option B', 'Option C']],
            'quiz' => ['title' => 'Quick Quiz', 'questions' => [['question' => 'Q?', 'options' => ['A', 'B'], 'correct' => 0]]],
            'testimonials' => ['items' => [['name' => 'John', 'text' => 'Great!', 'avatar' => '', 'rating' => 5]]],
            'review' => ['name' => '', 'text' => '', 'rating' => 5, 'avatar' => ''],
            'timeline' => ['items' => [['title' => 'Event', 'description' => 'Description', 'date' => '']]],
            'timeline_staged' => ['items' => [['title' => 'Stage 1', 'description' => '', 'status' => 'completed']]],

            'product' => ['name' => 'Product', 'description' => '', 'price' => '', 'image' => '', 'url' => '', 'badge' => ''],
            'service' => ['name' => 'Service', 'description' => '', 'price' => '', 'icon' => 'fa-star', 'url' => ''],
            'catalog' => ['items' => [['name' => 'Item', 'price' => '', 'image' => '', 'url' => '']]],
            'market' => ['items' => [['name' => 'Product', 'price' => '$0', 'image' => '', 'url' => '']]],
            'price' => ['amount' => '$99', 'period' => '/month', 'title' => 'Pro Plan', 'features' => ['Feature 1'], 'url' => ''],
            'donation' => ['title' => 'Support Us', 'description' => '', 'amounts' => [5, 10, 25, 50], 'currency' => 'USD', 'url' => ''],
            'coupon' => ['code' => 'SAVE20', 'description' => '20% off!', 'expires' => ''],
            'one_time_offer' => ['title' => 'Special Offer', 'description' => '', 'price' => '', 'original_price' => '', 'url' => '', 'countdown' => ''],
            'paypal' => ['email' => '', 'amount' => '', 'currency' => 'USD', 'button_text' => 'Pay Now'],

            'countdown' => ['target_date' => '', 'title' => 'Coming Soon'],
            'progress' => ['items' => [['label' => 'Progress', 'value' => 75, 'color' => '#7c3aed']]],
            'chart_pie' => ['items' => [['label' => 'Segment', 'value' => 50, 'color' => '#7c3aed']]],
            'qr_code' => ['url' => '', 'size' => 200],
            'share' => ['text' => 'Share this page', 'platforms' => ['twitter', 'facebook', 'linkedin', 'whatsapp']],
            'cta_button' => ['text' => 'Click Here', 'url' => '', 'color' => '#7c3aed', 'text_color' => '#ffffff', 'size' => 'lg'],
            'notification' => ['text' => 'New update!', 'type' => 'info', 'dismissible' => true],
            'nav_menu' => ['items' => [['text' => 'Home', 'url' => '']]],
            'ticker' => ['items' => ['Breaking news', 'Updates'], 'speed' => 'normal'],

            'spacer' => ['height' => 20],
            'card' => [
                'title' => '',
                'columns' => 2,
                'gap' => 12,
                'padding' => 16,
                'border_radius' => 16,
                'bg_type' => 'glass',
                'bg_color' => 'rgba(255,255,255,0.06)',
                'bg_gradient' => '',
                'bg_image' => '',
                'glass_blur' => 12,
                'glass_opacity' => 6,
                'border_color' => 'rgba(255,255,255,0.08)',
                'border_width' => 1,
                'shadow' => 'none',
                'shadow_color' => '#00000040',
            ],

            'card_slider' => ['cards' => [['title' => 'Card', 'description' => '', 'image' => '', 'url' => '']]],
            'scroll_cards' => ['cards' => [['title' => 'Card', 'description' => '', 'image' => '']]],
            'profile_card_v1' => ['name' => '', 'title' => '', 'avatar' => '', 'bio' => '', 'socials' => []],
            'profile_card_v2' => ['name' => '', 'title' => '', 'avatar' => '', 'cover' => '', 'bio' => ''],
            'profile_card_v3' => ['name' => '', 'title' => '', 'avatar' => '', 'stats' => [['label' => 'Followers', 'value' => '0']]],
            'profile_card_v4' => ['name' => '', 'title' => '', 'avatar' => '', 'bio' => '', 'badges' => []],

            'custom_html' => ['html' => ''],
            'iframe_embed' => ['url' => '', 'height' => 400],
            'typeform' => ['url' => ''],
            'calendly' => ['url' => ''],
            'discord_server' => ['server_id' => ''],
            'facebook_post' => ['url' => ''],
            'reddit_post' => ['url' => ''],
            'telegram_post' => ['url' => ''],

            'file' => ['url' => '', 'name' => 'Download File', 'size' => '', 'icon' => 'fa-file-download'],
            'external_item' => ['url' => '', 'title' => '', 'description' => '', 'image' => ''],
            'markdown' => ['content' => '# Hello\n\nYour markdown content here.'],

            'map' => ['address' => '', 'zoom' => 14],
            'yandex_maps' => ['address' => '', 'zoom' => 14],

            'vcard' => ['name' => '', 'email' => '', 'phone' => '', 'company' => '', 'title' => '', 'website' => ''],
            'avatar' => ['url' => '', 'size' => 96, 'rounded' => true],

            default => [],
        };
    }

    private function sanitizeVisibility(array $input): array
    {
        $allowed = [
            'continents' => ['Africa', 'Antarctica', 'Asia', 'Europe', 'North America', 'South America', 'Oceania'],
            'countries' => null,
            'cities' => null,
            'devices' => ['desktop', 'tablet', 'mobile'],
            'os' => ['iOS', 'Android', 'Windows', 'OS X', 'Linux', 'Chrome OS'],
            'browsers' => ['Chrome', 'Firefox', 'Safari', 'Edge', 'Opera', 'Brave', 'Vivaldi', 'Internet Explorer'],
            'languages' => null,
        ];

        $result = [];
        foreach ($allowed as $key => $validValues) {
            if (!isset($input[$key]) || !is_array($input[$key])) {
                $result[$key] = [];
                continue;
            }
            $values = array_filter(array_map('trim', $input[$key]), fn($v) => $v !== '');
            if ($validValues !== null) {
                $values = array_values(array_intersect($values, $validValues));
            } else {
                $values = array_values(array_map(fn($v) => substr(strip_tags($v), 0, 100), $values));
            }
            $result[$key] = $values;
        }
        return $result;
    }

    private function sanitizeBlockStyle(array $input): array
    {
        $enums = [
            'font_style' => ['normal', 'italic'],
            'border_style' => ['none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge'],
            'shadow_type' => ['none', 'soft', 'hard', 'neon', 'glow', 'neumorphic', 'inset'],
            'display_mode' => ['card', 'content'],
            'effect' => ['none', 'glass', 'gradient_border'],
        ];
        $numericBounds = [
            'font_size' => [8, 72],
            'bg_opacity' => [0, 100],
            'border_width' => [0, 10],
            'border_radius' => [0, 999],
            'shadow_x' => [-50, 50],
            'shadow_y' => [-50, 50],
            'shadow_blur' => [0, 100],
            'shadow_spread' => [-20, 50],
            'glass_blur' => [0, 100],
            'glass_opacity' => [0, 100],
            'padding' => [0, 60],
            'padding_top' => [0, 200],
            'padding_bottom' => [0, 200],
            'padding_left' => [0, 200],
            'padding_right' => [0, 200],
            'margin_top' => [-100, 200],
            'margin_bottom' => [-100, 200],
            'margin_left' => [-100, 200],
            'margin_right' => [-100, 200],
            'grid_span' => [1, 12],
        ];
        $colorKeys = ['text_color', 'bg_color', 'border_color', 'shadow_color'];
        $fontWeightKeys = ['font_weight'];
        $fontFamilyKeys = ['font_family'];
        $urlKeys = ['bg_image'];

        $allowed = array_keys(BiolinkBlock::STYLE_DEFAULTS);
        $result = [];
        foreach ($allowed as $key) {
            if (!isset($input[$key]) || $input[$key] === '') continue;
            $val = is_string($input[$key]) ? trim($input[$key]) : $input[$key];

            if (isset($enums[$key])) {
                if (in_array($val, $enums[$key], true)) $result[$key] = $val;
            } elseif (isset($numericBounds[$key])) {
                if (is_numeric($val)) {
                    $result[$key] = max($numericBounds[$key][0], min($numericBounds[$key][1], (float) $val));
                }
            } elseif (in_array($key, $colorKeys, true)) {
                if (preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*[\d.]+\s*)?\)|transparent)$/', $val)) {
                    $result[$key] = $val;
                }
            } elseif (in_array($key, $fontWeightKeys, true)) {
                if (preg_match('/^(300|400|500|600|700|800|900)$/', (string) $val)) {
                    $result[$key] = (string) $val;
                }
            } elseif (in_array($key, $fontFamilyKeys, true)) {
                $safe = preg_replace('/[^a-zA-Z0-9 ]/', '', substr((string) $val, 0, 60));
                if ($safe !== '') $result[$key] = $safe;
            } elseif (in_array($key, $urlKeys, true)) {
                if (filter_var($val, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//', $val)) {
                    $result[$key] = substr($val, 0, 500);
                }
            } elseif ($key === '_template') {
                $validTemplates = array_keys(BiolinkBlock::BLOCK_TEMPLATES);
                if (in_array($val, $validTemplates, true)) {
                    $result[$key] = $val;
                }
            }
        }
        return $result;
    }

    private function sanitizeImageStyle(array $input): array
    {
        $enums = [
            'mask_shape' => ['none', 'rounded', 'circle', 'square', 'diamond', 'hexagon', 'octagon', 'star', 'blob', 'arch'],
            'object_fit' => ['cover', 'contain', 'fill', 'none'],
            'border_style' => ['none', 'solid', 'dashed', 'dotted', 'double'],
            'shadow_type' => ['none', 'soft', 'hard', 'glow', 'neon', 'drop'],
        ];
        $numericBounds = [
            'border_radius' => [0, 999],
            'border_width' => [0, 10],
            'shadow_x' => [-40, 40],
            'shadow_y' => [-40, 40],
            'shadow_blur' => [0, 80],
            'shadow_spread' => [-20, 40],
        ];
        $colorKeys = ['border_color', 'shadow_color'];

        $result = [];
        foreach (array_keys(BiolinkBlock::IMAGE_STYLE_DEFAULTS) as $key) {
            if (!isset($input[$key]) || $input[$key] === '') continue;
            $val = is_string($input[$key]) ? trim($input[$key]) : $input[$key];

            if (isset($enums[$key])) {
                if (in_array($val, $enums[$key], true)) $result[$key] = $val;
            } elseif (isset($numericBounds[$key])) {
                if (is_numeric($val)) {
                    $result[$key] = max($numericBounds[$key][0], min($numericBounds[$key][1], (float) $val));
                }
            } elseif (in_array($key, $colorKeys, true)) {
                if (preg_match('/^(#[0-9a-fA-F]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*[\d.]+\s*)?\)|transparent)$/', $val)) {
                    $result[$key] = $val;
                }
            }
        }
        return $result;
    }

    private function sanitizeLinkSettings(array $input): array
    {
        $result = [];

        if (!empty($input['url'])) {
            $url = trim($input['url']);
            if (filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//', $url)) {
                $result['url'] = substr($url, 0, 2048);
            }
        }

        $allowedTargets = ['_blank', '_self'];
        if (isset($input['target']) && in_array($input['target'], $allowedTargets, true)) {
            $result['target'] = $input['target'];
        }

        $allowedRels = ['noopener', 'noopener nofollow', 'noopener noreferrer', 'noopener noreferrer nofollow', 'sponsored', 'ugc'];
        if (isset($input['rel']) && in_array($input['rel'], $allowedRels, true)) {
            $result['rel'] = $input['rel'];
        }

        if (!empty($input['title'])) {
            $result['title'] = substr(strip_tags(trim($input['title'])), 0, 200);
        }

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $utm) {
            if (!empty($input[$utm])) {
                $result[$utm] = preg_replace('/[^a-zA-Z0-9_\-. ]/', '', substr(trim($input[$utm]), 0, 100));
            }
        }

        return $result;
    }
}
