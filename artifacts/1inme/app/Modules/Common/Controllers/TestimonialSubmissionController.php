<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Testimonial;
use Illuminate\Http\Request;

class TestimonialSubmissionController extends Controller
{
    public function show()
    {
        return view('public.testimonials-submit');
    }

    public function store(Request $request)
    {
        if ($request->input('website') !== null && $request->input('website') !== '') {
            return redirect()->route('testimonials.submit.show');
        }

        $data = $request->validate([
            'quote'           => ['required', 'string', 'max:600'],
            'author_name'     => ['required', 'string', 'max:120'],
            'author_role'     => ['nullable', 'string', 'max:160'],
            'rating'          => ['nullable', 'integer', 'min:1', 'max:5'],
            'submitter_email' => ['nullable', 'email', 'max:200'],
            'website'         => ['nullable', 'max:0'],
        ]);

        Testimonial::create([
            'quote'           => $data['quote'],
            'author_name'     => $data['author_name'],
            'author_role'     => $data['author_role'] ?? null,
            'rating'          => (int) ($data['rating'] ?? 5),
            'submitter_email' => $data['submitter_email'] ?? null,
            'accent_color'    => '#3d6bff',
            'row'             => 'top',
            'is_active'       => false,
            'sort_order'      => 0,
            'status'          => 'pending',
            'source'          => 'public',
            'submitted_at'    => now(),
        ]);

        return redirect()->route('testimonials.submit.show')->with('success', true);
    }
}
