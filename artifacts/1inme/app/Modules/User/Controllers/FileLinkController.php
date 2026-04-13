<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\FileLink;
use Illuminate\Http\Request;

class FileLinkController extends Controller
{
    public function create(Request $request)
    {
        $projects = $request->user()->projects()->orderBy('name')->get();
        $maxFileSizeMb = (int) $request->user()->getPlanFeature('max_file_size_mb', 5);

        return view('user.links.create-file', compact('projects', 'maxFileSizeMb'));
    }

    public function store(Request $request)
    {
        $maxFileSizeMb = (int) $request->user()->getPlanFeature('max_file_size_mb', 5);
        $maxFileSizeKb = $maxFileSizeMb * 1024;

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'alias' => 'nullable|string|max:50|unique:links,alias|alpha_dash',
            'project_id' => ['nullable', 'exists:projects,id', function ($attribute, $value, $fail) use ($request) {
                if ($value && !\App\Modules\User\Models\Project::where('id', $value)->where('user_id', $request->user()->id)->exists()) {
                    $fail('The selected project does not belong to you.');
                }
            }],
            'file' => "required|file|max:{$maxFileSizeKb}",
            'expires_at' => 'nullable|date|after:now',
            'show_download_page' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
        $storedPath = $file->store('file-links', $disk);

        $alias = $validated['alias'] ?: Link::generateAlias();

        $link = Link::create([
            'user_id' => $request->user()->id,
            'type' => 'file',
            'alias' => $alias,
            'title' => $validated['title'] ?: $file->getClientOriginalName(),
            'project_id' => $validated['project_id'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => true,
        ]);

        FileLink::create([
            'link_id' => $link->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $storedPath,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'disk' => $disk,
            'show_download_page' => $request->boolean('show_download_page', true),
        ]);

        return redirect()->route('user.links.show', $link)
            ->with('success', 'File link created successfully.');
    }
}
