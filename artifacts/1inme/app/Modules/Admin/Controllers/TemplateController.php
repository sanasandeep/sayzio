<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\CardTemplate;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
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
     * Resolve the snapshot for store/update.
     * Priority: pasted JSON (if valid & non-empty) > source-link/card capture > existing.
     * Returns null only if nothing is available (caller should error).
     */
    private function resolveSnapshot(string $kind, array $input, ?array $existing): ?array
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
