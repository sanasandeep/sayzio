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

        $file = $request->file('file');
        $mime = $file->getMimeType();
        $ext = strtolower($file->getClientOriginalExtension());
        $sizeBytes = $file->getSize();

        $allowedMimes = UserFile::getAllAllowedMimes();
        if (!in_array($mime, $allowedMimes)) {
            return response()->json(['success' => false, 'error' => 'File type not allowed.'], 422);
        }

        $allowedExts = UserFile::getAllAllowedExtensions();
        if (!in_array($ext, $allowedExts)) {
            return response()->json(['success' => false, 'error' => 'File extension not allowed.'], 422);
        }

        $maxFileSizeMb = (int) $user->getPlanFeature('max_file_size_mb', 5);
        $maxFileBytes = $maxFileSizeMb * 1048576;
        if ($sizeBytes > $maxFileBytes) {
            return response()->json([
                'success' => false,
                'error' => "File exceeds maximum size of {$maxFileSizeMb}MB.",
            ], 422);
        }

        $remaining = $user->getStorageRemainingBytes();
        if ($sizeBytes > $remaining) {
            $usedMb = round($user->getStorageUsedBytes() / 1048576, 1);
            $limitMb = round($user->getStorageLimitBytes() / 1048576);
            return response()->json([
                'success' => false,
                'error' => "Storage quota exceeded. Used {$usedMb}MB of {$limitMb}MB.",
            ], 422);
        }

        $fileType = UserFile::detectType($mime);
        $disk = config('filesystems.default') === 's3' ? 's3' : 'user_files';
        $folder = "{$user->id}/{$fileType}s";
        $filename = Str::uuid() . '.' . $ext;

        $storedPath = $file->storeAs($folder, $filename, $disk);

        $userFile = UserFile::create([
            'user_id' => $user->id,
            'original_name' => $file->getClientOriginalName(),
            'filename' => $filename,
            'mime_type' => $mime,
            'size_bytes' => $sizeBytes,
            'type' => $fileType,
            'disk' => $disk,
            'path' => $storedPath,
        ]);

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
            $fileUrl = $file->url_path;
            $usedInBlock = \Illuminate\Support\Facades\DB::table('biolink_blocks')
                ->where('biolink_blocks.settings', 'like', '%' . $fileUrl . '%')
                ->join('links', 'biolink_blocks.link_id', '=', 'links.id')
                ->where('links.is_active', true)
                ->exists();
            if ($usedInBlock) {
                $authorized = true;
            }
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
