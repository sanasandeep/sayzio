<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BiolinkBlockController extends Controller
{
    public function editor(Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || $link->type !== 'biolink', 403);
        $blocks = $link->biolinkBlocks()->whereNull('parent_id')->orderBy('sort_order')->get();
        $blocks->load('children');
        $blockTypes = BiolinkBlock::TYPES;
        $blockCategories = BiolinkBlock::CATEGORIES;

        $userForms = auth()->user()->forms()
            ->orderByDesc('id')
            ->get(['id', 'title', 'slug', 'is_active'])
            ->map(fn ($f) => [
                'id'        => $f->id,
                'title'     => $f->title,
                'slug'      => $f->slug,
                'is_active' => (bool) $f->is_active,
            ])->values();

        $userBuzz = \App\Modules\User\Models\SocialProof::where('user_id', workspace_owner_id())
            ->orderByDesc('id')
            ->get(['id', 'name', 'type', 'is_active'])
            ->map(fn ($b) => [
                'id'        => $b->id,
                'name'      => $b->name,
                'type'      => $b->type,
                'is_active' => (bool) $b->is_active,
            ])->values();

        return view('user.links.biolink-editor', compact(
            'link', 'blocks', 'blockTypes', 'blockCategories', 'userForms', 'userBuzz'
        ));
    }

    public function settings(Link $link)
    {
        return redirect()->route('user.links.settings.appearance', $link);
    }

    public function settingsAppearance(Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || $link->type !== 'biolink', 403);
        $bgTemplates = \App\Modules\Admin\Models\BgTemplate::active()->get();
        $link->load(['pixels', 'aliases']);
        $projects = auth()->user()->projects()->orderBy('name')->get();
        $pixels = auth()->user()->pixels()->orderBy('name')->get();
        return view('user.links.settings.appearance', compact('link', 'bgTemplates', 'projects', 'pixels'));
    }

    public function settingsLayout(Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || $link->type !== 'biolink', 403);
        return view('user.links.settings.layout', compact('link'));
    }

    public function settingsBlockTheme(Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || $link->type !== 'biolink', 403);
        return view('user.links.settings.block-theme', compact('link'));
    }

    public function settingsAdvanced(Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || $link->type !== 'biolink', 403);
        return view('user.links.settings.advanced', compact('link'));
    }

    public function store(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || $link->type !== 'biolink', 403);

        $validated = $request->validate([
            'type' => 'required|string|in:' . implode(',', array_keys(BiolinkBlock::TYPES)),
            'settings' => 'nullable|array',
            'is_active' => 'boolean',
            'parent_id' => 'nullable|integer|exists:biolink_blocks,id',
            'insert_after' => 'nullable|integer|exists:biolink_blocks,id',
        ]);

        $parentId = $validated['parent_id'] ?? null;
        $insertAfterId = $validated['insert_after'] ?? null;

        if ($insertAfterId) {
            $afterBlock = BiolinkBlock::where('id', $insertAfterId)->where('link_id', $link->id)->firstOrFail();
            $parentId = $afterBlock->parent_id;
            $newSortOrder = $afterBlock->sort_order + 1;

            if ($parentId) {
                BiolinkBlock::where('parent_id', $parentId)
                    ->where('link_id', $link->id)
                    ->where('sort_order', '>=', $newSortOrder)
                    ->increment('sort_order');
            } else {
                $link->biolinkBlocks()
                    ->whereNull('parent_id')
                    ->where('sort_order', '>=', $newSortOrder)
                    ->increment('sort_order');
            }
            $sortOrder = $newSortOrder;
        } else {
            if ($parentId) {
                $parentBlock = BiolinkBlock::where('id', $parentId)->where('link_id', $link->id)->where('type', 'card')->firstOrFail();
                $maxSort = $parentBlock->children()->max('sort_order') ?? -1;
            } else {
                $maxSort = $link->biolinkBlocks()->whereNull('parent_id')->max('sort_order') ?? -1;
            }
            $sortOrder = $maxSort + 1;
        }

        $settings = $validated['settings'] ?? $this->getDefaultSettings($validated['type']);
        $settings = $this->sanitizeSettings($validated['type'], $settings);

        $block = $link->biolinkBlocks()->create([
            'type' => $validated['type'],
            'settings' => $settings,
            'sort_order' => $sortOrder,
            'is_active' => $validated['is_active'] ?? true,
            'parent_id' => $parentId,
        ]);

        // Notify followers about new biolink content (daily debounce per creator).
        $this->emitBlockAddedFeedEvent($link, $block);

        if ($request->ajax()) {
            return response()->json(['success' => true, 'block' => $block]);
        }

        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Block added.');
    }

    /**
     * Emit a follower feed event + notifications for a newly-added biolink
     * block. Debounced once-per-day per creator so editing sprees don't spam.
     */
    protected function emitBlockAddedFeedEvent(\App\Modules\User\Models\Link $link, BiolinkBlock $block): void
    {
        try {
            $creatorId = $link->user_id;
            $today     = now()->toDateString();

            $alreadyEmittedToday = \App\Modules\User\Models\FeedEvent::where('user_id', $creatorId)
                ->where('type', 'block_added')
                ->whereDate('occurred_at', $today)
                ->exists();
            if ($alreadyEmittedToday) return;

            $creator = $link->user;
            \App\Modules\User\Models\FeedEvent::create([
                'user_id'     => $creatorId,
                'type'        => 'block_added',
                'occurred_at' => now(),
                'data'        => [
                    'creator_name'   => $creator?->name,
                    'creator_avatar' => $creator?->avatar,
                    'link_alias'     => $link->alias,
                    'block_type'     => $block->type,
                    'block_label'    => BiolinkBlock::TYPES[$block->type] ?? $block->type,
                ],
            ]);

            // Per-follower in-app notifications, only if creator opted in.
            if ($creator && $creator->notify_follower_updates) {
                $followerIds = \App\Modules\User\Models\Follow::where('creator_id', $creatorId)->pluck('follower_id');
                foreach ($followerIds as $fid) {
                    \App\Modules\User\Models\UserNotification::create([
                        'user_id'    => $fid,
                        'type'       => 'creator_update',
                        'data'       => [
                            'creator_id'   => $creatorId,
                            'creator_name' => $creator->name,
                            'message'      => "{$creator->name} added something new to their biolink.",
                            'link_alias'   => $link->alias,
                        ],
                        'created_at' => now(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('block_added feed event failed: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== workspace_owner_id() || $block->link_id !== $link->id, 403);

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

        if (in_array($block->type, ['verified_heading', 'verified_avatar'])) {
            $existing = $block->settings;
            if ($block->type === 'verified_heading') {
                $settings['text'] = $existing['text'] ?? '';
                $settings['verified'] = 1;
                $settings['locked_text'] = 1;
            }
            if ($block->type === 'verified_avatar') {
                $settings['image_url'] = $existing['image_url'] ?? '';
                $settings['verified'] = 1;
                $settings['locked_image'] = 1;
            }
        }

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
        abort_if($link->user_id !== workspace_owner_id() || $block->link_id !== $link->id, 403);
        $blockTypes = BiolinkBlock::TYPES;
        $html = view('user.links.partials.block-edit-form-ajax', compact('link', 'block', 'blockTypes'))->render();
        return response()->json(['html' => $html]);
    }

    public function destroy(Link $link, BiolinkBlock $block)
    {
        abort_if($link->user_id !== workspace_owner_id() || $block->link_id !== $link->id, 403);
        if (in_array($block->type, ['verified_heading', 'verified_avatar'])) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Verified blocks cannot be deleted.'], 403);
            }
            return redirect()->back()->with('error', 'Verified blocks cannot be deleted.');
        }
        $block->delete();

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Block deleted.');
    }

    public function reorder(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || $link->type !== 'biolink', 403);

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
        abort_if($link->user_id !== workspace_owner_id() || $block->link_id !== $link->id, 403);

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
        abort_if($link->user_id !== workspace_owner_id() || $block->link_id !== $link->id, 403);
        $block->update(['is_active' => !$block->is_active]);

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => true, 'block' => $block->fresh()]);
        }

        return redirect()->route('user.links.blocks.editor', $link)->with('success', 'Block visibility toggled.');
    }

    public function updatePageSettings(Request $request, Link $link)
    {
        abort_if($link->user_id !== workspace_owner_id() || $link->type !== 'biolink', 403);

        $validated = $request->validate([
            'biolink_title' => 'nullable|string|max:100',
            'biolink_description' => 'nullable|string|max:500',
            'background_type' => 'nullable|string|in:color,gradient,image,slideshow,video,template',
            'background_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'background_gradient' => 'nullable|string|max:500',
            'background_image' => \App\Services\UploadPolicy::rule('link.background_image', $request->user()),
            'gradient_colors' => 'nullable|string|max:2000',
            'gradient_angle' => 'nullable|integer|min:0|max:360',
            'gradient_type' => 'nullable|string|in:linear,radial,conic',
            'slideshow_images' => 'nullable|array|max:10',
            'slideshow_images.*' => \App\Services\UploadPolicy::rule('link.slideshow_image', $request->user(), true),
            'slideshow_interval' => 'nullable|integer|min:1|max:30',
            'video_url' => 'nullable|string|max:500',
            'video_file' => \App\Services\UploadPolicy::rule('link.video_file', $request->user()),
            'bg_template_id' => 'nullable|integer|exists:bg_templates,id',
            'bg_attachment' => 'nullable|string|in:fixed,scroll',
            'bg_fallback_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'bg_fallback_image' => \App\Services\UploadPolicy::rule('link.bg_fallback_image', $request->user()),
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
            'favicon_upload' => \App\Services\UploadPolicy::rule('link.favicon_upload', $request->user()),
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
            'og_image_upload' => \App\Services\UploadPolicy::rule('link.og_image_upload', $request->user()),
            'twitter' => 'nullable|array',
            'twitter.card' => 'nullable|string|in:summary_large_image,summary,app,player',
            'twitter.site' => 'nullable|string|max:50',
            'twitter.title' => 'nullable|string|max:100',
            'twitter.description' => 'nullable|string|max:200',
            'favicons' => 'nullable|array',
            'favicons.apple_touch_icon' => 'nullable|url|max:500',
            'favicons.icon_512' => 'nullable|url|max:500',
            'apple_touch_upload' => \App\Services\UploadPolicy::rule('link.apple_touch_upload', $request->user()),
            'icon_512_upload' => \App\Services\UploadPolicy::rule('link.icon_512_upload', $request->user()),
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

            'share_button' => 'nullable|array',
            'share_button.enabled' => 'boolean',
            'share_button.show_qr' => 'boolean',
            'share_button.style' => 'nullable|string|in:fab,bar,icon',
            'share_button.position' => 'nullable|string|in:bottom-right,bottom-left,bottom-center,top-right,top-left',
            'share_button.color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'share_button.text_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'share_button.size' => 'nullable|string|in:sm,md,lg',
            'share_button.qr_size' => 'nullable|integer|min:100|max:400',
            'share_button.qr_fg_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'share_button.qr_bg_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'share_button.label' => 'nullable|string|max:30',

            'menu_bar' => 'nullable|array',
            'menu_bar.enabled' => 'boolean',
            'menu_bar.position' => 'nullable|string|in:top,bottom,floating-top-right,floating-top-left,floating-bottom-right,floating-bottom-left',
            'menu_bar.style' => 'nullable|string|in:pills,underline,flat',
            'menu_bar.bg_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'menu_bar.text_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'menu_bar.active_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'menu_bar.icon_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'menu_bar.overlay_bg' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'menu_bar.items' => 'nullable|string|max:5000',

            'auto_translate' => 'nullable|array',
            'auto_translate.enabled' => 'boolean',
            'auto_translate.position' => 'nullable|string|in:top-right,top-left,bottom-right,bottom-left',
            'auto_translate.default_lang' => 'nullable|string|max:5',
            'auto_translate.languages' => 'nullable|string|max:500',
            'auto_translate.style' => 'nullable|string|in:dropdown,flags,minimal',
            'auto_translate.bg_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'auto_translate.text_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
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
        $shareButtonInput = $validated['share_button'] ?? null;
        $menuBarInput = $validated['menu_bar'] ?? null;
        $autoTranslateInput = $validated['auto_translate'] ?? null;
        $slideshowFiles = $request->file('slideshow_images');
        $videoFile = $request->file('video_file');
        $fallbackImageFile = $request->file('bg_fallback_image');
        unset($validated['block_theme'], $validated['layout'], $validated['meta'], $validated['og'], $validated['twitter'], $validated['favicons'], $validated['manifest'], $validated['share_button'], $validated['menu_bar'], $validated['auto_translate'], $validated['og_image_upload'], $validated['apple_touch_upload'], $validated['icon_512_upload'], $validated['slideshow_images'], $validated['video_file'], $validated['bg_fallback_image']);

        if ($link->is_verified) {
            unset($validated['biolink_title']);
        }

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

        if ($shareButtonInput !== null) {
            $settings['biolink']['share_button'] = $nullifyEmpty($shareButtonInput);
            $settings['biolink']['share_button']['enabled'] = !empty($shareButtonInput['enabled']);
            $settings['biolink']['share_button']['show_qr'] = !empty($shareButtonInput['show_qr']);
        }

        if ($menuBarInput !== null) {
            $settings['biolink']['menu_bar'] = $nullifyEmpty($menuBarInput);
            $settings['biolink']['menu_bar']['enabled'] = !empty($menuBarInput['enabled']);
            if (!empty($menuBarInput['items'])) {
                $decoded = json_decode($menuBarInput['items'], true);
                if (is_array($decoded)) {
                    $sanitizedItems = [];
                    foreach ($decoded as $item) {
                        if (!is_array($item)) continue;
                        $label = trim(strip_tags(substr($item['label'] ?? '', 0, 30)));
                        if (empty($label)) continue;
                        $rawTarget = $item['target'] ?? '_self';
                        $target = in_array($rawTarget, ['_self', '_blank', 'tab'], true) ? $rawTarget : '_self';

                        if ($target === 'tab') {
                            $rawId = trim((string)($item['id'] ?? ''));
                            if (!preg_match('/^[a-z0-9\-]{1,50}$/i', $rawId)) {
                                $rawId = \Illuminate\Support\Str::slug($label);
                                if (empty($rawId)) {
                                    $rawId = 'tab-' . substr(md5($label . microtime(true) . count($sanitizedItems)), 0, 6);
                                }
                            }
                            $existingIds = array_column(
                                array_filter($sanitizedItems, fn($i) => ($i['target'] ?? '') === 'tab'),
                                'id'
                            );
                            $baseId = $rawId; $n = 1;
                            while (in_array($rawId, $existingIds, true)) {
                                $n++;
                                $rawId = $baseId . '-' . $n;
                            }
                            $sanitizedItems[] = [
                                'label' => $label,
                                'url' => '#' . $rawId,
                                'target' => 'tab',
                                'id' => $rawId,
                                'is_active' => !empty($item['is_active']),
                            ];
                            continue;
                        }

                        $url = trim($item['url'] ?? '');
                        if (empty($url)) continue;
                        if (!preg_match('#^(https?://|/)#i', $url)) continue;
                        $sanitizedItems[] = [
                            'label' => $label,
                            'url' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                            'target' => $target,
                            'is_active' => !empty($item['is_active']),
                        ];
                    }
                    $settings['biolink']['menu_bar']['items'] = array_slice($sanitizedItems, 0, 20);
                }
            }
            unset($settings['biolink']['menu_bar']['items_raw']);
        }

        if ($autoTranslateInput !== null) {
            $settings['biolink']['auto_translate'] = $nullifyEmpty($autoTranslateInput);
            $settings['biolink']['auto_translate']['enabled'] = !empty($autoTranslateInput['enabled']);
            if (!empty($autoTranslateInput['languages'])) {
                $codes = array_filter(array_map('trim', explode(',', $autoTranslateInput['languages'])));
                $validCodes = array_filter($codes, fn($c) => preg_match('/^[a-z]{2}(-[A-Z]{2,3})?$/', $c));
                $settings['biolink']['auto_translate']['languages'] = implode(',', array_slice($validCodes, 0, 30));
            }
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

        // Vault helper: store an UploadedFile and return its public vault URL.
        // Quota / size failures bubble up as RuntimeException; we catch them
        // once around the whole upload block below.
        $vault = fn ($file) => UserFile::createFromUpload($file, $user)->url;

        try {

        if ($request->hasFile('background_image')) {
            $settings['biolink']['background_image'] = $vault($request->file('background_image'));
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
                $existingSlides[] = $vault($file);
            }
            $settings['biolink']['slideshow_images'] = array_slice($existingSlides, 0, 10);
        }

        if ($videoFile) {
            $settings['biolink']['video_file'] = $vault($videoFile);
        }

        if ($fallbackImageFile) {
            $settings['biolink']['bg_fallback_image'] = $vault($fallbackImageFile);
        }

        if ($request->has('remove_slideshow_images')) {
            $removeIndexes = array_map('intval', (array) $request->input('remove_slideshow_images', []));
            $existing = $settings['biolink']['slideshow_images'] ?? [];
            $settings['biolink']['slideshow_images'] = array_values(array_diff_key($existing, array_flip($removeIndexes)));
        }

        $faviconValue = null;
        if ($request->hasFile('favicon_upload') && $user->getPlanFeature('custom_favicon', false)) {
            $faviconValue = $vault($request->file('favicon_upload'));
            $settings['biolink']['favicon_url'] = $faviconValue;
        } elseif (!empty($validated['favicon_url']) && $user->getPlanFeature('custom_favicon', false)) {
            $faviconValue = $this->sanitizeUrl($validated['favicon_url']);
            $settings['biolink']['favicon_url'] = $faviconValue;
        }

        if ($request->hasFile('apple_touch_upload') && $user->getPlanFeature('custom_favicon', false)) {
            $settings['biolink']['favicons']['apple_touch_icon'] = $vault($request->file('apple_touch_upload'));
        }

        if ($request->hasFile('icon_512_upload') && $user->getPlanFeature('custom_favicon', false)) {
            $settings['biolink']['favicons']['icon_512'] = $vault($request->file('icon_512_upload'));
        }

        if ($request->hasFile('og_image_upload')) {
            $settings['biolink']['og']['image_url'] = $vault($request->file('og_image_upload'));
        }

        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
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

    public function sanitizeSettings(string $type, array $settings): array
    {
        $urlFields = ['url', 'link', 'thumbnail', 'image', 'image_url', 'video_url',
                       'audio_url', 'file_url', 'embed_url', 'logo_url', 'cover',
                       'website', 'avatar'];
        foreach ($urlFields as $field) {
            if (isset($settings[$field]) && $settings[$field] !== '') {
                $settings[$field] = $this->sanitizeUrl($settings[$field]);
            }
        }

        // Pre-load this user's connection IDs once, keyed by platform, so we can
        // validate that any referenced connection_id (a) belongs to the current
        // user and (b) matches the entry's platform. Anything that fails either
        // check is silently dropped rather than persisted.
        $userId = optional(auth()->user())->id;
        $userConnByPlatform = [];
        if ($userId) {
            $userConnByPlatform = \App\Modules\User\Models\SocialAccountConnection::query()
                ->where('user_id', $userId)
                ->get(['id', 'platform'])
                ->groupBy('platform')
                ->map(fn ($g) => $g->pluck('id')->all())
                ->all();
        }

        $sanitizeConnRef = function (array $entry) use ($userConnByPlatform) {
            if (! array_key_exists('connection_id', $entry)) return $entry;
            $raw = $entry['connection_id'];
            if ($raw === '' || $raw === null) {
                $entry['connection_id'] = null;
                return $entry;
            }
            $cid = (int) $raw;
            $name = $entry['name'] ?? null;
            $allowed = $name && isset($userConnByPlatform[$name])
                ? $userConnByPlatform[$name] : [];
            $entry['connection_id'] = in_array($cid, $allowed, true) ? $cid : null;
            return $entry;
        };

        if (isset($settings['platforms']) && is_array($settings['platforms'])) {
            foreach ($settings['platforms'] as &$platform) {
                if (isset($platform['url'])) {
                    $platform['url'] = $this->sanitizeUrl($platform['url']);
                }
                // New per-entry follow-button settings (Task #48).
                if (isset($platform['display'])) {
                    $platform['display'] = in_array($platform['display'], ['icon','follow','follow_count'], true)
                        ? $platform['display'] : 'icon';
                }
                $platform = $sanitizeConnRef($platform);
            }
            unset($platform);
        }

        if (isset($settings['groups']) && is_array($settings['groups'])) {
            foreach ($settings['groups'] as &$grp) {
                if (isset($grp['platforms']) && is_array($grp['platforms'])) {
                    foreach ($grp['platforms'] as &$gp) {
                        if (isset($gp['display'])) {
                            $gp['display'] = in_array($gp['display'], ['icon','follow','follow_count'], true)
                                ? $gp['display'] : 'icon';
                        }
                        $gp = $sanitizeConnRef($gp);
                    }
                    unset($gp);
                }
            }
            unset($grp);
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

        if (isset($settings['_tab_id'])) {
            $tid = trim((string)$settings['_tab_id']);
            $settings['_tab_id'] = preg_match('/^[a-z0-9\-]{1,50}$/i', $tid) ? $tid : '';
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
            'email_subscribe' => ['title' => 'Join our Newsletter', 'description' => 'Get the latest updates delivered to your inbox.', 'placeholder' => 'Enter your email', 'button_text' => 'Subscribe', 'success_message' => 'Thanks for subscribing!', 'name_field' => true],
            'whatsapp_channel_subscribe' => ['title' => 'Follow our WhatsApp Channel', 'description' => 'Stay updated with our latest content.', 'channel_url' => '', 'button_text' => 'Follow Channel', 'icon_style' => 'branded'],
            'whatsapp_number_subscribe' => ['title' => 'Subscribe via WhatsApp', 'description' => 'Get updates directly on WhatsApp.', 'phone' => '', 'default_message' => 'Hi! I want to subscribe to updates.', 'button_text' => 'Subscribe on WhatsApp', 'collect_phone' => true],

            'verified_heading' => ['text' => '', 'verified' => true, 'locked_text' => true, 'font_size' => '24', 'alignment' => 'center'],
            'verified_avatar' => ['image_url' => '', 'verified' => true, 'locked_image' => true, 'size' => '100', 'shape' => 'circle'],

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
            'social_proof' => ['social_proof_id' => null],
            'form' => ['form_id' => null, 'height' => 600],
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
            'profile_card_v1' => ['name' => 'Your Name', 'title' => 'What you do', 'avatar' => '', 'bio' => 'A short, friendly bio about yourself.', 'socials' => []],
            'profile_card_v2' => ['name' => 'Your Name', 'title' => 'What you do', 'avatar' => '', 'cover' => '', 'bio' => 'A short, friendly bio about yourself.'],
            'profile_card_v3' => ['name' => 'Your Name', 'title' => 'What you do', 'avatar' => '', 'stats' => [['label' => 'Followers', 'value' => '1.2K'], ['label' => 'Following', 'value' => '320'], ['label' => 'Posts', 'value' => '48']]],
            'profile_card_v4' => ['name' => 'Your Name', 'title' => 'What you do', 'avatar' => '', 'bio' => 'A short, friendly bio about yourself.', 'badges' => []],

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

        // CSV-style fields (free-form text, comma-separated): accept scalar string too.
        $csvFields = ['countries', 'cities', 'languages'];

        $result = [];
        foreach ($allowed as $key => $validValues) {
            $raw = $input[$key] ?? null;
            if (is_string($raw) && in_array($key, $csvFields, true)) {
                $raw = array_map('trim', explode(',', $raw));
            }
            if (!is_array($raw)) {
                $result[$key] = [];
                continue;
            }
            $values = array_filter(array_map('trim', $raw), fn($v) => $v !== '');
            if ($validValues !== null) {
                $values = array_values(array_intersect($values, $validValues));
            } else {
                $values = array_values(array_map(fn($v) => substr(strip_tags($v), 0, 100), $values));
            }
            $result[$key] = $values;
        }

        // Time slots: list of { days: [mon..sun], start: HH:MM, end: HH:MM }.
        // Empty list = no time-of-day restriction.
        $validDays = ['mon','tue','wed','thu','fri','sat','sun'];
        $slots = [];
        $rawSlots = $input['time_slots'] ?? [];
        if (is_array($rawSlots)) {
            foreach ($rawSlots as $slot) {
                if (!is_array($slot)) continue;
                $days = is_array($slot['days'] ?? null)
                    ? array_values(array_intersect(array_map('strtolower', $slot['days']), $validDays))
                    : [];
                $start = (string)($slot['start'] ?? '');
                $end   = (string)($slot['end'] ?? '');
                if (!preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end)) continue;
                if (empty($days)) continue;
                $slots[] = ['days' => $days, 'start' => $start, 'end' => $end];
                if (count($slots) >= 20) break; // hard cap
            }
        }
        $result['time_slots'] = $slots;

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
