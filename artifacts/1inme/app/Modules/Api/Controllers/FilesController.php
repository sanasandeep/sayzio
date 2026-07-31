<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\Request;

/**
 * Task #5956 — mobile parity for the Sayzio Files vault. The web editor
 * uploads photo stickers via the session-authed `user.files.upload` AJAX
 * route; the mobile app needs the same two primitives over Sanctum:
 * list existing image files (vault picker) and upload a new one. Both
 * return the serialized UserFile (id + url_path) that the block
 * sanitizer's `sanitizePhotoStickers` ownership check accepts.
 */
class FilesController extends Controller
{
    use ApiResponses;

    /**
     * GET /me/files — the caller's vault files (system-generated files with
     * a `context` are excluded, matching the web vault UI). Optional
     * `?type=image|video|audio|document` filter; optional `?q=` name
     * search (case-insensitive, matches original_name); paginated.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $type = (string) $request->query('type', 'all');
        $q = trim((string) $request->query('q', ''));

        $query = $user->files()->whereNull('context')->orderByDesc('created_at');
        if (in_array($type, ['image', 'video', 'audio', 'document'], true)) {
            $query->where('type', $type);
        }
        if ($q !== '') {
            $query->where('original_name', 'ilike', '%' . addcslashes($q, '%_\\') . '%');
        }

        $files = $query->paginate(min(100, max(1, (int) $request->query('per_page', 48))));

        return $this->ok([
            'files' => collect($files->items())->map(fn (UserFile $f) => $this->serializeFile($f))->all(),
            'pagination' => [
                'current_page' => $files->currentPage(),
                'last_page'    => $files->lastPage(),
                'total'        => $files->total(),
            ],
        ]);
    }

    /**
     * POST /me/files/upload — multipart upload into the vault. Reuses the
     * shared UserFile::createFromUpload pipeline (quota, mime/extension
     * allowlist, image compression), so limits match the web dropzone.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $user = $request->user();

        try {
            $userFile = UserFile::createFromUpload($request->file('file'), $user);
        } catch (\App\Modules\User\Exceptions\StorageQuotaExceededException $e) {
            // Storage quota is a plan limit, not bad input — return the
            // standard plan-gate hint envelope (402 + recommended_plan in
            // error.details) so clients can route to the upgrade screen.
            return $this->planGate(
                $e->getMessage(),
                \App\Modules\User\Exceptions\StorageQuotaExceededException::FEATURE,
                $user,
                402,
                'plan_limit_reached'
            );
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'upload_failed');
        }

        // The sanctum API path doesn't bind the active workspace, so the
        // shared createFromUpload() lands the vault file with
        // workspace_id = null. workspace_id isn't mass-assignable, so set
        // it directly (mirrors BiolinkWizardController::uploadImage).
        if ($userFile->workspace_id === null) {
            $userFile->workspace_id = $this->activeWorkspaceId($user);
            $userFile->save();
        }

        return $this->ok(['file' => $this->serializeFile($userFile)], 201);
    }

    /**
     * POST /me/files/import-platform-asset — server-side import of a
     * curated platform asset (Task #6028). The curated-asset CDN serves
     * no CORS headers, so the Expo WEB client cannot browser-fetch the
     * asset blob to re-upload it (native downloads via FileSystem and is
     * unaffected). Instead the client sends the asset's S3 `key`; the
     * server validates it against the PlatformAssetCatalog folder
     * allowlist (assets/<folder>/ prefixes only — no arbitrary URLs or
     * keys), reads the object from S3 itself, and vault-writes it via
     * the shared createFromBytes pipeline (size cap + storage quota).
     */
    public function importPlatformAsset(Request $request)
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:512'],
        ]);

        $key = $data['key'];
        $folder = \App\Modules\User\Support\PlatformAssetCatalog::folderForKey(
            $key,
            array_keys(\App\Modules\User\Support\PlatformAssetCatalog::FOLDERS)
        );
        if ($folder === null) {
            return $this->fail('Unknown platform asset.', 422, 'invalid_asset_key');
        }

        try {
            $bytes = \Illuminate\Support\Facades\Storage::disk('s3')->get($key);
        } catch (\Throwable $e) {
            $bytes = null;
        }
        if (!is_string($bytes) || $bytes === '') {
            return $this->fail('That asset is unavailable right now.', 422, 'asset_unavailable');
        }

        $name = basename($key);
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png'          => 'image/png',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
            'svg'          => 'image/svg+xml',
            default        => 'image/jpeg',
        };

        $user = $request->user();

        try {
            $userFile = UserFile::createFromBytes($bytes, $name, $mime, $user);
        } catch (\App\Modules\User\Exceptions\StorageQuotaExceededException $e) {
            return $this->planGate(
                $e->getMessage(),
                \App\Modules\User\Exceptions\StorageQuotaExceededException::FEATURE,
                $user,
                402,
                'plan_limit_reached'
            );
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422, 'import_failed');
        }

        if ($userFile->workspace_id === null) {
            $userFile->workspace_id = $this->activeWorkspaceId($user);
            $userFile->save();
        }

        return $this->ok(['file' => $this->serializeFile($userFile)], 201);
    }

    /**
     * DELETE /me/files/{file} — remove a vault file. Mirrors the web
     * vault's UserFileController::destroy (ownership check + deleteFile,
     * which removes the stored object and the row).
     */
    public function destroy(Request $request, UserFile $file)
    {
        if ((int) $file->user_id !== (int) $request->user()->id) {
            return $this->fail('File not found.', 404, 'not_found');
        }

        $file->deleteFile();

        return $this->ok(['deleted' => true]);
    }

    /**
     * Minimal client-facing shape — enough for pickers and the sticker
     * flow without leaking storage paths or scan internals.
     */
    private function serializeFile(UserFile $file): array
    {
        return [
            'id'            => (int) $file->id,
            'type'          => (string) $file->type,
            'original_name' => (string) $file->original_name,
            'mime_type'     => (string) $file->mime_type,
            'url'           => (string) $file->url,
            'url_path'      => (string) $file->url_path,
            'size_human'    => (string) $file->size_human,
            'created_at'    => optional($file->created_at)->toIso8601String(),
        ];
    }
}
