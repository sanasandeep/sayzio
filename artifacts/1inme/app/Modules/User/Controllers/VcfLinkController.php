<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\VcfData;
use Illuminate\Http\Request;

class VcfLinkController extends Controller
{
    public function create(Request $request)
    {
        $projects = $request->user()->projects()->orderBy('name')->get();

        return view('user.links.create-vcf', compact('projects'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'alias' => 'nullable|string|max:50|unique:links,alias|alpha_dash',
            'project_id' => ['nullable', 'exists:projects,id', function ($attribute, $value, $fail) use ($request) {
                if ($value && !\App\Modules\User\Models\Project::where('id', $value)->where('user_id', $request->user()->id)->exists()) {
                    $fail('The selected project does not belong to you.');
                }
            }],
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:30',
            'phone_work' => 'nullable|string|max:30',
            'website' => 'nullable|url|max:2048',
            'street' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:2000',
        ]);

        $alias = $validated['alias'] ?: Link::generateAlias();
        $linkTitle = $validated['first_name'] . ' ' . ($validated['last_name'] ?? '');

        $link = Link::create([
            'user_id' => $request->user()->id,
            'type' => 'vcf',
            'alias' => $alias,
            'title' => trim($linkTitle),
            'project_id' => $validated['project_id'] ?? null,
            'is_active' => true,
        ]);

        unset($validated['alias'], $validated['project_id']);
        $validated['link_id'] = $link->id;

        VcfData::create($validated);

        return redirect()->route('user.links.show', $link)
            ->with('success', 'VCF contact link created successfully.');
    }
}
