<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Models\ViewerDmConversation;
use App\Modules\Common\Models\ViewerDmMessage;
use App\Modules\User\Models\InboxThread;
use App\Modules\User\Models\InboxThreadAssignment;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Inbox API: DM conversations on biolinks the authenticated user owns.
 *
 * The web inbox UI (`User\InboxController`) is the source of truth for
 * the underlying schema; this exposes a JSON-friendly view used by the
 * mobile app.
 *
 * Performance notes:
 * - accessibleOwnerIds() is memoised per request (keyed by caller user-id)
 *   since it resolves the workspace graph with 4 queries, and some actions
 *   (assign, setStatus) call it multiple times within one request.
 * - workspaceIdForOwner() is similarly memoised.
 */
class InboxController extends Controller
{
    use ApiResponses;

    /**
     * Per-request memoisation cache for accessibleOwnerIds().
     * Key = calling user_id (int), value = int[].
     *
     * @var array<int, int[]>
     */
    private array $ownerIdCache = [];

    /**
     * Per-request memoisation cache for workspaceIdForOwner().
     * Key = owner user_id (int), value = workspace_id (int, 0 if none).
     *
     * @var array<int, int>
     */
    private array $workspaceIdCache = [];

    /**
     * Legacy summary endpoint kept for older mobile clients.
     */
    public function threads(Request $request)
    {
        if (!\Schema::hasTable('viewer_dm_conversations')) {
            return $this->ok(['items' => []]);
        }

        $ownerIds = $this->accessibleOwnerIds((int) $request->user()->id);
        $rows = ViewerDmConversation::whereIn('owner_user_id', $ownerIds)
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();

        return $this->ok([
            'items' => $rows->map(fn ($c) => $this->transform($c))->all(),
        ]);
    }

