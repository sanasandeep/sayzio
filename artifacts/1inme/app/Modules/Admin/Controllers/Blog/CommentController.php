<?php

namespace App\Modules\Admin\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\BlogComment;
use App\Modules\Common\Services\BlogSettings;
use App\Modules\Common\Services\NotificationService;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        if (!in_array($status, ['pending', 'approved', 'spam', 'trash'], true)) {
            $status = 'pending';
        }

        $comments = BlogComment::with(['post:id,title,slug'])
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'pending'  => BlogComment::where('status', 'pending')->count(),
            'approved' => BlogComment::where('status', 'approved')->count(),
            'spam'     => BlogComment::where('status', 'spam')->count(),
            'trash'    => BlogComment::where('status', 'trash')->count(),
        ];

        return view('admin.blogs.comments.index', compact('comments', 'status', 'counts'));
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:approve,spam,trash,delete',
            'ids'    => 'required|array',
            'ids.*'  => 'integer|exists:blog_comments,id',
        ]);
        foreach (BlogComment::whereIn('id', $data['ids'])->get() as $c) {
            $this->applyAction($c, $data['action']);
        }
        return back()->with('success', 'Bulk action applied.');
    }

    public function update(Request $request, BlogComment $comment)
    {
        $data = $request->validate([
            'action' => 'required|in:approve,spam,trash,delete',
        ]);
        $this->applyAction($comment, $data['action']);
        return back()->with('success', 'Updated.');
    }

    public function reply(Request $request, BlogComment $comment)
    {
        $admin = Auth::guard('admin')->user();
        if (!$admin || (!$admin->isSuperAdmin() && !$admin->hasPermission('blogs.comments.reply'))) {
            abort(403);
        }
        // Optional role gating from settings.
        $settings = BlogSettings::all();
        $roleSlug = optional($admin->role)->slug;
        if (!$admin->isSuperAdmin() && !empty($settings['reply_role_slugs'])
            && !in_array($roleSlug, $settings['reply_role_slugs'], true)) {
            abort(403, 'Your role is not allowed to reply.');
        }

        $data = $request->validate(['body' => 'required|string|min:2|max:4000']);

        $reply = BlogComment::create([
            'post_id'       => $comment->post_id,
            'parent_id'     => $comment->id,
            'author_type'   => 'admin',
            'author_id'     => $admin->id,
            'author_name'   => $admin->name,
            'author_email'  => $admin->email,
            'author_avatar' => $admin->avatar,
            'body'          => $data['body'],
            'status'        => 'approved',
            'ip_address'    => substr((string) $request->ip(), 0, 64),
            'user_agent'    => substr((string) $request->userAgent(), 0, 250),
        ]);

        $this->notifyOriginalCommenter($comment, $reply);

        return back()->with('success', 'Reply posted.');
    }

    public function edit(Request $request, BlogComment $comment)
    {
        $data = $request->validate([
            'body' => 'required|string|min:1|max:8000',
        ]);
        $comment->update(['body' => $data['body']]);
        return back()->with('success', 'Comment updated.');
    }

    public function destroy(BlogComment $comment)
    {
        $comment->delete();
        return back()->with('success', 'Comment deleted.');
    }

    private function applyAction(BlogComment $c, string $action): void
    {
        $wasPending = $c->status === 'pending';
        switch ($action) {
            case 'approve':
                $c->update(['status' => 'approved']);
                if ($wasPending) {
                    $this->notifyApproval($c);
                }
                break;
            case 'spam':
                $c->update(['status' => 'spam']);
                break;
            case 'trash':
                $c->update(['status' => 'trash']);
                break;
            case 'delete':
                $c->delete();
                break;
        }
    }

    private function notifyApproval(BlogComment $c): void
    {
        try {
            if (in_array($c->author_type, ['user', 'viewer'], true) && $c->author_id) {
                $u = User::find($c->author_id);
                if ($u) {
                    UserNotification::create([
                        'user_id'    => $u->id,
                        'type'       => $c->author_type === 'viewer' ? 'blog_comment_approved_viewer' : 'blog_comment_approved',
                        'data'       => [
                            'subject'     => 'Your comment was approved',
                            'body'        => 'Your comment on "' . optional($c->post)->title . '" is now visible.',
                            'message'     => 'Your comment was approved.',
                            'target_url'  => $c->post ? route('site.blogs.show', $c->post->slug) . '#comment-' . $c->id : null,
                            'target_kind' => $c->author_type, // 'user' | 'viewer'
                        ],
                        'created_at' => now(),
                    ]);
                }
            }
            if ($c->author_email) {
                $url = $c->post ? route('site.blogs.show', $c->post->slug) . '#comment-' . $c->id : url('/');
                Mail::html(
                    '<p>Hi ' . e($c->author_name ?: 'there') . ',</p>' .
                    '<p>Your comment has been approved and is now live.</p>' .
                    '<p><a href="' . e($url) . '">View your comment</a></p>',
                    function ($m) use ($c) {
                        $m->to($c->author_email)->subject('Your comment was approved');
                    }
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Blog approval notify failed', ['error' => $e->getMessage()]);
        }
    }

    private function notifyOriginalCommenter(BlogComment $original, BlogComment $reply): void
    {
        try {
            if (in_array($original->author_type, ['user', 'viewer'], true) && $original->author_id) {
                $u = User::find($original->author_id);
                if ($u) {
                    UserNotification::create([
                        'user_id'    => $u->id,
                        'type'       => $original->author_type === 'viewer' ? 'blog_comment_reply_viewer' : 'blog_comment_reply',
                        'data'       => [
                            'subject'     => 'A staff member replied to your comment',
                            'body'        => $reply->author_name . ' replied to your comment on "' . optional($original->post)->title . '".',
                            'message'     => 'New reply from ' . $reply->author_name,
                            'target_url'  => $original->post ? route('site.blogs.show', $original->post->slug) . '#comment-' . $reply->id : null,
                            'target_kind' => $original->author_type, // 'user' | 'viewer'
                        ],
                        'created_at' => now(),
                    ]);
                }
            }
            if ($original->author_email) {
                $url = $original->post ? route('site.blogs.show', $original->post->slug) . '#comment-' . $reply->id : url('/');
                Mail::html(
                    '<p>Hi ' . e($original->author_name ?: 'there') . ',</p>' .
                    '<p><strong>' . e($reply->author_name) . '</strong> replied to your comment:</p>' .
                    '<blockquote style="border-left:3px solid #7c3aed;padding-left:12px;color:#374151;">' . nl2br(e($reply->body)) . '</blockquote>' .
                    '<p><a href="' . e($url) . '">View the reply</a></p>',
                    function ($m) use ($original) {
                        $m->to($original->author_email)->subject('New reply on your blog comment');
                    }
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Blog reply notify failed', ['error' => $e->getMessage()]);
        }
    }
}
