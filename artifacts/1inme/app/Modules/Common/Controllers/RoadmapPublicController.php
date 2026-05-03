<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\BannedNameChecker;
use App\Modules\Common\Services\RoadmapNotifier;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RoadmapComment;
use App\Modules\User\Models\RoadmapItem;
use App\Modules\User\Models\RoadmapVote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Public-facing endpoints for the Roadmap biolink block. All routes
 * are mounted under /community/{link}/blocks/{block}/roadmap and run
 * unauthenticated; rate-limits and per-visitor fingerprinting are
 * enforced both at the route and inside each handler.
 */
class RoadmapPublicController extends Controller
{
    public function __construct(private RoadmapNotifier $notifier) {}

    public function list(Request $request, Link $link, BiolinkBlock $block)
    {
        $this->ensureRoadmapBlock($link, $block);
        $items = $this->visibleItems($block);
        $voted = $this->votedIds($request, $items->pluck('id')->all());

        return response()->json([
            'ok'      => true,
            'columns' => $this->groupByStatus($items),
            'voted'   => $voted,
        ]);
    }

    public function submit(Request $request, Link $link, BiolinkBlock $block)
    {
        $this->ensureRoadmapBlock($link, $block);
        $cfg = $block->settings ?? [];

        if (!($cfg['allow_submissions'] ?? true)) {
            return response()->json(['ok' => false, 'error' => 'Submissions are closed.'], 423);
        }
        if (($cfg['require_login'] ?? false) && !$this->viewerUserId($request)) {
            return response()->json(['ok' => false, 'error' => 'Please sign in to submit an idea.'], 401);
        }

        $rules = [
            'title'       => ['required', 'string', 'min:3', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'name'        => ['nullable', 'string', 'max:80'],
        ];
        if ($cfg['require_email'] ?? true) {
            $rules['email'] = ['required', 'email', 'max:190'];
        } else {
            $rules['email'] = ['nullable', 'email', 'max:190'];
        }
        $data = $request->validate($rules);

        // Banned-name check applies to BOTH the title (so spam/abuse copy
        // doesn't make it onto a public board) and the submitter's
        // display name (same lockout the rest of the platform uses).
        if (BannedNameChecker::isBanned($data['title'])
            || (!empty($data['name']) && BannedNameChecker::isBanned($data['name']))) {
            return response()->json(['ok' => false, 'error' => 'Some text in your submission is not allowed.'], 422);
        }

        $fp = $this->fingerprint($request);

        // Soft per-visitor cap so a single fan can't flood the board.
        $recent = RoadmapItem::query()->withoutGlobalScope('workspace')
            ->where('block_id', $block->id)
            ->where('submitter_fingerprint', $fp)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($recent >= 5) {
            return response()->json(['ok' => false, 'error' => 'Too many submissions today. Try again tomorrow.'], 429);
        }

        $item = new RoadmapItem();
        $item->workspace_id          = $link->workspace_id;
        $item->link_id               = $link->id;
        $item->block_id              = $block->id;
        $item->status                = ($cfg['auto_approve'] ?? false) ? 'ideas' : 'pending';
        $item->title                 = trim($data['title']);
        $item->description           = trim($data['description'] ?? '');
        $item->submitter_name        = $data['name'] ?? null;
        $item->submitter_email       = $data['email'] ?? null;
        $item->submitter_user_id     = $this->viewerUserId($request);
        $item->submitter_fingerprint = $fp;
        $item->submitter_ip          = substr((string) $request->ip(), 0, 45);
        $item->save();

        // Auto-cast a vote from the submitter so their idea starts at 1.
        $this->castVote($item, $request, $fp);

        $this->notifier->notifyNewSubmission($item);

        return response()->json([
            'ok'      => true,
            'pending' => $item->status === 'pending',
            'item'    => $this->shape($item, true),
        ]);
    }

    public function vote(Request $request, Link $link, BiolinkBlock $block, RoadmapItem $item)
    {
        $this->ensureRoadmapBlock($link, $block);
        abort_if($item->block_id !== $block->id, 404);
        // Public-visibility gate: blocked items and non-public statuses
        // (pending/rejected/merged) must never accept new votes, even if a
        // visitor guesses an item ID.
        if ($item->is_blocked || !in_array($item->status, RoadmapItem::PUBLIC_STATUSES, true)) {
            return response()->json(['ok' => false, 'error' => 'This idea is not open for voting.'], 423);
        }

        $fp = $this->fingerprint($request);
        // Atomic vote toggle: lock the item row, branch on whether the
        // unique (item_id, fingerprint) row exists, then increment or
        // decrement the cached counter via DB-level math so concurrent
        // voters from different fingerprints never lose increments.
        $result = DB::transaction(function () use ($item, $fp, $request) {
            $locked = RoadmapItem::query()->withoutGlobalScope('workspace')
                ->lockForUpdate()->find($item->id);
            $existing = RoadmapVote::where('item_id', $locked->id)
                ->where('fingerprint', $fp)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                $existing->delete();
                RoadmapItem::query()->withoutGlobalScope('workspace')
                    ->where('id', $locked->id)
                    ->where('votes_count', '>', 0)
                    ->decrement('votes_count');
                return ['voted' => false];
            }
            RoadmapVote::create([
                'item_id'        => $locked->id,
                'viewer_user_id' => $this->viewerUserId($request),
                'fingerprint'    => $fp,
                'email'          => $request->input('email'),
                'ip'             => substr((string) $request->ip(), 0, 45),
            ]);
            RoadmapItem::query()->withoutGlobalScope('workspace')
                ->where('id', $locked->id)
                ->increment('votes_count');
            return ['voted' => true];
        });

        $count = (int) RoadmapItem::query()->withoutGlobalScope('workspace')
            ->where('id', $item->id)->value('votes_count');
        return response()->json(['ok' => true, 'voted' => $result['voted'], 'count' => $count]);
    }

