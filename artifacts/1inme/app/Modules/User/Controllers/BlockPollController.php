<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\BlockPoll;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;

/**
 * Creator-side CRUD for polls attached to any biolink block (or to a
 * specific Insider feed post). Public voting and visibility-aware reads
 * live in CommunityPublicController; this controller is only for the
 * authenticated owner managing their polls.
 */
class BlockPollController extends Controller
{
    private function authorizeOwner(Request $request, Link $link, BiolinkBlock $block): void
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        abort_if($block->link_id !== $link->id, 404);
    }

    public function index(Request $request, Link $link, BiolinkBlock $block)
    {
        $this->authorizeOwner($request, $link, $block);
        $polls = BlockPoll::query()
            ->where('block_id', $block->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($p) => array_merge($p->toArray(), ['tally' => $p->tally()]));
        return response()->json(['polls' => $polls]);
    }

    public function store(Request $request, Link $link, BiolinkBlock $block)
    {
        $this->authorizeOwner($request, $link, $block);

        $data = $request->validate([
            'question'     => ['required', 'string', 'max:500'],
            'options'      => ['required', 'array', 'min:2', 'max:10'],
            'options.*'    => ['required', 'string', 'max:200'],
            'visibility'   => ['required', 'in:' . implode(',', BlockPoll::VISIBILITIES)],
            'multi_select' => ['boolean'],
            'closes_at'    => ['nullable', 'date', 'after:now'],
            'post_id'      => ['nullable', 'integer'],
        ]);

        $poll = BlockPoll::create([
            'link_id'      => $link->id,
            'block_id'     => $block->id,
            'workspace_id' => $link->workspace_id ?? null,
            'post_id'      => $data['post_id'] ?? null,
            'question'     => $data['question'],
            'options'      => array_values($data['options']),
            'visibility'   => $data['visibility'],
            'multi_select' => (bool)($data['multi_select'] ?? false),
            'closes_at'    => $data['closes_at'] ?? null,
        ]);

        return response()->json(['ok' => true, 'poll_id' => $poll->id]);
    }

    public function update(Request $request, Link $link, BiolinkBlock $block, BlockPoll $poll)
    {
        $this->authorizeOwner($request, $link, $block);
        abort_if($poll->block_id !== $block->id, 404);

        $data = $request->validate([
            'question'     => ['nullable', 'string', 'max:500'],
            'visibility'   => ['nullable', 'in:' . implode(',', BlockPoll::VISIBILITIES)],
            'is_closed'    => ['nullable', 'boolean'],
            'closes_at'    => ['nullable', 'date'],
        ]);

        $poll->fill(array_filter($data, fn ($v) => !is_null($v)));
        $poll->save();

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Link $link, BiolinkBlock $block, BlockPoll $poll)
    {
        $this->authorizeOwner($request, $link, $block);
        abort_if($poll->block_id !== $block->id, 404);
        $poll->delete();
        return response()->json(['ok' => true]);
    }
}
