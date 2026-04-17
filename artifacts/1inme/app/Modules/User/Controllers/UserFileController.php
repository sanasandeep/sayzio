<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserFileController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $type = $request->get('type', 'all');

        $query = $user->files()->orderByDesc('created_at');
        if ($type !== 'all' && in_array($type, ['image', 'video', 'audio', 'document'])) {
            $query->where('type', $type);
        }

        $files = $query->paginate(24);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'files' => $files->items(),
                'pagination' => [
                    'current_page' => $files->currentPage(),
                    'last_page' => $files->lastPage(),
                    'total' => $files->total(),
                ],
                'quota' => $this->getQuotaInfo($user),
            ]);
        }

        return view('user.files.index', [
            'quota' => $this->getQuotaInfo($user),
        ]);
    }

    public function upload(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            $userFile = UserFile::createFromUpload($request->file('file'), $user);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'file' => $userFile,
            'quota' => $this->getQuotaInfo($user),
        ]);
    }

    public function serve(Request $request, $id, $filename)
    {
        $file = UserFile::findOrFail($id);

        if ($file->filename !== $filename) {
            abort(404);
        }

        $user = $request->user();
        $ownerId = (int) $file->user_id;

        $authorized = false;

        if ($user && (int) $user->id === $ownerId) {
            $authorized = true;
        }

        if (!$authorized && $user) {
            $owner = \App\Modules\User\Models\User::find($ownerId);
            if ($owner && method_exists($owner, 'teams') && method_exists($user, 'teams')) {
                $ownerTeamIds = $owner->teams()->pluck('teams.id')->toArray();
                $userTeamIds = $user->teams()->pluck('teams.id')->toArray();
                if (!empty(array_intersect($ownerTeamIds, $userTeamIds))) {
                    $authorized = true;
                }
            }
        }

        if (!$authorized) {
            $authorized = $this->isReferencedByPublicRecord($file);
        }

        if (!$authorized) {
            abort(403, 'Access denied.');
        }

        $disk = $file->disk;
        if ($disk === 's3') {
            return redirect(Storage::disk('s3')->temporaryUrl($file->path, now()->addMinutes(30)));
        }

        $storageDisk = $disk === 'public' ? 'public' : 'user_files';
        $fullPath = Storage::disk($storageDisk)->path($file->path);

        if (!file_exists($fullPath)) {
            abort(404, 'File not found.');
        }

        $headers = [
            'Content-Type' => $file->mime_type,
            'Content-Disposition' => 'inline; filename="' . addslashes($file->original_name) . '"',
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return response()->file($fullPath, $headers);
    }

    public function destroy(Request $request, UserFile $file)
    {
        $user = $request->user();
        if ((int) $file->user_id !== (int) $user->id) {
            return response()->json(['success' => false, 'error' => 'Unauthorized.'], 403);
        }

        $file->deleteFile();

        return response()->json([
            'success' => true,
            'quota' => $this->getQuotaInfo($user->fresh()),
        ]);
    }

    public function quota(Request $request)
    {
        return response()->json([
            'success' => true,
            'quota' => $this->getQuotaInfo($request->user()),
        ]);
    }

    /**
     * A vault file is publicly viewable when its URL is referenced from any
     * record that is itself rendered publicly: an active link, a splash page
     * attached to an active link, an approved verification request, an active
     * form (design assets) or a form submission stored on an active form.
     *
     * We match by the canonical /f/{id}/{filename} url_path so renames don't
     * accidentally widen the match.
     */
    private function isReferencedByPublicRecord(UserFile $file): bool
    {
        $needle = $file->url_path;
        $like   = '%' . $needle . '%';
        $db     = \Illuminate\Support\Facades\DB::class;

        // Biolink blocks on an active link
        if (\Illuminate\Support\Facades\DB::table('biolink_blocks')
            ->join('links', 'biolink_blocks.link_id', '=', 'links.id')
            ->where('links.is_active', true)
            ->where('biolink_blocks.settings', 'like', $like)
            ->exists()) {
            return true;
        }

        // File Share — vault file referenced from file_links.stored_path on an active link
        if (\Illuminate\Support\Facades\DB::table('file_links')
            ->join('links', 'file_links.link_id', '=', 'links.id')
            ->where('links.is_active', true)
            ->where(function ($q) use ($file) {
                $q->where('file_links.stored_path', $file->path)
                  ->orWhere('file_links.stored_path', $file->url_path);
            })
            ->exists()) {
            return true;
        }

        // Link assets (seo_image, favicon, verified_logo) on an active link
        if (\Illuminate\Support\Facades\DB::table('links')
            ->where('is_active', true)
            ->where(function ($q) use ($like) {
                $q->where('seo_image', 'like', $like)
                  ->orWhere('favicon', 'like', $like)
                  ->orWhere('verified_logo', 'like', $like);
            })
            ->exists()) {
            return true;
        }

        // Splash page assets attached to an active link
        if (\Illuminate\Support\Facades\Schema::hasTable('splash_pages')) {
            $exists = \Illuminate\Support\Facades\DB::table('splash_pages')
                ->join('links', 'links.splash_page_id', '=', 'splash_pages.id')
                ->where('links.is_active', true)
                ->where(function ($q) use ($like) {
                    $q->where('splash_pages.logo', 'like', $like)
                      ->orWhere('splash_pages.favicon', 'like', $like)
                      ->orWhere('splash_pages.og_image', 'like', $like);
                })
                ->exists();
            if ($exists) return true;
        }

        // Approved verification requests (logo + proof_files JSON)
        if (\Illuminate\Support\Facades\Schema::hasTable('verification_requests')) {
            $exists = \Illuminate\Support\Facades\DB::table('verification_requests')
                ->where('status', 'approved')
                ->where(function ($q) use ($needle, $like) {
                    $q->where('logo_path', 'like', $like)
                      ->orWhere('proof_files', 'like', $like);
                })
                ->exists();
            if ($exists) return true;
        }

        // Active forms (design assets in JSON)
        if (\Illuminate\Support\Facades\Schema::hasTable('forms')) {
            $exists = \Illuminate\Support\Facades\DB::table('forms')
                ->where('is_active', true)
                ->where('design', 'like', $like)
                ->exists();
            if ($exists) return true;
        }

        // Submission attachments — only authorize anonymous serve when the
        // submission belongs to a form whose OWNER also owns the file. This
        // prevents a leaked vault URL from a different user being served just
        // because someone pasted it into a submission body somewhere.
        if (\Illuminate\Support\Facades\Schema::hasTable('form_submissions')
            && \Illuminate\Support\Facades\Schema::hasTable('forms')) {
            $exists = \Illuminate\Support\Facades\DB::table('form_submissions')
                ->join('forms', 'form_submissions.form_id', '=', 'forms.id')
                ->where('forms.user_id', $file->user_id)
                ->where('forms.is_active', true)
                ->where('form_submissions.data', 'like', $like)
                ->exists();
            if ($exists) return true;
        }

        return false;
    }

    private function getQuotaInfo($user): array
    {
        $usedBytes = $user->getStorageUsedBytes();
        $limitBytes = $user->getStorageLimitBytes();
        $maxFileMb = (int) $user->getPlanFeature('max_file_size_mb', 5);

        return [
            'used_bytes' => $usedBytes,
            'limit_bytes' => $limitBytes,
            'used_mb' => round($usedBytes / 1048576, 1),
            'limit_mb' => round($limitBytes / 1048576),
            'percent' => $limitBytes > 0 ? round(($usedBytes / $limitBytes) * 100, 1) : 0,
            'max_file_size_mb' => $maxFileMb,
            'file_count' => $user->files()->count(),
        ];
    }
}
