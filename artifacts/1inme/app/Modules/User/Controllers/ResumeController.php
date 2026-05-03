<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Models\User;
use App\Modules\User\Services\ResumeColorThemeRegistry;
use App\Modules\User\Services\ResumePdfRenderer;
use App\Modules\User\Services\ResumeTemplateRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owner-only authoring API for the Resume / Portfolio module.
 *
 * Every endpoint resolves the resume from `auth()->user()->ensureResume()`,
 * so the route never trusts a resume id from the client — there is one
 * row per user and the controller is the only thing that touches it.
 *
 * Item routes (CRUD + reorder) bind the item via route-model and then
 * abort 403 if the item's resume isn't owned by the signed-in user, so
 * forging another user's item id can't poison data either.
 */
class ResumeController extends Controller
{
    /**
     * GET — render the editor page (Blade). Bootstraps the same JSON
     * payload as `show()` so the editor can render immediately without a
     * second round-trip on first paint.
     */
    public function editor(Request $request): View
    {
        $user   = $request->user();
        $resume = $user->ensureResume();
        $resume->load('items');

        return view('user.resume.editor', [
            'bootstrap' => [
                'resume'     => $this->present($resume),
                'registries' => [
                    'templates'    => ResumeTemplateRegistry::availableFor($user),
                    'color_themes' => ResumeColorThemeRegistry::all(),
                ],
            ],
        ]);
    }

    /**
     * GET — full resume + ordered items + registries.
     */
    public function show(Request $request): JsonResponse
    {
        $user   = $request->user();
        $resume = $user->ensureResume();
        $resume->load('items');

        return response()->json([
            'resume' => $this->present($resume),
            'registries' => [
                'templates'   => ResumeTemplateRegistry::availableFor($user),
                'color_themes' => ResumeColorThemeRegistry::all(),
            ],
        ]);
    }

