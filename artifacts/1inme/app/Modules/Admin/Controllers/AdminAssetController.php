<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AdminAsset;
use App\Modules\Admin\Models\AdminAssetFolder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminAssetController extends Controller
{
    public function index(Request $request)
    {
        $type   = $request->get('type', 'all');
        $folder = trim((string) $request->get('folder', ''));
        $search = trim((string) $request->get('q', ''));

        $query = AdminAsset::query()->orderByDesc('created_at');

        if ($type !== 'all' && in_array($type, ['image', 'video', 'audio', 'document', 'archive', 'other'], true)) {
            $query->where('type', $type);
        }
        if ($folder === '__root__') {
            $query->whereNull('folder');
        } elseif ($folder !== '') {
            $query->where('folder', $folder);
        }
        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('original_name', 'like', $like)
                  ->orWhere('label', 'like', $like)
                  ->orWhere('description', 'like', $like);
            });
        }

        $assets = $query->paginate(48)->withQueryString();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success'    => true,
                'assets'     => $assets->items(),
                'pagination' => [
                    'current_page' => $assets->currentPage(),
                    'last_page'    => $assets->lastPage(),
                    'total'        => $assets->total(),
                ],
                'storage'    => $this->storageInfo(),
                'folders'    => $this->folderList(),
            ]);
        }

        return view('admin.assets.index', [
            'assets'  => $assets,
            'type'    => $type,
            'folder'  => $folder,
            'search'  => $search,
            'folders' => $this->folderList(),
            'storage' => $this->storageInfo(),
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file'      => 'required|file',
            'folder'    => 'nullable|string|max:140',
            'label'     => 'nullable|string|max:200',
            'is_public' => 'nullable|boolean',
        ]);

        $folderSlug = $this->resolveFolderSlug($request->input('folder'));

        try {
            $asset = AdminAsset::createFromUpload(
                $request->file('file'),
                $request->user('admin') ?: $request->user(),
                [
                    'folder'    => $folderSlug,
                    'label'     => $request->input('label'),
                    'is_public' => (bool) $request->input('is_public', false),
                ]
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'asset'   => $asset,
            'storage' => $this->storageInfo(),
            'folders' => $this->folderList(),
        ]);
    }

    public function update(Request $request, AdminAsset $asset)
    {
        $request->validate([
            'label'       => 'nullable|string|max:200',
            'description' => 'nullable|string|max:2000',
            'folder'      => 'nullable|string|max:140',
            'is_public'   => 'nullable|boolean',
        ]);

        $asset->update([
            'label'       => $request->input('label'),
            'description' => $request->input('description'),
            'folder'      => $this->resolveFolderSlug($request->input('folder')),
            'is_public'   => (bool) $request->input('is_public', false),
        ]);

        return response()->json(['success' => true, 'asset' => $asset->fresh()]);
    }

    public function move(Request $request, AdminAsset $asset)
    {
        $request->validate([
            'folder' => 'nullable|string|max:140',
        ]);
        $asset->update(['folder' => $this->resolveFolderSlug($request->input('folder'))]);
        return response()->json(['success' => true, 'asset' => $asset->fresh(), 'folders' => $this->folderList()]);
    }

    public function destroy(AdminAsset $asset)
    {
        $asset->deleteFile();
        return response()->json(['success' => true, 'storage' => $this->storageInfo(), 'folders' => $this->folderList()]);
    }

    /* ============ Folder management ============ */

    public function listFolders()
    {
        return response()->json(['success' => true, 'folders' => $this->folderList()]);
    }

    public function createFolder(Request $request)
    {
        $request->validate(['name' => 'required|string|max:120']);
        $name = trim($request->input('name'));
        $slug = Str::slug($name, '-');
        if ($slug === '') {
            return response()->json(['success' => false, 'error' => 'Folder name is invalid.'], 422);
        }
        $folder = AdminAssetFolder::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'admin_id' => optional($request->user('admin') ?: $request->user())->id]
        );
        return response()->json(['success' => true, 'folder' => $folder, 'folders' => $this->folderList()]);
    }

    public function destroyFolder(Request $request, AdminAssetFolder $folder)
    {
        $cascade = $request->boolean('cascade');
        $assetCount = AdminAsset::where('folder', $folder->slug)->count();
        if ($assetCount > 0 && !$cascade) {
            return response()->json([
                'success' => false,
                'error'   => "Folder has {$assetCount} asset(s). Pass cascade=1 to delete them too, or move them first.",
            ], 422);
        }
        if ($cascade) {
            AdminAsset::where('folder', $folder->slug)->get()->each(fn ($a) => $a->deleteFile());
        }
        $folder->delete();
        return response()->json(['success' => true, 'folders' => $this->folderList(), 'storage' => $this->storageInfo()]);
    }

    /* ============ Public serve ============ */

    public function serve(Request $request, $id, $filename)
    {
        $asset = AdminAsset::findOrFail($id);
        if ($asset->filename !== $filename) {
            abort(404);
        }

        // Private assets (is_public === false) are restricted to admins only.
        // "Admin" means an authenticated admin-guard session OR a web user
        // whose account is bridged to an active admin record — a regular
        // logged-in front-end user is NOT sufficient. Unauthorized requests
        // get a 404 (same as a filename mismatch) so the endpoint never
        // discloses that a private asset exists, blocking ID enumeration.
        if (!$asset->is_public && !$this->requesterIsAdmin($request)) {
            abort(404);
        }

        $diskName = $asset->disk ?: AdminAsset::diskName();

        // S3-backed disks (whatever the disk is named) have no local path —
        // hand the visitor a short-lived signed URL, falling back to the
        // public/CloudFront URL if signing is unavailable.
        if (config("filesystems.disks.{$diskName}.driver") === 's3') {
            try {
                return redirect(Storage::disk($diskName)->temporaryUrl($asset->path, now()->addMinutes(15)));
            } catch (\Throwable $e) {
                return redirect(Storage::disk($diskName)->url($asset->path));
            }
        }

        $disk = Storage::disk($diskName);
        if (!$disk->exists($asset->path)) {
            abort(404, 'Asset not found.');
        }

        return response()->file($disk->path($asset->path), [
            'Content-Type'           => $asset->mime_type,
            'Content-Disposition'    => 'inline; filename="' . addslashes($asset->original_name) . '"',
            'Cache-Control'          => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /* ============ Helpers ============ */

    /**
     * True when the request is made by an admin principal: either an
     * authenticated admin-guard session, or a web user whose account is
     * bridged to an active admin record. A plain logged-in front-end user
     * is intentionally NOT treated as an admin here.
     */
    private function requesterIsAdmin(Request $request): bool
    {
        if ($request->user('admin')) {
            return true;
        }
        $webUser = $request->user();
        return $webUser instanceof \App\Modules\User\Models\User
            && $webUser->hasActiveAdminAccount();
    }

    private function resolveFolderSlug($input): ?string
    {
        $input = trim((string) $input);
        if ($input === '' || $input === '__root__') return null;
        $slug = Str::slug($input, '-');
        if ($slug === '') return null;
        // Auto-register the folder so it shows up in the folder strip even if empty.
        AdminAssetFolder::firstOrCreate(['slug' => $slug], ['name' => $input]);
        return $slug;
    }

    private function folderList(): array
    {
        $folders = AdminAssetFolder::orderBy('name')->get(['id', 'name', 'slug']);
        $counts = AdminAsset::query()
            ->selectRaw('folder, count(*) as c')
            ->groupBy('folder')
            ->pluck('c', 'folder');

        $rootCount = (int) ($counts[null] ?? $counts[''] ?? 0);
        $list = [
            ['id' => null, 'slug' => '__root__', 'name' => 'Unfiled', 'count' => $rootCount, 'system' => true],
        ];
        foreach ($folders as $f) {
            $list[] = [
                'id'    => $f->id,
                'slug'  => $f->slug,
                'name'  => $f->name,
                'count' => (int) ($counts[$f->slug] ?? 0),
                'system' => false,
            ];
        }
        return $list;
    }

    private function storageInfo(): array
    {
        $disk = AdminAsset::diskName();
        $totalBytes = (int) AdminAsset::sum('size_bytes');
        $count = (int) AdminAsset::count();
        return [
            'disk'        => $disk,
            'driver'      => config("filesystems.disks.$disk.driver", 'local'),
            'total_bytes' => $totalBytes,
            'total_human' => $this->humanBytes($totalBytes),
            'file_count'  => $count,
            'is_s3'       => config("filesystems.disks.$disk.driver", 'local') === 's3',
        ];
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }
}
