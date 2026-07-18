<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Extension-specific helper endpoints.
 *
 * POST /api/v1/me/files/fetch-url
 *   Fetches an image URL server-side and saves it to the user's Sayzio Files
 *   storage. Used by the browser extension's "Save image to Sayzio" context
 *   menu item, since the extension's service worker cannot do binary downloads
 *   directly with the user's Sayzio auth.
 *
 * POST /api/v1/me/links/health
 *   Checks the active/expired status of a batch of link aliases belonging to
 *   the authenticated user. Used by the extension popup's link health alerts.
 */
class ExtensionApiController extends Controller
{
    use ApiResponses;

    /**
     * Fetch an image from the given URL and store it in Sayzio Files.
     */
    public function fetchUrlAndSave(Request $request)
    {
        $data = $request->validate([
            'url'      => 'required|url|max:2048',
            'filename' => 'nullable|string|max:191',
        ]);

        $user = $request->user();

        // Enforce a per-user file-count cap as a minimal DoS guard.
        $plan = method_exists($user, 'activePlan') ? $user->activePlan() : null;
        $maxFiles = $plan?->settings['max_files'] ?? 200;
        $current  = UserFile::where('user_id', $user->id)->count();
        if ($current >= $maxFiles) {
            return $this->error('file_quota_exceeded', 'File storage quota reached. Delete some files and try again.', 422);
        }

        // Download the remote image with a tight timeout and size cap (10 MB).
        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Sayzio-Extension/0.2'])
                ->get($data['url']);
        } catch (\Exception $e) {
            return $this->error('fetch_failed', 'Could not download image: ' . $e->getMessage(), 422);
        }

        if (! $response->successful()) {
            return $this->error('fetch_failed', 'Remote server returned ' . $response->status(), 422);
        }

        $body = $response->body();
        if (strlen($body) > 10 * 1024 * 1024) {
            return $this->error('file_too_large', 'Image exceeds the 10 MB size limit.', 422);
        }

        $contentType = $response->header('Content-Type') ?: 'image/jpeg';
        $ext = match (true) {
            str_contains($contentType, 'png')  => 'png',
            str_contains($contentType, 'gif')  => 'gif',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'svg')  => 'svg',
            default                             => 'jpg',
        };

        $filename = $data['filename'] ?? null;
        if (! $filename) {
            $filename = pathinfo(parse_url($data['url'], PHP_URL_PATH) ?? '', PATHINFO_FILENAME) ?: 'image';
            $filename = Str::slug($filename) . '.' . $ext;
        }

        $storagePath = "users/{$user->id}/files/" . Str::uuid() . '/' . $filename;

        try {
            $disk = config('filesystems.default', 'local');
            Storage::disk($disk)->put($storagePath, $body);
        } catch (\Exception $e) {
            return $this->error('storage_failed', 'Could not save file: ' . $e->getMessage(), 500);
        }

        $file = UserFile::create([
            'user_id'      => $user->id,
            'filename'     => $filename,
            'mime_type'    => $contentType,
            'size'         => strlen($body),
            'storage_path' => $storagePath,
            'disk'         => config('filesystems.default', 'local'),
            'source'       => 'extension',
        ]);

        return $this->ok([
            'file' => [
                'id'       => $file->id,
                'name'     => $file->filename,
                'url'      => Storage::disk($file->disk)->url($storagePath),
                'size'     => $file->size,
                'mime'     => $file->mime_type,
            ],
        ]);
    }

    /**
     * Batch-check alias health for a list of short-link aliases owned by
     * the authenticated user. Returns is_active / is_expired per alias.
     */
    public function checkLinksHealth(Request $request)
    {
        $data = $request->validate([
            'aliases'   => 'required|array|min:1|max:50',
            'aliases.*' => 'string|max:191',
        ]);

        $user = $request->user();

        $links = Link::where('user_id', $user->id)
            ->whereIn('alias', $data['aliases'])
            ->get(['alias', 'is_active', 'expires_at']);

        $found = $links->keyBy('alias');

        $items = array_map(function (string $alias) use ($found) {
            /** @var Link|null $link */
            $link = $found->get($alias);
            if (! $link) {
                return [
                    'alias'      => $alias,
                    'exists'     => false,
                    'is_active'  => false,
                    'is_expired' => false,
                    'status'     => 'not_found',
                ];
            }
            $isExpired = $link->expires_at && $link->expires_at->isPast();
            $status    = ! $link->is_active ? 'inactive' : ($isExpired ? 'expired' : 'ok');
            return [
                'alias'      => $alias,
                'exists'     => true,
                'is_active'  => (bool) $link->is_active,
                'is_expired' => $isExpired,
                'status'     => $status,
            ];
        }, $data['aliases']);

        return $this->ok(['items' => $items]);
    }
}
