<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\OnboardingSlide;

/**
 * Public read-only endpoint that powers the mobile splash slider.
 * No auth required — runs before login and during onboarding.
 *
 * Each slide ships with:
 *   - image_url:   the legacy single background photo (kept for older
 *                  mobile clients).
 *   - image_urls:  ordered list of gallery photos. The mobile splash
 *                  renders these as a small auto-rotating carousel
 *                  inside the slide.
 */
class OnboardingSlideController extends Controller
{
    public function index()
    {
        $items = OnboardingSlide::active()->ordered()->get()
            ->map(fn (OnboardingSlide $s) => [
                'id'         => $s->id,
                'slug'       => $s->slug,
                'category'   => $s->category,
                'title'      => $s->title,
                'body'       => $s->body,
                'image_url'  => $s->imageUrl(),
                'image_urls' => $s->galleryUrls(),
                'sort_order' => $s->sort_order,
            ])->values();

        return response()->json(['items' => $items]);
    }
}
