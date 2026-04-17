<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AdminAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminAssetController extends Controller
{
    public function index(Request $request)
    {
        $type   = $request->get('type', 'all');
        $folder = trim((string) $request->get('folder', ''), '/');
        $search = trim((string) $request->get('q', ''));

        $query = AdminAsset::query()->orderByDesc('created_at');

        if ($type !== 'all' && in_array($type, ['image', 'video', 'audio', 'document', 'archive', 'other'], true)) {
            $query->where('type', $type);
        }
        if ($folder !== '') {
            $query->where('folder', $folder);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('original_name', 'like', $like)
                  ->orWhere('label', 'like', $like)
                  ->orWhere('description', 'like', $like);
            });
        }

        $assets = $query->paginate(48)->withQueryString();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'assets'  => $assets->items(),
                'pagination' => [
                    'current_page' => $assets->currentPage(),
                    'last_page'    => $assets->lastPage(),
                    'total'        => $assets->total(),
                ],
                'storage' => $this->storageInfo(),
            ]);
        }

        return view('admin.assets.index', [
            'assets'  => $assets,
            'type'    => $type,
            'folder'  => $folder,
            'search'  => $search,
            'folders' => AdminAsset::query()->whereNotNull('folder')->distinct()->orderBy('folder')->pluck('folder'),
            'storage' => $this->storageInfo(),
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file'     => 'required|file',
            'folder'   => 'nullable|string|max:120',
            'label'    => 'nullable|string|max:200',
            'is_public' => 'nullable|boolean',
        ]);

        try {
            $asset = AdminAsset::createFromUpload(
                $request->file('file'),
                $request->user('admin') ?: $request->user(),
                [
                    'folder'    => $request->input('folder'),
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
        ]);
    }

    public function update(Request $request, AdminAsset $asset)
    {
        $request->validate([
            'label'       => 'nullable|string|max:200',
            'description' => 'nullable|string|max:2000',
            'folder'      => 'nullable|string|max:120',
            'is_public'   => 'nullable|boolean',
        ]);

        $asset->update([
            'label'       => $request->input('label'),
            'description' => $request->input('description'),
            'folder'      => $request->filled('folder') ? \Illuminate\Support\Str::slug($request->input('folder'), '-') : null,
            'is_public'   => (bool) $request->input('is_public', false),
        ]);

        return response()->json(['success' => true, 'asset' => $asset->fresh()]);
    }

    public function destroy(AdminAsset $asset)
    {
        $asset->deleteFile();
        return response()->json(['success' => true, 'storage' => $this->storageInfo()]);
    }

    /**
     * Stream / redirect to an asset. S3-backed assets get a signed temp URL;
     * local assets are served directly. Public assets do not require admin
     * auth — gated by middleware on the route group.
     */
    public function serve(Request $request, $id, $filename)
    {
        $asset = AdminAsset::findOrFail($id);
        if ($asset->filename !== $filename) {
            abort(404);
        }

        if ($asset->disk === 's3') {
            try {
                return redirect(Storage::disk('s3')->temporaryUrl($asset->path, now()->addMinutes(15)));
            } catch (\Throwable $e) {
                return redirect(Storage::disk('s3')->url($asset->path));
            }
        }

        $diskName = $asset->disk ?: AdminAsset::diskName();
        $disk = Storage::disk($diskName);
        if (!$disk->exists($asset->path)) {
            abort(404, 'Asset not found.');
        }

        return response()->file($disk->path($asset->path), [
            'Content-Type'        => $asset->mime_type,
            'Content-Disposition' => 'inline; filename="' . addslashes($asset->original_name) . '"',
            'Cache-Control'       => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
            'is_s3'       => $disk === 's3',
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
