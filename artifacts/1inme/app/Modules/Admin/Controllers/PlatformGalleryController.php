<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Support\PlatformAssetCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Admin manager for the curated platform image galleries — the five
 * S3 folders under `assets/` (biolink backgrounds, grid images,
 * hand-drawn stickers, people/stock avatars) that power the user-side
 * pickers via PlatformAssetCatalog.
 *
 * Lets the platform owner upload, rename and delete gallery files
 * without AWS console access. Every mutation busts the 12-minute
 * catalog cache so user pickers refresh immediately.
 *
 * Tolerant by design: when the S3 disk is unreachable the page still
 * renders (empty listings + a warning banner), and mutation failures
 * come back as flash errors, never 500s.
 */
class PlatformGalleryController extends Controller
{
    public function index(Request $request)
    {
        $folders = array_keys(PlatformAssetCatalog::FOLDERS);
        $current = (string) $request->get('folder', $folders[0]);
        if (!PlatformAssetCatalog::isFolder($current)) {
            $current = $folders[0];
        }

        // Cheap reachability probe for the warning banner. Listings come
        // from the shared catalog cache — mutations bust it, so admins
        // always see their own changes immediately without this read
        // path defeating the cache.
        $storageOk = true;
        try {
            Storage::disk('s3')->files(PlatformAssetCatalog::FOLDERS[$current]);
        } catch (\Throwable $e) {
            $storageOk = false;
        }

        $assets = PlatformAssetCatalog::list($current);

        $counts = [];
        foreach ($folders as $folder) {
            $counts[$folder] = $folder === $current
                ? count($assets)
                : count(PlatformAssetCatalog::list($folder));
        }

        return view('admin.platform-gallery.index', [
            'folders'   => $folders,
            'current'   => $current,
            'assets'    => $assets,
            'counts'    => $counts,
            'storageOk' => $storageOk,
        ]);
    }

    public function upload(Request $request, string $folder)
    {
        if (!PlatformAssetCatalog::isFolder($folder)) {
            abort(404);
        }

        $request->validate([
            'files'   => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,svg'],
        ], [], ['files' => 'images', 'files.*' => 'image']);

        $prefix   = PlatformAssetCatalog::FOLDERS[$folder] . '/';
        $uploaded = 0;
        $skipped  = [];

        foreach ($request->file('files', []) as $file) {
            $name = $this->sanitizeFilename($file->getClientOriginalName());
            if ($name === null) {
                $skipped[] = $file->getClientOriginalName() ?: '(unnamed)';
                continue;
            }

            try {
                $key = $prefix . $this->uniqueName($prefix, $name);
                // No 'visibility' option: the bucket may have ACLs off
                // (public via bucket policy / CloudFront), and passing a
                // visibility would fail those buckets.
                Storage::disk('s3')->putFileAs(rtrim($prefix, '/'), $file, basename($key));
                $uploaded++;
            } catch (\Throwable $e) {
                Log::warning('PlatformGallery: upload failed', [
                    'folder' => $folder, 'name' => $name, 'error' => $e->getMessage(),
                ]);
                $skipped[] = $name;
            }
        }

        PlatformAssetCatalog::bustCache($folder);

        if ($uploaded === 0) {
            return back()->with('error', 'No files were uploaded.' . ($skipped ? ' Skipped: ' . implode(', ', $skipped) : ''));
        }

        $msg = $uploaded . ' ' . ($uploaded === 1 ? 'file' : 'files') . ' uploaded to ' . PlatformAssetCatalog::folderLabel($folder) . '.';
        if ($skipped) {
            $msg .= ' Skipped: ' . implode(', ', $skipped) . '.';
        }

        return back()->with('success', $msg);
    }

