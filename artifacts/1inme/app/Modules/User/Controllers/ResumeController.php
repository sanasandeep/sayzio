<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Services\ResumeAtsChecker;
use App\Modules\User\Services\ResumeColorThemeRegistry;
use App\Modules\User\Services\ResumePdfRenderer;
use App\Modules\User\Services\ResumePresenter;
use App\Modules\User\Services\ResumeTemplateRegistry;
use App\Modules\User\Services\ResumeVersionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
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
        $resume = $user->resolveResume($request);
        $resume->load('items');

        return view('user.resume.editor', [
            'bootstrap' => [
                'resume'     => $this->present($resume),
                'versions'   => ResumePresenter::presentVersions($user->resumes()->get()),
                'registries' => [
                    'templates'    => ResumeTemplateRegistry::availableFor($user),
                    'color_themes' => ResumeColorThemeRegistry::all(),
                ],
                // Default-version share URL — non-default versions
                // expose their own URL through `resume.public_url` in
                // the presenter payload above.
                'public_url' => url('/' . $user->publicHandle() . '/resume'),
            ],
            // Short `resume` links that surface this résumé, so the builder
            // can show their public URL + a jump to click analytics. A link
            // with no resume_id falls back to the owner's default version,
            // so include those when the resolved résumé is the default.
            'resumeLinks' => $this->resumeLinksFor($resume),
        ]);
    }

    /**
     * Short `resume`-type links that surface the given résumé. Returns a
     * plain array with the public short URL and a deep-link to each link's
     * click-analytics page so the builder can cross-link back to the link.
     *
     * A `resume` link with no `resume_id` falls back to the owner's default
     * version, so those are included only when the résumé being edited is
     * the default. Scoped to the résumé owner so no foreign links leak.
     *
     * @return array<int, array{title: string, public_url: string, analytics_url: string}>
     */
    private function resumeLinksFor(Resume $resume): array
    {
        return \App\Modules\User\Models\Link::query()
            ->where('user_id', $resume->user_id)
            ->where('type', \App\Modules\User\Models\Link::TYPE_RESUME)
            ->where(function ($q) use ($resume) {
                $q->where('resume_id', $resume->id);
                if ($resume->is_default) {
                    $q->orWhereNull('resume_id');
                }
            })
            ->latest()
            ->get()
            ->map(fn (\App\Modules\User\Models\Link $link) => [
                'title'         => $link->title ?: $link->alias,
                'public_url'    => $link->getShortUrl(),
                'analytics_url' => route('user.links.show', $link),
            ])
            ->all();
    }

    /**
     * GET — full resume + ordered items + registries.
     */
    public function show(Request $request): JsonResponse
    {
        $user   = $request->user();
        $resume = $user->resolveResume($request);
        $resume->load('items');

        return response()->json([
            'resume'   => $this->present($resume),
            'versions' => ResumePresenter::presentVersions($user->resumes()->get()),
            'registries' => [
                'templates'   => ResumeTemplateRegistry::availableFor($user),
                'color_themes' => ResumeColorThemeRegistry::all(),
            ],
        ]);
    }

    // ── Version management ─────────────────────────────────────────

    /** GET /resume/versions — list every version on the user's account. */
    public function versionsIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        // Provision the default row on first access so an empty list
        // never leaks back to the editor (which assumes >=1 row).
        $user->ensureResume();
        return response()->json([
            'versions' => ResumePresenter::presentVersions($user->resumes()->get()),
        ]);
    }

    /** POST /resume/versions — create a brand-new empty version. */
    public function versionStore(Request $request, ResumeVersionService $svc): JsonResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:80'],
            'template_id'    => ['nullable', 'string', Rule::in(ResumeTemplateRegistry::ids())],
            'color_theme_id' => ['nullable', 'string', Rule::in(ResumeColorThemeRegistry::ids())],
        ]);
        $user    = $request->user();
        $version = $svc->create($user, $data['name'], [
            'template_id'    => $data['template_id']    ?? null,
            'color_theme_id' => $data['color_theme_id'] ?? null,
        ]);

        return response()->json([
            'version'  => $this->present($version->fresh('items')),
            'versions' => ResumePresenter::presentVersions($user->resumes()->get()),
        ], 201);
    }

    /** PUT /resume/versions/{version} — rename a version. */
    public function versionRename(Request $request, Resume $version, ResumeVersionService $svc): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $svc->rename($request->user(), $version, $data['name']);

        return response()->json([
            'version'  => $this->present($version->fresh('items')),
            'versions' => ResumePresenter::presentVersions($request->user()->resumes()->get()),
        ]);
    }

    /** POST /resume/versions/{version}/duplicate — deep-copy a version. */
    public function versionDuplicate(Request $request, Resume $version, ResumeVersionService $svc): JsonResponse
    {
        $data = $request->validate(['name' => ['nullable', 'string', 'max:80']]);
        $copy = $svc->duplicate($request->user(), $version, $data['name'] ?? null);

        return response()->json([
            'version'  => $this->present($copy->fresh('items')),
            'versions' => ResumePresenter::presentVersions($request->user()->resumes()->get()),
        ], 201);
    }

    /** POST /resume/versions/{version}/default — promote a version to default. */
    public function versionSetDefault(Request $request, Resume $version, ResumeVersionService $svc): JsonResponse
    {
        $svc->setDefault($request->user(), $version);
        return response()->json([
            'versions' => ResumePresenter::presentVersions($request->user()->resumes()->get()),
        ]);
    }

    /** DELETE /resume/versions/{version} — remove a non-default version. */
    public function versionDestroy(Request $request, Resume $version, ResumeVersionService $svc): JsonResponse
    {
        $svc->delete($request->user(), $version);
        return response()->json([
            'versions' => ResumePresenter::presentVersions($request->user()->resumes()->get()),
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

        $resume   = $request->user()->resolveResume($request);
        $sections = $resume->getMergedSections();
        $sections['header'] = array_replace($sections['header'], array_map(
            fn ($v) => is_string($v) ? trim($v) : $v,
            $data
        ));
        $resume->update(['sections' => $sections]);

        // Lazily shrink any header photo that was uploaded before the
        // upload-time compression existed. No-op when already small.
        $this->reoptimizeHeaderPhoto($resume, $request->user());

        return response()->json(['resume' => $this->present($resume->fresh('items'))]);
    }

    /**
     * POST — set the header photo. Accepts three mutually exclusive modes:
     *
     *  • `photo`        (file)   — direct upload; stored in the user's vault.
     *  • `vault_file_id` (int)   — borrow an existing vault file; no copy made.
     *  • `photo_url`    (string) — fetch a remote image and store it in vault.
     *
     * Uploaded or fetched files are added to the vault so quota / serving /
     * cleanup logic stays uniform. "Borrowed" vault files are NOT deleted when
     * the photo is later replaced, since they remain useful in the vault.
     * Owned uploads (non-borrowed) ARE cleaned up on replacement.
     */
    public function uploadHeaderPhoto(Request $request): JsonResponse
    {
        $user   = $request->user();
        $resume = $user->resolveResume($request);

        $borrowed = false;

        if ($request->hasFile('photo')) {
            // ── Direct file upload ────────────────────────────────────────
            $request->validate([
                'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ]);
            try {
                $userFile = UserFile::createFromUpload($request->file('photo'), $user, [
                    'max_size_mb'    => 5,
                    'compress_image' => true,
                    'max_width'      => 800,
                    'max_height'     => 800,
                    'quality'        => 85,
                ]);
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

        } elseif ($request->filled('vault_file_id')) {
            // ── Vault pick — borrow an existing vault file ────────────────
            $request->validate([
                'vault_file_id' => ['required', 'integer'],
            ]);
            $userFile = UserFile::where('id', $request->integer('vault_file_id'))
                ->where('user_id', $user->id)
                ->first();
            if (! $userFile) {
                return response()->json(['message' => 'File not found in your vault.'], 422);
            }
            $borrowed = true;

        } elseif ($request->filled('photo_url')) {
            // ── Remote URL — fetch and store in vault ─────────────────────
            $request->validate([
                'photo_url' => ['required', 'url', 'max:2048'],
            ]);
            $photoUrl = $request->input('photo_url');
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(15)->get($photoUrl);
                if (! $response->ok()) {
                    return response()->json(['message' => 'Could not fetch the image at that URL.'], 422);
                }
                $mime    = strtolower(explode(';', $response->header('Content-Type') ?? 'image/jpeg')[0]);
                $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (! in_array($mime, $allowed)) {
                    return response()->json(['message' => 'The URL does not point to a supported image (JPG, PNG, WebP, GIF).'], 422);
                }
                $ext = match ($mime) {
                    'image/png'  => 'png',
                    'image/webp' => 'webp',
                    'image/gif'  => 'gif',
                    default      => 'jpg',
                };
                $userFile = UserFile::createFromBytes(
                    $response->body(),
                    'resume-photo.' . $ext,
                    $mime,
                    $user,
                    [
                        'max_size_mb'    => 5,
                        'compress_image' => true,
                        'max_width'      => 800,
                        'max_height'     => 800,
                        'quality'        => 85,
                    ]
                );
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            } catch (\Throwable $e) {
                return response()->json(['message' => 'Could not import the photo from that URL.'], 422);
            }

        } else {
            return response()->json(['message' => 'Please provide a photo file, vault file ID, or URL.'], 422);
        }

        $sections    = $resume->getMergedSections();
        $oldId       = $sections['header']['photo_user_file_id'] ?? null;
        $oldBorrowed = $sections['header']['photo_borrowed'] ?? false;

        $sections['header']['photo_user_file_id'] = $userFile->id;
        $sections['header']['photo_borrowed']     = $borrowed;
        $resume->update(['sections' => $sections]);

        // Delete the old file only if it was a dedicated upload (not borrowed).
        if ($oldId && ! $oldBorrowed && (int) $oldId !== (int) $userFile->id) {
            $old = UserFile::where('id', $oldId)->where('user_id', $user->id)->first();
            if ($old) {
                $old->deleteFile();
            }
        }

        return response()->json(['resume' => $this->present($resume->fresh('items'))]);
    }

    /**
     * DELETE — remove the header photo. Clears the reference and deletes
     * the underlying vault file only when it was a dedicated upload
     * (photo_borrowed=false). Vault-picked files stay in the vault.
     */
    public function removeHeaderPhoto(Request $request): JsonResponse
    {
        $user     = $request->user();
        $resume   = $user->resolveResume($request);
        $sections = $resume->getMergedSections();
        $oldId       = $sections['header']['photo_user_file_id'] ?? null;
        $oldBorrowed = $sections['header']['photo_borrowed'] ?? false;

        $sections['header']['photo_user_file_id'] = null;
        $sections['header']['photo_borrowed']     = false;
        $resume->update(['sections' => $sections]);

        if ($oldId && ! $oldBorrowed) {
            $old = UserFile::where('id', $oldId)->where('user_id', $user->id)->first();
            if ($old) {
                $old->deleteFile();
            }
        }

        return response()->json(['resume' => $this->present($resume->fresh('items'))]);
    }

    /** PUT — update summary. */
    public function updateSummary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'summary' => ['nullable', 'string', 'max:2000'],
        ]);

        $resume   = $request->user()->resolveResume($request);
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

        $resume = $user->resolveResume($request);
        $resume->update(['template_id' => $data['template_id']]);

        return response()->json(['resume' => $this->present($resume->fresh('items'))]);
    }

    /**
     * PUT — toggle the public-PDF privacy flag.
     *
     * When `is_public_pdf` is true, anyone (logged in or not) can fetch
     * `/{handle}/resume.pdf`. When false, only the owner can; visitors
     * get a 404 so the handle's existence isn't leaked.
     */
    public function updatePublicPdf(Request $request): JsonResponse
    {
        $data = $request->validate([
            'is_public_pdf' => ['required', 'boolean'],
        ]);

        $resume = $request->user()->resolveResume($request);
        $resume->update(['is_public_pdf' => (bool) $data['is_public_pdf']]);

        return response()->json(['resume' => $this->present($resume->fresh('items'))]);
    }

    /** PUT — switch color theme. */
    public function updateColorTheme(Request $request): JsonResponse
    {
        $data = $request->validate([
            'color_theme_id' => ['required', 'string', Rule::in(ResumeColorThemeRegistry::ids())],
        ]);

        $resume = $request->user()->resolveResume($request);
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

        $resume = $request->user()->resolveResume($request);
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

        $resume   = $request->user()->resolveResume($request);
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
        $resume = $request->user()->resolveResume($request);
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

        $resume = $request->user()->resolveResume($request);
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

        $resume = $request->user()->resolveResume($request);

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
        $resume = $user->resolveResume($request);
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
     * GET — stable URL `/{handle}/resume.pdf`. Mirrors the download
     * endpoint, but resolves the resume by handle so it can be shared.
     *
     * Access rules:
     *  - Owner (signed-in, handle matches): always allowed; uses owner
     *    throttle bucket (route-level throttle:20,1).
     *  - Anyone else (anonymous OR signed-in non-owner): allowed only
     *    when the owner has flipped `is_public_pdf` on. Visitor traffic
     *    is rate-limited separately on a stricter per-IP+handle bucket
     *    so a public link can't be weaponised against the renderer.
     *  - When the flag is off (or the handle doesn't exist), visitors
     *    get a 404 so handle existence isn't leaked.
     */
    public function downloadByHandle(Request $request, string $handle, ResumePdfRenderer $renderer, ?string $slug = null): Response
    {
        $owner = \App\Modules\User\Models\CreatorProfile::ownerUserForHandle($handle);
        if (!$owner) abort(404);

        // Bare /{handle}/resume.pdf still serves the default version;
        // /v/{slug}.pdf targets a specific version. An unknown slug
        // 404s so the visitor knows the link they were given is dead.
        if ($slug === null || $slug === '' || $slug === Resume::DEFAULT_SLUG) {
            $resume = $owner->ensureResume();
        } else {
            $resume = $owner->resumes()->where('slug', $slug)->first();
            if (!$resume) abort(404);
        }
        $signedIn = $request->user();
        $isOwner  = $signedIn && $signedIn->id === $owner->id;

        if (!$isOwner) {
            // Don't leak handle existence when sharing is off.
            if (!$resume->is_public_pdf) abort(404);

            // Stricter per-IP throttle for visitors so the public link
            // can't be hammered to drive PDF generation cost.
            $key = 'resume-public-pdf:' . sha1($handle . '|' . $request->ip());
            if (RateLimiter::tooManyAttempts($key, 10)) {
                $retryAfter = RateLimiter::availableIn($key);
                return response('Too Many Requests', 429, [
                    'Retry-After'           => (string) $retryAfter,
                    'X-RateLimit-Limit'     => '10',
                    'X-RateLimit-Remaining' => '0',
                ]);
            }
            RateLimiter::hit($key, 60);
        }

        $resume->load('items');
        $size = $renderer->normalizeSize($request->query('size'));
        $out  = $renderer->render($resume, $owner, $size);

        // Public downloads are safe to cache briefly at the edge; owner
        // downloads stay private so a stale copy can't be served back.
        $cache = $isOwner
            ? 'private, max-age=0, no-store'
            : 'public, max-age=60';

        return response($out['body'], 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $out['filename'] . '"',
            'Content-Length'      => (string) strlen($out['body']),
            'Cache-Control'       => $cache,
            'X-Resume-Paper-Size' => $size,
            'X-Resume-Access'     => $isOwner ? 'owner' : 'public',
        ]);
    }

    /**
     * PUT — toggle publish + visibility + indexing + password + expiration.
     *
     * Mirrors the visibility vocabulary used by Link.visibility so the
     * public-page renderer (PublicResumeController) can reuse the same
     * gating helpers. The password is hashed with Hash::make on write
     * and only sent when the visibility tier is `password`. The
     * `expires_at` field — when set — gates non-owner traffic after
     * the deadline, which is useful when sending a link to a specific
     * recruiter on a deadline.
     */
    public function updatePublishing(Request $request): JsonResponse
    {
        $data = $request->validate([
            'is_public'      => ['required', 'boolean'],
            'visibility'     => ['required', 'string', Rule::in(Resume::VISIBILITIES)],
            'allow_indexing' => ['required', 'boolean'],
            // Optional — only honored when visibility=password. An empty
            // string clears the existing password; a non-empty string
            // hashes and stores it.
            'password'         => ['nullable', 'string', 'max:200'],
            'meta_description' => ['nullable', 'string', 'max:240'],
            // Optional ISO-8601 datetime. Empty string / null clears it.
            // We accept anything Carbon can parse so the web (datetime-
            // local input) and mobile (Date.toISOString) can both POST.
            'expires_at'       => ['nullable', 'string', 'max:64'],
        ]);

        $resume = $request->user()->resolveResume($request);
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
                    return response()->json(['message' => 'Could not parse expiration date.'], 422);
                }
            }
        }

        if ($data['visibility'] === 'password') {
            if (array_key_exists('password', $data)) {
                $update['password'] = filled($data['password']) ? Hash::make($data['password']) : null;
            }
        } else {
            // Switching off password tier wipes the stored hash so a
            // future re-enable doesn't silently reuse an old credential.
            $update['password'] = null;
        }

        $resume->update($update);

        return response()->json([
            'resume'     => $this->present($resume->fresh('items')),
            'public_url' => url('/' . $request->user()->publicHandle() . '/resume'),
        ]);
    }

    /**
     * POST — run the ATS-readiness checks against the owner's resume.
     *
     * Side-effect free: the checker only reads the resume + items + the
     * template style metadata, so the editor can poll this endpoint
     * (e.g. before exporting a PDF) without churning the row. An
     * optional `target_role` body lets the caller paste a JD/role blurb
     * to get keyword coverage as part of the report.
     */
    public function atsCheck(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_role' => ['nullable', 'string', 'max:8000'],
        ]);

        $resume = $request->user()->resolveResume($request);
        $report = ResumeAtsChecker::check($resume, [
            'target_role' => $data['target_role'] ?? null,
        ]);

        return response()->json(['report' => $report]);
    }

    /**
     * POST — revoke the current share without changing the URL.
     *
     * Bumps `share_revision`, which is part of the unlock-session key,
     * so every visitor who had previously typed the password is forced
     * back to the prompt on their next visit. Optionally clears the
     * stored password so a brand-new credential must be set.
     */
    public function revokeShare(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clear_password' => ['nullable', 'boolean'],
        ]);

        $resume = $request->user()->resolveResume($request);
        $update = ['share_revision' => (int) ($resume->share_revision ?? 0) + 1];
        if (!empty($data['clear_password'])) {
            $update['password'] = null;
        }
        $resume->update($update);

        return response()->json([
            'resume' => $this->present($resume->fresh('items')),
        ]);
    }

    /**
     * GET — paginated audit log of who viewed the resume page.
     *
     * Owner-only by route convention. Cursorless `?page=N` pagination
     * with a hard cap on per_page so a malicious client can't pull the
     * whole table at once.
     */
    public function views(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 25);
        $perPage = max(5, min(100, $perPage));

        $resume = $request->user()->resolveResume($request);
        $page   = $resume->views()->paginate($perPage);

        return response()->json([
            'data' => collect($page->items())->map(fn ($v) => [
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

    // ── Internals ──────────────────────────────────────────────────

    /**
     * If the resume's current header photo is a raster image larger
     * than the upload-time cap, shrink it in place. Best-effort and
     * silent — failure here must never break a header save.
     */
    private function reoptimizeHeaderPhoto(Resume $resume, User $user): void
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

    /**
     * Shape we return from every endpoint so the client sees one schema.
     * Delegates to ResumePresenter so the mobile API controller emits
     * exactly the same JSON for the same row.
     */
    private function present(Resume $resume): array
    {
        return ResumePresenter::present($resume);
    }

    /** @return array<string,mixed> */
    private function presentItem(ResumeSectionItem $item): array
    {
        return ResumePresenter::presentItem($item);
    }
}
