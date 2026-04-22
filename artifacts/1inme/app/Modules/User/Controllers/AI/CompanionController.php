<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CompanionMessage;
use App\Modules\User\Models\CompanionThread;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\InsufficientAiCreditsException;
use App\Services\AI\OpenAiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Companion — multi-turn chat assistant. Conversations live in the
 * `companion_threads` / `companion_messages` tables, scoped by user (and,
 * when present, the active workspace) so they survive logout, browser
 * switches, and workspace changes.
 *
 *   GET    /user/ai/companion                 chat UI + thread sidebar
 *   GET    /user/ai/companion/{thread}        open a specific thread
 *   POST   /user/ai/companion                 start a brand-new thread
 *   POST   /user/ai/companion/{thread}/send   append a turn (+ AI reply)
 *   POST   /user/ai/companion/{thread}/rename rename a thread
 *   DELETE /user/ai/companion/{thread}        delete a thread
 *
 * Spend is tagged `feature => 'companion.chat'` for admin reporting.
 */
class CompanionController extends Controller
{
    /** Cap turns sent to the model to keep prompt size — and per-call cost
     *  — predictable. Older turns stay in the DB but aren't replayed. */
    protected const MAX_PROMPT_TURNS = 12;

    public function __construct(
        protected OpenAiService $ai,
        protected AiCreditService $credits,
    ) {}

    public function show(Request $request, ?int $thread = null)
    {
        $this->ensureEnabled();
        $user = $request->user();
        $wsId = $this->workspaceId();

        $threads = $this->threadQuery($user->id, $wsId)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $active = null;
        if ($thread) {
            $active = $this->threadQuery($user->id, $wsId)->find($thread);
            if (!$active) abort(404);
        } elseif ($threads->isNotEmpty()) {
            $active = $threads->first();
        }

        $history = $active
            ? $active->messages()->get()->map(fn($m) => [
                'role'    => $m->role,
                'content' => $m->content,
                'meta'    => $m->meta ?? [],
            ])->all()
            : [];

        return view('user.ai.companion', [
            'balance' => $this->credits->getBalance($user),
            'threads' => $threads,
            'active'  => $active,
            'history' => $history,
        ]);
    }

    /** Create a new (empty) thread and redirect to it. */
    public function store(Request $request)
    {
        $this->ensureEnabled();
        $thread = CompanionThread::create([
            'user_id'      => $request->user()->id,
            'workspace_id' => $this->workspaceId(),
            'title'        => 'New conversation',
        ]);
        return redirect()->route('user.ai.companion.thread', $thread->id);
    }

    public function send(Request $request, int $thread)
    {
        $this->ensureEnabled();
        $data = $request->validate([
            'message' => 'required|string|min:1|max:2000',
        ]);

        $user = $request->user();
        $wsId = $this->workspaceId();

        $threadModel = $this->threadQuery($user->id, $wsId)->find($thread);
        if (!$threadModel) abort(404);

        // Append the user's turn first so a model failure still leaves a
        // visible record they can retry from. We set `created_at`
        // explicitly (instead of leaning on the DB default) so the
        // in-memory model carries a real timestamp we can use below.
        $now = now();
        $userMsg = CompanionMessage::create([
            'thread_id'  => $threadModel->id,
            'role'       => 'user',
            'content'    => $data['message'],
            'created_at' => $now,
        ]);

        // Build the rolling window for the model: most recent N turns
        // including the message we just stored. Use a fresh query (rather
        // than the relation, which carries a default ascending order) so
        // the ORDER BY for the limit is unambiguous.
        $recent = CompanionMessage::query()
            ->where('thread_id', $threadModel->id)
            ->orderByDesc('id')
            ->limit(self::MAX_PROMPT_TURNS * 2)
            ->get()
            ->reverse()
            ->values();

        $messages = array_merge(
            [['role' => 'system', 'content' =>
                "You are Companion, a friendly and concise assistant. "
                . "Keep replies clear and short unless the user asks for depth."]],
            $recent->map(fn($m) => ['role' => $m->role, 'content' => $m->content])->all(),
        );

        try {
            $out = $this->ai->chat($user, AiEngineSettings::featureModel('companion'), $messages, [
                'feature'     => 'companion.chat',
                'temperature' => 0.7,
                'max_tokens'  => 600,
                'reason'      => 'Companion: chat reply',
            ]);
        } catch (\RuntimeException $e) {
            if ($e instanceof InsufficientAiCreditsException) throw $e;
            Log::warning('Companion AI call failed: ' . $e->getMessage());
            // Touch the thread so the user can see their saved turn.
            $threadModel->forceFill(['last_message_at' => $now])->save();
            return back()->with('error',
                'Companion could not reply right now. Please try again.');
        }

        $assistant = CompanionMessage::create([
            'thread_id'  => $threadModel->id,
            'role'       => 'assistant',
            'content'    => $out['content'],
            'meta'       => ['credits_spent' => $out['credits_spent']],
            'created_at' => now(),
        ]);

        $updates = ['last_message_at' => $assistant->created_at];
        // Auto-title brand new conversations from the user's first prompt
        // so the sidebar isn't a wall of "New conversation" entries.
        if ($threadModel->title === 'New conversation') {
            $updates['title'] = $this->autoTitle($data['message']);
        }
        $threadModel->forceFill($updates)->save();

        return redirect()->route('user.ai.companion.thread', $threadModel->id);
    }

    public function rename(Request $request, int $thread)
    {
        $this->ensureEnabled();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120',
                function ($attr, $value, $fail) {
                    if (trim((string) $value) === '') {
                        $fail('The title cannot be blank.');
                    }
                },
            ],
        ]);
        $threadModel = $this->threadQuery($request->user()->id, $this->workspaceId())->find($thread);
        if (!$threadModel) abort(404);
        $threadModel->update(['title' => trim($data['title'])]);
        return redirect()->route('user.ai.companion.thread', $threadModel->id)
            ->with('status', 'Conversation renamed.');
    }

    public function destroy(Request $request, int $thread)
    {
        $this->ensureEnabled();
        $threadModel = $this->threadQuery($request->user()->id, $this->workspaceId())->find($thread);
        if (!$threadModel) abort(404);
        $threadModel->delete();
        return redirect()->route('user.ai.companion.show')
            ->with('status', 'Conversation deleted.');
    }

    protected function threadQuery(int $userId, ?int $workspaceId): Builder
    {
        $q = CompanionThread::query()->where('user_id', $userId);
        if ($workspaceId === null) {
            $q->whereNull('workspace_id');
        } else {
            $q->where('workspace_id', $workspaceId);
        }
        return $q;
    }

    protected function workspaceId(): ?int
    {
        return app()->bound('current_workspace')
            ? (int) app('current_workspace')->id
            : null;
    }

    protected function autoTitle(string $message): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $message));
        return Str::limit($clean, 60, '…') ?: 'New conversation';
    }

    protected function ensureEnabled(): void
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
    }
}
