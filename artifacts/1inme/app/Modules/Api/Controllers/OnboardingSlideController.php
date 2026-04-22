<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\OnboardingSlide;

/**
 * Public read-only endpoint that powers the mobile splash slider.
 * No auth required — runs before login and during onboarding.
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
                'sort_order' => $s->sort_order,
            ])->values();

        return response()->json(['items' => $items]);
    }
}
