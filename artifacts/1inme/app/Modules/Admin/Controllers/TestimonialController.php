<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Testimonial;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    public function index(Request $request)
    {
        $row = $request->query('row');
        $query = Testimonial::query()->orderBy('row')->orderBy('sort_order')->orderBy('id');
        if (in_array($row, ['top', 'bottom'], true)) {
            $query->where('row', $row);
        }
        $testimonials = $query->get();

        $counts = [
            'total'  => Testimonial::count(),
            'active' => Testimonial::where('is_active', true)->count(),
            'top'    => Testimonial::where('row', 'top')->count(),
            'bottom' => Testimonial::where('row', 'bottom')->count(),
        ];

        return view('admin.testimonials.index', compact('testimonials', 'row', 'counts'));
    }

    public function create()
    {
        return view('admin.testimonials.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Testimonial::create($data);
        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial added.');
    }

    public function edit(Testimonial $testimonial)
    {
        return view('admin.testimonials.edit', compact('testimonial'));
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $testimonial->update($this->validated($request));
        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial updated.');
    }

    public function destroy(Testimonial $testimonial)
    {
        $testimonial->delete();
        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial deleted.');
    }

    public function toggle(Testimonial $testimonial)
    {
        $testimonial->update(['is_active' => !$testimonial->is_active]);
        return back()->with('success', $testimonial->is_active ? 'Testimonial enabled.' : 'Testimonial disabled.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'quote'        => ['required', 'string', 'max:600'],
            'author_name'  => ['required', 'string', 'max:120'],
            'author_role'  => ['nullable', 'string', 'max:160'],
            'accent_color' => ['nullable', 'string', 'max:16', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'rating'       => ['nullable', 'integer', 'min:1', 'max:5'],
            'row'          => ['required', 'in:top,bottom'],
            'is_active'    => ['nullable', 'boolean'],
            'sort_order'   => ['nullable', 'integer', 'min:0', 'max:99999'],
        ]);
        $data['accent_color'] = $data['accent_color'] ?? '#7c3aed';
        $data['rating']       = $data['rating']       ?? 5;
        $data['is_active']    = (bool) ($request->input('is_active', false));
        $data['sort_order']   = $data['sort_order']   ?? 0;
        return $data;
    }
}