    public function rename(Request $request, string $folder)
    {
        if (!PlatformAssetCatalog::isFolder($folder)) {
            abort(404);
        }

        $request->validate([
            'key'      => ['required', 'string', 'max:512'],
            'new_name' => ['required', 'string', 'max:200'],
        ]);

        $key = (string) $request->input('key');
        if (!PlatformAssetCatalog::isValidKey($folder, $key)) {
            return back()->with('error', 'Unknown file.');
        }

        // Keep the original extension; the admin only edits the basename.
        $ext     = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $rawName = trim((string) $request->input('new_name'));
        $rawName = preg_replace('/\.[A-Za-z0-9]+$/', '', $rawName) ?? $rawName; // drop any typed extension
        $newName = $this->sanitizeFilename($rawName . '.' . $ext);
        if ($newName === null) {
            return back()->with('error', 'That name contains unsupported characters.');
        }

        $prefix = PlatformAssetCatalog::FOLDERS[$folder] . '/';
        $newKey = $prefix . $newName;
        if ($newKey === $key) {
            return back()->with('success', 'Name unchanged.');
        }

        try {
            $disk = Storage::disk('s3');
            if ($disk->exists($newKey)) {
                return back()->with('error', 'A file named "' . $newName . '" already exists in this folder.');
            }
            $disk->move($key, $newKey);

            // Hand-drawn PNG+SVG pairs share a basename: move the sibling
            // variant too so the pair stays grouped.
            $siblingExt = $ext === 'svg' ? 'png' : 'svg';
            $oldSibling = $prefix . pathinfo($key, PATHINFO_FILENAME) . '.' . $siblingExt;
            $newSibling = $prefix . pathinfo($newName, PATHINFO_FILENAME) . '.' . $siblingExt;
            if ($disk->exists($oldSibling) && !$disk->exists($newSibling)) {
                $disk->move($oldSibling, $newSibling);
            }
        } catch (\Throwable $e) {
            Log::warning('PlatformGallery: rename failed', [
                'folder' => $folder, 'key' => $key, 'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Rename failed — storage error.');
        }

        PlatformAssetCatalog::bustCache($folder);

        return back()->with('success', 'Renamed to "' . $newName . '".');
    }

    public function destroy(Request $request, string $folder)
    {
        if (!PlatformAssetCatalog::isFolder($folder)) {
            abort(404);
        }

        $request->validate(['key' => ['required', 'string', 'max:512']]);

        $key = (string) $request->input('key');
        if (!PlatformAssetCatalog::isValidKey($folder, $key)) {
            return back()->with('error', 'Unknown file.');
        }

        try {
            $disk = Storage::disk('s3');
            $disk->delete($key);

            // Delete the paired variant (hand-drawn PNG+SVG) as well so
            // no orphan half remains in the listing.
            $prefix     = PlatformAssetCatalog::FOLDERS[$folder] . '/';
            $ext        = strtolower(pathinfo($key, PATHINFO_EXTENSION));
            $siblingExt = $ext === 'svg' ? 'png' : 'svg';
            $sibling    = $prefix . pathinfo($key, PATHINFO_FILENAME) . '.' . $siblingExt;
            if ($disk->exists($sibling)) {
                $disk->delete($sibling);
            }
        } catch (\Throwable $e) {
            Log::warning('PlatformGallery: delete failed', [
                'folder' => $folder, 'key' => $key, 'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Delete failed — storage error.');
        }

        PlatformAssetCatalog::bustCache($folder);

        return back()->with('success', basename($key) . ' deleted.');
    }

    /**
     * Normalize an uploaded/typed filename into the catalog's accepted
     * shape (safe characters, lowercase extension). Returns null when
     * nothing usable remains.
     */
    private function sanitizeFilename(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = pathinfo($name, PATHINFO_FILENAME);

        // Replace anything outside the accepted character set with '-'.
        $base = preg_replace('/[^A-Za-z0-9 _().,&\'!\[\]-]+/u', '-', $base) ?? '';
        $base = trim(preg_replace('/-{2,}/', '-', $base) ?? '', ' -.');
        if ($base === '' || $ext === '') {
            return null;
        }

        $candidate = $base . '.' . $ext;

        return PlatformAssetCatalog::isValidFilename($candidate) ? $candidate : null;
    }

    /** Suffix "-2", "-3", … until the name is free under the prefix. */
    private function uniqueName(string $prefix, string $name): string
    {
        $disk = Storage::disk('s3');
        if (!$disk->exists($prefix . $name)) {
            return $name;
        }

        $ext  = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);
        for ($i = 2; $i <= 100; $i++) {
            $candidate = $base . '-' . $i . '.' . $ext;
            if (!$disk->exists($prefix . $candidate)) {
                return $candidate;
            }
        }

        return $base . '-' . time() . '.' . $ext;
    }
}
