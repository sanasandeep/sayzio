<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Support\BlockDefaults;
use App\Modules\User\Support\BlockTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        'border_radius_tl', 'border_radius_tr', 'border_radius_bl', 'border_radius_br',
        'border_top_style', 'border_top_width', 'border_top_color',
        'border_right_style', 'border_right_width', 'border_right_color',
        'border_bottom_style', 'border_bottom_width', 'border_bottom_color',
        'border_left_style', 'border_left_width', 'border_left_color',
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
        $startBlank    = (bool) ($adminOverride['start_blank'] ?? false);
        $contentBase   = $startBlank ? BlockDefaults::blankedContent($systemContent) : $systemContent;
        $effectiveContent = array_replace($contentBase, $adminOverride['content'] ?? []);
        $effectiveStyle   = array_merge($systemStyle, $adminOverride['style'] ?? []);
        $hasOverride = !empty($adminOverride);

        // Simple scalar content keys (strings / numbers / booleans) get
        // friendly form fields; nested structures stay JSON-only.
        $scalarContentKeys = [];
        foreach ($systemContent as $key => $value) {
            if (!str_starts_with((string) $key, '_') && is_scalar($value)) {
                $scalarContentKeys[] = $key;
            }
        }

        // Repeatable list keys: arrays of strings or arrays of flat
        // scalar-valued objects get an add/remove/reorder row editor.
        // Deeper nesting (arrays inside items) stays JSON-only.
        $arrayContentKeys = $this->arrayContentKeys($systemContent);

        return view('admin.block-defaults.edit', compact(
            'type',
            'adminOverride',
            'systemContent',
            'systemStyle',
            'effectiveContent',
            'effectiveStyle',
            'hasOverride',
            'startBlank',
            'scalarContentKeys',
            'arrayContentKeys',
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
        // Explicit empty strings / empty arrays inside the JSON are honoured
        // as real "blank" overrides; only a fully-empty textarea (or an empty
        // object) means "use system defaults".
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

        // --- Start blank ---
        $data['start_blank'] = $request->boolean('start_blank');

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

        // Effective content = system defaults (blanked when "start blank" is
        // on) overlaid with the JSON override. Explicit empty values in the
        // JSON are honoured so the preview shows genuinely blank fields.
        $content = $this->rawSystemContent($type);
        if ($request->boolean('start_blank')) {
            $content = BlockDefaults::blankedContent($content);
        }
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

    /**
     * Bulk-copy one type's admin override onto several other types at once.
     *
     * The source type's stored override (content + style) fully replaces
     * any existing override on each selected target type, so targets end
     * up exactly matching the source.
     */
    public function copyTo(Request $request, string $type)
    {
        $type = BlockTypeRegistry::canonical($type);
        abort_unless(in_array($type, self::allCanonicalTypes(), true), 404);

        $source = BlockDefaults::getAdminOverrideForType($type);
        if (empty($source)) {
            return redirect()->route('admin.block-defaults.index')
                ->withErrors(['copy' => '"' . $type . '" has no overrides to copy.']);
        }

        $validated = $request->validate([
            'targets'   => ['required', 'array', 'min:1'],
            'targets.*' => ['string'],
        ]);

        $valid = self::allCanonicalTypes();
        $targets = [];
        foreach ($validated['targets'] as $raw) {
            $canonical = BlockTypeRegistry::canonical((string) $raw);
            if ($canonical !== $type && in_array($canonical, $valid, true)) {
                $targets[$canonical] = true;
            }
        }
        $targets = array_keys($targets);

        if (empty($targets)) {
            return redirect()->route('admin.block-defaults.index')
                ->withErrors(['copy' => 'No valid target types were selected.']);
        }

        foreach ($targets as $target) {
            // Empty arrays clear any part the source doesn't override
            // (saveAdminOverrideForType treats [] as "unset this part"), so
            // the target ends up an exact copy of the source override.
            BlockDefaults::saveAdminOverrideForType($target, [
                'content'     => $source['content'] ?? [],
                'style'       => $source['style'] ?? [],
                'start_blank' => (bool) ($source['start_blank'] ?? false),
            ]);
        }

        return redirect()->route('admin.block-defaults.index')
            ->with('success', 'Copied "' . $type . '" overrides to ' . count($targets) . ' ' . Str::plural('type', count($targets)) . '.');
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
     * Derive repeatable-list content keys from a system-content payload.
     *
     * Returns key => shape metadata for keys whose system value is a
     * non-empty list of strings ('strings') or a list of flat objects
     * whose values are all scalars/null ('objects', with a fields map of
     * field => string|number|boolean derived across all items). Keys
     * with empty arrays, mixed shapes, or nested structures are skipped
     * and remain JSON-only.
     *
     * @param array<string,mixed> $systemContent
     * @return array<string,array{kind:string,fields?:array<string,string>}>
     */
    private function arrayContentKeys(array $systemContent): array
    {
        $out = [];
        foreach ($systemContent as $key => $value) {
            if (str_starts_with((string) $key, '_') || !is_array($value) || $value === [] || !array_is_list($value)) {
                continue;
            }
            $allStrings = true;
            $allObjects = true;
            foreach ($value as $item) {
                if (!is_string($item)) {
                    $allStrings = false;
                }
                if (!is_array($item) || $item === [] || array_is_list($item)) {
                    $allObjects = false;
                    continue;
                }
                foreach ($item as $v) {
                    if (!is_scalar($v) && $v !== null) {
                        $allObjects = false;
                        break;
                    }
                }
            }
            if ($allStrings) {
                $out[$key] = ['kind' => 'strings'];
            } elseif ($allObjects) {
                $fields = [];
                foreach ($value as $item) {
                    foreach ($item as $field => $v) {
                        if (!isset($fields[$field])) {
                            $fields[$field] = is_bool($v) ? 'boolean' : (is_int($v) || is_float($v) ? 'number' : 'string');
                        }
                    }
                }
                $out[$key] = ['kind' => 'objects', 'fields' => $fields];
            }
        }
        return $out;
    }

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
