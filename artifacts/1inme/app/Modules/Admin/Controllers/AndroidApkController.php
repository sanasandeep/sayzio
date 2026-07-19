<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AndroidApkRelease;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Admin surface for managing Android APK releases hosted on our own storage.
 *
 * Admins can:
 *  - Upload an APK file directly via multipart upload.
 *  - Pull an APK from a remote URL (e.g. an EAS signed artifact URL) — the
 *    server streams it in, stores it, and records the metadata.
 *  - Set any stored release as the live one.
 *  - Delete old releases.
 *
 * The live release is served to the public by AndroidApkPublicController.
 */
class AndroidApkController extends Controller
{
    private const MAX_FETCH_BYTES = 300 * 1024 * 1024; // 300 MB cap for remote fetch

    public function index()
    {
        $releases = AndroidApkRelease::orderByDesc('created_at')->paginate(20);
        $live     = AndroidApkRelease::live();
        $diskName = AndroidApkRelease::diskName();
        $isS3     = config("filesystems.disks.{$diskName}.driver") === 's3';

        return view('admin.android-apk.index', compact('releases', 'live', 'diskName', 'isS3'));
    }

    /**
     * Upload an APK file from the admin's local machine.
     */
    public function upload(Request $request)
    {
        $data = $request->validate([
            'apk_file'     => ['required', 'file', 'max:307200'], // 300 MB in KB
            'version_name' => ['required', 'string', 'max:64'],
            'build_number' => ['nullable', 'string', 'max:64'],
            'notes'        => ['nullable', 'string', 'max:2000'],
            'set_live'     => ['nullable', 'boolean'],
        ]);

        $file   = $request->file('apk_file');
        $disk   = AndroidApkRelease::diskName();
        $path   = 'apk/sayzio-' . now()->format('Ymd-His') . '-' . Str::random(6) . '.apk';

        Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()), 'private');

        $release = AndroidApkRelease::create([
            'version_name'          => trim($data['version_name']),
            'build_number'          => $data['build_number'] ?? null,
            'file_size_bytes'       => $file->getSize(),
            'disk'                  => $disk,
            'path'                  => $path,
            'notes'                 => $data['notes'] ?? null,
            'published_by_admin_id' => optional($request->user('admin') ?: $request->user())->id,
            'is_live'               => false,
        ]);

        if ($request->boolean('set_live')) {
            $release->setAsLive();
        }

        return redirect()->route('admin.android-apk.index')
            ->with('success', "APK v{$release->version_name} uploaded successfully." .
                ($release->is_live ? ' It is now live.' : ''));
    }

    /**
     * Fetch an APK from a remote URL (e.g. EAS signed artifact URL) server-side.
     * The file is streamed into storage so no temp giant file sits on disk.
     */
    public function fetch(Request $request)
    {
        $data = $request->validate([
            'source_url'   => ['required', 'url', 'max:2000'],
            'version_name' => ['required', 'string', 'max:64'],
            'build_number' => ['nullable', 'string', 'max:64'],
            'eas_build_id' => ['nullable', 'string', 'max:128'],
            'notes'        => ['nullable', 'string', 'max:2000'],
            'set_live'     => ['nullable', 'boolean'],
        ]);

        $sourceUrl = $data['source_url'];

        try {
            $response = Http::timeout(120)
                ->withOptions(['stream' => true])
                ->get($sourceUrl);

            if (!$response->successful()) {
                return back()->withInput()
                    ->with('error', "Remote fetch failed: HTTP {$response->status()} from the source URL.");
            }

            $contentType = $response->header('Content-Type') ?? '';
            if (!empty($contentType) && !str_contains($contentType, 'octet-stream')
                && !str_contains($contentType, 'vnd.android.package-archive')
                && !str_contains($contentType, 'zip')) {
                Log::warning('android-apk.fetch: unexpected content-type', [
                    'url'          => $sourceUrl,
                    'content_type' => $contentType,
                ]);
            }

            $body      = $response->body();
            $sizeBytes = strlen($body);

            if ($sizeBytes > self::MAX_FETCH_BYTES) {
                return back()->withInput()
                    ->with('error', "Remote file is too large (" . AndroidApkRelease::humanBytes($sizeBytes) . " > 300 MB limit).");
            }

            if ($sizeBytes < 1024) {
                return back()->withInput()
                    ->with('error', "Remote file appears empty or too small — fetch may have failed.");
            }

            $disk = AndroidApkRelease::diskName();
            $path = 'apk/sayzio-' . now()->format('Ymd-His') . '-' . Str::random(6) . '.apk';

            Storage::disk($disk)->put($path, $body, 'private');

            $release = AndroidApkRelease::create([
                'version_name'          => trim($data['version_name']),
                'build_number'          => $data['build_number'] ?? null,
                'file_size_bytes'       => $sizeBytes,
                'disk'                  => $disk,
                'path'                  => $path,
                'eas_build_id'          => $data['eas_build_id'] ?? null,
                'source_url'            => $sourceUrl,
                'notes'                 => $data['notes'] ?? null,
                'published_by_admin_id' => optional($request->user('admin') ?: $request->user())->id,
                'is_live'               => false,
            ]);

            if ($request->boolean('set_live')) {
                $release->setAsLive();
            }

            return redirect()->route('admin.android-apk.index')
                ->with('success', "APK v{$release->version_name} fetched and stored successfully." .
                    ($release->is_live ? ' It is now live.' : ''));

        } catch (\Throwable $e) {
            Log::error('android-apk.fetch.error', [
                'url'   => $sourceUrl,
                'error' => $e->getMessage(),
            ]);
            return back()->withInput()
                ->with('error', 'Fetch failed: ' . $e->getMessage());
        }
    }

    /**
     * Mark a release as the single live/published APK.
     */
    public function setLive(Request $request, AndroidApkRelease $release)
    {
        $release->setAsLive();

        return redirect()->route('admin.android-apk.index')
            ->with('success', "APK v{$release->version_name} is now the live download.");
    }

    /**
     * Delete a release record and its stored file.
     */
    public function destroy(Request $request, AndroidApkRelease $release)
    {
        if ($release->is_live) {
            return redirect()->route('admin.android-apk.index')
                ->with('error', 'Cannot delete the currently live APK. Set another release as live first.');
        }

        try {
            Storage::disk($release->disk)->delete($release->path);
        } catch (\Throwable $e) {
            Log::warning('android-apk.destroy: storage delete failed', [
                'id'    => $release->id,
                'error' => $e->getMessage(),
            ]);
        }

        $release->delete();

        return redirect()->route('admin.android-apk.index')
            ->with('success', "APK v{$release->version_name} deleted.");
    }
}
