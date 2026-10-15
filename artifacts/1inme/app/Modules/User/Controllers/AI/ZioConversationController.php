<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\SiteAssistantConversation;
use Illuminate\Http\Request;

class ZioConversationController extends Controller
{
    public function index(Request $request)
    {
        return view('user.ai.zio-conversations', ['conversations' => SiteAssistantConversation::where('bound_user_id', $request->user()->id)->whereHas('messages', fn ($q) => $q->where('role', 'user'))->withCount(['messages as user_messages_count' => fn ($q) => $q->where('role', 'user'), 'messages as assistant_messages_count' => fn ($q) => $q->where('role', 'assistant')])->withSum('messages', 'credits_spent')->with(['messages' => fn ($q) => $q->where('role', 'user')->oldest('id')->limit(1)])->latest('last_message_at')->paginate(20), 'conversation' => null]);
    }

    public function show(Request $request, SiteAssistantConversation $conversation)
    {
        abort_unless((int) $conversation->bound_user_id === (int) $request->user()->id, 404);

        return view('user.ai.zio-conversations', ['conversations' => null, 'conversation' => $conversation, 'messages' => $conversation->messages()->whereIn('role', ['user', 'assistant'])->oldest('id')->paginate(100)]);
    }
}