    public function comment(Request $request, Link $link, BiolinkBlock $block, RoadmapItem $item)
    {
        $this->ensureRoadmapBlock($link, $block);
        abort_if($item->block_id !== $block->id, 404);
        // Mirror the vote endpoint: do not accept comments on items that
        // are blocked or in non-public statuses.
        if ($item->is_blocked || !in_array($item->status, RoadmapItem::PUBLIC_STATUSES, true)) {
            return response()->json(['ok' => false, 'error' => 'Comments are closed on this idea.'], 423);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:1000'],
            'name' => ['nullable', 'string', 'max:80'],
        ]);
        if (!empty($data['name']) && BannedNameChecker::isBanned($data['name'])) {
            return response()->json(['ok' => false, 'error' => 'Display name is not allowed.'], 422);
        }

        $vid = $this->viewerUserId($request);
        $name = $data['name']
            ?: $request->session()->get('viewer_user_name')
            ?: 'Guest';

        RoadmapComment::create([
            'item_id'        => $item->id,
            'viewer_user_id' => $vid,
            'author_name'    => Str::limit($name, 80, ''),
            'body'           => trim($data['body']),
            'is_creator'     => false,
            'fingerprint'    => $this->fingerprint($request),
            'ip'             => substr((string) $request->ip(), 0, 45),
        ]);
        return response()->json(['ok' => true]);
    }

    /** ─── Helpers ──────────────────────────────────────────── */

    private function ensureRoadmapBlock(Link $link, BiolinkBlock $block): void
    {
        abort_if($block->link_id !== $link->id, 404);
        abort_if($block->type !== 'roadmap', 404);
        abort_if(!$block->is_active, 404);
    }

    private function visibleItems(BiolinkBlock $block)
    {
        return RoadmapItem::query()->withoutGlobalScope('workspace')
            ->where('block_id', $block->id)
            ->where('is_blocked', false)
            ->whereIn('status', RoadmapItem::PUBLIC_STATUSES)
            ->orderByDesc('votes_count')
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }

    private function groupByStatus($items): array
    {
        $out = array_fill_keys(RoadmapItem::PUBLIC_STATUSES, []);
        foreach ($items as $i) $out[$i->status][] = $this->shape($i);
        return $out;
    }

    private function shape(RoadmapItem $i, bool $full = false): array
    {
        $row = [
            'id'          => $i->id,
            'title'       => $i->title,
            'description' => Str::limit((string) $i->description, $full ? 2000 : 280),
            'status'      => $i->status,
            'votes'       => (int) $i->votes_count,
            'shipped_at'  => optional($i->shipped_at)->toIso8601String(),
        ];
        return $row;
    }

    private function votedIds(Request $request, array $itemIds): array
    {
        if (empty($itemIds)) return [];
        $fp = $this->fingerprint($request);
        return RoadmapVote::whereIn('item_id', $itemIds)
            ->where('fingerprint', $fp)
            ->pluck('item_id')
            ->all();
    }

    private function castVote(RoadmapItem $item, Request $request, string $fp): void
    {
        // Atomic counter increment + unique-constrained vote insert. We
        // intentionally avoid read-modify-write on votes_count because two
        // concurrent fingerprints would otherwise both read the same value
        // and clobber each other's +1.
        DB::transaction(function () use ($item, $request, $fp) {
            $created = RoadmapVote::firstOrCreate(
                ['item_id' => $item->id, 'fingerprint' => $fp],
                [
                    'viewer_user_id' => $this->viewerUserId($request),
                    'email'          => $request->input('email'),
                    'ip'             => substr((string) $request->ip(), 0, 45),
                ]
            );
            if ($created->wasRecentlyCreated) {
                RoadmapItem::query()->withoutGlobalScope('workspace')
                    ->where('id', $item->id)
                    ->increment('votes_count');
            }
        });
    }

    private function viewerUserId(Request $request): ?int
    {
        $vid = $request->session()->get('viewer_user_id');
        return $vid ? (int) $vid : null;
    }

    private function fingerprint(Request $request): string
    {
        $sid = $request->session()->getId() ?: $request->ip();
        return substr(hash('sha256', $sid . '|' . ($request->userAgent() ?? '')), 0, 32);
    }
}
