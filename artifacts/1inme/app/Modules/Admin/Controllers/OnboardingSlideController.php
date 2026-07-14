<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\OnboardingSlide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Admin CRUD for the mobile-app onboarding/splash slider.
 *
 * Each slide can have:
 *   - one legacy "background" image (`image_path`) — kept for back-compat
 *   - a gallery (`gallery_images` JSON array of paths) — rendered as an
 *     auto-rotating image slider inside the slide on the mobile splash
 *
 * Uploads land on the `public` disk under onboarding/, served via
 * /storage/onboarding/<file>.png after `php artisan storage:link`.
 * Removed images are deleted from disk so it doesn't accumulate orphans.
 */
class OnboardingSlideController extends Controller
{
    public function index(Request $request)
    {
        $slides  = OnboardingSlide::ordered()->get();
        $drifted = $slides->filter->hasDriftedFromDefault()->values();
        return view('admin.onboarding-slides.index', compact('slides', 'drifted'));
    }

    public function create()
    {
        return view('admin.onboarding-slides.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($data['title']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')
                ->store('onboarding', 'public');
        }

        $data['gallery_images'] = $this->collectGalleryUploads($request, []);

        OnboardingSlide::create($data);

        return redirect()->route('admin.onboarding-slides.index')
            ->with('success', 'Slide created.');
    }

    public function edit(OnboardingSlide $onboardingSlide)
    {
        return view('admin.onboarding-slides.edit', [
            'slide' => $onboardingSlide,
        ]);
    }

    public function update(Request $request, OnboardingSlide $onboardingSlide)
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            // Drop the previous file so the disk stays tidy.
            if ($onboardingSlide->image_path) {
                Storage::disk('public')->delete($onboardingSlide->image_path);
            }
            $data['image_path'] = $request->file('image')
                ->store('onboarding', 'public');
        }

        // Existing gallery minus anything the admin asked to remove,
        // then append any newly-uploaded files.
        $current = $onboardingSlide->gallery_images ?: [];
        $remove  = (array) $request->input('remove_gallery', []);
        $kept    = [];
        foreach ($current as $p) {
            if (in_array($p, $remove, true)) {
                Storage::disk('public')->delete($p);
            } else {
                $kept[] = $p;
            }
        }
        $data['gallery_images'] = $this->collectGalleryUploads($request, $kept);

        $onboardingSlide->update($data);

        return redirect()->route('admin.onboarding-slides.index')
            ->with('success', 'Slide updated.');
    }

    public function destroy(OnboardingSlide $onboardingSlide)
    {
        if ($onboardingSlide->image_path) {
            Storage::disk('public')->delete($onboardingSlide->image_path);
        }
        foreach (($onboardingSlide->gallery_images ?: []) as $p) {
            Storage::disk('public')->delete($p);
        }
        $onboardingSlide->delete();

        return redirect()->route('admin.onboarding-slides.index')
            ->with('success', 'Slide deleted.');
    }

    /**
     * @param  array<int, string>  $existing
     * @return array<int, string>
     */
    private function collectGalleryUploads(Request $request, array $existing): array
    {
        $files = $request->file('gallery_images', []);
        if (!is_array($files)) {
            $files = [$files];
        }
        foreach ($files as $f) {
            if ($f && $f->isValid()) {
                $existing[] = $f->store('onboarding', 'public');
            }
        }
        return array_values($existing);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category'           => ['required', 'string', 'max:80'],
            'title'              => ['required', 'string', 'max:255'],
            'body'               => ['nullable', 'string', 'max:600'],
            'status'             => ['required', 'in:active,inactive'],
            'sort_order'         => ['nullable', 'integer', 'min:0', 'max:9999'],
            'image'              => ['nullable', 'image', 'max:5120'],
            'gallery_images'     => ['nullable', 'array', 'max:10'],
            'gallery_images.*'   => ['image', 'max:5120'],
            'remove_gallery'     => ['nullable', 'array'],
            'remove_gallery.*'   => ['string'],
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'slide';
        return \App\Support\UniqueSuffix::resolve(OnboardingSlide::query(), $base);
    }
}
