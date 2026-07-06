<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Concerns\RespondsWithUploadErrors;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\FileLink;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\Request;

class FileLinkController extends Controller
{
    use RespondsWithUploadErrors;

    public function create(Request $request)
    {
        $projects = workspace_owner()->projects()->orderBy('name')->get();
        $maxFileSizeMb = (int) workspace_owner()->getPlanFeature('max_file_size_mb', 5);

        $prefillAlias = (string) $request->query('alias', '');
        $aliasLimits  = workspace_owner()->getAliasLengthLimits();
        // The "open in app" toggle is only meaningful when files on the
        // upload disk could actually resolve to a known app. For self-hosted
        // files (app domain / S3 bucket) this is never the case, so the toggle
        // stays hidden instead of promising behaviour it can't deliver.
        $deepLinkSupported = FileLink::diskSupportsDeepLink(self::fileShareDisk());
        return view('user.links.create-file', compact('projects', 'maxFileSizeMb', 'prefillAlias', 'aliasLimits', 'deepLinkSupported'));
    }

    /**
     * The storage disk new File Share uploads land on. Mirrors the disk
     * selection inside UserFile::createFromUpload() so the create form can
     * reason about deep-link support before the file is uploaded.
     */
    private static function fileShareDisk(): string
    {
        return UserFile::uploadDisk();
    }

    public function store(Request $request)
    {
        $maxFileSizeMb = (int) workspace_owner()->getPlanFeature('max_file_size_mb', 5);
        $maxFileSizeKb = $maxFileSizeMb * 1024;

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'alias' => array_merge(
                ['nullable', 'string', new \App\Modules\User\Rules\AliasFormat(), new \App\Modules\User\Rules\UniqueAliasCi()],
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
            'open_in_app' => 'nullable|boolean',
            'visibility' => 'nullable|in:public,registered,followers,subscribers',
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
            return $this->uploadError($request, $e->getMessage());
        }

        $alias = ($validated['alias'] ?? null) ?: Link::generateAlias();

        // Deep-link / "open in app" — same settings.open_in_app field used by
        // Short Links, plan-gated by the deep_link feature. Opt-in for files
        // (default off) since most file URLs won't match a known app.
        $settings = [];
        if ($request->boolean('open_in_app')) {
            if (!workspace_owner()->userCanUseLinkSetting('deep_link')) {
                return $this->uploadError($request, 'The "deep link" link setting isn\'t available on your current plan. Upgrade to enable it.', 403);
            }
            // Only persist the flag when files on this disk could actually
            // resolve to a known app. Otherwise it would be a silent no-op,
            // so we drop it rather than store a setting that never fires.
            if (FileLink::diskSupportsDeepLink(self::fileShareDisk())) {
                $settings['open_in_app'] = true;
            }
        }

        $link = Link::create([
            'user_id' => workspace_owner_id(),
            'type' => 'file',
            'alias' => $alias,
            'title' => ($validated['title'] ?? null) ?: $file->getClientOriginalName(),
            'project_id' => $validated['project_id'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => true,
            'visibility' => $validated['visibility'] ?? 'public',
            'settings' => $settings ?: null,
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