    public function conversations(Request $request)
    {
        if (!\Schema::hasTable('viewer_dm_conversations')) {
            return $this->ok(['items' => [], 'meta' => ['unread' => 0]]);
        }

        $userId = (int) $request->user()->id;
        $ownerIds = $this->accessibleOwnerIds($userId);

        // "Assigned to me" filter: restrict to conversations whose
        // matching unified inbox_threads row is assigned to the caller.
        $assigneeFilter = $request->string('assignee')->toString();
        $idsAssignedToMe = null;
        if ($assigneeFilter === 'me') {
            $idsAssignedToMe = InboxThread::where('source_type', 'viewer_dm')
                ->where('assignee_user_id', $userId)
                ->pluck('source_id')->all();
            if (empty($idsAssignedToMe)) {
                return $this->ok(['items' => [], 'meta' => ['current_page' => 1, 'per_page' => 30, 'total' => 0, 'last_page' => 1, 'unread' => 0]]);
            }
        }

        $page = ViewerDmConversation::with(['link:id,alias,title', 'viewer:id,name,avatar'])
            ->whereIn('owner_user_id', $ownerIds)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($idsAssignedToMe !== null, fn ($q) => $q->whereIn('id', $idsAssignedToMe))
            ->orderByDesc('last_message_at')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 30))));

        $unread = ViewerDmConversation::whereIn('owner_user_id', $ownerIds)
            ->where('owner_unread_count', '>', 0)
            ->count();

        $threadsByConv = InboxThread::where('source_type', 'viewer_dm')
            ->whereIn('source_id', collect($page->items())->pluck('id')->all())
            ->with('assignee:id,name')
            ->get()->keyBy('source_id');

        return $this->ok([
            'items' => collect($page->items())->map(fn ($c) => $this->transform($c, $threadsByConv->get($c->id)))->all(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
                'unread'       => $unread,
            ],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $userId = (int) $request->user()->id;
        $ownerIds = $this->accessibleOwnerIds($userId);
        $c = ViewerDmConversation::with(['link:id,alias,title', 'viewer:id,name,avatar', 'messages'])
            ->whereIn('owner_user_id', $ownerIds)
            ->find($id);
        if (!$c) return $this->notFound('Conversation not found');

        // Only the owner of the biolink should clear their unread badge;
        // teammates viewing should not.
        if ((int) $c->owner_user_id === $userId && $c->owner_unread_count > 0) {
            $c->forceFill(['owner_unread_count' => 0])->save();
        }

        $thread = InboxThread::with('assignee:id,name')
            ->where('source_type', 'viewer_dm')->where('source_id', $c->id)->first();

        return $this->ok([
            'conversation' => $this->transform($c, $thread),
            'messages' => $c->messages->map(fn ($m) => [
                'id'           => $m->id,
                'sender_type'  => $m->sender_type,
                'body'         => $m->body,
                'created_at'   => optional($m->created_at)->toIso8601String(),
                'read_at'      => optional($m->read_at)->toIso8601String(),
            ])->all(),
        ]);
    }

    public function reply(Request $request, int $id)
    {
        $data = $request->validate(['body' => 'required|string|max:5000']);

        $c = ViewerDmConversation::whereIn('owner_user_id', $this->accessibleOwnerIds((int) $request->user()->id))->find($id);
        if (!$c) return $this->notFound('Conversation not found');
        if ($c->isBlocked()) return $this->forbidden('Conversation is blocked');

        $msg = DB::transaction(function () use ($c, $data, $request) {
            $msg = ViewerDmMessage::create([
                'conversation_id' => $c->id,
                'sender_type'     => 'owner',
                'sender_user_id'  => $request->user()->id,
                'body'            => $data['body'],
            ]);
            $c->forceFill([
                'owner_msg_count'      => ($c->owner_msg_count ?? 0) + 1,
                'owner_replied'        => true,
                'last_message_at'      => now(),
                'last_message_preview' => mb_substr($data['body'], 0, 160),
                'last_sender'          => 'owner',
                'viewer_unread_count'  => ($c->viewer_unread_count ?? 0) + 1,
            ])->save();
            return $msg;
        });

        return $this->created([
            'message' => [
                'id'          => $msg->id,
                'sender_type' => $msg->sender_type,
                'body'        => $msg->body,
                'created_at'  => optional($msg->created_at)->toIso8601String(),
            ],
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        // Destruction stays restricted to the actual biolink owner — a
        // teammate shouldn't be able to permanently delete somebody
        // else's conversation history.
        $c = ViewerDmConversation::where('owner_user_id', $request->user()->id)->find($id);
        if (!$c) return $this->notFound('Conversation not found');
        DB::transaction(function () use ($c) {
            ViewerDmMessage::where('conversation_id', $c->id)->delete();
            $c->delete();
        });
        return $this->noContent();
    }

    public function setStatus(Request $request, int $id)
    {
        $data = $request->validate(['status' => 'required|in:open,archived,blocked']);
        $userId = (int) $request->user()->id;
        $c = ViewerDmConversation::whereIn('owner_user_id', $this->accessibleOwnerIds($userId))->find($id);
        if (!$c) return $this->notFound('Conversation not found');

        $previousStatus = $c->status;
        $c->forceFill([
            'status'     => $data['status'],
            'blocked_at' => $data['status'] === 'blocked' ? now() : null,
        ])->save();

        // Mirror the unified inbox: keep the linked thread row in sync and
        // log a `resolved` history row when the conversation leaves the
        // open queue (archived or blocked) so we capture the assignee at
        // close time.
        $thread = $this->threadFor($c);
        if ($thread) {
            $thread->forceFill([
                'status' => $data['status'] === 'open' ? 'open' : 'archived',
            ])->save();

            $isResolution = in_array($data['status'], ['archived', 'blocked'], true)
                && $previousStatus !== $data['status'];
            if ($isResolution && $thread->assignee_user_id) {
                InboxThreadAssignment::create([
                    'thread_id'     => $thread->id,
                    'from_user_id'  => (int) $thread->assignee_user_id,
                    'to_user_id'    => (int) $thread->assignee_user_id,
                    'actor_user_id' => $userId,
                    'action'        => 'resolved',
                    'note'          => $data['status'],
                    'created_at'    => now(),
                ]);
            }
        }

        return $this->ok(['conversation' => $this->transform($c->fresh(['link', 'viewer']), $thread?->fresh('assignee'))]);
    }

    /**
     * Find or lazily-create the unified `inbox_threads` row for a DM
     * conversation. Workspace binding follows the **conversation owner**
     * (the biolink owner), NOT the acting teammate — otherwise a
     * teammate who is a member of multiple workspaces could create the
     * thread under the wrong workspace and leak it across tenants.
     */
    protected function threadFor(ViewerDmConversation $c): ?InboxThread
    {
        $thread = InboxThread::firstOrNew(['source_type' => 'viewer_dm', 'source_id' => $c->id]);
        if ($thread->exists) return $thread;

        $ownerId = (int) $c->owner_user_id;
        $wsId = $this->workspaceIdForOwner($ownerId);
        if (!$wsId) return null;

        $thread->fill([
            'workspace_id'    => $wsId,
            'user_id'         => $ownerId,
            'channel'         => 'biolink_dm',
            'subject'         => 'DM via /' . ($c->link?->alias ?? 'biolink'),
            'preview'         => (string) $c->last_message_preview,
            'sender_name'     => $c->viewer?->name ?: 'Viewer',
            'last_message_at' => $c->last_message_at,
            'category'        => 'lead',
            'status'          => 'open',
        ])->save();
        return $thread;
    }

    /**
     * Assign / reassign / unassign a biolink-DM conversation to a workspace
     * teammate. Mirrors the web unified-inbox `assign` action by writing
     * through the matching `inbox_threads` row, recording history, and
     * notifying the previous + new assignee.
     */
    public function assign(Request $request, int $id)
    {
        $data = $request->validate([
            'assignee_user_id' => 'nullable|integer',
            'note'             => 'nullable|string|max:500',
        ]);

        $userId = (int) $request->user()->id;
        $c = ViewerDmConversation::whereIn('owner_user_id', $this->accessibleOwnerIds($userId))->find($id);
        if (!$c) return $this->notFound('Conversation not found');

        $newId = isset($data['assignee_user_id']) && $data['assignee_user_id'] ? (int) $data['assignee_user_id'] : null;
        $note  = isset($data['note']) ? trim($data['note']) : '';

        // The thread row may not exist yet for very fresh conversations
        // that haven't been hit by InboxThreadSync; create a minimal one
        // so assignment + history work without waiting for a web visit.
        // Workspace is resolved from the conversation owner so multi-
        // workspace teammates can't accidentally create it in the wrong
        // tenant.
        $thread = $this->threadFor($c);
        if (!$thread) return $this->forbidden('No workspace available for assignment.');

        // The acting teammate must be in the thread's workspace too.
        if (!$this->teammateExists((int) $thread->workspace_id, $userId)) {
            return $this->forbidden('You do not have access to this workspace.');
        }

        if ($newId && !$this->teammateExists($thread->workspace_id, $newId)) {
            return $this->forbidden('That teammate is not in this workspace.');
        }

        $oldId = $thread->assignee_user_id ? (int) $thread->assignee_user_id : null;
        if ($oldId !== $newId || $note !== '') {
            $thread->forceFill(['assignee_user_id' => $newId])->save();

            $action = match (true) {
                $newId === null && $oldId !== null => 'unassign',
                $newId !== null && $oldId === null => 'assign',
                default => 'reassign',
            };
            InboxThreadAssignment::create([
                'thread_id'     => $thread->id,
                'from_user_id'  => $oldId,
                'to_user_id'    => $newId,
                'actor_user_id' => $userId,
                'action'        => $action,
                'note'          => $note !== '' ? $note : null,
                'created_at'    => now(),
            ]);

            $actorName = optional($request->user())->name ?? 'A teammate';
            $subject = $thread->subject ?: 'an inbox thread';
            if ($newId && $newId !== $userId) {
                UserNotification::create([
                    'user_id'    => $newId,
                    'type'       => 'inbox_assigned',
                    'data'       => ['message' => $actorName . ' assigned you a thread: ' . $subject, 'thread_id' => $thread->id, 'note' => $note ?: null],
                    'created_at' => now(),
                ]);
            }
            if ($oldId && $oldId !== $userId && $oldId !== $newId) {
                UserNotification::create([
                    'user_id'    => $oldId,
                    'type'       => 'inbox_unassigned',
                    'data'       => ['message' => $actorName . ' reassigned a thread you were handling: ' . $subject, 'thread_id' => $thread->id, 'note' => $note ?: null],
                    'created_at' => now(),
                ]);
            }
        }

        return $this->ok(['conversation' => $this->transform($c->fresh(['link', 'viewer']), $thread->fresh('assignee'))]);
    }

    /**
     * Workspace teammates available to assign a thread to. Includes the
     * caller's owned workspace AND any workspaces they are a member of —
     * non-owner teammates need to see the full team, not an empty list.
     * Owners are listed first per workspace, deduped across workspaces.
     */
    public function teammates(Request $request)
    {
        $userId = (int) $request->user()->id;

        $workspaceIds = collect();
        $owned = Workspace::where('owner_user_id', $userId)->pluck('id');
        $member = WorkspaceMember::where('user_id', $userId)->pluck('workspace_id');
        $workspaceIds = $owned->merge($member)->unique()->values();
        if ($workspaceIds->isEmpty()) return $this->ok(['items' => []]);

        $rows = [];
        $seen = [];
        foreach (Workspace::whereIn('id', $workspaceIds)->get() as $ws) {
            if ($ws->owner_user_id && empty($seen[(int) $ws->owner_user_id])) {
                $owner = User::find($ws->owner_user_id);
                if ($owner) {
                    $rows[] = ['id' => (int) $owner->id, 'name' => $owner->name . ' (owner)'];
                    $seen[(int) $owner->id] = true;
                }
            }
            $members = WorkspaceMember::with('user:id,name')->where('workspace_id', $ws->id)->get();
            foreach ($members as $m) {
                if ($m->user && empty($seen[(int) $m->user->id])) {
                    $rows[] = ['id' => (int) $m->user->id, 'name' => $m->user->name];
                    $seen[(int) $m->user->id] = true;
                }
            }
        }
        return $this->ok(['items' => $rows]);
    }

    /**
     * Resolve the primary workspace ID for a given owner user.
     * Memoised per request to avoid repeated Workspace queries when
     * threadFor() or assign() is invoked more than once per request.
     */
    protected function workspaceIdForOwner(int $userId): int
    {
        if (array_key_exists($userId, $this->workspaceIdCache)) {
            return $this->workspaceIdCache[$userId];
        }

        $ws = Workspace::where('owner_user_id', $userId)->first();
        if ($ws) {
            return $this->workspaceIdCache[$userId] = (int) $ws->id;
        }
        // Fall back to the first workspace the user is a member of so
        // non-owner teammates can still create the linked thread row.
        $member = WorkspaceMember::where('user_id', $userId)->first();
        return $this->workspaceIdCache[$userId] = $member ? (int) $member->workspace_id : 0;
    }

    /**
     * The set of biolink-owner user IDs whose DM conversations the
     * caller can see/manage. This is the union of every workspace the
     * caller belongs to (owned + member): every owner of those
     * workspaces and every member of those workspaces. The caller is
     * always included so legacy single-user setups keep working.
     *
     * Memoised per request (keyed by caller user-id) because some
     * actions (assign, setStatus) call this method multiple times
     * within one request cycle.
     */
    protected function accessibleOwnerIds(int $userId): array
    {
        if (array_key_exists($userId, $this->ownerIdCache)) {
            return $this->ownerIdCache[$userId];
        }

        $ids = [$userId];

        $ownedWorkspaceIds  = Workspace::where('owner_user_id', $userId)->pluck('id');
        $memberWorkspaceIds = WorkspaceMember::where('user_id', $userId)->pluck('workspace_id');
        $workspaceIds = $ownedWorkspaceIds->merge($memberWorkspaceIds)->unique();

        if ($workspaceIds->isNotEmpty()) {
            $owners  = Workspace::whereIn('id', $workspaceIds)->pluck('owner_user_id');
            $members = WorkspaceMember::whereIn('workspace_id', $workspaceIds)->pluck('user_id');
            $ids = array_values(array_unique(array_filter(array_merge(
                $ids,
                $owners->all(),
                $members->all(),
            ))));
        }

        return $this->ownerIdCache[$userId] = $ids;
    }

    protected function teammateExists(int $workspaceId, int $userId): bool
    {
        $ws = Workspace::find($workspaceId);
        if (!$ws) return false;
        if ((int) $ws->owner_user_id === $userId) return true;
        return WorkspaceMember::where('workspace_id', $workspaceId)->where('user_id', $userId)->exists();
    }

    protected function transform(ViewerDmConversation $c, ?InboxThread $thread = null): array
    {
        return [
            'id'                  => $c->id,
            'link_id'             => (int) $c->link_id,
            'link_alias'          => $c->link?->alias,
            'link_title'          => $c->link?->title,
            'viewer_user_id'      => $c->viewer_user_id ? (int) $c->viewer_user_id : null,
            'viewer_name'         => $c->viewer?->name,
            'viewer_avatar'       => $c->viewer?->avatar,
            'status'              => $c->status ?? 'open',
            'last_message_at'     => optional($c->last_message_at)->toIso8601String(),
            'last_message_preview'=> $c->last_message_preview,
            'last_sender'         => $c->last_sender,
            'owner_unread_count'  => (int) ($c->owner_unread_count ?? 0),
            'viewer_msg_count'    => (int) ($c->viewer_msg_count ?? 0),
            'owner_msg_count'     => (int) ($c->owner_msg_count ?? 0),
            'assignee_user_id'    => $thread && $thread->assignee_user_id ? (int) $thread->assignee_user_id : null,
            'assignee_name'       => $thread && $thread->assignee ? $thread->assignee->name : null,
        ];
    }
}
