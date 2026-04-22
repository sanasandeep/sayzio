<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AskCoachMessage;
use App\Modules\User\Models\AskCoachThread;
use App\Services\AI\AiCreditService;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AskCoach\AskCoachToolRegistry;
use App\Services\AI\InsufficientAiCreditsException;
use App\Services\AI\OpenAiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ask Coach — multi-turn, data-aware self-support chatbot.
 *
 * Differs from Companion in three ways:
 *   1. Each user turn fans out to the {@see AskCoachToolRegistry} so the
 *      reply is grounded in the asker's live 1INME data (biolinks,
 *      links, analytics, payments, audience, account).
 *   2. Snapshots, citations, deep-link actions and inline charts/
 *      tables are persisted in `meta` so the renderer can show the
 *      same "Coach knew this when answering" panel after a reload.
 *   3. Per-plan kill switch + central system prompt, both edited from
 *      `/admin/ask-coach`, gate every call.
 *
 * Spend is tagged `feature => 'ask_coach.chat'` for admin reporting.
 */
class AskCoachController extends Controller
{
    /** Cap turns sent to the model. Older turns stay in the DB. */
    protected const MAX_PROMPT_TURNS = 10;

    /** Sidebar pagination. */
    protected const THREADS_PER_PAGE = 50;

    public function __construct(
        protected OpenAiService $ai,
        protected AiCreditService $credits,
        protected AskCoachToolRegistry $tools,
    ) {}

    public function show(Request $request, ?int $thread = null)
    {
        $this->ensureEnabled($request);
        $user = $request->user();
        $wsId = $this->workspaceId();

        $search = trim((string) $request->query('q', ''));

        $threadsQuery = $this->threadQuery($user->id, $wsId);
        $snippets = [];
        $titles = [];

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $matched = AskCoachMessage::query()
                ->whereIn('thread_id', (clone $threadsQuery)->select('id'))
                ->where('content', 'like', $like)
                ->distinct()
                ->pluck('thread_id')
                ->all();
            $threadsQuery->where(function ($w) use ($like, $matched) {
                $w->where('title', 'like', $like);
                if ($matched) $w->orWhereIn('id', $matched);
            });
        }

        $threads = $threadsQuery
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(self::THREADS_PER_PAGE)
            ->withQueryString();

        if ($search !== '' && $threads->isNotEmpty()) {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $rows = AskCoachMessage::query()
                ->whereIn('thread_id', $threads->pluck('id'))
                ->where('content', 'like', $like)
                ->orderBy('thread_id')
                ->orderBy('id')
                ->get(['thread_id', 'content']);
            foreach ($rows as $r) {
                if (!isset($snippets[$r->thread_id])) {
                    $snippets[$r->thread_id] = $this->snippet((string) $r->content, $search);
                }
            }
            foreach ($threads as $t) $titles[$t->id] = (string) $t->title;
        }

        $active = null;
        if ($thread) {
            $active = $this->threadQuery($user->id, $wsId)->find($thread);
            if (!$active) abort(404);
        } elseif ($threads->isNotEmpty() && $search === '' && $request->query('page') === null) {
            $active = $threads->first();
        }

        $history = $active
            ? $active->messages()->get()->map(fn($m) => [
                'id'       => $m->id,
                'role'     => $m->role,
                'content'  => $m->content,
                'meta'     => $m->meta ?? [],
                'feedback' => $m->feedback,
            ])->all()
            : [];