    /** PUT — update header. */
    public function updateHeader(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['nullable', 'string', 'max:120'],
            'headline' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:160'],
            'email'    => ['nullable', 'string', 'email', 'max:191'],
            'phone'    => ['nullable', 'string', 'max:40'],
            'website'  => ['nullable', 'string', 'url', 'max:255'],
        ]);

        $resume   = $request->user()->ensureResume();
        $sections = $resume->getMergedSections();
        $sections['header'] = array_replace($sections['header'], array_map(
            fn ($v) => is_string($v) ? trim($v) : $v,
            $data
        ));
        $resume->update(['sections' => $sections]);

        return response()->json(['resume' => $this->present($resume->fresh('items'))]);
    }

    /** PUT — update summary. */
    public function updateSummary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'summary' => ['nullable', 'string', 'max:2000'],
        ]);

        $resume   = $request->user()->ensureResume();
        $sections = $resume->getMergedSections();
        $sections['summary'] = (string) ($data['summary'] ?? '');
        $resume->update(['sections' => $sections]);

        return response()->json(['resume' => $this->present($resume->fresh('items'))]);
    }

    /** PUT — switch template. */
    public function updateTemplate(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'template_id' => ['required', 'string', Rule::in(ResumeTemplateRegistry::ids())],
        ]);

        if (!ResumeTemplateRegistry::userCanUse($user, $data['template_id'])) {
            return response()->json([
                'message' => 'This template is not available on your current plan.',
            ], 403);
        }

        $resume = $user->ensureResume();
        $resume->update(['template_id' => $data['template_id']]);

        return response()->json(['resume' => $this->present($resume->fresh('items'))]);
    }

    /** PUT — switch color theme. */
    public function updateColorTheme(Request $request): JsonResponse
    {
        $data = $request->validate([
            'color_theme_id' => ['required', 'string', Rule::in(ResumeColorThemeRegistry::ids())],
        ]);

        $resume = $request->user()->ensureResume();
        $resume->update(['color_theme_id' => $data['color_theme_id']]);

        return response()->json(['resume' => $this->present($resume->fresh('items'))]);
    }

    /**
     * POST — add a custom section. Custom sections only declare a
     * key + title; their items are stored as ResumeSectionItem rows of
     * type "custom" with `data.custom_section_key` matching the key.
     */
    public function addCustomSection(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key'   => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/'],
            'title' => ['required', 'string', 'max:80'],
        ]);

        $resume = $request->user()->ensureResume();
        $sections = $resume->getMergedSections();
        $existing = collect($sections['custom_sections']);
        if ($existing->contains(fn ($s) => ($s['key'] ?? null) === $data['key'])) {
            return response()->json(['message' => 'A custom section with that key already exists.'], 422);
        }

        $sections['custom_sections'] = $existing
            ->push(['key' => $data['key'], 'title' => trim($data['title'])])
            ->values()
            ->all();
        $resume->update(['sections' => $sections]);

        return response()->json(['resume' => $this->present($resume->fresh('items'))]);
    }

    /** PUT — rename a custom section. */
    public function updateCustomSection(Request $request, string $key): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:80'],
        ]);

        $resume   = $request->user()->ensureResume();
        $sections = $resume->getMergedSections();
        $found    = false;
        $sections['custom_sections'] = array_map(function ($s) use ($key, $data, &$found) {
            if (($s['key'] ?? null) === $key) {
                $found    = true;
                $s['title'] = trim($data['title']);
            }
            return $s;
        }, $sections['custom_sections']);

        if (!$found) abort(404);

        $resume->update(['sections' => $sections]);

        return response()->json(['resume' => $this->present($resume->fresh('items'))]);
    }

    /** DELETE — remove a custom section + all its items. */
    public function destroyCustomSection(Request $request, string $key): JsonResponse
    {
        $resume = $request->user()->ensureResume();
        $sections = $resume->getMergedSections();
        $before   = count($sections['custom_sections']);
        $sections['custom_sections'] = array_values(array_filter(
            $sections['custom_sections'],
            fn ($s) => ($s['key'] ?? null) !== $key
        ));
        if (count($sections['custom_sections']) === $before) abort(404);

        DB::transaction(function () use ($resume, $sections, $key) {
            $resume->update(['sections' => $sections]);
            $resume->items()
                ->where('section_type', 'custom')
                ->whereJsonContains('data->custom_section_key', $key)
                ->delete();
        });

        return response()->json(['resume' => $this->present($resume->fresh('items'))]);
    }

    /** POST — append a new section item. Position lands at the end of its type. */
    public function storeItem(Request $request): JsonResponse
    {
        $base = $request->validate([
            'section_type' => ['required', 'string', Rule::in(ResumeSectionItem::TYPES)],
            'data'         => ['required', 'array'],
        ]);

        $resume = $request->user()->ensureResume();
        $payload = $this->validateItemData(
            $base['section_type'],
            $base['data'],
            $resume,
        );

        $maxPos = (int) $resume->itemsOfType($base['section_type'])->max('position');
        $item   = $resume->items()->create([
            'section_type' => $base['section_type'],
            'position'     => $maxPos + 1,
            'data'         => $payload,
        ]);

        return response()->json([
            'item'   => $this->presentItem($item),
            'resume' => $this->present($resume->fresh('items')),
        ], 201);
    }

    /** PUT — update an existing item. */
    public function updateItem(Request $request, ResumeSectionItem $item): JsonResponse
    {
        $this->authorizeItem($request, $item);

        $base = $request->validate([
            'data' => ['required', 'array'],
        ]);
        $payload = $this->validateItemData($item->section_type, $base['data'], $item->resume);
        $item->update(['data' => $payload]);

        return response()->json(['item' => $this->presentItem($item->fresh())]);
    }

    /** DELETE — remove an item. */
    public function destroyItem(Request $request, ResumeSectionItem $item): JsonResponse
    {
        $this->authorizeItem($request, $item);
        $item->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * POST — reorder items inside a single section type. Only items
     * the user owns AND of the given type are touched; foreign ids are
     * silently ignored so a malformed client can't reach across users.
     */
    public function reorderItems(Request $request): JsonResponse
    {
        $data = $request->validate([
            'section_type' => ['required', 'string', Rule::in(ResumeSectionItem::TYPES)],
            'item_ids'     => ['required', 'array', 'min:1'],
            'item_ids.*'   => ['integer'],
        ]);

        $resume = $request->user()->ensureResume();

        DB::transaction(function () use ($resume, $data) {
            // Pull every item id in this section so we can both validate
            // the caller's payload AND append any items they omitted to
            // the end — guaranteeing positions stay dense and unique
            // even when the client only sends a partial list.
            $allIds = $resume->itemsOfType($data['section_type'])
                ->orderBy('position')->orderBy('id')
                ->pluck('id')->all();
            $validSet = array_flip($allIds);

            $ordered = [];
            $seen = [];
            foreach ($data['item_ids'] as $id) {
                if (!isset($validSet[$id]) || isset($seen[$id])) continue;
                $ordered[] = $id;
                $seen[$id] = true;
            }
            // Append any items the client didn't mention, preserving
            // their existing relative order — protects against renderer
            // ambiguity from duplicate or missing positions.
            foreach ($allIds as $id) {
                if (!isset($seen[$id])) $ordered[] = $id;
            }

            $position = 1;
            foreach ($ordered as $id) {
                ResumeSectionItem::whereKey($id)->update(['position' => $position++]);
            }
        });

        return response()->json(['resume' => $this->present($resume->fresh('items'))]);
    }

    /**
     * GET — stream a polished PDF of the resume to the signed-in owner.
     *
     * `?size=a4|letter` toggles paper size (defaults to A4). The output
     * is rendered server-side from the same template + theme metadata
     * the live editor preview uses, so the PDF is visually identical.
     * Generation is throttled at the route level and cached for a short
     * window per (resume content, size).
     */
    public function download(Request $request, ResumePdfRenderer $renderer): Response
    {
        $user   = $request->user();
        $resume = $user->ensureResume();
        $resume->load('items');

        $size = $renderer->normalizeSize($request->query('size'));
        $out  = $renderer->render($resume, $user, $size);

        return response($out['body'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $out['filename'] . '"',
            'Content-Length'      => (string) strlen($out['body']),
            'Cache-Control'       => 'private, max-age=0, no-store',
            'X-Resume-Paper-Size' => $size,
        ]);
    }

    /**
     * GET — stable owner-only URL `/{handle}/resume.pdf`. Mirrors the
     * download endpoint, but resolves the resume by handle so future
     * features (sharing, link cards, embeds) can hand out a memorable
     * URL without needing a session-scoped path. Strictly owner-only
     * for now; visitors get a 404 to avoid revealing handle existence.
     */
    public function downloadByHandle(Request $request, string $handle, ResumePdfRenderer $renderer): Response
    {
        $signedIn = $request->user();
        if (!$signedIn) abort(404);

        $owner = User::where('handle', $handle)->first();
        if (!$owner || $owner->id !== $signedIn->id) abort(404);

        $resume = $owner->ensureResume();
        $resume->load('items');

        $size = $renderer->normalizeSize($request->query('size'));
        $out  = $renderer->render($resume, $owner, $size);

        return response($out['body'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $out['filename'] . '"',
            'Content-Length'      => (string) strlen($out['body']),
            'Cache-Control'       => 'private, max-age=0, no-store',
            'X-Resume-Paper-Size' => $size,
        ]);
    }

    // ── Internals ──────────────────────────────────────────────────

    private function authorizeItem(Request $request, ResumeSectionItem $item): void
    {
        $resume = $item->resume()->first();
        abort_if(!$resume || $resume->user_id !== $request->user()->id, 403);
    }

    /**
     * Per-section-type input validation. Keeps junk dates / URLs / etc.
     * out of the JSON blob so renderers can trust what they read.
     *
     * Returns the cleaned payload (only the keys we know about, trimmed).
     *
     * @return array<string,mixed>
     */
    private function validateItemData(string $type, array $data, Resume $resume): array
    {
        $rules = match ($type) {
            'experience' => [
                'company'     => ['required', 'string', 'max:160'],
                'role'        => ['required', 'string', 'max:160'],
                'location'    => ['nullable', 'string', 'max:160'],
                'start_date'  => ['nullable', 'date_format:Y-m'],
                'end_date'    => ['nullable', 'date_format:Y-m', 'after_or_equal:start_date'],
                'is_current'  => ['nullable', 'boolean'],
                'description' => ['nullable', 'string', 'max:2000'],
            ],
            'education' => [
                'school'      => ['required', 'string', 'max:160'],
                'degree'      => ['nullable', 'string', 'max:160'],
                'field'       => ['nullable', 'string', 'max:160'],
                'start_date'  => ['nullable', 'date_format:Y-m'],
                'end_date'    => ['nullable', 'date_format:Y-m', 'after_or_equal:start_date'],
                'description' => ['nullable', 'string', 'max:1000'],
            ],
            'skills' => [
                'name'  => ['required', 'string', 'max:80'],
                'level' => ['nullable', 'integer', 'between:1,5'],
                'group' => ['nullable', 'string', 'max:80'],
            ],
            'projects' => [
                'name'        => ['required', 'string', 'max:160'],
                'role'        => ['nullable', 'string', 'max:160'],
                'url'         => ['nullable', 'string', 'url', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'],
                'start_date'  => ['nullable', 'date_format:Y-m'],
                'end_date'    => ['nullable', 'date_format:Y-m', 'after_or_equal:start_date'],
            ],
            'certifications' => [
                'name'         => ['required', 'string', 'max:160'],
                'issuer'       => ['nullable', 'string', 'max:160'],
                'issued_on'    => ['nullable', 'date_format:Y-m'],
                'expires_on'   => ['nullable', 'date_format:Y-m', 'after_or_equal:issued_on'],
                'credential_url' => ['nullable', 'string', 'url', 'max:255'],
            ],
            'awards' => [
                'title'       => ['required', 'string', 'max:160'],
                'issuer'      => ['nullable', 'string', 'max:160'],
                'date'        => ['nullable', 'date_format:Y-m'],
                'description' => ['nullable', 'string', 'max:1000'],
            ],
            'languages' => [
                'name'        => ['required', 'string', 'max:80'],
                'proficiency' => ['nullable', 'string', Rule::in(['basic', 'conversational', 'professional', 'fluent', 'native'])],
            ],
            'links' => [
                'label' => ['required', 'string', 'max:80'],
                'url'   => ['required', 'string', 'url', 'max:255'],
                'icon'  => ['nullable', 'string', 'max:40'],
            ],
            'custom' => [
                'custom_section_key' => ['required', 'string', 'max:40'],
                'title'              => ['nullable', 'string', 'max:160'],
                'subtitle'           => ['nullable', 'string', 'max:160'],
                'date'               => ['nullable', 'date_format:Y-m'],
                'description'        => ['nullable', 'string', 'max:2000'],
                'url'                => ['nullable', 'string', 'url', 'max:255'],
            ],
            default => [],
        };

        $validated = validator($data, $rules)->validate();

        // Custom-section items must reference an existing custom section
        // on this resume — otherwise an orphan slips into the JSON tree.
        if ($type === 'custom') {
            $keys = collect($resume->getMergedSections()['custom_sections'])
                ->pluck('key')->all();
            if (!in_array($validated['custom_section_key'], $keys, true)) {
                abort(422, 'Unknown custom section key.');
            }
        }

        return $validated;
    }

    /** Shape we return from every endpoint so the client sees one schema. */
    private function present(Resume $resume): array
    {
        $items = $resume->items->map(fn ($i) => $this->presentItem($i))->groupBy('section_type');

        return [
            'id'             => $resume->id,
            'template_id'    => $resume->template_id,
            'template'       => $resume->templateMeta(),
            'color_theme_id' => $resume->color_theme_id,
            'color_theme'    => $resume->colorThemeMeta(),
            'sections'       => $resume->getMergedSections(),
            'items'          => $items,
            'updated_at'     => optional($resume->updated_at)->toIso8601String(),
        ];
    }

    /** @return array<string,mixed> */
    private function presentItem(ResumeSectionItem $item): array
    {
        return [
            'id'           => $item->id,
            'section_type' => $item->section_type,
            'position'     => $item->position,
            'data'         => $item->data ?? [],
        ];
    }
}
