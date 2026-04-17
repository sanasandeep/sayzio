<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\IcsData;
use Illuminate\Http\Request;

class IcsLinkController extends Controller
{
    public function create(Request $request)
    {
        $projects = $request->user()->projects()->orderBy('name')->get();

        $prefillAlias = (string) $request->query('alias', '');
        $aliasLimits  = $request->user()->getAliasLengthLimits();
        return view('user.links.create-ics', compact('projects', 'prefillAlias', 'aliasLimits'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'alias' => array_merge(
                ['nullable', 'string', 'alpha_dash', 'unique:links,alias'],
                ['min:' . $request->user()->getAliasLengthLimits()['min']],
                ['max:' . $request->user()->getAliasLengthLimits()['max']],
            ),
            'project_id' => ['nullable', 'exists:projects,id', function ($attribute, $value, $fail) use ($request) {
                if ($value && !\App\Modules\User\Models\Project::where('id', $value)->where('user_id', $request->user()->id)->exists()) {
                    $fail('The selected project does not belong to you.');
                }
            }],
            'event_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'location' => 'nullable|string|max:500',
            'organizer' => 'nullable|string|max:255',
            'organizer_email' => 'nullable|email|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'timezone' => 'required|string|max:100',
            'url' => 'nullable|url|max:2048',
        ]);

        $alias = $validated['alias'] ?: Link::generateAlias();

        $link = Link::create([
            'user_id' => $request->user()->id,
            'type' => 'ics',
            'alias' => $alias,
            'title' => $validated['event_name'],
            'project_id' => $validated['project_id'] ?? null,
            'is_active' => true,
            'settings' => $request->boolean('show_preview_page') ? ['show_preview_page' => true] : null,
        ]);

        IcsData::create([
            'link_id' => $link->id,
            'event_name' => $validated['event_name'],
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'organizer' => $validated['organizer'] ?? null,
            'organizer_email' => $validated['organizer_email'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'timezone' => $validated['timezone'],
            'url' => $validated['url'] ?? null,
        ]);

        return redirect()->route('user.links.show', $link)
            ->with('success', 'Event Invite created successfully.');
    }
}
