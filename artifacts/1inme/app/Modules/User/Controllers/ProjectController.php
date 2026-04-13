<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = $request->user()->projects()
            ->withCount('links')
            ->latest()
            ->paginate(12);

        return view('user.projects.index', compact('projects'));
    }

    public function create()
    {
        return view('user.projects.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'description' => 'nullable|string|max:1000',
        ]);

        $request->user()->projects()->create($validated);

        return redirect()->route('user.projects.index')
            ->with('success', 'Project created successfully.');
    }

    public function show(Request $request, Project $project)
    {
        abort_if($project->user_id !== $request->user()->id, 403);

        $links = $project->links()
            ->latest()
            ->paginate(15);

        return view('user.projects.show', compact('project', 'links'));
    }

    public function edit(Request $request, Project $project)
    {
        abort_if($project->user_id !== $request->user()->id, 403);

        return view('user.projects.edit', compact('project'));
    }

    public function update(Request $request, Project $project)
    {
        abort_if($project->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'description' => 'nullable|string|max:1000',
        ]);

        $project->update($validated);

        return redirect()->route('user.projects.index')
            ->with('success', 'Project updated successfully.');
    }

    public function destroy(Request $request, Project $project)
    {
        abort_if($project->user_id !== $request->user()->id, 403);

        $project->delete();

        return redirect()->route('user.projects.index')
            ->with('success', 'Project deleted successfully.');
    }
}
