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
 * Uploads land on the `public` disk under onboarding/, served via
 * /storage/onboarding/<file>.png after `php artisan storage:link`.
 * Old images are deleted when a new one is uploaded so the disk
 * doesn't accumulate orphans.
 */
class OnboardingSlideController extends Controller
{
    public function index(Request $request)
    {
        $slides = OnboardingSlide::ordered()->get();
        return view('admin.onboarding-slides.index', compact('slides'));
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

        $onboardingSlide->update($data);

        return redirect()->route('admin.onboarding-slides.index')
            ->with('success', 'Slide updated.');
    }

    public function destroy(OnboardingSlide $onboardingSlide)
    {
        if ($onboardingSlide->image_path) {
            Storage::disk('public')->delete($onboardingSlide->image_path);
        }
        $onboardingSlide->delete();

        return redirect()->route('admin.onboarding-slides.index')
            ->with('success', 'Slide deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'category'    => ['required', 'string', 'max:80'],
            'title'       => ['required', 'string', 'max:255'],
            'body'        => ['nullable', 'string', 'max:600'],
            'status'      => ['required', 'in:active,inactive'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:9999'],
            'image'       => ['nullable', 'image', 'max:5120'],
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'slide';
        $slug = $base;
        $i = 2;
        while (OnboardingSlide::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }
}
