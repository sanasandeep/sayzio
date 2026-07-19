<?php

namespace App\Modules\Api\Controllers;

use App\Modules\User\Controllers\UpdatesController;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\UpdateEntry;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile REST API for the Creator Updates / Changelog page type.
 *
 * Public endpoints (under api.optional_auth):
 *   GET  /api/v1/updates/{alias}               → paginated published entries
 *   GET  /api/v1/updates/{alias}/entries/{id}  → single published entry
 *
 * Owner-only endpoints (auth:sanctum):
 *   POST   /api/v1/me/updates/{link}/entries          → create entry
 *   PUT    /api/v1/me/updates/{link}/entries/{entry}  → update entry
 *   DELETE /api/v1/me/updates/{link}/entries/{entry}  → delete entry
 *   PATCH  /api/v1/me/updates/{link}/settings         → update page settings
 */
class UpdatesApiController extends Controller
{
    /** Public: paginated list of published entries for a Updates-type link. */
    public function index(Request $request, string $alias): JsonResponse
    {
        $link = $this->resolvePublicLink($alias);

        $settings = array_merge(
            UpdatesController::DEFAULT_SETTINGS,
            $link->settings['updates'] ?? []
        );

        $perPage = max(1, min(100, (int) ($settings['per_page'] ?? 10)));

        $entries = UpdateEntry::where('link_id', $link->id)
            ->published()
            ->orderByDesc('published_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => [
                'link'     => $this->linkMeta($link, $settings),
                'entries'  => $entries->map(fn ($e) => $this->entryJson($e))->values(),
                'meta'     => [
                    'current_page'  => $entries->currentPage(),
                    'last_page'     => $entries->lastPage(),
                    'per_page'      => $entries->perPage(),
                    'total'         => $entries->total(),
                    'has_more'      => $entries->hasMorePages(),
                    'next_page_url' => $entries->nextPageUrl(),
                ],
            ],
        ]);
    }

    /** Public: single published entry. */
    public function show(Request $request, string $alias, int $entryId): JsonResponse
    {
        $link  = $this->resolvePublicLink($alias);
        $entry = UpdateEntry::where('link_id', $link->id)
            ->where('id', $entryId)
            ->published()
            ->firstOrFail();

        return response()->json(['data' => $this->entryJson($entry)]);
    }

    /** Owner: create a new entry. */
    public function storeEntry(Request $request, Link $link): JsonResponse
    {
        $this->authorizeOwner($link);

        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'body'           => 'nullable|string|max:50000',
            'tag'            => 'nullable|string|in:' . implode(',', UpdateEntry::allowedTags()),
            'published_date' => 'nullable|date',
            'status'         => 'nullable|in:draft,published',
            'image_url'      => 'nullable|url|max:2048',
        ]);

        $status = $data['status'] ?? 'draft';

        $entry = UpdateEntry::create([
            'link_id'        => $link->id,
            'user_id'        => auth()->id(),
            'title'          => $data['title'],
            'body'           => isset($data['body']) ? $this->sanitizeBody($data['body']) : null,
            'image'          => $data['image_url'] ?? null,
            'tag'            => $data['tag'] ?? null,
            'published_date' => $data['published_date'] ?? now()->toDateString(),
            'status'         => $status,
            'sort_order'     => 0,
        ]);

        if ($entry->needsFollowerNotification()) {
            $this->notifyFollowers($link, $entry);
        }

        return response()->json(['data' => $this->entryJson($entry)], 201);
    }

    /** Owner: update an existing entry. */
    public function updateEntry(Request $request, Link $link, UpdateEntry $entry): JsonResponse
    {
        $this->authorizeOwner($link);
        abort_if($entry->link_id !== $link->id, 404);

        $data = $request->validate([
            'title'          => 'sometimes|required|string|max:255',
            'body'           => 'nullable|string|max:50000',
            'tag'            => 'nullable|string|in:' . implode(',', UpdateEntry::allowedTags()),
            'published_date' => 'nullable|date',
            'status'         => 'nullable|in:draft,published',
            'image_url'      => 'nullable|url|max:2048',
            'remove_image'   => 'nullable|boolean',
        ]);

        if (array_key_exists('image_url', $data)) {
            $data['image'] = $data['image_url'];
        } elseif (!empty($data['remove_image'])) {
            $data['image'] = null;
        }
        unset($data['image_url'], $data['remove_image']);

        if (array_key_exists('body', $data) && $data['body']) {
            $data['body'] = $this->sanitizeBody($data['body']);
        }

        $wasUnnotified = $entry->notified_at === null;
        $entry->fill($data)->save();

        if ($wasUnnotified && $entry->needsFollowerNotification()) {
            $this->notifyFollowers($link, $entry);
        }

        return response()->json(['data' => $this->entryJson($entry->fresh())]);
    }

    /** Owner: delete an entry. */
    public function destroyEntry(Request $request, Link $link, UpdateEntry $entry): JsonResponse
    {
        $this->authorizeOwner($link);
        abort_if($entry->link_id !== $link->id, 404);

        $entry->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /** Owner: list all entries (draft + published) for a link. */
    public function ownerEntries(Request $request, Link $link): JsonResponse
    {
        $this->authorizeOwner($link);

        $entries = UpdateEntry::where('link_id', $link->id)
            ->orderByDesc('published_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => [
                'entries' => $entries->map(fn ($e) => $this->entryJson($e))->values(),
            ],
        ]);
    }

    /** Owner: update page settings (heading / subheading / per_page). */
    public function updateSettings(Request $request, Link $link): JsonResponse
    {
        $this->authorizeOwner($link);

        $data = $request->validate([
            'heading'    => 'nullable|string|max:120',
            'subheading' => 'nullable|string|max:255',
            'per_page'   => 'nullable|integer|min:1|max:100',
        ]);

        $settings = $link->settings ?? [];
        $settings['updates'] = array_merge(
            UpdatesController::DEFAULT_SETTINGS,
            $link->settings['updates'] ?? [],
            array_filter($data, fn ($v) => $v !== null)
        );
        $link->settings = $settings;
        $link->save();

        return response()->json(['data' => $settings['updates']]);
    }

    // ─── Private helpers ────────────────────────────────────────────────────

    private function resolvePublicLink(string $alias): Link
    {
        $link = Link::where('alias', $alias)->where('type', 'updates')->first();
        abort_unless($link, 404);
        return $link;
    }

    private function authorizeOwner(Link $link): void
    {
        abort_if($link->type !== 'updates', 404);
        abort_if(!auth()->check() || auth()->id() !== (int) $link->user_id, 403);
    }

    private function entryJson(UpdateEntry $entry): array
    {
        return [
            'id'             => $entry->id,
            'title'          => $entry->title,
            'body'           => $entry->body,
            'image'          => $entry->image ? \App\Support\PublicStorageUrl::resolve($entry->image) : null,
            'tag'            => $entry->tag,
            'published_date' => $entry->published_date?->toDateString(),
            'status'         => $entry->status,
            'notified_at'    => $entry->notified_at?->toIso8601String(),
            'anchor_id'      => $entry->anchorId(),
            'created_at'     => $entry->created_at?->toIso8601String(),
            'updated_at'     => $entry->updated_at?->toIso8601String(),
        ];
    }

    private function linkMeta(Link $link, array $settings): array
    {
        $creator = \App\Modules\User\Models\User::find($link->user_id);
        return [
            'id'         => $link->id,
            'alias'      => $link->alias,
            'title'      => $link->title,
            'url'        => $link->getShortUrl(),
            'heading'    => $settings['heading'],
            'subheading' => $settings['subheading'],
            'per_page'   => (int) $settings['per_page'],
            'creator'    => $creator ? [
                'id'     => $creator->id,
                'name'   => $creator->name,
                'handle' => $creator->handle,
                'avatar' => $creator->avatar ? \App\Support\PublicStorageUrl::resolve($creator->avatar) : null,
            ] : null,
        ];
    }

    private function notifyFollowers(Link $link, UpdateEntry $entry): void
    {
        $creator = \App\Modules\User\Models\User::find($link->user_id);
        if (!$creator) {
            return;
        }
        $message = $creator->name . ' posted a new update: ' . $entry->title;
        \App\Modules\User\Controllers\CreatorPostController::notifyFollowersDebounced($creator, $message);
        $entry->forceFill(['notified_at' => now()])->save();
    }

    private function sanitizeBody(string $html): string
    {
        $allowed = '<p><br><b><strong><i><em><u><s><ul><ol><li><a><blockquote><code><pre><h1><h2><h3><h4>';
        return strip_tags($html, $allowed);
    }
}
