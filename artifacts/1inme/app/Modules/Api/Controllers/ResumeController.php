<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Services\ResumeColorThemeRegistry;
use App\Modules\User\Services\ResumePresenter;
use App\Modules\User\Services\ResumeTemplateRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Mobile/API surface for the Resume / Portfolio module.
 *
 * Mirrors the web ResumeController in spirit (one resume per user,
 * resolved server-side from the bearer token, items authorized by
 * ownership) but returns `{data: ...}` shaped responses so it slots in
 * with the rest of the v1 API contract used by the mobile app.
 *
 * The serialization is delegated to ResumePresenter so the JSON shape
 * matches what the web editor consumes — the two clients are talking
 * about the same Resume model with the same field names.
 */
class ResumeController extends Controller
{
    use ApiResponses;

    /** GET /resume — full resume + items + registries. */
    public function show(Request $request)
    {
        $user   = $request->user();
        $resume = $user->ensureResume();
        $resume->load('items');

        return $this->ok([
            'resume'     => ResumePresenter::present($resume),
            'registries' => [
                'templates'    => ResumeTemplateRegistry::availableFor($user),
                'color_themes' => ResumeColorThemeRegistry::all(),
            ],
        ]);
    }

    /** PUT /resume/header — update header fields. */
    public function updateHeader(Request $request)
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

        return $this->ok(['resume' => ResumePresenter::present($resume->fresh('items'))]);
    }

    /** PUT /resume/summary — update the summary blurb. */
    public function updateSummary(Request $request)
    {
        $data = $request->validate([
            'summary' => ['nullable', 'string', 'max:2000'],
        ]);

        $resume   = $request->user()->ensureResume();
        $sections = $resume->getMergedSections();
        $sections['summary'] = (string) ($data['summary'] ?? '');
        $resume->update(['sections' => $sections]);

        return $this->ok(['resume' => ResumePresenter::present($resume->fresh('items'))]);
    }

    /** PUT /resume/template — switch template (gated by plan). */
    public function updateTemplate(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'template_id' => ['required', 'string', Rule::in(ResumeTemplateRegistry::ids())],
        ]);

        if (!ResumeTemplateRegistry::userCanUse($user, $data['template_id'])) {
            return $this->fail('This template is not available on your current plan.', 403, 'plan_required');
        }

        $resume = $user->ensureResume();
        $resume->update(['template_id' => $data['template_id']]);

        return $this->ok(['resume' => ResumePresenter::present($resume->fresh('items'))]);
    }

    /** PUT /resume/color-theme — switch color theme. */
    public function updateColorTheme(Request $request)
    {
        $data = $request->validate([
            'color_theme_id' => ['required', 'string', Rule::in(ResumeColorThemeRegistry::ids())],
        ]);

        $resume = $request->user()->ensureResume();
        $resume->update(['color_theme_id' => $data['color_theme_id']]);

        return $this->ok(['resume' => ResumePresenter::present($resume->fresh('items'))]);
    }

    /** POST /resume/items — append a new section item. */
    public function storeItem(Request $request)
    {
        $base = $request->validate([
            'section_type' => ['required', 'string', Rule::in(ResumeSectionItem::TYPES)],
            'data'         => ['required', 'array'],
        ]);

        $resume  = $request->user()->ensureResume();
        $payload = $this->validateItemData($base['section_type'], $base['data']);

        $maxPos = (int) $resume->itemsOfType($base['section_type'])->max('position');
        $item   = $resume->items()->create([
            'section_type' => $base['section_type'],
            'position'     => $maxPos + 1,
            'data'         => $payload,
        ]);

        return $this->created([
            'item'   => ResumePresenter::presentItem($item),
            'resume' => ResumePresenter::present($resume->fresh('items')),
        ]);
    }

    /** PUT /resume/items/{item} — update an existing item. */
    public function updateItem(Request $request, ResumeSectionItem $item)
    {
        $this->authorizeItem($request, $item);

        $base = $request->validate([
            'data' => ['required', 'array'],
        ]);
        $payload = $this->validateItemData($item->section_type, $base['data']);
        $item->update(['data' => $payload]);

        return $this->ok(['item' => ResumePresenter::presentItem($item->fresh())]);
    }

    /** DELETE /resume/items/{item} — remove an item. */
    public function destroyItem(Request $request, ResumeSectionItem $item)
    {
        $this->authorizeItem($request, $item);
        $item->delete();
        return $this->noContent();
    }

    /** POST /resume/items/reorder — reorder items inside a section type. */
    public function reorderItems(Request $request)
    {
        $data = $request->validate([
            'section_type' => ['required', 'string', Rule::in(ResumeSectionItem::TYPES)],
            'item_ids'     => ['required', 'array', 'min:1'],
            'item_ids.*'   => ['integer'],
        ]);

        $resume = $request->user()->ensureResume();

        DB::transaction(function () use ($resume, $data) {
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
            foreach ($allIds as $id) {
                if (!isset($seen[$id])) $ordered[] = $id;
            }

            $position = 1;
            foreach ($ordered as $id) {
                ResumeSectionItem::whereKey($id)->update(['position' => $position++]);
            }
        });

        return $this->ok(['resume' => ResumePresenter::present($resume->fresh('items'))]);
    }

    // ── Internals ──────────────────────────────────────────────────

    private function authorizeItem(Request $request, ResumeSectionItem $item): void
    {
        $resume = $item->resume()->first();
        abort_if(!$resume || $resume->user_id !== $request->user()->id, 403);
    }

    /**
     * Per-section-type validation. Mirrors the web controller's rules so
     * a payload that's accepted via the mobile API would also be
     * accepted via the web editor (and vice versa).
     *
     * @return array<string,mixed>
     */
    private function validateItemData(string $type, array $data): array
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
            default => [],
        };

        return validator($data, $rules)->validate();
    }
}
