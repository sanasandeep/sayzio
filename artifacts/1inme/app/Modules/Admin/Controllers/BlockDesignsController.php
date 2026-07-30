<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\AdminBlockDesigns;
use App\Modules\User\Support\BlockStyleSanitizer;
use App\Modules\User\Support\BlockTypeRegistry;
use App\Modules\User\Support\BlockVariantCatalog;
use Illuminate\Http\Request;

/**
 * Admin manager for the biolink Designs gallery + global Block Theme
 * presets (Task #6045). Built-in variants stay code-defined and can only
 * be hidden; admin-created (adm_*) entries are fully editable and are
 * merged into every user's editor at read time via BlockVariantCatalog /
 * BiolinkBlock::blockTemplates(). Style payloads are validated through
 * the exact same BlockStyleSanitizer allowlist the user editor uses.
 */
class BlockDesignsController extends Controller
{
    /** Block types the "applies to" picker offers (canonical slugs). */
    private static function typeOptions(): array
    {
        return array_values(array_unique(BlockTypeRegistry::canonicalTypeSlugs()));
    }

    public function index(Request $request)
    {
        $previewType = (string) $request->query('type', 'link');
        if (!in_array($previewType, self::typeOptions(), true)) {
            $previewType = 'link';
        }

        // Unfiltered merged catalog for the chosen type so hidden entries
        // still show (with a "hidden" badge) and can be un-hidden.
        $variants = BlockVariantCatalog::forType($previewType, false);
        $hiddenKeys = AdminBlockDesigns::hiddenVariantKeys();
        $customByKey = [];
        foreach (AdminBlockDesigns::customVariants() as $v) {
            $customByKey[$v['key'] ?? ''] = $v;
        }

        $templates = BiolinkBlock::blockTemplates(false);
        $customTemplates = AdminBlockDesigns::customTemplates();
        $hiddenTemplateKeys = AdminBlockDesigns::hiddenTemplateKeys();

        return view('admin.block-designs.index', [
            'previewType'        => $previewType,
            'typeOptions'        => self::typeOptions(),
            'variants'           => $variants,
            'hiddenKeys'         => $hiddenKeys,
            'customByKey'        => $customByKey,
            'customVariants'     => AdminBlockDesigns::customVariants(),
            'templates'          => $templates,
            'customTemplates'    => $customTemplates,
            'hiddenTemplateKeys' => $hiddenTemplateKeys,
            'catalogVersion'     => BlockVariantCatalog::version(),
        ]);
    }

    /* ---------------- Designs-gallery variants ---------------- */

    public function createVariant()
    {
        return view('admin.block-designs.variant-form', [
            'variant'     => null,
            'typeOptions' => self::typeOptions(),
            'tags'        => BlockVariantCatalog::TAGS,
            'shapes'      => BlockVariantCatalog::SHAPES,
        ]);
    }

    public function editVariant(string $key)
    {
        $variant = AdminBlockDesigns::findCustomVariant($key);
        abort_if($variant === null, 404);

        return view('admin.block-designs.variant-form', [
            'variant'     => $variant,
            'typeOptions' => self::typeOptions(),
            'tags'        => BlockVariantCatalog::TAGS,
            'shapes'      => BlockVariantCatalog::SHAPES,
        ]);
    }

    public function saveVariant(Request $request)
    {
        $data = $request->validate([
            'key'        => ['nullable', 'string', 'max:40'],
            'name'       => ['required', 'string', 'max:60'],
            'shape'      => ['nullable', 'string', 'max:30'],
            'tags'       => ['nullable', 'array'],
            'tags.*'     => ['string', 'max:30'],
            'types'      => ['nullable', 'array'],
            'types.*'    => ['string', 'max:50'],
            'enabled'    => ['nullable'],
            'style_json' => ['required', 'string', 'max:20000'],
        ]);

        $key = (string) ($data['key'] ?? '');
        if ($key !== '' && AdminBlockDesigns::findCustomVariant($key) === null) {
            abort(404);
        }

        $decoded = json_decode($data['style_json'], true);
        if (!is_array($decoded)) {
            return back()->withErrors(['style_json' => 'Style must be a valid JSON object.'])->withInput();
        }
        $style = BlockStyleSanitizer::sanitize($decoded);
        unset($style['_variant'], $style['_variant_version'], $style['_template'],
              $style['_style_custom_snapshot'], $style['apply_to_all']);
        if ($style === []) {
            return back()->withErrors(['style_json' => 'No valid style properties survived sanitization — check the property names and values.'])->withInput();
        }

        $saved = AdminBlockDesigns::saveVariant([
            'key'     => $key,
            'name'    => $data['name'],
            'tags'    => $data['tags'] ?? [],
            'shape'   => $data['shape'] ?? '',
            'types'   => $data['types'] ?? [],
            'style'   => $style,
            'enabled' => $request->boolean('enabled'),
        ]);

        return redirect()->route('admin.block-designs.index')
            ->with('success', 'Design variant "' . $saved['name'] . '" saved.');
    }

