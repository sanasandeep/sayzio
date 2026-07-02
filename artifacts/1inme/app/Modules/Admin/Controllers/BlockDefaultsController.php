<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\BlockDefaults;
use App\Modules\User\Support\BlockTypeRegistry;
use Illuminate\Http\Request;

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
        $styleFields = [
            'font_family', 'font_size', 'font_weight', 'font_style',
            'text_color', 'bg_color', 'bg_image', 'bg_opacity',
            'border_color', 'border_radius', 'border_width', 'border_style',
            'shadow_preset', 'glass_preset',
            'display_mode', 'effect', 'padding',
            'padding_top', 'padding_bottom', 'padding_left', 'padding_right',
            'margin_top', 'margin_bottom', 'margin_left', 'margin_right',
            'grid_span',
        ];
        $styleOverride = [];
        foreach ($styleFields as $field) {
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
