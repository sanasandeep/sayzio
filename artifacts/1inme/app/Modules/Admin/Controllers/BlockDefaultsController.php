<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Support\BlockDefaults;
use App\Modules\User\Support\BlockTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin editor for first-paint defaults on new biolink blocks.
 *
 * Each block type can have its sample/placeholder content (text, media
 * URLs, etc.) and structural style properties overridden here. Overrides
 * are stored as a single JSON blob under the AppSetting key
 * `block_defaults.overrides` and are merged on top of the hardcoded
 * system defaults at block-creation time — only newly-created blocks are
 * affected, never existing ones.
 */
class BlockDefaultsController extends Controller
{
    /**
     * Human-readable labels for BiolinkBlock::TYPES / registry categories.
     */
    private const CATEGORY_LABELS = [
        'basic'         => 'Text & Links',
        'layout'        => 'Layout',
        'media'         => 'Media',
        'social'        => 'Social',
        'music'         => 'Music',
        'video'         => 'Video',
        'embed'         => 'Embeds & Widgets',
        'tools'         => 'Tools',
        'interactive'   => 'Interactive',
        'commerce'      => 'Commerce & Monetisation',
        'communication' => 'Communication',
    ];

    /**
     * Return all canonical block types grouped by category.
     * Derived dynamically from BlockTypeRegistry::canonicalTypeSlugs() so
     * no manual maintenance is needed when new types are added.
     *
     * @return array<string,string[]>  groupLabel => [type, ...]
     */
    private static function groupedTypes(): array
    {
        $canonicals = array_unique(BlockTypeRegistry::canonicalTypeSlugs());
        $allMeta = array_merge(BiolinkBlock::TYPES, BlockTypeRegistry::newTypes());

        $groups = [];
        foreach ($canonicals as $type) {
            $cat = $allMeta[$type]['category'] ?? 'other';
            $label = self::CATEGORY_LABELS[$cat] ?? ucfirst($cat);
            $groups[$label][] = $type;
        }
        return $groups;
    }

    /**
     * Flat list of every valid canonical type slug for validation.
     *
     * @return string[]
     */
    private static function allCanonicalTypes(): array
    {
        return array_unique(BlockTypeRegistry::canonicalTypeSlugs());
    }

    /**
     * The full set of style tokens the editor form can override. Shared by
     * update() (persist) and preview() (transient render) so they cannot
     * drift apart.
     */
    private const STYLE_FIELDS = [
        'font_family', 'font_size', 'font_weight', 'font_style',
        'text_color', 'bg_color', 'bg_image', 'bg_opacity',
        'border_color', 'border_radius', 'border_width', 'border_style',
        'shadow_preset', 'glass_preset',
        'display_mode', 'effect', 'padding',
        'padding_top', 'padding_bottom', 'padding_left', 'padding_right',
        'margin_top', 'margin_bottom', 'margin_left', 'margin_right',
        'grid_span',
    ];

    public function index()
    {
        $overrides = BlockDefaults::getAdminOverrides();
        $groups = self::groupedTypes();
        $customized = count($overrides);
        return view('admin.block-defaults.index', compact('groups', 'overrides', 'customized'));
    }

    public function edit(string $type)
    {
        $type = BlockTypeRegistry::canonical($type);
        abort_unless(in_array($type, self::allCanonicalTypes(), true), 404);

        $adminOverride = BlockDefaults::getAdminOverrideForType($type);
        $systemContent = $this->rawSystemContent($type);
        $systemStyle   = $this->rawSystemStyle($type);
        $effectiveContent = array_replace($systemContent, $adminOverride['content'] ?? []);
        $effectiveStyle   = array_merge($systemStyle, $adminOverride['style'] ?? []);
        $hasOverride = !empty($adminOverride);

        return view('admin.block-defaults.edit', compact(
            'type',
            'adminOverride',
            'systemContent',
            'systemStyle',
            'effectiveContent',
            'effectiveStyle',
            'hasOverride',
        ));
    }