        return view('user.ai.ask-coach', [
            'balance'  => $this->credits->getBalance($user),
            'threads'  => $threads,
            'active'   => $active,
            'history'  => $history,
            'search'   => $search,
            'snippets' => $snippets,
            'titles'   => $titles,
            'tools'    => $this->tools->tools(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureEnabled($request);
        $thread = AskCoachThread::create([
            'user_id'      => $request->user()->id,
            'workspace_id' => $this->workspaceId(),
            'title'        => 'New chat',
        ]);
        return redirect()->route('user.ai.ask-coach.thread', $thread->id);
    }

    /**
     * Append a user turn, invoke the relevant data tools, call the LLM
     * with the snapshots spliced into the system prompt, then persist
     * the assistant turn (with citations + actions + insight cards).
     */
    public function send(Request $request, int $thread)
    {
        $this->ensureEnabled($request);
        $data = $request->validate([
            'message' => 'required|string|min:1|max:2000',
        ]);

        $user = $request->user();
        $threadModel = $this->threadQuery($user->id, $this->workspaceId())->find($thread);
        if (!$threadModel) abort(404);

        $now = now();
        AskCoachMessage::create([
            'thread_id'  => $threadModel->id,
            'role'       => 'user',
            'content'    => $data['message'],
            'created_at' => $now,
        ]);

        // 1) Pick + run the read-only tools relevant to this question.
        $picks = $this->tools->pickToolsForQuestion($data['message']);
        $invocations = [];      // for the model prompt + UI rendering
        $citations = [];
        $insights = [];
        $actions = [];
        foreach ($picks as $tool) {
            $r = $this->tools->run($tool, $user);
            if (($r['summary'] ?? '') === '') continue;
            $invocations[] = $r;
            if (!empty($r['citation'])) $citations[] = $r['citation'];
            if (!empty($r['data']))     $insights[] = ['tool' => $tool, 'data' => $r['data']];
            foreach ($r['actions'] ?? [] as $a) {
                if ($a) $actions[] = $a;
            }
        }

        // 2) Build the rolling chat window.
        $recent = AskCoachMessage::query()
            ->where('thread_id', $threadModel->id)
            ->orderByDesc('id')
            ->limit(self::MAX_PROMPT_TURNS * 2)
            ->get(['role', 'content'])
            ->reverse()
            ->values();

        $systemPrompt = AiEngineSettings::askCoachSystemPrompt();
        if ($invocations) {
            $systemPrompt .= "\n\nSnapshots from the user's data (read-only, do not invent values beyond these):\n";
            foreach ($invocations as $inv) {
                $systemPrompt .= "\n[{$inv['tool']}]\n" . $inv['summary'] . "\n";
            }
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $recent->map(fn($m) => ['role' => $m->role, 'content' => $m->content])->all(),
        );

        // 3) Call the model.
        try {
            $out = $this->ai->chat($user, AiEngineSettings::featureModel('ask_coach'), $messages, [
                'feature'     => 'ask_coach.chat',
                'temperature' => 0.4,
                'max_tokens'  => 600,
                'reason'      => 'Ask Coach: data-aware reply',
            ]);
        } catch (\RuntimeException $e) {
            if ($e instanceof InsufficientAiCreditsException) throw $e;
            Log::warning('Ask Coach AI call failed: ' . $e->getMessage());
            $threadModel->forceFill(['last_message_at' => $now])->save();
            return back()->with('error', 'Coach could not reply right now. Please try again.');
        }

        // 4) Persist the assistant turn with everything the renderer
        //    needs to redraw the message after a page reload.
        AskCoachMessage::create([
            'thread_id'  => $threadModel->id,
            'role'       => 'assistant',
            'content'    => $out['content'],
            'meta'       => [
                'credits_spent' => (int) $out['credits_spent'],
                'model'         => $out['model'] ?? null,
                'tools_used'    => $picks,
                'citations'     => $citations,
                'insights'      => $insights,
                'actions'       => array_values(array_filter($actions)),
            ],
            'created_at' => now(),
        ]);

        $updates = ['last_message_at' => now()];
        if ($threadModel->title === 'New chat') {
            $updates['title'] = $this->autoTitle($data['message']);
        }
        $threadModel->forceFill($updates)->save();

        return redirect()->route('user.ai.ask-coach.thread', $threadModel->id);
    }

    public function feedback(Request $request, int $message)
    {
        $this->ensureEnabled($request);
        $data = $request->validate([
            'feedback' => 'required|in:up,down,clear',
            'note'     => 'nullable|string|max:500',
        ]);

        $msg = AskCoachMessage::query()
            ->where('id', $message)
            ->whereHas('thread', fn ($q) => $q
                ->where('user_id', $request->user()->id)
                ->where(function ($w) {
                    $wsId = $this->workspaceId();
                    $wsId === null ? $w->whereNull('workspace_id')
                                   : $w->where('workspace_id', $wsId);
                }))
            ->where('role', 'assistant')
            ->first();
        if (!$msg) abort(404);

        $msg->feedback = $data['feedback'] === 'clear' ? null : $data['feedback'];
        $msg->feedback_note = $data['feedback'] === 'down' ? ($data['note'] ?? null) : null;
        $msg->save();

        return back()->with('status', 'Thanks for the feedback.');
    }

    public function rename(Request $request, int $thread)
    {
        $this->ensureEnabled($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
        ]);
        $threadModel = $this->threadQuery($request->user()->id, $this->workspaceId())->find($thread);
        if (!$threadModel) abort(404);
        $threadModel->update(['title' => trim($data['title'])]);
        return redirect()->route('user.ai.ask-coach.thread', $threadModel->id)
            ->with('status', 'Chat renamed.');
    }

    public function destroy(Request $request, int $thread)
    {
        $this->ensureEnabled($request);
        $threadModel = $this->threadQuery($request->user()->id, $this->workspaceId())->find($thread);
        if (!$threadModel) abort(404);
        $threadModel->delete();
        return redirect()->route('user.ai.ask-coach.show')
            ->with('status', 'Chat deleted.');
    }

    public function export(Request $request, int $thread): StreamedResponse
    {
        $this->ensureEnabled($request);
        $format = $request->query('format') === 'txt' ? 'txt' : 'md';

        $threadModel = $this->threadQuery($request->user()->id, $this->workspaceId())->find($thread);
        if (!$threadModel) abort(404);

        $filename = $this->exportFilename($threadModel->title) . '.' . $format;
        $mime = $format === 'md' ? 'text/markdown; charset=UTF-8' : 'text/plain; charset=UTF-8';

        return response()->streamDownload(function () use ($threadModel, $format) {
            $out = fopen('php://output', 'w');

            if ($format === 'md') {
                fwrite($out, '# ' . $threadModel->title . "\n\n");
                fwrite($out, '_Coach chat exported ' . now()->toDayDateTimeString() . "_\n\n---\n\n");
            } else {
                fwrite($out, $threadModel->title . "\n");
                fwrite($out, str_repeat('=', max(3, mb_strlen($threadModel->title))) . "\n");
                fwrite($out, 'Exported ' . now()->toDayDateTimeString() . "\n\n");
            }

            AskCoachMessage::query()
                ->where('thread_id', $threadModel->id)
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($out, $format) {
                    foreach ($rows as $m) {
                        $label = $m->role === 'user' ? 'You'
                            : ($m->role === 'assistant' ? 'Coach' : ucfirst($m->role));
                        $ts = $m->created_at ? $m->created_at->toDayDateTimeString() : '';
                        if ($format === 'md') {
                            fwrite($out, '## ' . $label . ($ts ? ' · ' . $ts : '') . "\n\n");
                            fwrite($out, rtrim((string) $m->content) . "\n\n");
                            $cites = $m->meta['citations'] ?? [];
                            if ($cites) {
                                fwrite($out, "_Sources: "
                                    . implode(', ', array_map(fn($c) => $c['label'] ?? $c['source'] ?? '', $cites))
                                    . "_\n\n");
                            }
                        } else {
                            fwrite($out, '[' . $label . ($ts ? ' · ' . $ts : '') . "]\n");
                            fwrite($out, rtrim((string) $m->content) . "\n\n");
                        }
                    }
                    if (function_exists('ob_get_level') && ob_get_level() > 0) @ob_flush();
                    @flush();
                });

            fclose($out);
        }, $filename, [
            'Content-Type'      => $mime,
            'Cache-Control'     => 'no-store, no-cache, must-revalidate',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    // ── helpers ───────────────────────────────────────────────────

    protected function threadQuery(int $userId, ?int $workspaceId): Builder
    {
        $q = AskCoachThread::query()->where('user_id', $userId);
        $workspaceId === null ? $q->whereNull('workspace_id')
                              : $q->where('workspace_id', $workspaceId);
        return $q;
    }

    protected function workspaceId(): ?int
    {
        return app()->bound('current_workspace')
            ? (int) app('current_workspace')->id
            : null;
    }

    protected function exportFilename(string $title): string
    {
        $slug = Str::slug($title);
        if ($slug === '') $slug = 'ask-coach-chat';
        return $slug . '-' . now()->format('Ymd-His');
    }

    protected function autoTitle(string $message): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $message));
        return Str::limit($clean, 80, '…') ?: 'New chat';
    }

    protected function snippet(string $content, string $term): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $content));
        if ($term === '') return Str::limit($clean, 120, '…');
        $pos = mb_stripos($clean, $term);
        if ($pos === false) return Str::limit($clean, 120, '…');
        $start = max(0, $pos - 60);
        $slice = mb_substr($clean, $start, 120);
        if ($start > 0) $slice = '…' . $slice;
        return $slice . '…';
    }

    /**
     * Refuse early when the master AI switch is off OR the asker's
     * plan isn't on the Ask Coach allow-list.
     */
    protected function ensureEnabled(Request $request): void
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
        $user = $request->user();
        if ($user && !AiEngineSettings::askCoachAllowedFor($user)) {
            abort(403, 'Ask Coach is not available on your current plan.');
        }
    }
}
