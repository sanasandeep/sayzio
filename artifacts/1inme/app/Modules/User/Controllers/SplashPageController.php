<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\SplashPage;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SplashPageController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->splashPages()->with('project')->withCount('links');
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('title', 'ilike', "%{$search}%");
            });
        }
        if ($projectId = $request->get('project_id')) {
            $query->where('project_id', $projectId);
        }
        $splashPages = $query->latest()->paginate(20)->withQueryString();
        $projects = $request->user()->projects()->orderBy('name')->get();
        return view('user.splash-pages.index', compact('splashPages', 'projects'));
    }

    public function create(Request $request)
    {
        $projects = $request->user()->projects()->orderBy('name')->get();
        $splashPage = new SplashPage(['countdown' => 5, 'cta_label' => 'Continue']);
        return view('user.splash-pages.create', compact('splashPage', 'projects'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateData($request);
        $splashPage = new SplashPage($validated);
        $splashPage->user_id = $request->user()->id;
        $splashPage->save();
        try {
            $this->handleUploads($request, $splashPage);
        } catch (\RuntimeException $e) {
            return redirect()->route('user.splash-pages.edit', $splashPage)
                ->with('error', $e->getMessage());
        }
        return redirect()->route('user.splash-pages.edit', $splashPage)
            ->with('success', 'Splash page created.');
    }

    public function show(Request $request, SplashPage $splashPage)
    {
        $this->authorizeOwnership($request, $splashPage);
        $splashPage->load(['project', 'links' => fn($q) => $q->latest()->limit(50)]);
        return view('user.splash-pages.show', compact('splashPage'));
    }

    public function edit(Request $request, SplashPage $splashPage)
    {
        $this->authorizeOwnership($request, $splashPage);
        $projects = $request->user()->projects()->orderBy('name')->get();
        return view('user.splash-pages.edit', compact('splashPage', 'projects'));
    }

    public function update(Request $request, SplashPage $splashPage)
    {
        $this->authorizeOwnership($request, $splashPage);
        $validated = $this->validateData($request);
        $splashPage->fill($validated)->save();
        try {
            $this->handleUploads($request, $splashPage);
        } catch (\RuntimeException $e) {
            return redirect()->route('user.splash-pages.edit', $splashPage)
                ->with('error', $e->getMessage());
        }
        return redirect()->route('user.splash-pages.edit', $splashPage)
            ->with('success', 'Splash page saved.');
    }

    public function destroy(Request $request, SplashPage $splashPage)
    {
        $this->authorizeOwnership($request, $splashPage);
        foreach (['logo', 'favicon', 'og_image'] as $f) {
            if ($splashPage->$f) {
                $p = ltrim(parse_url($splashPage->$f, PHP_URL_PATH) ?? '', '/');
                if (str_starts_with($p, 'storage/')) Storage::disk('public')->delete(substr($p, 8));
            }
        }
        $splashPage->delete();
        return redirect()->route('user.splash-pages.index')
            ->with('success', 'Splash page deleted.');
    }

    /** Live preview of the splash page in the standalone editor. */
    public function preview(Request $request, SplashPage $splashPage)
    {
        $this->authorizeOwnership($request, $splashPage);
        $splash = $splashPage->toRenderArray();
        $continueUrl = url('/');
        $destinationUrl = url('/');
        $link = (object) ['title' => $splashPage->title ?: $splashPage->name, 'id' => 0];
        return response()->view('common.splash', compact('link', 'splash', 'continueUrl', 'destinationUrl'));
    }

    // ---------- helpers ----------
    private function authorizeOwnership(Request $request, SplashPage $sp): void
    {
        abort_unless($sp->user_id === $request->user()->id, 403);
    }

    private function validateData(Request $request): array
    {
        $userId = $request->user()->id;
        $hexRule = ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'];
        $validated = $request->validate([
            'name'          => 'required|string|max:120',
            'project_id'    => ['nullable', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'title'         => 'nullable|string|max:160',
            'description'   => 'nullable|string|max:1000',
            'cta_label'     => 'nullable|string|max:60',
            'cta_url'       => 'nullable|url|max:2000|regex:/^https?:\/\//i',
            'cta_bg_color'  => $hexRule,
            'cta_text_color'=> $hexRule,
            'extra_buttons'                   => 'nullable|array|max:10',
            'extra_buttons.*.label'           => 'nullable|string|max:60',
            'extra_buttons.*.url'             => 'nullable|url|max:2000|regex:/^https?:\/\//i',
            'extra_buttons.*.bg_color'        => $hexRule,
            'extra_buttons.*.text_color'      => $hexRule,
            'auto_redirect' => 'sometimes|boolean',
            'countdown'     => 'nullable|integer|min:0|max:120',
            'custom_css'    => 'nullable|string|max:50000',
            'custom_js'     => 'nullable|string|max:50000',
            'logo'          => \App\Services\UploadPolicy::rule('splash.logo', $request->user()),
            'favicon'       => \App\Services\UploadPolicy::rule('splash.favicon', $request->user()),
            'og_image'      => \App\Services\UploadPolicy::rule('splash.og', $request->user()),
            'remove_logo'    => 'sometimes|boolean',
            'remove_favicon' => 'sometimes|boolean',
            'remove_og'      => 'sometimes|boolean',
        ]);

        // Drop empty button rows (no label AND no url) so save doesn't persist blanks.
        if (!empty($validated['extra_buttons']) && is_array($validated['extra_buttons'])) {
            $validated['extra_buttons'] = array_values(array_filter(
                array_map(fn ($b) => [
                    'label'      => trim((string) ($b['label'] ?? '')),
                    'url'        => trim((string) ($b['url'] ?? '')),
                    'bg_color'   => $b['bg_color']   ?? null,
                    'text_color' => $b['text_color'] ?? null,
                ], $validated['extra_buttons']),
                fn ($b) => $b['label'] !== '' || $b['url'] !== ''
            ));
            if (empty($validated['extra_buttons'])) {
                $validated['extra_buttons'] = null;
            }
        } else {
            $validated['extra_buttons'] = null;
        }

        return $validated;
    }

    private function handleUploads(Request $request, SplashPage $sp): void
    {
        // Per-asset size caps come from the user's plan via UploadPolicy.
        $user = $request->user();
        $map = [
            'logo'     => ['col' => 'logo',     'max_mb' => \App\Services\UploadPolicy::for('splash.logo',    $user)['max_mb']],
            'favicon'  => ['col' => 'favicon',  'max_mb' => \App\Services\UploadPolicy::for('splash.favicon', $user)['max_mb']],
            'og_image' => ['col' => 'og_image', 'max_mb' => \App\Services\UploadPolicy::for('splash.og',      $user)['max_mb']],
        ];
        $changed = false;
        foreach ($map as $field => $cfg) {
            $col = $cfg['col'];
            $removeFlag = 'remove_' . ($field === 'og_image' ? 'og' : $field);
            if ($request->hasFile($field)) {
                $this->deleteLegacyPublicAsset($sp->$col);
                // Bubble RuntimeException up to store()/update(), which wrap
                // this call in try/catch and flash an error to the user.
                $userFile = UserFile::createFromUpload($request->file($field), $user, [
                    'max_size_mb' => $cfg['max_mb'],
                ]);
                $sp->$col = $userFile->url;
                $changed = true;
            } elseif ($request->boolean($removeFlag)) {
                $this->deleteLegacyPublicAsset($sp->$col);
                $sp->$col = null;
                $changed = true;
            }
        }
        if ($changed) $sp->save();
    }

    /**
     * Best-effort cleanup of a legacy public-disk asset path. Vault assets
     * (URLs starting with /f/) are left alone — the UserFile garbage
     * collector handles those when the row is hard-deleted.
     */
    private function deleteLegacyPublicAsset(?string $url): void
    {
        if (! $url) return;
        $rel = ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/');
        if (str_starts_with($rel, 'storage/')) {
            Storage::disk('public')->delete(substr($rel, 8));
        }
    }
}
