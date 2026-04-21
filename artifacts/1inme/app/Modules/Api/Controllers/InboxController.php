<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Models\ViewerDmConversation;
use App\Modules\Common\Models\ViewerDmMessage;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Inbox API: DM conversations on biolinks the authenticated user owns.
 *
 * The web inbox UI (`User\InboxController`) is the source of truth for
 * the underlying schema; this exposes a JSON-friendly view used by the
 * mobile app.
 */
class InboxController extends Controller
{
    use ApiResponses;

    /**
     * Legacy summary endpoint kept for older mobile clients.
     */
    public function threads(Request $request)
    {
        if (!\Schema::hasTable('viewer_dm_conversations')) {
            return $this->ok(['items' => []]);
        }

        $userId = $request->user()->id;
        $rows = ViewerDmConversation::where('owner_user_id', $userId)
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

        $userId = $request->user()->id;
        $page = ViewerDmConversation::with(['link:id,alias,title', 'viewer:id,name,avatar'])
            ->where('owner_user_id', $userId)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('last_message_at')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 30))));

        $unread = ViewerDmConversation::where('owner_user_id', $userId)
            ->where('owner_unread_count', '>', 0)
            ->count();

        return $this->ok([
            'items' => collect($page->items())->map(fn ($c) => $this->transform($c))->all(),
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
        $c = ViewerDmConversation::with(['link:id,alias,title', 'viewer:id,name,avatar', 'messages'])
            ->where('owner_user_id', $request->user()->id)
            ->find($id);
        if (!$c) return $this->notFound('Conversation not found');

        if ($c->owner_unread_count > 0) {
            $c->forceFill(['owner_unread_count' => 0])->save();
        }

        return $this->ok([
            'conversation' => $this->transform($c),
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

        $c = ViewerDmConversation::where('owner_user_id', $request->user()->id)->find($id);
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
        $c = ViewerDmConversation::where('owner_user_id', $request->user()->id)->find($id);
        if (!$c) return $this->notFound('Conversation not found');
        $c->forceFill([
            'status'     => $data['status'],
            'blocked_at' => $data['status'] === 'blocked' ? now() : null,
        ])->save();
        return $this->ok(['conversation' => $this->transform($c->fresh(['link', 'viewer']))]);
    }

    protected function transform(ViewerDmConversation $c): array
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
        ];
    }
}
