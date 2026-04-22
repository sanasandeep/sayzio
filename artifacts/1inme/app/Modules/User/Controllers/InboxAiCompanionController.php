<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\AiCompanionConversation;
use App\Modules\User\Models\AiCompanion;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\CompanionRuntime;
use Illuminate\Http\Request;

/**
 * Inbox surface for AI Companions: each Companion shows up as a
 * "contact" the owner (and team members with inbox.reply) can chat
 * with directly. Reuses the public CompanionRuntime so the same
 * persona, history, free-turn quota, and credit metering apply —
 * the only difference is the visitor token, which is synthesised
 * from the workspace owner id so every team member shares the same
 * thread per companion.
 */
class InboxAiCompanionController extends Controller
{
    public function __construct(protected CompanionRuntime $runtime) {}

    public function index(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) abort(404);

        $companions = AiCompanion::where('user_id', workspace_owner_id())
            ->orderByDesc('updated_at')
            ->get();

        return view('user.inbox.ai-companions.index', [
            'companions' => $companions,
        ]);
    }

    public function show(Request $request, AiCompanion $companion)
    {
        $this->authorize_($companion);

        $token = $this->ownerVisitorToken();
        $conversation = AiCompanionConversation::firstOrCreate(
            ['companion_id' => $companion->id, 'visitor_token' => $token],
            [
                'visitor_name'  => 'Owner / Team',
                'visitor_email' => $request->user()->email,
                'source_origin' => 'inbox',
            ],
        );

        $messages = $conversation->messages()->orderBy('id')->limit(500)->get();

        return view('user.inbox.ai-companions.show', [
            'companion'    => $companion,
            'conversation' => $conversation,
            'messages'     => $messages,
        ]);
    }

    public function send(Request $request, AiCompanion $companion)
    {
        $this->authorize_($companion);

        $data = $request->validate([
            'message' => 'required|string|max:4000',
        ]);

        $result = $this->runtime->turn(
            $companion,
            $this->ownerVisitorToken(),
            $data['message'],
            [
                'name'   => $request->user()->name,
                'email'  => $request->user()->email,
                'ip'     => $request->ip(),
                'ua'     => substr((string) $request->userAgent(), 0, 255),
                'origin' => 'inbox',
            ],
        );

        if ($request->wantsJson()) {
            return response()->json($result, $result['ok'] ? 200 : 422);
        }
        return back();
    }

    protected function authorize_(AiCompanion $companion): void
    {
        if ((int) $companion->user_id !== (int) workspace_owner_id()) abort(403);
        if (!AiEngineSettings::isEnabled()) abort(404);
    }

    /** Stable per-workspace token so all team members share one thread per companion. */
    protected function ownerVisitorToken(): string
    {
        return 'owner_' . workspace_owner_id();
    }
}
