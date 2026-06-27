<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\RoadmapKanbanSync;
use App\Modules\Common\Services\RoadmapNotifier;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RoadmapComment;
use App\Modules\User\Models\RoadmapItem;
use App\Modules\User\Models\RoadmapVote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Bearer-token (auth:sanctum) parity for the web roadmap triage dashboard
 * (App\Modules\User\Controllers\RoadmapTriageController). Lets a creator
 * triage their biolink's public roadmap submissions from the mobile app:
 *   - GET    /links/{link}/roadmap                  → list items + counts
 *   - PATCH  /links/{link}/roadmap/items/{item}     → update status/fields
 *   - DELETE /links/{link}/roadmap/items/{item}     → delete an idea
 *   - POST   /links/{link}/roadmap/items/{item}/merge → merge into another
 *
 * Authorisation matches the web `links.edit` gate: the link must belong to
 * the authenticated user and be a biolink-family page. All responses use
 * the unified {data}/{error} envelope.
 */
class RoadmapTriageController extends Controller
{
    use ApiResponses;

    public function __construct(
        private RoadmapKanbanSync $sync,
        private RoadmapNotifier $notifier,
    ) {}

    public function index(Request $request, int $link): JsonResponse
    {
        $owned = $this->resolveLink($request, $link);
        if (!$owned) return $this->notFound('Roadmap not found.');

        $status = (string) $request->query('status', 'pending');
        if (!array_key_exists($status, RoadmapItem::STATUSES)) $status = 'pending';

        $blocks = BiolinkBlock::query()
            ->where('link_id', $owned->id)
            ->where('type', 'roadmap')
            ->get();

        $blockId = (int) $request->query('block_id', 0);

        $itemsQuery = RoadmapItem::query()->where('link_id', $owned->id);
        if ($blockId > 0) $itemsQuery->where('block_id', $blockId);
        $itemsQuery->where('status', $status);

        $page = $itemsQuery->orderByDesc('votes_count')->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 40))));

        $counts = RoadmapItem::query()
            ->where('link_id', $owned->id)
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status')
            ->all();

        // Merge targets: every non-merged idea on this link so the mobile
        // merge picker can offer a destination regardless of which status
        // tab the creator is currently viewing.
        $mergeTargets = RoadmapItem::query()
            ->where('link_id', $owned->id)
            ->where('status', '!=', 'merged')
            ->orderByDesc('votes_count')->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'title', 'status', 'votes_count'])
            ->map(fn (RoadmapItem $i) => [
                'id'          => $i->id,
                'title'       => $i->title,
                'status'      => $i->status,
                'votes_count' => (int) $i->votes_count,
            ])->all();

        return $this->ok([
            'status'         => $status,
            'statuses'       => RoadmapItem::STATUSES,
            'public_statuses'=> RoadmapItem::PUBLIC_STATUSES,
            'block_id'       => $blockId,
            'blocks'         => $blocks->map(fn (BiolinkBlock $b) => [
                'id'    => $b->id,
                'title' => data_get($b->settings, 'title') ?: ('Block #' . $b->id),
            ])->all(),
            'counts'         => array_map('intval', $counts),
            'merge_targets'  => $mergeTargets,
            'items'          => collect($page->items())->map(fn (RoadmapItem $i) => $this->itemArray($i))->all(),
            'meta'           => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    public function update(Request $request, int $link, int $item): JsonResponse
    {
        $owned = $this->resolveLink($request, $link);
        if (!$owned) return $this->notFound('Roadmap not found.');

        $model = RoadmapItem::query()->where('link_id', $owned->id)->find($item);
        if (!$model) return $this->notFound('Idea not found.');

        $data = $request->validate([
            'status'         => ['nullable', 'string', 'in:' . implode(',', array_keys(RoadmapItem::STATUSES))],
            'title'          => ['nullable', 'string', 'min:3', 'max:200'],
            'description'    => ['nullable', 'string', 'max:5000'],
            'is_blocked'     => ['nullable', 'boolean'],
            'sync_to_kanban' => ['nullable', 'boolean'],
        ]);

        $oldStatus = $model->status;

        if (isset($data['title']))                  $model->title = trim($data['title']);
        if (array_key_exists('description', $data)) $model->description = trim((string) $data['description']);
        if (array_key_exists('is_blocked', $data))  $model->is_blocked = (bool) $data['is_blocked'];
        if (!empty($data['status']))                $model->status = $data['status'];

        if ($model->status === 'shipped' && !$model->shipped_at) $model->shipped_at = now();
        if ($model->status !== 'shipped') $model->shipped_at = null;

        $model->save();

        // Mirror the web flow: optionally keep a kanban card in sync.
        if ($request->boolean('sync_to_kanban', true) && $model->status !== 'pending' && $model->status !== 'rejected') {
            $this->sync->ensureCardForItem($model);
            $this->sync->pushStatusToCard($model);
        }

        $message = 'Idea updated.';
        if ($model->status === 'shipped' && $oldStatus !== 'shipped') {
            $sent = $this->notifier->notifyShipped($model);
            $message = "Marked as shipped — notified {$sent} upvoter(s).";
        }

        return $this->ok([
            'item'    => $this->itemArray($model->fresh()),
            'message' => $message,
        ]);
    }

    public function destroy(Request $request, int $link, int $item): JsonResponse
    {
        $owned = $this->resolveLink($request, $link);
        if (!$owned) return $this->notFound('Roadmap not found.');

        $model = RoadmapItem::query()->where('link_id', $owned->id)->find($item);
        if (!$model) return $this->notFound('Idea not found.');

        DB::transaction(function () use ($model) {
            RoadmapVote::where('item_id', $model->id)->delete();
            RoadmapComment::where('item_id', $model->id)->delete();
            $model->delete();
        });

        return $this->ok(['message' => 'Idea deleted.']);
    }

    public function merge(Request $request, int $link, int $item): JsonResponse
    {
        $owned = $this->resolveLink($request, $link);
        if (!$owned) return $this->notFound('Roadmap not found.');

        $model = RoadmapItem::query()->where('link_id', $owned->id)->find($item);
        if (!$model) return $this->notFound('Idea not found.');

        $data = $request->validate([
            'into_id' => ['required', 'integer'],
        ]);

        // `different:item` can't compare against route bindings, so guard
        // self-merge explicitly (mirrors the web controller) — otherwise the
        // body would delete the item's own votes and mark it merged into
        // itself, an unrecoverable state.
        if ((int) $data['into_id'] === (int) $model->id) {
            return $this->fail('Cannot merge an idea into itself.', 422, 'self_merge');
        }

        $target = RoadmapItem::query()->where('link_id', $owned->id)->find($data['into_id']);
        if (!$target) return $this->notFound('Target idea not found.');
        if ($target->status === 'merged') {
            return $this->fail('Target idea is already merged elsewhere.', 422, 'target_merged');
        }

        DB::transaction(function () use ($model, $target) {
            // Move votes to the target, deduping on fingerprint.
            $existingFps = RoadmapVote::where('item_id', $target->id)->pluck('fingerprint')->all();
            RoadmapVote::where('item_id', $model->id)
                ->whereNotIn('fingerprint', $existingFps)
                ->update(['item_id' => $target->id]);
            // Drop any leftover dupes on the source side.
            RoadmapVote::where('item_id', $model->id)->delete();
            RoadmapComment::where('item_id', $model->id)->update(['item_id' => $target->id]);

            $target->votes_count = RoadmapVote::where('item_id', $target->id)->count();
            $target->save();

            $model->status = 'merged';
            $model->merged_into_id = $target->id;
            $model->save();
        });

        return $this->ok([
            'item'    => $this->itemArray($model->fresh()),
            'target'  => $this->itemArray($target->fresh()),
            'message' => 'Merged into "' . $target->title . '".',
        ]);
    }

    /**
     * Resolve a biolink-family link owned by the authenticated user. The
     * Sanctum API path never runs SetActiveWorkspace, so we scope by
     * user_id directly — the same ownership check the rest of the API
     * LinkController uses, equivalent to the web `links.edit` gate.
     */
    private function resolveLink(Request $request, int $linkId): ?Link
    {
        $link = Link::where('user_id', $request->user()->id)->find($linkId);
        if (!$link || !$link->isBiolinkFamily()) return null;
        return $link;
    }

    private function itemArray(RoadmapItem $i): array
    {
        return [
            'id'             => $i->id,
            'status'         => $i->status,
            'status_label'   => $i->statusLabel(),
            'title'          => $i->title,
            'description'    => $i->description,
            'votes_count'    => (int) $i->votes_count,
            'is_blocked'     => (bool) $i->is_blocked,
            'block_id'       => $i->block_id,
            'submitter_name' => $i->submitter_name,
            'submitter_email'=> $i->submitter_email,
            'task_card_id'   => $i->task_card_id,
            'merged_into_id' => $i->merged_into_id,
            'shipped_at'     => optional($i->shipped_at)->toIso8601String(),
            'created_at'     => optional($i->created_at)->toIso8601String(),
        ];
    }
}
