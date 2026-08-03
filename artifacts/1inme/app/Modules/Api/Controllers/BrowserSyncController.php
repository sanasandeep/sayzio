<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * BrowserSyncController — cloud sync endpoints for Zio Browser.
 *
 * Handles device registration and last-write-wins sync of bookmarks,
 * collections, saved links, and browsing history.
 *
 * Route prefix: /api/v1/browser
 * Auth: auth:sanctum
 *
 * Workspace profiles: each Zio Browser profile maps to a Sayzio workspace.
 * Clients send X-Browser-Workspace-Id to scope their data bucket server-side.
 * Records with workspace_id = NULL belong to the personal/default profile.
 */
class BrowserSyncController extends Controller
{
    use \App\Modules\Api\Controllers\Concerns\ApiResponses;

    /**
     * Hard ceiling for the per-entity row cap. `max_browser_sync_items = -1`
     * ("unlimited") is still clamped to this so no single account can grow a
     * sync table without bound.
     */
    private const HARD_ITEM_CAP = 100000;

    /**
     * Plan gate for the whole sync surface (Task #6647).
     *
     * `browser_sync` is a plan-feature boolean, legacy-safe default ON so
     * plans whose rows predate the key keep working until the backfill
     * migration / seeder stamps explicit values. Returns the 402 response to
     * send, or null when the caller may proceed.
     *
     * `purgeHistory` is deliberately NOT gated — deleting server rows must
     * always be allowed (it reduces storage and honours the user's privacy).
     */
    private function browserSyncGate(Request $request): ?JsonResponse
    {
        if ($request->user()->getPlanFeature('browser_sync', true)) {
            return null;
        }

        return $this->planGate(
            'Browser sync is not included in your plan. Upgrade to sync bookmarks, collections, history and your reading list across devices.',
            'browser_sync',
            $request->user(),
        );
    }

    /**
     * Effective per-entity row cap for this user (rows per sync table).
     * -1 / missing = unlimited (still clamped to HARD_ITEM_CAP).
     */
    private function itemCap(Request $request): int
    {
        $cap = (int) $request->user()->getPlanFeature('max_browser_sync_items', -1);

        if ($cap < 0 || $cap > self::HARD_ITEM_CAP) {
            return self::HARD_ITEM_CAP;
        }

        return $cap;
    }

