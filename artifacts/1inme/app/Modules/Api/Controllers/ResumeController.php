<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\ResumeColorThemeRegistry;
use App\Modules\User\Services\ResumePresenter;
use App\Modules\User\Services\ResumeTemplateRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        $user     = $request->user();
        $resume   = $user->ensureResume();
        $sections = $resume->getMergedSections();
        $sections['header'] = array_replace($sections['header'], array_map(
            fn ($v) => is_string($v) ? trim($v) : $v,
            $data
        ));
        $resume->update(['sections' => $sections]);

        // Lazily shrink any header photo that was uploaded before the
        // upload-time compression existed. No-op when already small.
        $this->reoptimizeHeaderPhoto($resume, $user);

        return $this->ok(['resume' => ResumePresenter::present($resume->fresh('items'))]);
    }

    /**
     * POST /resume/header/photo — upload a header photo from the mobile
     * client. Mirrors the web controller: stores the uploaded image as a
     * UserFile in the user's vault so quotas / serving / cleanup stay
     * uniform across web + mobile, then writes its id onto
     * `sections.header.photo_user_file_id`. Replacing an existing photo
     * deletes the previous vault entry.
     */
    public function uploadHeaderPhoto(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user   = $request->user();
        $resume = $user->ensureResume();

        try {
            $userFile = UserFile::createFromUpload($request->file('photo'), $user, [
                'max_size_mb'    => 5,
                'compress_image' => true,
                'max_width'      => 800,
                'max_height'     => 800,
                'quality'        => 85,
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'upload_failed');
        }

        $sections = $resume->getMergedSections();
        $oldId    = $sections['header']['photo_user_file_id'] ?? null;
        $sections['header']['photo_user_file_id'] = $userFile->id;
        $resume->update(['sections' => $sections]);

        if ($oldId && (int) $oldId !== (int) $userFile->id) {
            $old = UserFile::where('id', $oldId)->where('user_id', $user->id)->first();
            if ($old) $old->deleteFile();
        }

        return $this->ok(['resume' => ResumePresenter::present($resume->fresh('items'))]);
    }

    /**
     * DELETE /resume/header/photo — remove the header photo and the
     * underlying vault file (it was uploaded explicitly for this slot).
     */
    public function removeHeaderPhoto(Request $request)
    {
        $user     = $request->user();
        $resume   = $user->ensureResume();
        $sections = $resume->getMergedSections();
        $oldId    = $sections['header']['photo_user_file_id'] ?? null;

        $sections['header']['photo_user_file_id'] = null;
        $resume->update(['sections' => $sections]);

        if ($oldId) {
            $old = UserFile::where('id', $oldId)->where('user_id', $user->id)->first();
            if ($old) $old->deleteFile();
        }

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

    /**
     * PUT /resume/publishing — toggle publish + visibility + indexing + password.
     *
     * Mirrors the web ResumeController::updatePublishing route so the
     * mobile editor can flip the public-page gating without bouncing
     * out to the web. Password is hashed on write and only honored when
     * the visibility tier is `password`; switching off the tier wipes
     * the stored hash so a re-enable can't silently reuse old creds.
     */
    public function updatePublishing(Request $request)
    {
        $data = $request->validate([
            'is_public'        => ['required', 'boolean'],
            'visibility'       => ['required', 'string', Rule::in(Resume::VISIBILITIES)],
            'allow_indexing'   => ['required', 'boolean'],
            'password'         => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:240'],
            'expires_at'       => ['nullable', 'string', 'max:64'],
        ]);

        $user   = $request->user();
        $resume = $user->ensureResume();
        $update = [
            'is_public'        => (bool) $data['is_public'],
            'visibility'       => $data['visibility'],
            'allow_indexing'   => (bool) $data['allow_indexing'],
            'meta_description' => $data['meta_description'] ?? null,
        ];

        if (array_key_exists('expires_at', $data)) {
            $raw = trim((string) ($data['expires_at'] ?? ''));
            if ($raw === '') {
                $update['expires_at'] = null;
            } else {
                try {
                    $update['expires_at'] = \Illuminate\Support\Carbon::parse($raw);
                } catch (\Throwable $e) {
                    return $this->fail('Could not parse expiration date.', 422, 'bad_expiration');
                }
            }
        }

        if ($data['visibility'] === 'password') {
            if (array_key_exists('password', $data)) {
                $update['password'] = filled($data['password']) ? Hash::make($data['password']) : null;
            }
        } else {
            $update['password'] = null;
        }

        $resume->update($update);

        return $this->ok([
            'resume'     => ResumePresenter::present($resume->fresh('items')),
            'public_url' => url('/' . $user->publicHandle() . '/resume'),
        ]);
    }

    /**
     * POST /resume/share/revoke — bump share_revision so every
     * previously-unlocked visitor is forced back to the password
     * prompt without changing the public URL. Optionally clears the
     * password too so a brand-new credential must be set.
     */
    public function revokeShare(Request $request)
    {
        $data = $request->validate([
            'clear_password' => ['nullable', 'boolean'],
        ]);

        $resume = $request->user()->ensureResume();
        $update = ['share_revision' => (int) ($resume->share_revision ?? 0) + 1];
        if (!empty($data['clear_password'])) {
            $update['password'] = null;
        }
        $resume->update($update);

        return $this->ok([
            'resume' => ResumePresenter::present($resume->fresh('items')),
        ]);
    }

    /**
     * GET /resume/views — paginated audit log of who viewed the
     * public resume page. Owner-scoped (always reads the signed-in
     * user's resume). Capped per_page so a malicious client can't
     * pull the entire table at once.
     */
    public function views(Request $request)
    {
        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(5, min(100, $perPage));

        $resume = $request->user()->ensureResume();
        $page   = $resume->views()->paginate($perPage);

        return $this->ok([
            'views' => collect($page->items())->map(fn ($v) => [
                'id'             => $v->id,
                'viewed_at'      => optional($v->viewed_at)->toIso8601String(),
                'country_code'   => $v->country_code,
                'referrer'       => $v->referrer,
                'viewer_handle'  => $v->viewer_handle,
                'viewer_user_id' => $v->viewer_user_id,
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    /**
     * PUT /resume/public-pdf — toggle the public-PDF privacy flag.
     *
     * When on, the stable `/{handle}/resume.pdf` URL is reachable by
     * anyone; when off, only the owner can fetch it (visitors get a
     * 404 so handle existence isn't leaked).
     */
    public function updatePublicPdf(Request $request)
    {
        $data = $request->validate([
            'is_public_pdf' => ['required', 'boolean'],
        ]);

        $resume = $request->user()->ensureResume();
        $resume->update(['is_public_pdf' => (bool) $data['is_public_pdf']]);

        return $this->ok(['resume' => ResumePresenter::present($resume->fresh('items'))]);
    }

    // ── Internals ──────────────────────────────────────────────────

    /**
     * If the resume's current header photo is a raster image larger
     * than the upload-time cap, shrink it in place. Best-effort and
     * silent — failure here must never break a header save.
     */
    private function reoptimizeHeaderPhoto(\App\Modules\User\Models\Resume $resume, \App\Modules\User\Models\User $user): void
    {
        $sections = $resume->getMergedSections();
        $photoId  = $sections['header']['photo_user_file_id'] ?? null;
        if (!$photoId) return;

        $photo = UserFile::where('id', $photoId)->where('user_id', $user->id)->first();
        if (!$photo) return;

        try {
            $photo->reoptimizeImageInPlace(800, 800, 85);
        } catch (\Throwable $e) {
            // best-effort, ignore
        }
    }

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
