<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\BgTemplate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin CRUD for biolink background templates.
 *
 * Templates seeded via BgTemplateSeeder / BgPatternTemplatesSeeder are
 * fully editable here — the seeders are idempotent (updateOrCreate by
 * slug) so re-running them only restores any missing originals; admin
 * edits are never overwritten unless an admin re-runs a seeder
 * explicitly with the same slug.
 *
 * Each template renders as `.bg-template-<slug>` on the biolink page.
 * Admins editing the CSS/JS should keep the selector convention
 * (`position:fixed;inset:0;z-index:-1` on the wrapper) so the layer
 * sits behind biolink content.
 */
class BgTemplateController extends Controller
{
    private const CATEGORIES = ['animated', 'gradient', 'mesh', 'pattern', 'svg', 'neon'];

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $cat = (string) $request->get('cat', '');

        $query = BgTemplate::query()->orderBy('sort_order')->orderBy('name');
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'ILIKE', "%{$q}%")->orWhere('slug', 'ILIKE', "%{$q}%");
            });
        }
        if ($cat !== '' && in_array($cat, self::CATEGORIES, true)) {
            $query->where('category', $cat);
        }
        $templates = $query->paginate(40)->withQueryString();

        $categoryCounts = BgTemplate::query()
            ->selectRaw('category, count(*) as c')
            ->groupBy('category')
            ->pluck('c', 'category');

        return view('admin.bg-templates.index', [
            'templates'      => $templates,
            'categories'     => self::CATEGORIES,
            'categoryCounts' => $categoryCounts,
            'currentCat'     => $cat,
            'q'              => $q,
            'totalCount'     => BgTemplate::count(),
        ]);
    }

    public function create()
    {
        $template = new BgTemplate([
            'category'   => 'pattern',
            'is_active'  => true,
            'sort_order' => (int) BgTemplate::max('sort_order') + 1,
        ]);
        return view('admin.bg-templates.create', [
            'template'   => $template,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $template = BgTemplate::create($data);
        return redirect()
            ->route('admin.bg-templates.edit', $template)
            ->with('success', "Template \"{$template->name}\" created.");
    }

    public function edit(BgTemplate $bgTemplate)
    {
        return view('admin.bg-templates.edit', [
            'template'   => $bgTemplate,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function update(Request $request, BgTemplate $bgTemplate)
    {
        $data = $this->validateData($request, $bgTemplate->id);
        $bgTemplate->update($data);
        return redirect()
            ->route('admin.bg-templates.edit', $bgTemplate)
            ->with('success', 'Template updated.');
    }

    public function destroy(BgTemplate $bgTemplate)
    {
        $name = $bgTemplate->name;
        $bgTemplate->delete();
        return redirect()
            ->route('admin.bg-templates.index')
            ->with('success', "Template \"{$name}\" deleted.");
    }

    public function toggleActive(BgTemplate $bgTemplate)
    {
        $bgTemplate->update(['is_active' => ! $bgTemplate->is_active]);
        return back()->with('success', $bgTemplate->name . ' is now ' . ($bgTemplate->is_active ? 'active' : 'hidden') . '.');
    }

    /** @return array<string,mixed> */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100'],
            'slug'          => [
                'required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9\-]*$/',
                Rule::unique('bg_templates', 'slug')->ignore($ignoreId),
            ],
            'category'      => ['required', Rule::in(self::CATEGORIES)],
            'preview_color' => ['nullable', 'string', 'max:1000'],
            'css'           => ['required', 'string', 'max:50000'],
            'js'            => ['nullable', 'string', 'max:20000'],
            'sort_order'    => ['nullable', 'integer'],
        ], [], ['preview_color' => 'preview swatch']);

        $data['css'] = self::sanitizeCss((string) $data['css']);
        if (!empty($data['js'])) {
            $data['js'] = self::sanitizeJs((string) $data['js']);
        }
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    /**
     * Strip any HTML-tag tokens from CSS so an admin (or compromised admin
     * account) cannot break out of a `<style>` block by including
     * `</style><script>…`. We block any `</` and `<script` substrings —
     * neither has a legitimate use inside CSS.
     */
    public static function sanitizeCss(string $css): string
    {
        $css = preg_replace('#</[a-zA-Z]#', '/* */', $css) ?? '';
        return preg_replace('#<\s*script#i', '/* */', $css) ?? '';
    }

    /**
     * Strip closing-tag tokens from JS so admin-supplied snippets can't
     * end an inline `<script>` block early.
     */
    public static function sanitizeJs(string $js): string
    {
        return preg_replace('#</\s*script#i', '<\\/script', $js) ?? '';
    }
}
