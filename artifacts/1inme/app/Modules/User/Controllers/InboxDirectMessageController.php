<?php

namespace App\Modules\User\Controllers;

use App\Modules\Common\Models\ViewerDmConversation;
use App\Modules\Common\Models\ViewerDmMessage;
use App\Modules\Common\Models\ViewerDmUserBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creator dashboard side of the Direct Message inbox.
 */
class InboxDirectMessageController
{
    public function index(Request $request)
    {
        $userId = workspace_owner_id();

        $tab = $request->get('tab', 'active'); // active|blocked

        $query = ViewerDmConversation::with(['viewer:id,name,email,profile_picture', 'link:id,alias'])
            ->where('owner_user_id', $userId);

        if ($tab === 'blocked') {
            $query->where('status', 'blocked');
        } else {
            $query->where('status', 'active');
        }

        $conversations = $query->orderByDesc('last_message_at')->paginate(25);
        $unreadTotal   = ViewerDmConversation::where('owner_user_id', $userId)
                            ->where('status', 'active')
                            ->sum('owner_unread_count');

        return view('user.inbox.dms.index', [
            'conversations' => $conversations,
            'unreadTotal'   => (int) $unreadTotal,
            'tab'           => $tab,
        ]);
    }

    public function thread(Request $request, ViewerDmConversation $conversation)
    {
        abort_unless((int) $conversation->owner_user_id === (int) workspace_owner_id(), 404);

        $conversation->load(['viewer:id,name,email,profile_picture', 'link:id,alias']);
        $messages = $conversation->messages()->limit(500)->get();

        // Mark viewer-sent messages as read by the owner.
        if ($conversation->owner_unread_count > 0) {
            $conversation->messages()->where('sender_type', 'viewer')->whereNull('read_at')->update(['read_at' => now()]);
            $conversation->owner_unread_count = 0;
            $conversation->save();
        }

        return view('user.inbox.dms.thread', [
            'conversation' => $conversation,
            'messages'     => $messages,
        ]);
    }

    public function reply(Request $request, ViewerDmConversation $conversation)
    {
        abort_unless((int) $conversation->owner_user_id === (int) workspace_owner_id(), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        if ($conversation->isBlocked()) {
            return back()->with('error', 'This conversation is blocked. Unblock it before replying.');
        }

        $body    = trim((string) $data['body']);
        $preview = Str::limit(preg_replace('/\s+/', ' ', $body), 220, '…');

        DB::transaction(function () use ($conversation, $request, $body, $preview) {
            ViewerDmMessage::create([
                'conversation_id' => $conversation->id,
                'sender_type'     => 'owner',
                'sender_user_id'  => workspace_owner_id(),
                'body'            => $body,
            ]);

            $conversation->owner_msg_count       = $conversation->owner_msg_count + 1;
            $conversation->owner_replied         = true;
            $conversation->viewer_unread_count   = $conversation->viewer_unread_count + 1;
            $conversation->last_message_at       = Carbon::now();
            $conversation->last_message_preview  = $preview;
            $conversation->last_sender           = 'owner';
            $conversation->save();
        });

        return back()->with('success', 'Reply sent.');
    }

    public function block(Request $request, ViewerDmConversation $conversation)
    {
        abort_unless((int) $conversation->owner_user_id === (int) workspace_owner_id(), 404);

        $alsoAccount = $request->boolean('account_wide');

        $conversation->status     = 'blocked';
        $conversation->blocked_at = now();
        $conversation->save();

        if ($alsoAccount) {
            ViewerDmUserBlock::firstOrCreate([
                'owner_user_id'  => $conversation->owner_user_id,
                'viewer_user_id' => $conversation->viewer_user_id,
            ]);
        }

        return redirect()
            ->route('user.inbox.dms.index')
            ->with('success', $alsoAccount
                ? 'Viewer blocked. They cannot message you again.'
                : 'Conversation blocked.');
    }

    public function unblock(Request $request, ViewerDmConversation $conversation)
    {
        abort_unless((int) $conversation->owner_user_id === (int) workspace_owner_id(), 404);

        $conversation->status     = 'active';
        $conversation->blocked_at = null;
        $conversation->save();

        // Only lift the account-wide ban if the owner explicitly asked for it.
        // Per-conversation unblock should NOT silently re-enable contact across
        // every other biolink the owner runs.
        if ($request->boolean('account_wide')) {
            ViewerDmUserBlock::where('owner_user_id', $conversation->owner_user_id)
                ->where('viewer_user_id', $conversation->viewer_user_id)
                ->delete();
        }

        return back()->with('success', 'Conversation unblocked.');
    }
}
