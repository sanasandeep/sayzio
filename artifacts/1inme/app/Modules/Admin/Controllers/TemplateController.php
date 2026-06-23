<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\CardTemplate;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Support\TemplateSnapshotValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    public function __construct(private TemplateService $templates) {}

    public function index(Request $request)
    {
        $tab = $request->get('tab', 'page') === 'card' ? 'card' : 'page';
        $pageTemplates = PageTemplate::orderBy('sort_order')->orderBy('name')->get();
        $cardTemplates = CardTemplate::orderBy('sort_order')->orderBy('name')->get();
        return view('admin.templates.index', compact('tab', 'pageTemplates', 'cardTemplates'));
    }

    public function create(Request $request)
    {
        $kind = $request->get('kind', 'page') === 'card' ? 'card' : 'page';
        $categories = $kind === 'card' ? CardTemplate::categories() : PageTemplate::categories();
        $plans = Plan::orderBy('sort_order')->get();
        return view('admin.templates.create', compact('kind', 'categories', 'plans'));
    }

    public function store(Request $request)
    {
        $kind = $request->input('kind') === 'card' ? 'card' : 'page';
        $modelClass = $kind === 'card' ? CardTemplate::class : PageTemplate::class;

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => 'nullable|string|max:140',
            'category' => 'required|string|max:50',
            'description' => 'nullable|string|max:2000',
            'thumbnail_url' => 'nullable|url|max:500',
            'plan_tier' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'source_link_id' => 'nullable|integer|exists:links,id',
            'source_card_id' => 'nullable|integer|exists:biolink_blocks,id',
            'snapshot_json' => 'nullable|string',
            'recommended_personas' => 'nullable|array',
            'recommended_personas.*' => ['string', \Illuminate\Validation\Rule::in(\App\Modules\User\Services\PersonaCatalog::slugs())],
        ]);

        $snapshot = $this->resolveSnapshot($kind, $validated, null);
        if (!$snapshot) {
            return back()->withInput()->withErrors([
                'source_link_id' => 'Pick a source ' . ($kind === 'card' ? 'card block' : 'Link in Bio page') . ' to capture, or paste valid snapshot JSON.',
            ]);
        }

        $slug = $validated['slug'] ?? Str::slug($validated['name']);
        $slug = $this->uniqueSlug($modelClass, $slug);

        $payload = [
            'name' => $validated['name'],
            'slug' => $slug,
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'thumbnail_url' => $validated['thumbnail_url'] ?? null,
            'plan_tier' => $validated['plan_tier'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'snapshot' => $snapshot,
        ];
        if ($kind === 'page') {
            $payload['recommended_personas'] = $validated['recommended_personas'] ?? [];
        }
        $modelClass::create($payload);

        return redirect()->route('admin.templates.index', ['tab' => $kind])
            ->with('success', ucfirst($kind) . ' template created.');
    }

    public function edit(string $kind, int $id)
    {
        $tpl = $this->resolve($kind, $id);
        $categories = $kind === 'card' ? CardTemplate::categories() : PageTemplate::categories();
        $plans = Plan::orderBy('sort_order')->get();
        return view('admin.templates.edit', compact('tpl', 'kind', 'categories', 'plans'));
    }

    public function update(Request $request, string $kind, int $id)
    {
        $tpl = $this->resolve($kind, $id);

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => 'required|string|max:140',
            'category' => 'required|string|max:50',
            'description' => 'nullable|string|max:2000',
            'thumbnail_url' => 'nullable|url|max:500',
            'plan_tier' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'source_link_id' => 'nullable|integer|exists:links,id',
            'source_card_id' => 'nullable|integer|exists:biolink_blocks,id',
            'recapture' => 'nullable|boolean',
            'snapshot_json' => 'nullable|string',
            'recommended_personas' => 'nullable|array',
            'recommended_personas.*' => ['string', \Illuminate\Validation\Rule::in(\App\Modules\User\Services\PersonaCatalog::slugs())],
        ]);

        $snapshot = $this->resolveSnapshot($kind, $validated, $tpl->snapshot);
        if ($snapshot && $snapshot !== $tpl->snapshot) {
            $tpl->snapshot = $snapshot;
        }

        $newSlug = $validated['slug'];
        if ($newSlug !== $tpl->slug) {
            $newSlug = $this->uniqueSlug(get_class($tpl), $newSlug, $tpl->id);
        }

        $fillPayload = [
            'name' => $validated['name'],
            'slug' => $newSlug,
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'thumbnail_url' => $validated['thumbnail_url'] ?? null,
            'plan_tier' => $validated['plan_tier'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
        if ($kind === 'page') {
            // Unchecking every box must clear the tags, not no-op them.
            $fillPayload['recommended_personas'] = $validated['recommended_personas'] ?? [];
        }
        $tpl->fill($fillPayload)->save();

        return redirect()->route('admin.templates.index', ['tab' => $kind])
            ->with('success', ucfirst($kind) . ' template updated.');
    }

    public function destroy(string $kind, int $id)
    {
        $tpl = $this->resolve($kind, $id);
        $tpl->delete();
        return redirect()->route('admin.templates.index', ['tab' => $kind])
            ->with('success', ucfirst($kind) . ' template deleted.');
    }

    public function uploadThumbnail(Request $request, string $kind, int $id)
    {
        $tpl = $this->resolve($kind, $id);

        $request->validate([
            'thumbnail' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $previous = $tpl->thumbnail_url;

        $path = $request->file('thumbnail')->store('template-thumbnails', 'public');
        $tpl->thumbnail_url = Storage::disk('public')->url($path);
        $tpl->save();

        $this->deleteLocalThumbnail($previous);

        return back()->with('success', 'Thumbnail updated.');
    }

    public function removeThumbnail(string $kind, int $id)
    {
        $tpl = $this->resolve($kind, $id);

        $previous = $tpl->thumbnail_url;
        $tpl->thumbnail_url = null;
        $tpl->save();

        $this->deleteLocalThumbnail($previous);

        return back()->with('success', 'Thumbnail removed.');
    }

    /**
     * Delete a previously-stored thumbnail file off the public disk, if the
     * URL points at one we own. External URLs (set via the edit form) are
     * left alone.
     */
    private function deleteLocalThumbnail(?string $url): void
    {
        if (!$url) return;
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $marker = '/storage/template-thumbnails/';
        $pos = strpos($path, $marker);
        if ($pos === false) return;
        $relative = 'template-thumbnails/' . substr($path, $pos + strlen($marker));
        if (Storage::disk('public')->exists($relative)) {
            Storage::disk('public')->delete($relative);
        }
    }

    public function toggle(string $kind, int $id)
    {
        $tpl = $this->resolve($kind, $id);
        $tpl->is_active = !$tpl->is_active;
        $tpl->save();
        return back()->with('success', 'Template ' . ($tpl->is_active ? 'activated' : 'deactivated') . '.');
    }

    /**
     * Bulk activate/deactivate templates of a given kind. Accepts a list
     * of template IDs and an action ("activate"|"deactivate"). Used by
     * the admin index "Activate selected" / "Deactivate selected"
     * controls so admins can curate large libraries (100+ card
     * templates) without clicking each row.
     */
    public function bulkToggle(Request $request, string $kind)
    {
        $kind = $kind === 'card' ? 'card' : 'page';
        $modelClass = $kind === 'card' ? CardTemplate::class : PageTemplate::class;

        $data = $request->validate([
            'action' => 'required|in:activate,deactivate',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        $isActive = $data['action'] === 'activate';
        $count = $modelClass::whereIn('id', $data['ids'])->update(['is_active' => $isActive]);

        $verb = $isActive ? 'activated' : 'deactivated';
        return redirect()->route('admin.templates.index', ['tab' => $kind])
            ->with('success', "{$count} template" . ($count === 1 ? '' : 's') . " {$verb}.");
    }

    /**
     * Show the diff between an outdated persona-seeded page template and
     * the current blueprint design. Admins land here from the "Outdated
     * design" badge on the templates index and can either keep their
     * customized row or one-click reset to the current blueprint.
     */
    public function blueprintDiff(int $id)
    {
        $tpl = PageTemplate::findOrFail($id);
        $blueprint = $tpl->currentBlueprint();
        if (!$blueprint) {
            return redirect()->route('admin.templates.index', ['tab' => 'page'])
                ->with('error', 'This template is not managed by the persona seeder, so there is no blueprint to diff against.');
        }

        $current = [
            'name'        => (string) $tpl->name,
            'description' => (string) $tpl->description,
            'snapshot'    => (array) ($tpl->snapshot ?? []),
        ];
        $latest = [
            'name'        => (string) $blueprint['name'],
            'description' => (string) $blueprint['description'],
            'snapshot'    => (array) $blueprint['snapshot'],
        ];

        return view('admin.templates.blueprint_diff', [
            'tpl'             => $tpl,
            'current'         => $current,
            'latest'          => $latest,
            'storedVersion'   => $tpl->seedVersion(),
            'currentVersion'  => \Database\Seeders\ExpandedPageTemplateLibrarySeeder::SEED_VERSION,
        ]);
    }

    /**
     * Replace this template's stored snapshot, name, and description
     * with the current blueprint values, stamping the latest
     * SEED_VERSION so the row is no longer flagged as outdated.
     */
    public function resetBlueprint(int $id)
    {
        $tpl = PageTemplate::findOrFail($id);
        $blueprint = $tpl->currentBlueprint();
        if (!$blueprint) {
            return redirect()->route('admin.templates.index', ['tab' => 'page'])
                ->with('error', 'No current blueprint found for this template; nothing to reset to.');
        }

        $tpl->name        = $blueprint['name'];
        $tpl->description = $blueprint['description'];
        $tpl->snapshot    = $blueprint['snapshot'];
        $tpl->save();

        return redirect()->route('admin.templates.index', ['tab' => 'page'])
            ->with('success', 'Reset "' . $tpl->slug . '" to the current blueprint design.');
    }

    /**
     * Show the guided design-fix view for a template flagged with design
     * issues (unknown block types / stale design-variant keys). Lists each
     * concrete issue and offers two repairs: re-capture from a source Link
     * in Bio page / card block, or strip the offending stale variant keys.
     * Admins land here from the "Design issues" badge on the index.
     */
    public function designFix(string $kind, int $id)
    {
        $tpl = $this->resolve($kind, $id);
        $kind = $kind === 'card' ? 'card' : 'page';

        $snapshot = (array) ($tpl->snapshot ?? []);
        $issues = TemplateSnapshotValidator::issues($snapshot, $kind);

        // What would be left if the admin chose the "strip variants" repair?
        // If empty, stripping alone fully resolves the row; otherwise (e.g.
        // unknown block types) a re-capture is required.
        $afterStrip = TemplateSnapshotValidator::issues(
            TemplateSnapshotValidator::stripStaleVariants($snapshot, $kind),
            $kind
        );

        return view('admin.templates.design_fix', [
            'tpl'        => $tpl,
            'kind'       => $kind,
            'issues'     => $issues,
            'afterStrip' => $afterStrip,
        ]);
    }

    /**
     * Apply a one-click design repair. Two modes:
     *   - "strip": remove every stale design-variant key from the stored
     *     snapshot (surgical; preserves all other content/styling).
     *   - "recapture": replace the snapshot with a fresh capture from a
     *     chosen source Link in Bio page / card block.
     * Either way the result is re-validated before saving.
     */
    public function repairDesign(Request $request, string $kind, int $id)
    {
        $tpl = $this->resolve($kind, $id);
        $kind = $kind === 'card' ? 'card' : 'page';

        $data = $request->validate([
            'mode'           => 'required|in:strip,recapture',
            'source_link_id' => 'nullable|integer|exists:links,id',
            'source_card_id' => 'nullable|integer|exists:biolink_blocks,id',
        ]);

        if ($data['mode'] === 'strip') {
            $snapshot = TemplateSnapshotValidator::stripStaleVariants((array) ($tpl->snapshot ?? []), $kind);
            $tpl->snapshot = $snapshot;
            $tpl->save();

            $remaining = TemplateSnapshotValidator::issues($snapshot, $kind);
            if (!empty($remaining)) {
                return redirect()->route('admin.templates.design.fix', ['kind' => $kind, 'id' => $tpl->id])
                    ->with('error', 'Stripped stale design-variant keys, but other issues remain (e.g. unknown block types). Re-capture from a source page to fully resolve them.');
            }

            return redirect()->route('admin.templates.index', ['tab' => $kind])
                ->with('success', 'Stripped stale design-variant keys from "' . $tpl->slug . '".');
        }

        // mode === 'recapture'
        $captured = $this->captureSnapshot($kind, $data);
        if (!$captured) {
            return back()->withErrors([
                'source_link_id' => 'Pick a source ' . ($kind === 'card' ? 'card block' : 'Link in Bio page') . ' to re-capture from.',
            ]);
        }

        $issues = TemplateSnapshotValidator::issues($captured, $kind);
        if (!empty($issues)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'source_link_id' => array_merge(
                    ['The chosen source still has design problems that would silently degrade on the public page:'],
                    $issues
                ),
            ]);
        }

        $tpl->snapshot = $captured;
        $tpl->save();

        return redirect()->route('admin.templates.index', ['tab' => $kind])
            ->with('success', 'Re-captured the design for "' . $tpl->slug . '" from the chosen source.');
    }

    public function searchLinks(Request $request)
    {
        $kind = $request->get('kind') === 'card' ? 'card' : 'page';
        $q = trim((string) $request->get('q', ''));
        $query = Link::where('type', 'biolink')->with('user:id,name,email');
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'ilike', "%{$q}%")
                  ->orWhere('alias', 'ilike', "%{$q}%")
                  ->orWhereHas('user', function ($u) use ($q) {
                      $u->where('email', 'ilike', "%{$q}%")->orWhere('name', 'ilike', "%{$q}%");
                  });
            });
        }
        $links = $query->latest('id')->limit(15)->get(['id', 'title', 'alias', 'user_id']);

        $out = $links->map(function ($l) use ($kind) {
            $row = [
                'id' => $l->id,
                'label' => ($l->title ?: $l->alias) . ' — ' . ($l->user->email ?? ''),
                'alias' => $l->alias,
            ];
            if ($kind === 'card') {
                $row['cards'] = $l->biolinkBlocks()->where('type', 'card')->get(['id', 'settings'])
                    ->map(function ($c) {
                        $title = $c->settings['title'] ?? null;
                        return ['id' => $c->id, 'label' => $title ? "Card #{$c->id} — {$title}" : "Card #{$c->id}"];
                    })->values();
            }
            return $row;
        });

        return response()->json(['items' => $out]);
    }

    /**
     * Live inline validation of a pasted snapshot JSON, used by the
     * create/edit forms so admins see design problems (unknown block
     * type, unresolvable `_style._variant`) as they edit — before they
     * submit and get bounced back. Returns the same per-issue messages
     * the server raises on save (structural JSON checks mirrored from
     * {@see buildSnapshot()} plus {@see TemplateSnapshotValidator::issues()}).
     */
    public function validateSnapshot(Request $request)
    {
        $kind = $request->input('kind') === 'card' ? 'card' : 'page';
        $json = trim((string) $request->input('snapshot_json', ''));

        if ($json === '') {
            return response()->json(['ok' => true, 'issues' => []]);
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return response()->json([
                'ok' => false,
                'issues' => ['Snapshot JSON is not valid JSON.'],
            ]);
        }

        if ($kind === 'card' && ($decoded['type'] ?? null) !== 'card') {
            return response()->json([
                'ok' => false,
                'issues' => ['Card snapshot must have "type": "card" at the root.'],
            ]);
        }

        if ($kind === 'page' && !isset($decoded['blocks']) && !isset($decoded['biolink'])) {
            return response()->json([
                'ok' => false,
                'issues' => ['Page snapshot must include a "blocks" array.'],
            ]);
        }

        $issues = TemplateSnapshotValidator::issues($decoded, $kind);

        return response()->json([
            'ok' => empty($issues),
            'issues' => array_values($issues),
        ]);
    }

    /**
     * Resolve the snapshot for store/update.
     * Priority: pasted JSON (if valid & non-empty) > source-link/card capture > existing.
     * Returns null only if nothing is available (caller should error).
     */
    private function resolveSnapshot(string $kind, array $input, ?array $existing): ?array
    {
        $snapshot = $this->buildSnapshot($kind, $input, $existing);

        // Only validate a freshly-supplied/changed snapshot — re-using the
        // existing stored snapshot unchanged (e.g. an admin editing only the
        // name) must not be blocked by a pre-existing design issue.
        if ($snapshot !== null && $snapshot !== $existing) {
            $this->validateSnapshotDesign($kind, $snapshot);
        }

        return $snapshot;
    }

    /**
     * Build the snapshot for store/update.
     * Priority: pasted JSON (if valid & non-empty) > source-link/card capture > existing.
     * Returns null only if nothing is available (caller should error).
     */
    private function buildSnapshot(string $kind, array $input, ?array $existing): ?array
    {
        $json = trim((string) ($input['snapshot_json'] ?? ''));
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (!is_array($decoded)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'snapshot_json' => 'Snapshot JSON is not valid JSON.',
                ]);
            }
            if ($kind === 'card' && ($decoded['type'] ?? null) !== 'card') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'snapshot_json' => 'Card snapshot must have "type": "card" at the root.',
                ]);
            }
            if ($kind === 'page' && !isset($decoded['blocks']) && !isset($decoded['biolink'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'snapshot_json' => 'Page snapshot must include a "blocks" array.',
                ]);
            }
            return $decoded;
        }

        $captured = $this->captureSnapshot($kind, $input);
        if ($captured) return $captured;

        return $existing;
    }

    private function captureSnapshot(string $kind, array $input): ?array
    {
        if ($kind === 'card') {
            $cardId = $input['source_card_id'] ?? null;
            if (!$cardId) return null;
            $card = BiolinkBlock::where('id', $cardId)->where('type', 'card')->first();
            if (!$card) return null;
            return $this->templates->captureFromCardBlock($card);
        }

        $linkId = $input['source_link_id'] ?? null;
        if (!$linkId) return null;
        $link = Link::where('id', $linkId)->where('type', 'biolink')->first();
        if (!$link) return null;
        return $this->templates->captureFromLink($link);
    }

    /**
     * Reject a snapshot whose blocks use an unknown type or a baked
     * design-variant key that no longer resolves — the same checks the
     * seeder test enforces at CI time, applied to admin-authored
     * snapshots so a hand-edited stale key/typo can't silently degrade
     * the public page.
     */
    private function validateSnapshotDesign(string $kind, array $snapshot): void
    {
        $issues = TemplateSnapshotValidator::issues($snapshot, $kind);
        if (!empty($issues)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'snapshot_json' => array_merge(
                    ['This template has design problems that would silently degrade on the public page:'],
                    $issues
                ),
            ]);
        }
    }

    /**
     * Render a full public-style biolink page from a template's stored
     * snapshot so an admin can confirm how it looks *before* activating
     * (publishing) it. Works for both kinds and regardless of is_active:
     *   - page: the snapshot is already a {biolink, blocks:[...]} page.
     *   - card: the card snapshot is wrapped as the single top-level block
     *     of an otherwise-empty page so its children render in context.
     *
     * Built entirely in-memory via TemplateService::buildPreviewLink (the
     * same sanitizer applyPage/applyCard use) — no DB writes. A snapshot
     * with an unknown/degraded block type would otherwise 500; we catch it
     * and surface a readable message pointing the admin at the design-fix
     * flow instead of a stack trace.
     */
    public function preview(Request $request, string $kind, int $id)
    {
        $kind = $kind === 'card' ? 'card' : 'page';
        $tpl = $this->resolve($kind, $id);

        $snapshot = (array) ($tpl->snapshot ?? []);
        $pageSnapshot = $kind === 'card'
            ? ['biolink' => [], 'blocks' => [$snapshot]]
            : $snapshot;

        try {
            $link = $this->templates->buildPreviewLink($pageSnapshot, $request->user(), (string) $tpl->name);
            $html = view('common.biolink', compact('link'))->render();
        } catch (\Throwable $e) {
            $html = view('admin.templates.preview_error', [
                'tpl'  => $tpl,
                'kind' => $kind,
                'message' => $e->getMessage(),
            ])->render();
        }

        return response($html)
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Content-Security-Policy', "frame-ancestors 'self'")
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    private function resolve(string $kind, int $id)
    {
        if ($kind === 'card') return CardTemplate::findOrFail($id);
        if ($kind === 'page') return PageTemplate::findOrFail($id);
        abort(404);
    }

    private function uniqueSlug(string $modelClass, string $base, ?int $ignoreId = null): string
    {
        $base = Str::slug($base) ?: 'template';
        $slug = $base;
        $i = 1;
        while ($modelClass::where('slug', $slug)->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