    /**
     * Duplicate any built-in or custom variant into a fresh editable
     * custom (adm_*) entry so admins never hand-write style JSON from
     * scratch. Lands on the new copy's edit form.
     */
    public function duplicateVariant(string $key)
    {
        $source = AdminBlockDesigns::findCustomVariant($key);
        $types = [];
        if ($source !== null) {
            $types = array_values(array_filter((array) ($source['types'] ?? [])));
        } else {
            // Built-in: resolve via the merged catalog and derive which
            // block types it applies to. Common variants resolve for
            // every type -> empty list (= all types), bundle/one-off
            // variants only for their own types.
            $foundFor = [];
            foreach (self::typeOptions() as $t) {
                $v = BlockVariantCatalog::find($t, $key);
                if ($v !== null) {
                    $foundFor[] = $t;
                    $source ??= $v;
                }
            }
            abort_if($source === null, 404);
            if (count($foundFor) < count(self::typeOptions())) {
                $types = $foundFor;
            }
        }

        $style = BlockStyleSanitizer::sanitize(is_array($source['style'] ?? null) ? $source['style'] : []);
        unset($style['_variant'], $style['_variant_version'], $style['_template'],
              $style['_style_custom_snapshot'], $style['apply_to_all']);

        $saved = AdminBlockDesigns::saveVariant([
            'key'     => '',
            'name'    => mb_substr('Copy of ' . (string) ($source['name'] ?? $key), 0, 60),
            'tags'    => (array) ($source['tags'] ?? []),
            'shape'   => (string) ($source['shape'] ?? ''),
            'types'   => $types,
            'style'   => $style,
            'enabled' => $source['key'] === $key && isset($source['enabled'])
                ? !empty($source['enabled'])
                : true,
        ]);

        return redirect()->route('admin.block-designs.variants.edit', $saved['key'])
            ->with('success', 'Duplicated as "' . $saved['name'] . '" — tweak and save.');
    }

    public function deleteVariant(string $key)
    {
        abort_unless(AdminBlockDesigns::deleteVariant($key), 404);
        return redirect()->route('admin.block-designs.index')
            ->with('success', 'Design variant deleted.');
    }

    public function moveVariant(Request $request, string $key)
    {
        $direction = $request->input('direction') === 'up' ? 'up' : 'down';
        AdminBlockDesigns::moveVariant($key, $direction);
        return redirect()->route('admin.block-designs.index');
    }

    public function toggleVariant(Request $request, string $key)
    {
        AdminBlockDesigns::setVariantHidden($key, $request->boolean('hidden'));
        return redirect()->back()->with('success', 'Visibility updated.');
    }

    /* ---------------- Global Block Theme presets ---------------- */

    public function createTemplate()
    {
        return view('admin.block-designs.template-form', [
            'templateKey' => null,
            'template'    => null,
        ]);
    }

    public function editTemplate(string $key)
    {
        $template = AdminBlockDesigns::customTemplates()[$key] ?? null;
        abort_if($template === null, 404);

        return view('admin.block-designs.template-form', [
            'templateKey' => $key,
            'template'    => $template,
        ]);
    }

    public function saveTemplate(Request $request)
    {
        $data = $request->validate([
            'key'        => ['nullable', 'string', 'max:40'],
            'label'      => ['required', 'string', 'max:40'],
            'icon'       => ['nullable', 'string', 'max:40'],
            'enabled'    => ['nullable'],
            'style_json' => ['required', 'string', 'max:20000'],
        ]);

        $key = (string) ($data['key'] ?? '');
        if ($key !== '' && !isset(AdminBlockDesigns::customTemplates()[$key])) {
            abort(404);
        }

        $decoded = json_decode($data['style_json'], true);
        if (!is_array($decoded)) {
            return back()->withErrors(['style_json' => 'Style must be a valid JSON object.'])->withInput();
        }
        $style = BlockStyleSanitizer::sanitize($decoded);
        unset($style['_variant'], $style['_variant_version'], $style['_template'],
              $style['_style_custom_snapshot'], $style['apply_to_all']);
        if ($style === []) {
            return back()->withErrors(['style_json' => 'No valid style properties survived sanitization — check the property names and values.'])->withInput();
        }

        AdminBlockDesigns::saveTemplate($key !== '' ? $key : null, [
            'label'   => $data['label'],
            'icon'    => $data['icon'] ?? '',
            'style'   => $style,
            'enabled' => $request->boolean('enabled'),
        ]);

        return redirect()->route('admin.block-designs.index')
            ->with('success', 'Theme preset "' . $data['label'] . '" saved.');
    }

    /**
     * Duplicate any built-in or custom Block Theme preset into a fresh
     * editable custom (adm_*) entry. Lands on the copy's edit form.
     */
    public function duplicateTemplate(string $key)
    {
        $source = BiolinkBlock::blockTemplates(false)[$key] ?? null;
        abort_if($source === null, 404);

        $style = BlockStyleSanitizer::sanitize(is_array($source['style'] ?? null) ? $source['style'] : []);
        unset($style['_variant'], $style['_variant_version'], $style['_template'],
              $style['_style_custom_snapshot'], $style['apply_to_all']);

        $custom = AdminBlockDesigns::customTemplates()[$key] ?? null;
        $label = mb_substr('Copy of ' . (string) ($source['label'] ?? $key), 0, 40);

        $newKey = AdminBlockDesigns::saveTemplate(null, [
            'label'   => $label,
            'icon'    => (string) ($source['icon'] ?? ''),
            'style'   => $style,
            'enabled' => $custom !== null ? !empty($custom['enabled']) : true,
        ]);

        return redirect()->route('admin.block-designs.templates.edit', $newKey)
            ->with('success', 'Duplicated as "' . $label . '" — tweak and save.');
    }

    public function deleteTemplate(string $key)
    {
        abort_unless(AdminBlockDesigns::deleteTemplate($key), 404);
        return redirect()->route('admin.block-designs.index')
            ->with('success', 'Theme preset deleted.');
    }

    public function toggleTemplate(Request $request, string $key)
    {
        AdminBlockDesigns::setTemplateHidden($key, $request->boolean('hidden'));
        return redirect()->back()->with('success', 'Visibility updated.');
    }
}
