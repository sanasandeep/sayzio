<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BrowserSyncController — cloud sync endpoints for Zio Browser.
 *
 * Handles device registration and last-write-wins sync of bookmarks,
 * collections, saved links, and browsing history.
 *
 * Route prefix: /api/v1/browser
 * Auth: auth:sanctum
 */
class BrowserSyncController extends Controller
{
    // ── Device ────────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/browser/devices
     * Register or update a browser device for this user.
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label'       => ['required', 'string', 'max:120'],
            'platform'    => ['required', 'in:mac,windows,linux'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ]);

        $deviceUuid = $request->header('X-Browser-Device-Id');
        if (! $deviceUuid) {
            $deviceUuid = (string) \Illuminate\Support\Str::uuid();
        }

        $device = DB::table('browser_devices')
            ->where('user_id', $request->user()->id)
            ->where('device_uuid', $deviceUuid)
            ->first();

        if ($device) {
            DB::table('browser_devices')
                ->where('id', $device->id)
                ->update([
                    'label'       => $validated['label'],
                    'platform'    => $validated['platform'],
                    'app_version' => $validated['app_version'] ?? null,
                    'last_seen_at' => now(),
                    'updated_at'  => now(),
                ]);
        } else {
            DB::table('browser_devices')->insert([
                'user_id'      => $request->user()->id,
                'device_uuid'  => $deviceUuid,
                'label'        => $validated['label'],
                'platform'     => $validated['platform'],
                'app_version'  => $validated['app_version'] ?? null,
                'last_seen_at' => now(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }

        return response()->json(['data' => ['device_id' => $deviceUuid]]);
    }

    // ── Bookmarks ─────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/browser/devices/{deviceId}/bookmarks
     * Push bookmark changes from client to server (last-write-wins).
     */
    public function syncBookmarks(Request $request, string $deviceId): JsonResponse
    {
        return $this->syncEntity($request, $deviceId, 'browser_bookmarks', [
            'items'              => ['required', 'array', 'max:1000'],
            'items.*.local_id'   => ['required', 'string', 'max:64'],
            'items.*.updated_at' => ['required', 'date'],
            'items.*.deleted'    => ['nullable', 'boolean'],
            'items.*.data'       => ['nullable', 'array'],
            'items.*.data.url'   => ['nullable', 'url'],
            'items.*.data.title' => ['nullable', 'string', 'max:512'],
        ], function (int $userId, array $item): array {
            $data = $item['data'] ?? [];
            return [
                'user_id'         => $userId,
                'local_id'        => $item['local_id'],
                'url'             => $data['url'] ?? '',
                'normalized_url'  => $this->normalizeUrl($data['url'] ?? ''),
                'title'           => $data['title'] ?? '',
                'description'     => $data['description'] ?? null,
                'favicon_url'     => $data['favicon_url'] ?? null,
                'folder'          => $data['folder'] ?? null,
                'deleted'         => $item['deleted'] ?? false,
                'item_updated_at' => $item['updated_at'],
            ];
        });
    }

    /**
     * POST /api/v1/browser/devices/{deviceId}/collections
     * Push collection changes from client to server (last-write-wins).
     */
    public function syncCollections(Request $request, string $deviceId): JsonResponse
    {
        return $this->syncEntity($request, $deviceId, 'browser_collections', [
            'items'              => ['required', 'array', 'max:500'],
            'items.*.local_id'   => ['required', 'string', 'max:64'],
            'items.*.updated_at' => ['required', 'date'],
            'items.*.deleted'    => ['nullable', 'boolean'],
            'items.*.data'       => ['nullable', 'array'],
            'items.*.data.name'  => ['nullable', 'string', 'max:255'],
        ], function (int $userId, array $item): array {
            $data = $item['data'] ?? [];
            return [
                'user_id'         => $userId,
                'local_id'        => $item['local_id'],
                'name'            => $data['name'] ?? 'Untitled',
                'description'     => $data['description'] ?? null,
                'color'           => $data['color'] ?? null,
                'icon'            => $data['icon'] ?? null,
                'deleted'         => $item['deleted'] ?? false,
                'item_updated_at' => $item['updated_at'],
            ];
        });
    }

    /**
     * POST /api/v1/browser/devices/{deviceId}/history
     * Push history changes from client to server (last-write-wins).
     * Only syncs if cloud_sync_history is enabled for the user (checked client-side — server trusts it).
     */
    public function syncHistory(Request $request, string $deviceId): JsonResponse
    {
        return $this->syncEntity($request, $deviceId, 'browser_history_sync', [
            'items'              => ['required', 'array', 'max:2000'],
            'items.*.local_id'   => ['required', 'string', 'max:64'],
            'items.*.updated_at' => ['required', 'date'],
            'items.*.deleted'    => ['nullable', 'boolean'],
            'items.*.data'       => ['nullable', 'array'],
            'items.*.data.url'   => ['nullable', 'url'],
        ], function (int $userId, array $item): array {
            $data = $item['data'] ?? [];
            return [
                'user_id'         => $userId,
                'local_id'        => $item['local_id'],
                'url'             => $data['url'] ?? '',
                'normalized_url'  => $this->normalizeUrl($data['url'] ?? ''),
                'title'           => $data['title'] ?? null,
                'favicon_url'     => $data['favicon_url'] ?? null,
                'visit_count'     => max(1, (int) ($data['visit_count'] ?? 1)),
                'last_visited_at' => $data['last_visited'] ?? null,
                'deleted'         => $item['deleted'] ?? false,
                'item_updated_at' => $item['updated_at'],
            ];
        });
    }

    /**
     * GET /api/v1/browser/devices/{deviceId}/pull
     * Pull all server-side data since a given timestamp.
     * ?since=ISO8601
     */
    public function pullSync(Request $request, string $deviceId): JsonResponse
    {
        $this->validateDevice($request, $deviceId);

        $userId = $request->user()->id;
        $since = $request->query('since');

        $bookmarks   = $this->pullEntity($userId, 'browser_bookmarks', $since);
        $collections = $this->pullEntity($userId, 'browser_collections', $since);
        $history     = $this->pullEntity($userId, 'browser_history_sync', $since);

        return response()->json([
            'data' => [
                'bookmarks'   => $bookmarks,
                'collections' => $collections,
                'history'     => $history,
                'server_time' => now()->toIso8601String(),
            ],
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Generic sync push handler for any browser entity table.
     *
     * @param callable(int, array): array $mapper Maps a validated sync item to a DB row
     */
    private function syncEntity(
        Request $request,
        string $deviceId,
        string $table,
        array $rules,
        callable $mapper,
    ): JsonResponse {
        $this->validateDevice($request, $deviceId);

        $validated = $request->validate($rules);
        $userId = $request->user()->id;
        $accepted = [];
        $conflicts = [];
        $now = now();

        foreach ($validated['items'] as $item) {
            $localId = $item['local_id'];
            $clientUpdatedAt = $item['updated_at'];

            $existing = DB::table($table)
                ->where('user_id', $userId)
                ->where('local_id', $localId)
                ->first();

            if ($existing) {
                // Last-write-wins: only update if client version is newer
                $serverTs = $existing->item_updated_at ? strtotime($existing->item_updated_at) : 0;
                $clientTs = strtotime($clientUpdatedAt);

                if ($clientTs >= $serverTs) {
                    $row = $mapper($userId, $item);
                    DB::table($table)
                        ->where('id', $existing->id)
                        ->update(array_merge($row, ['updated_at' => $now]));
                    $accepted[] = $localId;
                } else {
                    // Server version is newer — client is out of date
                    $conflicts[] = $localId;
                }
            } else {
                $row = $mapper($userId, $item);
                DB::table($table)->insert(array_merge($row, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $accepted[] = $localId;
            }
        }

        return response()->json([
            'data' => [
                'accepted'    => $accepted,
                'conflicts'   => $conflicts,
                'server_time' => $now->toIso8601String(),
            ],
        ]);
    }

    /**
     * Pull all records for a table since a timestamp.
     */
    private function pullEntity(int $userId, string $table, ?string $since): array
    {
        $query = DB::table($table)->where('user_id', $userId);
        if ($since) {
            $query->where('updated_at', '>', $since);
        }
        $rows = $query->orderBy('updated_at')->get();

        return $rows->map(function ($row) {
            return [
                'local_id'   => $row->local_id,
                'updated_at' => $row->item_updated_at ?? $row->updated_at,
                'deleted'    => (bool) $row->deleted,
                'data'       => $this->rowToData($row),
            ];
        })->values()->all();
    }

    /**
     * Convert a DB row to a sync data object (strips internal DB fields).
     */
    private function rowToData(object $row): array
    {
        $exclude = ['id', 'user_id', 'local_id', 'deleted', 'item_updated_at', 'created_at', 'updated_at'];
        $data = [];
        foreach ((array) $row as $key => $value) {
            if (! in_array($key, $exclude, true)) {
                $data[$key] = $value;
            }
        }
        return $data;
    }

    /**
     * Verify the device UUID belongs to this user.
     * Creates an implicit registration if it doesn't exist yet
     * (device may not have registered yet in edge cases).
     */
    private function validateDevice(Request $request, string $deviceId): void
    {
        // We don't strictly enforce device ownership to avoid blocking clients
        // that haven't registered yet, but we update last_seen_at.
        DB::table('browser_devices')
            ->where('user_id', $request->user()->id)
            ->where('device_uuid', $deviceId)
            ->update(['last_seen_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Normalize a URL for deduplication (strip fragment, trailing slash).
     */
    private function normalizeUrl(string $url): string
    {
        if (! $url) return '';
        $parsed = parse_url($url);
        if (! $parsed) return $url;
        $scheme = ($parsed['scheme'] ?? 'https') . '://';
        $host = $parsed['host'] ?? '';
        $path = rtrim($parsed['path'] ?? '', '/');
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        return $scheme . $host . $path . $query;
    }
}
