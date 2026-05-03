<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\BlockComment;
use App\Modules\User\Models\CommunityMember;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;

/**
 * Creator-side moderation surface for comments left on any block.
 * Lists comments, lets the creator hide/restore/delete, lock/unlock
 * threads, pin a comment, and ban a poster's display name.
 */
class BlockCommentController extends Controller
{
    private function authorize(Request $request, Link $link, BiolinkBlock $block): void
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        abort_if($block->link_id !== $link->id, 404);
    }

    public function index(Request $request, Link $link, BiolinkBlock $block)
    {
        $this->authorize($request, $link, $block);

        $comments = BlockComment::query()
            ->where('block_id', $block->id)
            ->orderByDesc('id')
            ->paginate(50);

        return view('user.comments.index', compact('link', 'block', 'comments'));
    }

    public function update(Request $request, Link $link, BiolinkBlock $block, BlockComment $comment)
    {
        $this->authorize($request, $link, $block);
        abort_if($comment->block_id !== $block->id, 404);

        $data = $request->validate([
            'status'    => ['nullable', 'in:visible,hidden,spam,deleted'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_locked' => ['nullable', 'boolean'],
        ]);

        $comment->fill(array_filter($data, fn ($v) => $v !== null))->save();
        return back()->with('success', 'Comment updated.');
    }

    public function destroy(Request $request, Link $link, BiolinkBlock $block, BlockComment $comment)
    {
        $this->authorize($request, $link, $block);
        abort_if($comment->block_id !== $block->id, 404);

        $comment->delete();
        return back()->with('success', 'Comment deleted.');
    }

    public function banAuthor(Request $request, Link $link, BiolinkBlock $block, BlockComment $comment)
    {
        $this->authorize($request, $link, $block);
        abort_if($comment->block_id !== $block->id, 404);

        // Per-link ban: mark the matching CommunityMember (if any) as
        // banned so they're rejected on future joins/posts on this
        // creator's block. We deliberately do NOT write to the global
        // Admin BannedName list — that's a platform-wide moderation
        // surface that only admins can update.
        if ($comment->author_email) {
            CommunityMember::query()
                ->withoutGlobalScope('workspace')
                ->where('link_id', $link->id)
                ->where('email', $comment->author_email)
                ->update(['status' => 'banned']);
        }

        // Hide every comment by this author across this block.
        BlockComment::query()
            ->where('block_id', $block->id)
            ->when($comment->author_email, fn ($q) => $q->where('author_email', $comment->author_email))
            ->when(!$comment->author_email && $comment->author_name, fn ($q) => $q->where('author_name', $comment->author_name))
            ->update(['status' => 'hidden']);

        return back()->with('success', 'Author banned and existing comments hidden.');
    }
}
