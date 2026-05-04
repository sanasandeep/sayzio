<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CloudFile;
use App\Modules\User\Models\CloudFileAttachment;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\FeedEvent;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\PostApprovalComment;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceActivityRecorder;
use Illuminate\Http\Request;

class CreatorPostController extends Controller
{
    public function index()
    {
        // Lazily publish any due scheduled posts in this workspace before listing.
        // workspace_owner is bound by SetActiveWorkspace; falls back to auth user.
        $ownerId = app()->bound('workspace_owner') ? app('workspace_owner')->id : auth()->id();
        CreatorPost::publishDuePosts($ownerId);

        $posts = CreatorPost::query()
            ->with(['cloudAttachments.cloudFile', 'approvalComments.user:id,name,avatar', 'approvalDecider:id,name'])
            ->orderByRaw("CASE WHEN approval_status = 'pending_review' THEN 0 ELSE 1 END")
            ->orderByDesc('pinned_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        $workspace = app()->bound('current_workspace') ? app('current_workspace') : null;
        $approvalEnabled = $workspace ? $workspace->postApprovalEnabled() : false;
        $userIsApprover  = $workspace ? $workspace->userCanApprovePosts(auth()->user()) : false;
        $approverRoles   = $workspace ? $workspace->postApproverRoles() : [];

        return view('user.posts.index', compact(
            'posts', 'workspace', 'approvalEnabled', 'userIsApprover', 'approverRoles'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'        => 'nullable|string|max:200',
            'body'         => 'required|string|max:5000',
            'image'        => 'nullable|image|max:5120',
            'scheduled_at' => 'nullable|date|after:now',
            'is_pinned'    => 'nullable|boolean',
            'cloud_file_ids'   => 'nullable|array|max:20',
            'cloud_file_ids.*' => 'integer',
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = '/storage/' . $request->file('image')->store('post-images', 'public');
        }

        $scheduledAt = !empty($data['scheduled_at']) ? \Carbon\Carbon::parse($data['scheduled_at']) : null;
        $isFuture = $scheduledAt && $scheduledAt->isFuture();

        // Attribute the post to the workspace owner (so existing per-creator
        // queries — feed events, follower notifications — keep working) while
        // workspace_id + created_by_user_id are auto-filled by the trait.
        $ownerId   = app()->bound('workspace_owner') ? app('workspace_owner')->id : auth()->id();
        $workspace = app()->bound('current_workspace') ? app('current_workspace') : null;
        $actor     = auth()->user();

        // Approval gate: when the workspace requires approval AND the actor
        // isn't an approver themselves, the post enters the queue instead of
        // going live. We also stash the intended schedule so the publish job
        // can't pick it up before review, and so we can restore it later.
        $needsApproval = $workspace
            && $workspace->postApprovalEnabled()
            && !$workspace->userCanApprovePosts($actor);

        $post = CreatorPost::create([
            'user_id'               => $ownerId,
            'title'                 => $data['title'] ?? null,
            'body'                  => $data['body'],
            'image'                 => $imagePath,
            // Hold the schedule out of the live column until approval.
            'scheduled_at'          => $needsApproval ? null : $scheduledAt,
            'intended_scheduled_at' => $needsApproval ? $scheduledAt : null,
            'published_at'          => ($needsApproval || $isFuture) ? null : now(),
            'approval_status'       => $needsApproval ? CreatorPost::APPROVAL_PENDING : null,
            'approval_requested_at' => $needsApproval ? now() : null,
        ]);

        $this->syncCloudAttachments($post, (array) $request->input('cloud_file_ids', []));

        if ($needsApproval) {
            // Drop the editor's note as the first comment in the thread, and
            // notify everyone who can approve in this workspace.
            PostApprovalComment::create([
                'creator_post_id' => $post->id,
                'user_id'         => $actor->id,
                'action'          => 'submit',
                'body'            => null,
            ]);
            $this->notifyApproversQueueEntered($workspace, $post, $actor);

            return redirect()->route('user.posts.index')->with('success',
                'Sent to your reviewers. You\'ll be notified once it\'s approved.');
        }

        $me = app()->bound('workspace_owner') ? app('workspace_owner') : auth()->user();

        if (!$isFuture) {
            FeedEvent::create([
                'user_id'      => $me->id,
                'type'         => 'post',
                'subject_id'   => $post->id,
                'subject_type' => CreatorPost::class,
                'data'         => ['title' => $post->title, 'body_excerpt' => mb_substr($post->body, 0, 160), 'creator_name' => $me->name, 'creator_avatar' => $me->avatar],
                'occurred_at'  => now(),
            ]);

            $this->notifyFollowersDebounced($me, 'New post: ' . ($post->title ?: mb_substr($post->body, 0, 60)), $post);
        }

        if (!empty($data['is_pinned'])) {
            // Only published posts can be pinned. If this one is scheduled,
            // it will be pinnable from the list once it goes live.
            if (!$isFuture) {
                // Workspace-scoped: clears any other pinned post in this workspace.
                CreatorPost::query()->whereNotNull('pinned_at')->update(['pinned_at' => null]);
                $post->pinned_at = now();
                $post->save();
            }
        }

        $msg = $isFuture
            ? 'Post scheduled for ' . $scheduledAt->format('M j, Y g:i A') . '.'
            : 'Post published to your followers.';

        WorkspaceActivityRecorder::record(
            null, 'post.publish', 'post', $post->id,
            $post->title ?: mb_substr($post->body, 0, 60),
            route('user.posts.index'),
            ['scheduled' => $isFuture],
        );

        return redirect()->route('user.posts.index')->with('success', $msg);
    }

    /**
     * Approve a pending post. Publishes immediately if no schedule was set,
     * otherwise restores the editor's intended schedule so the publish job
     * picks it up at the right time.
     */
    public function approve(Request $request, CreatorPost $post)
    {
        $workspace = app('current_workspace');
        if (! $this->guardApprover($workspace, $request, $post)) {
            return back()->with('error', 'You\'re not allowed to approve posts in this workspace.');
        }
        $data = $request->validate(['note' => 'nullable|string|max:2000']);

        $intended = $post->intended_scheduled_at;
        $isFuture = $intended && $intended->isFuture();

        $post->approval_status              = CreatorPost::APPROVAL_APPROVED;
        $post->approval_decided_at          = now();
        $post->approval_decided_by_user_id  = $request->user()->id;
        $post->scheduled_at                 = $isFuture ? $intended : null;
        $post->intended_scheduled_at        = null;
        $post->published_at                 = $isFuture ? null : now();
        $post->save();

        PostApprovalComment::create([
            'creator_post_id' => $post->id,
            'user_id'         => $request->user()->id,
            'action'          => 'approve',
            'body'            => $data['note'] ?? null,
        ]);

        // Run the same publish-time side-effects an immediate Publish would
        // have. For scheduled posts the existing publishDuePosts() job
        // emits these when the schedule fires.
        if (!$isFuture) {
            $owner = $workspace->owner ?? User::find($workspace->owner_user_id);
            if ($owner) {
                FeedEvent::create([
                    'user_id'      => $owner->id,
                    'type'         => 'post',
                    'subject_id'   => $post->id,
                    'subject_type' => CreatorPost::class,
                    'data'         => [
                        'title'          => $post->title,
                        'body_excerpt'   => mb_substr($post->body, 0, 160),
                        'creator_name'   => $owner->name,
                        'creator_avatar' => $owner->avatar,
                    ],
                    'occurred_at'  => now(),
                ]);
                static::notifyFollowersDebounced(
                    $owner,
                    'New post: ' . ($post->title ?: mb_substr($post->body, 0, 60)),
                    $post
                );
            }
        }

        $this->notifyEditorOfDecision($post, 'approve', $request->user(), $data['note'] ?? null);

        $msg = $isFuture
            ? 'Approved. Will publish on ' . $intended->format('M j, Y g:i A') . '.'
            : 'Approved and published.';
        return back()->with('success', $msg);
    }

    /**
     * Reviewer asks the editor to make changes. The post stays in the
     * workspace as a draft (changes_requested) so the editor can edit and
     * re-submit. A note is required so the editor knows what to change.
     */
    public function requestChanges(Request $request, CreatorPost $post)
    {
        $workspace = app('current_workspace');
        if (! $this->guardApprover($workspace, $request, $post)) {
            return back()->with('error', 'You\'re not allowed to review posts in this workspace.');
        }
        $data = $request->validate(['note' => 'required|string|max:2000']);

        $post->approval_status              = CreatorPost::APPROVAL_CHANGES;
        $post->approval_decided_at          = now();
        $post->approval_decided_by_user_id  = $request->user()->id;
        $post->save();

        PostApprovalComment::create([
            'creator_post_id' => $post->id,
            'user_id'         => $request->user()->id,
            'action'          => 'changes_requested',
            'body'            => $data['note'],
        ]);

        $this->notifyEditorOfDecision($post, 'changes_requested', $request->user(), $data['note']);
        return back()->with('success', 'Sent back to the editor with your note.');
    }

    /**
     * Reject a pending post. The post stays in the workspace as a rejected
     * draft (so the editor can see the reason) but never publishes.
     */
    public function reject(Request $request, CreatorPost $post)
    {
        $workspace = app('current_workspace');
        if (! $this->guardApprover($workspace, $request, $post)) {
            return back()->with('error', 'You\'re not allowed to reject posts in this workspace.');
        }
        $data = $request->validate(['note' => 'nullable|string|max:2000']);

        $post->approval_status              = CreatorPost::APPROVAL_REJECTED;
        $post->approval_decided_at          = now();
        $post->approval_decided_by_user_id  = $request->user()->id;
        // Drop any held schedule — a rejected post should not auto-publish
        // even if the editor later edits it without removing the date.
        $post->intended_scheduled_at        = null;
        $post->scheduled_at                 = null;
        $post->save();

        PostApprovalComment::create([
            'creator_post_id' => $post->id,
            'user_id'         => $request->user()->id,
            'action'          => 'reject',
            'body'            => $data['note'] ?? null,
        ]);

        $this->notifyEditorOfDecision($post, 'reject', $request->user(), $data['note'] ?? null);
        return back()->with('success', 'Rejected. The editor was notified.');
    }

    /**
     * Re-submit a draft (rejected or changes_requested) for review. Only
     * the original author can resubmit. Resets the decision metadata and
     * notifies the approvers again.
     */
    public function resubmit(Request $request, CreatorPost $post)
    {
        $workspace = app('current_workspace');
        $actor     = $request->user();

        if (!$workspace || !$workspace->postApprovalEnabled()) {
            return back()->with('error', 'Approval is no longer required in this workspace.');
        }
        if ((int) $post->created_by_user_id !== (int) $actor->id) {
            return back()->with('error', 'Only the original author can resubmit this post.');
        }
        if (!in_array($post->approval_status, [CreatorPost::APPROVAL_CHANGES, CreatorPost::APPROVAL_REJECTED], true)) {
            return back()->with('error', 'This post can\'t be resubmitted in its current state.');
        }
        $data = $request->validate(['note' => 'nullable|string|max:2000']);

        $post->approval_status              = CreatorPost::APPROVAL_PENDING;
        $post->approval_requested_at        = now();
        $post->approval_decided_at          = null;
        $post->approval_decided_by_user_id  = null;
        $post->save();

        PostApprovalComment::create([
            'creator_post_id' => $post->id,
            'user_id'         => $actor->id,
            'action'          => 'submit',
            'body'            => $data['note'] ?? null,
        ]);

        $this->notifyApproversQueueEntered($workspace, $post, $actor);
        return back()->with('success', 'Re-sent for review.');
    }

    /** Add a plain reply to the threaded approval discussion. */
    public function comment(Request $request, CreatorPost $post)
    {
        $workspace = app('current_workspace');
        $actor     = $request->user();
        // Author of the post and any approver can comment in the thread.
        $isAuthor   = (int) $post->created_by_user_id === (int) $actor->id;
        $isApprover = $workspace && $workspace->userCanApprovePosts($actor);
        if (!$isAuthor && !$isApprover) {
            return back()->with('error', 'You don\'t have access to this thread.');
        }
        $data = $request->validate(['body' => 'required|string|max:2000']);

        PostApprovalComment::create([
            'creator_post_id' => $post->id,
            'user_id'         => $actor->id,
            'action'          => null,
            'body'            => $data['body'],
        ]);

        return back()->with('success', 'Comment added.');
    }

    public function pin(CreatorPost $post)
    {
        // The route binding + workspace global scope guarantees this post belongs
        // to the active workspace. The `workspace.can:posts.edit` middleware on
        // the route is the authorization gate.
        if (!$post->isPublished()) {
            return back()->with('error', 'You can only pin a published post.');
        }
        // Unpin any other pinned post in this workspace.
        CreatorPost::query()
            ->whereNotNull('pinned_at')
            ->where('id', '!=', $post->id)
            ->update(['pinned_at' => null]);
        $post->pinned_at = now();
        $post->save();
        WorkspaceActivityRecorder::record(null, 'post.pin', 'post', $post->id, $post->title ?: mb_substr($post->body, 0, 60), route('user.posts.index'));
        return back()->with('success', 'Post pinned.');
    }

    public function unpin(CreatorPost $post)
    {
        // Auth handled by route middleware + global workspace scope.
        $post->pinned_at = null;
        $post->save();
        WorkspaceActivityRecorder::record(null, 'post.unpin', 'post', $post->id, $post->title ?: mb_substr($post->body, 0, 60), route('user.posts.index'));
        return back()->with('success', 'Post unpinned.');
    }

    public function destroy(CreatorPost $post)
    {
        // Auth handled by route middleware + global workspace scope.
        $label = $post->title ?: mb_substr($post->body, 0, 60);
        $postId = $post->id;
        $post->approvalComments()->delete();
        $post->delete();
        WorkspaceActivityRecorder::record(null, 'post.delete', 'post', $postId, $label, route('user.posts.index'));
        return back()->with('success', 'Post deleted.');
    }

    /**
     * Shared guard: the post must still be in a reviewable state and the
     * actor must hold the approver role for the active workspace.
     */
    protected function guardApprover(?Workspace $workspace, Request $request, CreatorPost $post): bool
    {
        if (!$workspace) return false;
        if (!$workspace->userCanApprovePosts($request->user())) return false;
        // Only pending posts can transition. Re-approving an already
        // approved/rejected post is a no-op rather than an error.
        if ($post->approval_status !== CreatorPost::APPROVAL_PENDING) return false;
        return true;
    }

    /** Notify every workspace member with an approver role + the owner. */
    protected function notifyApproversQueueEntered(Workspace $workspace, CreatorPost $post, User $actor): void
    {
        $recipients = collect();

        // Owner is always an approver.
        if ($workspace->owner_user_id && (int) $workspace->owner_user_id !== (int) $actor->id) {
            $owner = User::find($workspace->owner_user_id);
            if ($owner) $recipients->push($owner);
        }

        $approverRoles = $workspace->postApproverRoles();
        if (!empty($approverRoles)) {
            $memberUserIds = WorkspaceMember::where('workspace_id', $workspace->id)
                ->whereIn('role', $approverRoles)
                ->where('user_id', '!=', $actor->id)
                ->pluck('user_id');
            if ($memberUserIds->isNotEmpty()) {
                $recipients = $recipients->concat(User::whereIn('id', $memberUserIds)->get());
            }
        }

        $recipients = $recipients->unique('id');
        if ($recipients->isEmpty()) return;

        $excerpt = mb_substr($post->title ?: $post->body, 0, 80);
        $message = "{$actor->name} sent a post for review in {$workspace->name}: \"{$excerpt}\"";

        foreach ($recipients as $recipient) {
            UserNotification::create([
                'user_id'    => $recipient->id,
                'type'       => 'post_review_request',
                'data'       => [
                    'workspace_id'   => $workspace->id,
                    'workspace_name' => $workspace->name,
                    'post_id'        => $post->id,
                    'editor_id'      => $actor->id,
                    'editor_name'    => $actor->name,
                    'message'        => $message,
                    'url'            => route('user.posts.index'),
                ],
                'created_at' => now(),
                'emailed_at' => null,
            ]);

            try {
                \Mail::raw(
                    $message . "\n\nReview it: " . route('user.posts.index'),
                    function ($m) use ($recipient) {
                        $m->to($recipient->email)
                          ->subject('A post is waiting for your review');
                    }
                );
                UserNotification::where('user_id', $recipient->id)
                    ->where('type', 'post_review_request')
                    ->whereNull('emailed_at')
                    ->latest('id')->first()
                    ?->update(['emailed_at' => now()]);
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Notify the editor (post author) of a reviewer's decision. Falls back
     * to the workspace owner if the author has been removed.
     */
    protected function notifyEditorOfDecision(CreatorPost $post, string $action, User $reviewer, ?string $note): void
    {
        $editor = $post->created_by_user_id ? User::find($post->created_by_user_id) : null;
        if (!$editor) return;
        // Don't ping yourself if you reviewed your own post.
        if ((int) $editor->id === (int) $reviewer->id) return;

        $verbs = [
            'approve'           => 'approved',
            'changes_requested' => 'requested changes on',
            'reject'            => 'rejected',
        ];
        $verb = $verbs[$action] ?? 'updated';
        $excerpt = mb_substr($post->title ?: $post->body, 0, 80);
        $message = "{$reviewer->name} {$verb} your post: \"{$excerpt}\"";
        if ($note) $message .= " — {$note}";

        $notif = UserNotification::create([
            'user_id'    => $editor->id,
            'type'       => 'post_review_decision',
            'data'       => [
                'post_id'       => $post->id,
                'action'        => $action,
                'reviewer_id'   => $reviewer->id,
                'reviewer_name' => $reviewer->name,
                'note'          => $note,
                'message'       => $message,
                'url'           => route('user.posts.index'),
            ],
            'created_at' => now(),
            'emailed_at' => null,
        ]);

        try {
            \Mail::raw(
                $message . "\n\nOpen your posts: " . route('user.posts.index'),
                function ($m) use ($editor) {
                    $m->to($editor->email)->subject('Update on your post review');
                }
            );
            $notif->emailed_at = now();
            $notif->save();
        } catch (\Throwable $e) {}
    }

    /**
     * Attach selected cloud-library files to the given post. Only files in
     * the active workspace (and therefore visible via the workspace global
     * scope on CloudFile) end up attached.
     */
    protected function syncCloudAttachments(CreatorPost $post, array $cloudFileIds): void
    {
        $cloudFileIds = array_values(array_unique(array_filter(array_map('intval', $cloudFileIds))));
        if (empty($cloudFileIds)) return;
        $valid = CloudFile::query()->whereIn('id', $cloudFileIds)->pluck('id');
        foreach ($valid as $cfId) {
            CloudFileAttachment::firstOrCreate([
                'cloud_file_id'   => $cfId,
                'attachable_type' => CreatorPost::class,
                'attachable_id'   => $post->id,
            ], [
                'attached_by_user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Record creator activity for each follower. Per-follower delivery is
     * controlled by their `follower_updates_mode` preference:
     *   - 'instant' : email immediately (and create the in-app notification)
     *   - 'digest'  : create the in-app notification; the daily
     *                 `followers:send-digest` job batches one email per day
     *   - 'off'     : create no in-app row and send no email
     *
     * The historical per-creator-per-day debounce was removed: the digest
     * mode (which is now the default) gives the same "at most one email per
     * day" behaviour without dropping in-app notifications.
     *
     * Method name kept for backwards compatibility with existing call sites
     * (LinkController, ProfileController, etc.).
     */
    public static function notifyFollowersDebounced($creator, string $message, ?CreatorPost $post = null): void
    {
        $followerIds = Follow::where('creator_id', $creator->id)->pluck('follower_id');
        if ($followerIds->isEmpty()) return;

        $followers = \App\Modules\User\Models\User::whereIn('id', $followerIds)->get();

        foreach ($followers as $follower) {
            $mode = self::resolveFollowerMode($follower);
            if ($mode === 'off') continue;

            $data = [
                'creator_id'     => (int) $creator->id,
                'creator_name'   => $creator->name,
                'creator_avatar' => $creator->avatar,
                'message'        => $message,
            ];
            if ($post) {
                $data['post_id']    = (int) $post->id;
                $data['post_image'] = $post->image;
            }

            $notif = UserNotification::create([
                'user_id' => $follower->id,
                'type'    => 'follower_update',
                'data'    => $data,
                'created_at' => now(),
                'emailed_at' => null,
            ]);

            if ($mode === 'instant') {
                try {
                    \Mail::raw("{$creator->name}: {$message}", function ($m) use ($follower) {
                        $m->to($follower->email)->subject('New activity from a creator you follow');
                    });
                    $notif->emailed_at = now();
                    $notif->save();
                } catch (\Throwable $e) {}
            }
            // 'digest' mode: leave emailed_at null so the daily job picks it up.
        }
    }

    /**
     * Resolve the effective notification mode for a follower, with safe
     * fallback for users on rows that pre-date the new column.
     */
    private static function resolveFollowerMode($follower): string
    {
        $mode = $follower->follower_updates_mode ?? null;
        if (in_array($mode, ['instant', 'digest', 'off'], true)) return $mode;
        return $follower->notify_follower_updates ? 'digest' : 'off';
    }
}
