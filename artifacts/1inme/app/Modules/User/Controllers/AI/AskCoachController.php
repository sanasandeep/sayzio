<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AskCoachMessage;
use App\Modules\User\Models\AskCoachThread;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AskCoach\AskCoachToolRegistry;
use App\Services\AI\InsufficientCoinsForAiException;
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

    /**
     * Hard cap on how many times we round-trip the model in a single
     * user turn while it asks for more tools. Stops a runaway loop
     * (and runaway credit spend) if the model keeps requesting data.
     */
    protected const MAX_TOOL_ITERATIONS = 4;

    /** Sidebar pagination. */
    protected const THREADS_PER_PAGE = 50;

    public function __construct(
        protected OpenAiService $ai,
        protected AiUsageCharger $credits,
        protected AskCoachToolRegistry $tools,
    ) {}

    public function show(Request $request, ?int $thread = null)
    {
        if (!AiEngineSettings::isEnabled()) {
            return view('user.ai.disabled', ['title' => 'Ask Coach']);
        }
        // Engine is on but the user's plan may not unlock Ask Coach. Show
        // the self-serve gate page (upgrade + coins) instead of a bare 403
        // so they know exactly how to switch it on.
        if (!AiEngineSettings::askCoachAllowedFor($request->user())) {
            return view('user.ai.disabled', [
                'title'       => 'Ask Coach',
                'upgradePlan' => AiEngineSettings::askCoachUpgradePlanFor($request->user()),
            ]);
        }
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

        // Branch into the SSE variant when the client opts in. Web UI
        // and mobile screen both use this so words land as they're
        // generated instead of after a full round-trip.
        if ($this->wantsStream($request)) {
            return $this->sendStream($request, $threadModel, $data['message']);
        }

        $now = now();
        AskCoachMessage::create([
            'thread_id'  => $threadModel->id,
            'role'       => 'user',
            'content'    => $data['message'],
            'created_at' => $now,
        ]);

        // 1) Build the rolling chat window once. Tool calls are
        //    appended to a transient $messages array per turn, never
        //    persisted, so reloads always start from clean history.
        $recent = AskCoachMessage::query()
            ->where('thread_id', $threadModel->id)
            ->orderByDesc('id')
            ->limit(self::MAX_PROMPT_TURNS * 2)
            ->get(['role', 'content'])
            ->reverse()
            ->values();

        $systemPrompt = AiEngineSettings::askCoachSystemPrompt();
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $recent->map(fn($m) => ['role' => $m->role, 'content' => $m->content])->all(),
        );

        $model = AiEngineSettings::featureModel('ask_coach');

        // 2) Native OpenAI function-calling loop. Let the model itself
        //    decide which (if any) data tools to pull, instead of the
        //    legacy keyword router. Falls back to that router if the
        //    tool-calling round-trip blows up (model variant doesn't
        //    support tools, transport error after we've emitted any
        //    tool messages, etc.).
        $picks = [];
        $invocations = [];
        $citations = [];
        $insights = [];
        $actions = [];
        $totalCredits = 0;
        $out = null;
        $usedFallback = false;

        try {
            $tools = $this->tools->functionDefinitions();
            for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
                $out = $this->ai->chat($user, $model, $messages, [
                    'feature'     => 'ask_coach.chat',
                    'temperature' => 0.4,
                    'max_tokens'  => 600,
                    'reason'      => 'Ask Coach: data-aware reply',
                    'tools'       => $tools,
                ]);
                $totalCredits += (int) ($out['credits_spent'] ?? 0);

                $toolCalls = $out['tool_calls'] ?? [];
                if (!$toolCalls) break;

                // Echo the assistant's tool_calls back in the next
                // request — OpenAI requires this so each `tool`
                // response can be paired by id.
                $messages[] = [
                    'role'       => 'assistant',
                    'content'    => $out['content'] !== '' ? $out['content'] : null,
                    'tool_calls' => $toolCalls,
                ];

                foreach ($toolCalls as $call) {
                    $name = (string) ($call['function']['name'] ?? '');
                    $callId = (string) ($call['id'] ?? '');
                    if (!array_key_exists($name, $this->tools->tools())) {
                        $messages[] = [
                            'role'         => 'tool',
                            'tool_call_id' => $callId,
                            'content'      => 'Unknown tool.',
                        ];
                        continue;
                    }
                    $r = $this->tools->run($name, $user);
                    $picks[] = $name;
                    $summary = (string) ($r['summary'] ?? '');
                    if ($summary !== '') {
                        $invocations[] = $r;
                        if (!empty($r['citation'])) $citations[] = $r['citation'];
                        if (!empty($r['data']))     $insights[] = ['tool' => $name, 'data' => $r['data']];
                        foreach ($r['actions'] ?? [] as $a) {
                            if ($a) $actions[] = $a;
                        }
                    }
                    $messages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $callId,
                        'content'      => $summary !== '' ? $summary : 'No data available for this user.',
                    ];
                }
            }
        } catch (InsufficientCoinsForAiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Ask Coach tool-calling failed, retrying with keyword fallback: ' . $e->getMessage());
            $usedFallback = true;
            $out = null;
        }

        // 3) Fallback: legacy keyword router + prompt-injected
        //    snapshots. Used when the model never returned a usable
        //    final answer (loop exhausted) or the tool-calling path
        //    threw mid-flight.
        if ($usedFallback || $out === null || $out['content'] === '') {
            // Mark fallback for any path that actually re-runs the
            // keyword router (loop exhausted, empty final content,
            // exception), not just exceptions, so the persisted meta
            // accurately reflects which path produced the answer.
            $usedFallback = true;
            $picks = $this->tools->pickToolsForQuestion($data['message']);
            $invocations = []; $citations = []; $insights = []; $actions = [];
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

            $fallbackPrompt = AiEngineSettings::askCoachSystemPrompt();
            if ($invocations) {
                $fallbackPrompt .= "\n\nSnapshots from the user's data (read-only, do not invent values beyond these):\n";
                foreach ($invocations as $inv) {
                    $fallbackPrompt .= "\n[{$inv['tool']}]\n" . $inv['summary'] . "\n";
                }
            }
            $fallbackMessages = array_merge(
                [['role' => 'system', 'content' => $fallbackPrompt]],
                $recent->map(fn($m) => ['role' => $m->role, 'content' => $m->content])->all(),
            );

            try {
                $out = $this->ai->chat($user, $model, $fallbackMessages, [
                    'feature'     => 'ask_coach.chat',
                    'temperature' => 0.4,
                    'max_tokens'  => 600,
                    'reason'      => 'Ask Coach: data-aware reply (fallback)',
                ]);
                $totalCredits += (int) ($out['credits_spent'] ?? 0);
            } catch (\RuntimeException $e) {
                if ($e instanceof InsufficientCoinsForAiException) throw $e;
                Log::warning('Ask Coach AI call failed: ' . $e->getMessage());
                $threadModel->forceFill(['last_message_at' => $now])->save();
                return back()->with('error', 'Coach could not reply right now. Please try again.');
            }
        }

        // 4) Persist the assistant turn with everything the renderer
        //    needs to redraw the message after a page reload.
        AskCoachMessage::create([
            'thread_id'  => $threadModel->id,
            'role'       => 'assistant',
            'content'    => $out['content'],
            'meta'       => [
                'credits_spent' => $totalCredits,
                'model'         => $out['model'] ?? null,
                'tools_used'    => array_values(array_unique($picks)),
                'citations'     => $citations,
                'insights'      => $insights,
                'actions'       => array_values(array_filter($actions)),
                'fallback'      => $usedFallback,
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

    /**
     * SSE variant of send(). Emits incremental token frames so the
     * browser/mobile renderer can paint words as they arrive, then a
     * final "done" frame with citations / insights / actions / id so
     * the bubble matches what a fresh page load would show.
     */
    protected function sendStream(Request $request, AskCoachThread $threadModel, string $message): StreamedResponse
    {
        $user = $request->user();
        $now  = now();
        AskCoachMessage::create([
            'thread_id'  => $threadModel->id,
            'role'       => 'user',
            'content'    => $message,
            'created_at' => $now,
        ]);

        // Pick + run the read-only tools, build the prompt — same
        // shape as the non-streaming branch above, just hoisted so we
        // can flush meta in the closing "done" frame.
        $picks = $this->tools->pickToolsForQuestion($message);
        $invocations = []; $citations = []; $insights = []; $actions = [];
        foreach ($picks as $tool) {
            $r = $this->tools->run($tool, $user);
            if (($r['summary'] ?? '') === '') continue;
            $invocations[] = $r;
            if (!empty($r['citation'])) $citations[] = $r['citation'];
            if (!empty($r['data']))     $insights[] = ['tool' => $tool, 'data' => $r['data']];
            foreach ($r['actions'] ?? [] as $a) if ($a) $actions[] = $a;
        }

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

        $response = new StreamedResponse(function () use ($user, $messages, $threadModel, $picks, $citations, $insights, $actions, $message) {
            $emit = function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                if (function_exists('ob_get_level') && ob_get_level() > 0) @ob_flush();
                @flush();
            };
            // Initial frame — lets the client paint the empty bubble.
            $emit('open', ['ok' => true]);

            try {
                $out = $this->ai->chatStream(
                    $user,
                    AiEngineSettings::featureModel('ask_coach'),
                    $messages,
                    [
                        'feature'     => 'ask_coach.chat',
                        'temperature' => 0.4,
                        'max_tokens'  => 600,
                        'reason'      => 'Ask Coach: streamed reply',
                    ],
                    function (string $delta) use ($emit) {
                        $emit('token', ['delta' => $delta]);
                    },
                );
            } catch (InsufficientCoinsForAiException $e) {
                $emit('error', ['code' => 'insufficient_credits', 'message' => $e->getMessage()]);
                return;
            } catch (\RuntimeException $e) {
                Log::warning('Ask Coach stream failed: ' . $e->getMessage());
                $threadModel->forceFill(['last_message_at' => now()])->save();
                $emit('error', ['code' => 'ai_unavailable', 'message' => 'Coach could not reply right now. Please try again.']);
                return;
            }

            $assistant = AskCoachMessage::create([
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
                    'streamed'      => true,
                ],
                'created_at' => now(),
            ]);

            $updates = ['last_message_at' => now()];
            if ($threadModel->title === 'New chat') {
                $updates['title'] = $this->autoTitle($message);
            }
            $threadModel->forceFill($updates)->save();

            $emit('done', [
                'message' => [
                    'id'       => $assistant->id,
                    'role'     => 'assistant',
                    'content'  => $assistant->content,
                    'meta'     => $assistant->meta,
                    'feedback' => $assistant->feedback,
                ],
                'thread'  => ['id' => $threadModel->id, 'title' => $threadModel->title],
                'balance' => $this->credits->getBalance($user),
            ]);
        });

        $response->headers->set('Content-Type', 'text/event-stream; charset=UTF-8');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Connection', 'keep-alive');
        return $response;
    }

    protected function wantsStream(Request $request): bool
    {
        if ($request->boolean('stream')) return true;
        $accept = (string) $request->header('Accept', '');
        return str_contains(strtolower($accept), 'text/event-stream');
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