    public function update(Request $request, string $type)
    {
        $type = BlockTypeRegistry::canonical($type);
        abort_unless(in_array($type, self::allCanonicalTypes(), true), 404);

        $data = [];

        // --- Style overrides ---
        $styleOverride = [];
        foreach (self::STYLE_FIELDS as $field) {
            $val = $request->input("style.{$field}");
            if ($val !== null && $val !== '') {
                $styleOverride[$field] = $val;
            }
        }
        if (!empty($styleOverride)) {
            $data['style'] = $styleOverride;
        } else {
            $data['style'] = null;
        }

        // --- Content overrides ---
        $rawJson = trim((string) $request->input('content_json', ''));
        if ($rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // Strip internal meta keys the admin must not override
                unset($decoded['_style'], $decoded['_placeholder']);
                $data['content'] = $decoded ?: null;
            } else {
                return back()->withErrors(['content_json' => 'Invalid JSON — please check your syntax.'])->withInput();
            }
        } else {
            $data['content'] = null;
        }

        BlockDefaults::saveAdminOverrideForType($type, $data);

        return redirect()->route('admin.block-defaults.edit', $type)
            ->with('success', 'Block defaults saved for "' . $type . '".');
    }

    /**
     * Server-rendered live preview for the edit screen.
     *
     * Builds a transient (never persisted) BiolinkBlock from the current
     * form state — system defaults + the submitted style/content overrides,
     * exactly the merge BiolinkBlockController::store() would apply — and
     * renders it through the same shared public renderer + wrapper
     * semantics as common/biolink.blade.php. Returned as a standalone HTML
     * document the editor injects into a sandboxed iframe.
     */
    public function preview(Request $request, string $type)
    {
        $type = BlockTypeRegistry::canonical($type);
        abort_unless(in_array($type, self::allCanonicalTypes(), true), 404);

        // Effective style = system defaults overlaid with non-empty form values.
        $style = $this->rawSystemStyle($type);
        foreach (self::STYLE_FIELDS as $field) {
            $val = $request->input("style.{$field}");
            if ($val !== null && $val !== '') {
                $style[$field] = $val;
            }
        }

        // Effective content = system defaults overlaid with the JSON override.
        $content = $this->rawSystemContent($type);
        $rawJson = trim((string) $request->input('content_json', ''));
        if ($rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return response()->json(['error' => ['message' => 'Invalid JSON in content overrides.']], 422);
            }
            unset($decoded['_style'], $decoded['_placeholder']);
            $content = array_replace($content, $decoded);
        }

        // Transient models — never saved, just enough for the renderer.
        $block = new BiolinkBlock();
        $block->type = $type;
        $block->id = 0;
        $block->settings = $content + ['_style' => $style];

        $link = new Link();
        $link->alias = 'preview';
        $link->type = 'biolink';
        $link->settings = ['biolink' => []];

        try {
            $html = view('admin.block-defaults.preview-frame', [
                'type'  => $type,
                'block' => $block,
                'link'  => $link,
            ])->render();
        } catch (\Throwable $e) {
            // Some block partials need live records (feeds, embeds with real
            // IDs, …). Fail soft with a simple placeholder frame instead of
            // a broken iframe.
            Log::info('Block-defaults preview render failed', ['type' => $type, 'error' => $e->getMessage()]);
            $safeType = e($type);
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>'
                . '<body style="margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
                . 'background:linear-gradient(135deg,#0b0f1a 0%,#101830 50%,#0b0f1a 100%);'
                . 'font-family:sans-serif;color:rgba(255,255,255,0.65);font-size:13px;text-align:center;padding:24px;">'
                . '<div>The &laquo; ' . $safeType . ' &raquo; block needs live page data and cannot be previewed here.<br>'
                . 'Style overrides will still apply to new blocks.</div></body></html>';
        }

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function reset(string $type)
    {
        $type = BlockTypeRegistry::canonical($type);
        abort_unless(in_array($type, self::allCanonicalTypes(), true), 404);
        BlockDefaults::resetAdminOverrideForType($type);

        return redirect()->route('admin.block-defaults.edit', $type)
            ->with('success', 'Block defaults for "' . $type . '" reset to system defaults.');
    }

    // ---------------------------------------------------------------
    // Helpers

    /**
     * Return hardcoded system content for a type, bypassing any admin
     * override. Uses the suppress-flag mechanism in BlockDefaults so no
     * DB writes are required.
     */
    private function rawSystemContent(string $type): array
    {
        $result = BlockDefaults::withoutAdminOverrides(
            fn() => BlockDefaults::contentForType($type)
        );
        unset($result['_placeholder'], $result['_style']);
        return $result;
    }

    /**
     * Return hardcoded system style for a type, bypassing any admin override.
     */
    private function rawSystemStyle(string $type): array
    {
        return BlockDefaults::withoutAdminOverrides(
            fn() => BlockDefaults::styleForType($type)
        );
    }
}
