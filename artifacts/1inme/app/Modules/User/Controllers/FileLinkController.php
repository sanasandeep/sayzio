<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\FileLink;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\Request;

class FileLinkController extends Controller
{
    public function create(Request $request)
    {
        $projects = workspace_owner()->projects()->orderBy('name')->get();
        $maxFileSizeMb = (int) workspace_owner()->getPlanFeature('max_file_size_mb', 5);

        $prefillAlias = (string) $request->query('alias', '');
        $aliasLimits  = workspace_owner()->getAliasLengthLimits();
        return view('user.links.create-file', compact('projects', 'maxFileSizeMb', 'prefillAlias', 'aliasLimits'));
    }

    public function store(Request $request)
    {
        $maxFileSizeMb = (int) workspace_owner()->getPlanFeature('max_file_size_mb', 5);
        $maxFileSizeKb = $maxFileSizeMb * 1024;

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'alias' => array_merge(
                ['nullable', 'string', 'alpha_dash', 'unique:links,alias'],
                ['min:' . workspace_owner()->getAliasLengthLimits()['min']],
                ['max:' . workspace_owner()->getAliasLengthLimits()['max']],
                [new \App\Modules\Admin\Rules\NotBannedName()],
            ),
            'project_id' => ['nullable', 'exists:projects,id', function ($attribute, $value, $fail) use ($request) {
                if ($value && !\App\Modules\User\Models\Project::where('id', $value)->where('user_id', workspace_owner_id())->exists()) {
                    $fail('The selected project does not belong to you.');
                }
            }],
            'file' => "required|file|max:{$maxFileSizeKb}",
            'expires_at' => 'nullable|date|after:now',
            'show_download_page' => 'nullable|boolean',
        ]);

        $file = $request->file('file');

        // Route uploads through the central Vault so storage quota and
        // per-plan size limits are enforced. File Share intentionally
        // accepts arbitrary file types, so we disable the mime/ext
        // allowlist here.
        try {
            $userFile = UserFile::createFromUpload($file, $request->user(), [
                'enforce_allowlist' => false,
                'upload_key'        => 'link.file_share',
            ]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $alias = $validated['alias'] ?: Link::generateAlias();

        $link = Link::create([
            'user_id' => workspace_owner_id(),
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
            'stored_path' => $userFile->path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'disk' => $userFile->disk,
            'show_download_page' => $request->boolean('show_download_page', true),
        ]);

        return redirect()->route('user.links.show', $link)
            ->with('success', 'File Share created successfully.');
    }
}