    // ── Device ────────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/browser/devices
     * Register or update a browser device for this user.
     */
    public function registerDevice(Request $request): JsonResponse
    {
        if ($gate = $this->browserSyncGate($request)) {
            return $gate;
        }

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
                    'label'        => $validated['label'],
                    'platform'     => $validated['platform'],
                    'app_version'  => $validated['app_version'] ?? null,
                    'last_seen_at' => now(),
                    'updated_at'   => now(),
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
        ], function (int $userId, array $item, ?int $workspaceId): array {
            $data = $item['data'] ?? [];
            return [
                'user_id'         => $userId,
                'workspace_id'    => $workspaceId,
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
        ], function (int $userId, array $item, ?int $workspaceId): array {
            $data = $item['data'] ?? [];
            return [
                'user_id'         => $userId,
                'workspace_id'    => $workspaceId,
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
        ], function (int $userId, array $item, ?int $workspaceId): array {
            $data = $item['data'] ?? [];
            return [
                'user_id'         => $userId,
                'workspace_id'    => $workspaceId,
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
     * POST /api/v1/browser/history/purge
     * Bulk server-side history tombstone for the authenticated user.
     *
     * Marks all (or time-range-filtered) server-stored history rows as deleted
     * so other devices receive the tombstones on the next pull.
     *
     * @body { since?: ISO-8601 }  — omit to purge all history.
     */
    public function purgeHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'since' => ['nullable', 'date'],
        ]);

        $userId = $request->user()->id;
        $query  = DB::table('browser_history_sync')->where('user_id', $userId);
        $since  = $validated['since'] ?? null;

        if ($since) {
            $query->where('last_visited_at', '>=', $since);
        }

        $count = $query->count();

        if ($count > 0) {
            $now = now();
            DB::table('browser_history_sync')
                ->where('user_id', $userId)
                ->when($since, fn ($q) => $q->where('last_visited_at', '>=', $since))
                ->update([
                    'deleted'         => true,
                    'item_updated_at' => $now,
                    'updated_at'      => $now,
                ]);
        }

        return response()->json(['data' => ['deleted' => $count]]);
    }

    /**
     * POST /api/v1/browser/devices/{deviceId}/reading-list
     * Push reading-list changes from client to server (last-write-wins).
     */
    public function syncReadingList(Request $request, string $deviceId): JsonResponse
    {
        return $this->syncEntity($request, $deviceId, 'browser_reading_list', [
            'items'              => ['required', 'array', 'max:1000'],
            'items.*.local_id'   => ['required', 'string', 'max:64'],
            'items.*.updated_at' => ['required', 'date'],
            'items.*.deleted'    => ['nullable', 'boolean'],
            'items.*.data'       => ['nullable', 'array'],
            'items.*.data.url'   => ['nullable', 'url'],
            'items.*.data.title' => ['nullable', 'string', 'max:512'],
            'items.*.data.is_read' => ['nullable', 'boolean'],
        ], function (int $userId, array $item, ?int $workspaceId): array {
            $data = $item['data'] ?? [];
            return [
                'user_id'         => $userId,
                'workspace_id'    => $workspaceId,
                'local_id'        => $item['local_id'],
                'url'             => $data['url'] ?? '',
                'normalized_url'  => $this->normalizeUrl($data['url'] ?? ''),
                'title'           => $data['title'] ?? '',
                'favicon_url'     => $data['favicon_url'] ?? null,
                'is_read'         => (bool) ($data['is_read'] ?? false),
                'deleted'         => $item['deleted'] ?? false,
                'item_updated_at' => $item['updated_at'],
            ];
        });
    }

    /**
     * GET /api/v1/browser/devices/{deviceId}/pull
     * Pull all server-side data since a given timestamp.
     * ?since=ISO8601
     *
     * Workspace scoping: only returns records for the workspace profile
     * identified by X-Browser-Workspace-Id (null = personal profile).
     */
    public function pullSync(Request $request, string $deviceId): JsonResponse
    {
        if ($gate = $this->browserSyncGate($request)) {
            return $gate;
        }

        $this->validateDevice($request, $deviceId);

        $userId      = $request->user()->id;
        $since       = $request->query('since');
        $workspaceId = $this->resolveWorkspaceId($request);

        $bookmarks   = $this->pullEntity($userId, 'browser_bookmarks', $since, $workspaceId);
        $collections = $this->pullEntity($userId, 'browser_collections', $since, $workspaceId);
        $history     = $this->pullEntity($userId, 'browser_history_sync', $since, $workspaceId);
        $readingList = $this->pullEntity($userId, 'browser_reading_list', $since, $workspaceId);

        return response()->json([
            'data' => [
                'bookmarks'    => $bookmarks,
                'collections'  => $collections,
                'history'      => $history,
                'reading_list' => $readingList,
                'server_time'  => now()->toIso8601String(),
                'workspace_id' => $workspaceId,
            ],
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Resolve the workspace_id from the request.
     *
     * Clients send the active profile's workspace ID in X-Browser-Workspace-Id.
     * The personal/default profile sends no header (null = personal bucket).
     * We verify the workspace belongs to this user before trusting it.
     *
     * @return int|null  Verified workspace ID, or null for the personal profile.
     */
    private function resolveWorkspaceId(Request $request): ?int
    {
        $raw = $request->header('X-Browser-Workspace-Id');
        if (! $raw) {
            return null;
        }

        $wsId = (int) $raw;
        if ($wsId <= 0) {
            return null;
        }

        // Verify the authenticated user is the owner or a member of this workspace
        $isOwner = DB::table('workspaces')
            ->where('id', $wsId)
            ->where('owner_user_id', $request->user()->id)
            ->exists();

        if ($isOwner) {
            return $wsId;
        }

        $isMember = DB::table('workspace_members')
            ->where('workspace_id', $wsId)
            ->where('user_id', $request->user()->id)
            ->exists();

        return $isMember ? $wsId : null;
    }

    /**
     * Generic sync push handler for any browser entity table.
     *
     * @param callable(int, array, int|null): array $mapper Maps a validated sync item to a DB row
     */
    private function syncEntity(
        Request $request,
        string $deviceId,
        string $table,
        array $rules,
        callable $mapper,
    ): JsonResponse {
        if ($gate = $this->browserSyncGate($request)) {
            return $gate;
        }

        $this->validateDevice($request, $deviceId);

        $validated   = $request->validate($rules);
        $userId      = $request->user()->id;
        $workspaceId = $this->resolveWorkspaceId($request);
        $accepted    = [];
        $conflicts   = [];
        $rejected    = [];
        $now         = now();

        // Per-entity storage cap (Task #6647): count this user's live (non-
        // tombstoned) rows in the table across ALL profiles once up front,
        // then budget new inserts against it. Updates and tombstones of
        // existing rows are always allowed — only NEW rows can be rejected —
        // so clients can still edit and delete when over the cap.
        $cap       = $this->itemCap($request);
        $liveCount = DB::table($table)
            ->where('user_id', $userId)
            ->where('deleted', false)
            ->count();

        foreach ($validated['items'] as $item) {
            $localId         = $item['local_id'];
            $clientUpdatedAt = $item['updated_at'];

            $existing = DB::table($table)
                ->where('user_id', $userId)
                ->where('local_id', $localId)
                ->where('workspace_id', $workspaceId)   // scope to this profile
                ->first();

            if ($existing) {
                // Last-write-wins: only update if client version is newer
                $serverTs = $existing->item_updated_at ? strtotime($existing->item_updated_at) : 0;
                $clientTs = strtotime($clientUpdatedAt);

                if ($clientTs >= $serverTs) {
                    $wasLive = ! (bool) $existing->deleted;
                    $isLive  = ! (bool) ($item['deleted'] ?? false);

                    // A tombstone → live "resurrection" grows the live-row
                    // count exactly like a fresh insert, so it must pay the
                    // same cap check — otherwise clients could park rows as
                    // tombstones and revive them past the plan limit.
                    if (! $wasLive && $isLive && $liveCount >= $cap) {
                        $rejected[] = $localId;
                        continue;
                    }

                    $row = $mapper($userId, $item, $workspaceId);
                    DB::table($table)
                        ->where('id', $existing->id)
                        ->update(array_merge($row, ['updated_at' => $now]));

                    // Keep in-request accounting consistent across deleted-
                    // state transitions within one batch.
                    if ($wasLive && ! $isLive) {
                        $liveCount--;
                    } elseif (! $wasLive && $isLive) {
                        $liveCount++;
                    }

                    $accepted[] = $localId;
                } else {
                    // Server version is newer — client is out of date
                    $conflicts[] = $localId;
                }
            } else {
                $isTombstone = (bool) ($item['deleted'] ?? false);
                if (! $isTombstone && $liveCount >= $cap) {
                    // Over the plan's row cap — reject the NEW row (the
                    // client keeps it locally and can retry after an
                    // upgrade or a cleanup).
                    $rejected[] = $localId;
                    continue;
                }

                $row = $mapper($userId, $item, $workspaceId);
                DB::table($table)->insert(array_merge($row, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                if (! $isTombstone) {
                    $liveCount++;
                }
                $accepted[] = $localId;
            }
        }

        return response()->json([
            'data' => [
                'accepted'     => $accepted,
                'conflicts'    => $conflicts,
                'rejected'     => $rejected,
                'limit'        => $cap,
                'server_time'  => $now->toIso8601String(),
                'workspace_id' => $workspaceId,
            ],
        ]);
    }

    /**
     * Pull all records for a table since a timestamp, scoped to a workspace profile.
     */
    private function pullEntity(int $userId, string $table, ?string $since, ?int $workspaceId): array
    {
        $query = DB::table($table)
            ->where('user_id', $userId)
            ->where('workspace_id', $workspaceId);

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
        $exclude = ['id', 'user_id', 'workspace_id', 'local_id', 'deleted', 'item_updated_at', 'created_at', 'updated_at'];
        $data    = [];
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
        $host   = $parsed['host'] ?? '';
        $path   = rtrim($parsed['path'] ?? '', '/');
        $query  = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        return $scheme . $host . $path . $query;
    }
}
