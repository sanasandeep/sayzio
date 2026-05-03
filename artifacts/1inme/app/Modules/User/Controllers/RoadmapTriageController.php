<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\RoadmapKanbanSync;
use App\Modules\Common\Services\RoadmapNotifier;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RoadmapComment;
use App\Modules\User\Models\RoadmapItem;
use App\Modules\User\Models\RoadmapVote;
use App\Modules\User\Models\TaskBoard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Workspace-side dashboard for triaging public roadmap submissions.
 * Mounted under /user/links/{link}/roadmap. Authorisation is the
 * standard biolink-edit gate: the link must belong to the active
 * workspace owner.
 */
class RoadmapTriageController extends Controller
{
    public function __construct(
        private RoadmapKanbanSync $sync,
        private RoadmapNotifier $notifier,
    ) {}

    public function index(Request $request, Link $link)
    {
        $this->authorize($link);

        $status = $request->query('status', 'pending');
        $allStatuses = array_keys(RoadmapItem::STATUSES);
        if (!in_array($status, $allStatuses, true)) $status = 'pending';

        $blocks = BiolinkBlock::query()
            ->where('link_id', $link->id)
            ->where('type', 'roadmap')
            ->get();

        $blockId = (int) $request->query('block_id', 0);
        $itemsQuery = RoadmapItem::query()->where('link_id', $link->id);
        if ($blockId > 0) $itemsQuery->where('block_id', $blockId);
        $itemsQuery->where('status', $status);

        $items = $itemsQuery->orderByDesc('votes_count')->orderByDesc('id')->paginate(40)->withQueryString();

        $counts = RoadmapItem::query()
            ->where('link_id', $link->id)
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status')
            ->all();

        $boards = TaskBoard::query()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();

        return view('user.roadmap.triage', compact('link', 'items', 'status', 'counts', 'blocks', 'blockId', 'boards'));
    }

    public function update(Request $request, Link $link, RoadmapItem $item)
    {
        $this->authorize($link);
        abort_if($item->link_id !== $link->id, 404);

        $data = $request->validate([
            'status'   => ['nullable', 'string', 'in:' . implode(',', array_keys(RoadmapItem::STATUSES))],
            'title'    => ['nullable', 'string', 'min:3', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_blocked'  => ['nullable', 'boolean'],
            'sync_to_kanban' => ['nullable', 'boolean'],
        ]);

        $oldStatus = $item->status;

        if (isset($data['title']))       $item->title = trim($data['title']);
        if (array_key_exists('description', $data)) $item->description = trim((string) $data['description']);
        if (array_key_exists('is_blocked', $data))  $item->is_blocked = (bool) $data['is_blocked'];
        if (!empty($data['status']))     $item->status = $data['status'];

        if ($item->status === 'shipped' && !$item->shipped_at) $item->shipped_at = now();
        if ($item->status !== 'shipped') $item->shipped_at = null;

        $item->save();

        // If creator opted to sync, ensure the kanban card exists and
        // reflects the new public status.
        if ($request->boolean('sync_to_kanban', true) && $item->status !== 'pending' && $item->status !== 'rejected') {
            $this->sync->ensureCardForItem($item);
            $this->sync->pushStatusToCard($item);
        }

        // Fan-out shipping notifications when an item just transitioned
        // into "shipped" from anything else.
        if ($item->status === 'shipped' && $oldStatus !== 'shipped') {
            $sent = $this->notifier->notifyShipped($item);
            return back()->with('success', "Marked as shipped — notified {$sent} upvoter(s).");
        }

        return back()->with('success', 'Idea updated.');
    }

    public function destroy(Request $request, Link $link, RoadmapItem $item)
    {
        $this->authorize($link);
        abort_if($item->link_id !== $link->id, 404);

        DB::transaction(function () use ($item) {
            RoadmapVote::where('item_id', $item->id)->delete();
            RoadmapComment::where('item_id', $item->id)->delete();
            $item->delete();
        });

        return back()->with('success', 'Idea deleted.');
    }

    public function merge(Request $request, Link $link, RoadmapItem $item)
    {
        $this->authorize($link);
        abort_if($item->link_id !== $link->id, 404);

        $data = $request->validate([
            'into_id' => ['required', 'integer'],
        ]);
        // `different:item` doesn't compare against route bindings, so we
        // explicitly guard against self-merge — otherwise the merge body
        // would delete the item's own votes and mark it merged into
        // itself, which is an unrecoverable state.
        abort_if((int) $data['into_id'] === (int) $item->id, 422, 'Cannot merge an idea into itself.');
        $target = RoadmapItem::query()->where('link_id', $link->id)->findOrFail($data['into_id']);
        abort_if($target->status === 'merged', 422, 'Target idea is already merged elsewhere.');

        DB::transaction(function () use ($item, $target) {
            // Move votes to the target, deduping on fingerprint.
            $existingFps = RoadmapVote::where('item_id', $target->id)->pluck('fingerprint')->all();
            RoadmapVote::where('item_id', $item->id)
                ->whereNotIn('fingerprint', $existingFps)
                ->update(['item_id' => $target->id]);
            // Drop any leftover dupes on the source side.
            RoadmapVote::where('item_id', $item->id)->delete();
            RoadmapComment::where('item_id', $item->id)->update(['item_id' => $target->id]);

            $target->votes_count = RoadmapVote::where('item_id', $target->id)->count();
            $target->save();

            $item->status = 'merged';
            $item->merged_into_id = $target->id;
            $item->save();
        });

        return back()->with('success', 'Merged into "' . $target->title . '".');
    }

    private function authorize(Link $link): void
    {
        abort_if($link->user_id !== workspace_owner_id() || $link->type !== 'biolink', 403);
    }
}
