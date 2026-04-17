<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\SplashPage;
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
        $this->handleUploads($request, $splashPage);
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
        $this->handleUploads($request, $splashPage);
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
        return $request->validate([
            'name'          => 'required|string|max:120',
            'project_id'    => ['nullable', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'title'         => 'nullable|string|max:160',
            'description'   => 'nullable|string|max:1000',
            'cta_label'     => 'nullable|string|max:60',
            'cta_url'       => 'nullable|url|max:2000|regex:/^https?:\/\//i',
            'auto_redirect' => 'sometimes|boolean',
            'countdown'     => 'nullable|integer|min:0|max:120',
            'custom_css'    => 'nullable|string|max:50000',
            'custom_js'     => 'nullable|string|max:50000',
            'logo'          => 'nullable|image|max:2048',
            'favicon'       => 'nullable|image|max:512',
            'og_image'      => 'nullable|image|max:4096',
            'remove_logo'    => 'sometimes|boolean',
            'remove_favicon' => 'sometimes|boolean',
            'remove_og'      => 'sometimes|boolean',
        ]);
    }

    private function handleUploads(Request $request, SplashPage $sp): void
    {
        $map = ['logo' => 'logo', 'favicon' => 'favicon', 'og_image' => 'og_image'];
        $changed = false;
        foreach ($map as $field => $col) {
            $removeFlag = 'remove_' . ($field === 'og_image' ? 'og' : $field);
            if ($request->hasFile($field)) {
                if ($sp->$col) {
                    $old = ltrim(parse_url($sp->$col, PHP_URL_PATH) ?? '', '/');
                    if (str_starts_with($old, 'storage/')) Storage::disk('public')->delete(substr($old, 8));
                }
                $path = $request->file($field)->store("splash-pages/{$sp->id}", 'public');
                $sp->$col = Storage::disk('public')->url($path);
                $changed = true;
            } elseif ($request->boolean($removeFlag)) {
                if ($sp->$col) {
                    $old = ltrim(parse_url($sp->$col, PHP_URL_PATH) ?? '', '/');
                    if (str_starts_with($old, 'storage/')) Storage::disk('public')->delete(substr($old, 8));
                }
                $sp->$col = null;
                $changed = true;
            }
        }
        if ($changed) $sp->save();
    }
}
