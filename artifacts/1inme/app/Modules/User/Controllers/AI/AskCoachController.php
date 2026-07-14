<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiMindDefault;
use App\Modules\User\Models\AskCoachMessage;
use App\Modules\User\Models\AskCoachThread;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiMindProvisioner;
use App\Services\AI\AiMindQueryService;
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
 *      reply is grounded in the asker's live Sayzio data (biolinks,
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
        protected AiMindQueryService $minds,
    ) {}

    public function show(Request $request, ?int $thread = null)
    {
        if (!AiEngineSettings::isEnabled()) {
            return view('user.ai.disabled', ['title' => 'Ask Coach']);
        }
        // Engine is on but the user's plan may not unlock Ask Coach. Show
        // the self-serve gate page (upgrade + coins) instead of a bare 403
        // so they know exactly how to switch it on.
        if (!\App\Services\AI\AiPlanAccess::featureAllowed($request->user(), 'ask_coach')) {
            return view('user.ai.disabled', [
                'title'       => 'Ask Coach',
                'upgradePlan' => \App\Services\AI\AiPlanAccess::featureUpgradePlan($request->user(), 'ask_coach'),
            ]);
        }
        $this->ensureEnabled($request);
        $user = $request->user();
        $wsId = $this->workspaceId();

        // Ensure the user's own Minds exist, then pre-populate the KB
        // picker from a fresh session selection or the user's saved
        // Ask Coach default (mirrors Coach / Persona).
        AiMindProvisioner::ensureForUser($user);
        $input   = session('ai.ask_coach.input', []);
        $default = AiMindDefault::forUserFeature($user->id, 'ask_coach');
        if ($default && !array_key_exists('mind_ids', $input)) {
            $input['mind_ids']         = $default->mind_ids ?? [];
            $input['include_platform'] = $default->include_platform;
        }

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
            'input'        => $input,
            'mineMinds'    => $this->userMinds($user),
            'platformMind' => $this->platformMind(),
            'hasDefault'   => (bool) $default,
            'defaultFeature' => 'ask_coach',
        ]);
    }

    /**
     * Save the current Mind selection (from the picker form) as this
     * user's default for Ask Coach. Subsequent visits pre-populate it.
     */
    public function saveDefaults(Request $request)
    {
        $this->ensureEnabled($request);
        $data = $request->validate([
            'mind_ids'         => 'nullable|array',
            'mind_ids.*'       => 'integer',
            'include_platform' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $mindIds = array_values(array_unique(array_map('intval', $data['mind_ids'] ?? [])));
        // Constrain to the user's own active Minds so we don't store
        // stale or cross-user ids in defaults.
        if ($mindIds) {
            $mindIds = AiMind::where('user_id', $user->id)
                ->where('is_disabled', false)
                ->whereIn('id', $mindIds)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->all();
        }

        AiMindDefault::updateOrCreate(
            ['user_id' => $user->id, 'feature' => 'ask_coach'],
            [
                'mind_ids'         => $mindIds,
                'include_platform' => (bool) ($data['include_platform'] ?? false),
            ],
        );

        return redirect()->route('user.ai.ask-coach.show')
            ->with('status', 'Saved as your default Mind selection for Ask Coach.');
    }

    /**
     * Forget this user's default Mind selection for Ask Coach.
     */
    public function clearDefaults(Request $request)
    {
        $this->ensureEnabled($request);
        AiMindDefault::where('user_id', $request->user()->id)
            ->where('feature', 'ask_coach')
            ->delete();

        return redirect()->route('user.ai.ask-coach.show')
            ->with('status', 'Cleared your default Mind selection for Ask Coach.');
    }

    public function store(Request $request)
    {
        $this->ensureEnabled($request);
        $thread = AskCoachThread::create([
            'user_id'      => $request->user()->id,
            'workspace_id' => $this->workspaceId(),
            'title'        => 'New chat',
        ]);

        // Emit the admin-configured greeting as the first assistant message
        // in the new thread so users see it on their first visit.
        $greeting = AiEngineSettings::askCoachGreeting();
        if ($greeting !== '') {
            AskCoachMessage::create([
                'thread_id' => $thread->id,
                'role'      => 'assistant',
                'content'   => $greeting,
                'meta'      => ['is_greeting' => true],
            ]);
            $thread->forceFill(['last_message_at' => now()])->save();
        }

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
            'message'          => 'required|string|min:1|max:2000',
            'mind_ids'         => 'nullable|array',
            'mind_ids.*'       => 'integer',
            'include_platform' => 'nullable|boolean',
        ]);

        $user = $request->user();
        $threadModel = $this->threadQuery($user->id, $this->workspaceId())->find($thread);
        if (!$threadModel) abort(404);

        $mindIds         = array_map('intval', $data['mind_ids'] ?? []);
        $includePlatform = (bool) ($data['include_platform'] ?? false);
        // Remember the picker selection so a full page reload re-checks
        // the same Minds (the picker form posts here, not to show()).
        session()->flash('ai.ask_coach.input', [
            'mind_ids'         => $mindIds,
            'include_platform' => $includePlatform,
        ]);

        // Pre-flight guards: cooldown, plan cap, banned topics.
        // These fire before any message is persisted and before the
        // SSE branch so both paths benefit from the same enforcement.
        if ($response = $this->checkPreflightErrors($request, $user, $data['message'])) {
            return $response;
        }

        // Branch into the SSE variant when the client opts in. Web UI
        // and mobile screen both use this so words land as they're
        // generated instead of after a full round-trip.
        if ($this->wantsStream($request)) {
            return $this->sendStream($request, $threadModel, $data['message'], $mindIds, $includePlatform);
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

        // Resolve the picked Knowledge Bases and pull the most relevant
        // chunks for this turn. Selecting none leaves $kb empty → the
        // prompt and spend are identical to today's behavior.
        $kb = $this->resolveKb($user, $mindIds, $includePlatform, $data['message']);

        $systemPrompt = $this->appendKbContext($this->buildSystemPrompt(), $kb['kbContext']);
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $recent->map(fn($m) => ['role' => $m->role, 'content' => $m->content])->all(),
        );

        $model        = AiEngineSettings::featureModel('ask_coach');
        $temperature  = AiEngineSettings::askCoachTemperature();
        $maxTokens    = AiEngineSettings::askCoachMaxTokens();

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
            $tools = $this->filteredFunctionDefinitions();
            for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
                $out = $this->ai->chat($user, $model, $messages, [
                    'feature'     => 'ask_coach.chat',
                    'temperature' => $temperature,
                    'max_tokens'  => $maxTokens,
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
                    // Decode any arguments the model supplied (e.g.
                    // event_lookup's `query`). OpenAI sends them as a JSON
                    // string; parameter-less tools send "" / "{}".
                    $args = [];
                    $rawArgs = $call['function']['arguments'] ?? null;
                    if (is_string($rawArgs) && trim($rawArgs) !== '') {
                        $decoded = json_decode($rawArgs, true);
                        if (is_array($decoded)) $args = $decoded;
                    } elseif (is_array($rawArgs)) {
                        $args = $rawArgs;
                    }

                    $r = $this->tools->run($name, $user, $args);
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
            $picks = $this->filteredPickTools($data['message']);
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

            $fallbackPrompt = $this->buildSystemPrompt();
            if ($invocations) {
                $fallbackPrompt .= "\n\nSnapshots from the user's data (read-only, do not invent values beyond these):\n";
                foreach ($invocations as $inv) {
                    $fallbackPrompt .= "\n[{$inv['tool']}]\n" . $inv['summary'] . "\n";
                }
            }
            $fallbackPrompt = $this->appendKbContext($fallbackPrompt, $kb['kbContext']);
            $fallbackMessages = array_merge(
                [['role' => 'system', 'content' => $fallbackPrompt]],
                $recent->map(fn($m) => ['role' => $m->role, 'content' => $m->content])->all(),
            );

            try {
                $out = $this->ai->chat($user, $model, $fallbackMessages, [
                    'feature'     => 'ask_coach.chat',
                    'temperature' => $temperature,
                    'max_tokens'  => $maxTokens,
                    'reason'      => 'Ask Coach: data-aware reply (fallback)',
                ]);
                $totalCredits += (int) ($out['credits_spent'] ?? 0);
            } catch (\RuntimeException $e) {
                if ($e instanceof InsufficientCoinsForAiException) throw $e;
                Log::warning('Ask Coach AI call failed: ' . $e->getMessage());
                $threadModel->forceFill(['last_message_at' => $now])->save();
                $fallbackMsg = AiEngineSettings::askCoachFallbackMessage()
                    ?: 'Coach could not reply right now. Please try again.';
                return back()->with('error', $fallbackMsg);
            }
        }

        // 4) Persist the assistant turn with everything the renderer
        //    needs to redraw the message after a page reload.
        $multiplierSurcharge = $this->applyCreditMultiplierSurcharge($user, $totalCredits);
        AskCoachMessage::create([
            'thread_id'  => $threadModel->id,
            'role'       => 'assistant',
            'content'    => $out['content'],
            'meta'       => [
                'credits_spent' => $totalCredits + $multiplierSurcharge + $kb['creditsSpent'],
                'model'         => $out['model'] ?? null,
                'tools_used'    => array_values(array_unique($picks)),
                'citations'     => array_merge($citations, $kb['citations']),
                'minds_used'    => $this->mindsUsed($kb['selectedMinds'], $kb['mindStats']),
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
    protected function sendStream(Request $request, AskCoachThread $threadModel, string $message, array $mindIds = [], bool $includePlatform = false): StreamedResponse
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
        $picks = $this->filteredPickTools($message);
        $invocations = []; $citations = []; $insights = []; $actions = [];
        foreach ($picks as $tool) {
            $r = $this->tools->run($tool, $user);
            if (($r['summary'] ?? '') === '') continue;
            $invocations[] = $r;
            if (!empty($r['citation'])) $citations[] = $r['citation'];
            if (!empty($r['data']))     $insights[] = ['tool' => $tool, 'data' => $r['data']];
            foreach ($r['actions'] ?? [] as $a) if ($a) $actions[] = $a;
        }

        // Resolve the picked Knowledge Bases and pull the most relevant
        // chunks. Selecting none leaves $kb empty → unchanged behavior.
        $kb = $this->resolveKb($user, $mindIds, $includePlatform, $message);
        $kbCitations = $kb['citations'];
        $kbCredits   = $kb['creditsSpent'];
        $mindsUsed   = $this->mindsUsed($kb['selectedMinds'], $kb['mindStats']);

        $recent = AskCoachMessage::query()
            ->where('thread_id', $threadModel->id)
            ->orderByDesc('id')
            ->limit(self::MAX_PROMPT_TURNS * 2)
            ->get(['role', 'content'])
            ->reverse()
            ->values();

        $systemPrompt = $this->buildSystemPrompt();
        if ($invocations) {
            $systemPrompt .= "\n\nSnapshots from the user's data (read-only, do not invent values beyond these):\n";
            foreach ($invocations as $inv) {
                $systemPrompt .= "\n[{$inv['tool']}]\n" . $inv['summary'] . "\n";
            }
        }
        $systemPrompt = $this->appendKbContext($systemPrompt, $kb['kbContext']);

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $recent->map(fn($m) => ['role' => $m->role, 'content' => $m->content])->all(),
        );

        $streamTemperature = AiEngineSettings::askCoachTemperature();
        $streamMaxTokens   = AiEngineSettings::askCoachMaxTokens();

        $response = new StreamedResponse(function () use ($user, $messages, $threadModel, $picks, $citations, $insights, $actions, $message, $kbCitations, $kbCredits, $mindsUsed, $streamTemperature, $streamMaxTokens) {
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
                        'temperature' => $streamTemperature,
                        'max_tokens'  => $streamMaxTokens,
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
                $fallbackMsg = AiEngineSettings::askCoachFallbackMessage()
                    ?: 'Coach could not reply right now. Please try again.';
                $emit('error', ['code' => 'ai_unavailable', 'message' => $fallbackMsg]);
                return;
            }

            $baseCredits      = (int) $out['credits_spent'];
            $surcharge        = $this->applyCreditMultiplierSurcharge($user, $baseCredits);
            $assistant = AskCoachMessage::create([
                'thread_id'  => $threadModel->id,
                'role'       => 'assistant',
                'content'    => $out['content'],
                'meta'       => [
                    'credits_spent' => $baseCredits + $surcharge + $kbCredits,
                    'model'         => $out['model'] ?? null,
                    'tools_used'    => $picks,
                    'citations'     => array_merge($citations, $kbCitations),
                    'minds_used'    => $mindsUsed,
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
        if ($user && !\App\Services\AI\AiPlanAccess::featureAllowed($user, 'ask_coach')) {
            abort(403, 'Ask Coach is not available on your current plan.');
        }
    }

    // ── Admin settings — runtime enforcement ──────────────────────

    /**
     * Run pre-flight checks (cooldown, plan cap, banned topics) before
     * any message is written. Returns a response to short-circuit the
     * request, or null when all checks pass.
     */
    protected function checkPreflightErrors(Request $request, $user, string $message): mixed
    {
        // Cooldown — per-user gap between messages
        $cooldown = AiEngineSettings::askCoachCooldownSeconds();
        if ($cooldown > 0) {
            $lastAt = AskCoachMessage::query()
                ->whereHas('thread', fn($q) => $q->where('user_id', $user->id))
                ->where('role', 'user')
                ->latest()
                ->value('created_at');
            if ($lastAt) {
                $elapsed = (int) \Carbon\Carbon::parse($lastAt)->diffInSeconds(now());
                if ($elapsed < $cooldown) {
                    $remaining = $cooldown - $elapsed;
                    return $this->preflightError(
                        $request,
                        "Please wait {$remaining} second(s) before sending another message.",
                        'cooldown'
                    );
                }
            }
        }

        // Per-plan message cap (daily or monthly)
        $caps = AiEngineSettings::askCoachPlanCaps();
        if ($caps) {
            $slug = ($user->plan_id && $user->plan) ? (string) $user->plan->slug : 'free';
            if (isset($caps[$slug])) {
                $capCfg = $caps[$slug];
                $since  = $capCfg['period'] === 'monthly' ? now()->startOfMonth() : now()->startOfDay();
                $count  = AskCoachMessage::query()
                    ->whereHas('thread', fn($q) => $q->where('user_id', $user->id))
                    ->where('role', 'user')
                    ->where('created_at', '>=', $since)
                    ->count();
                if ($count >= $capCfg['cap']) {
                    $resetLabel = $capCfg['period'] === 'monthly' ? 'next month' : 'tomorrow';
                    return $this->preflightError(
                        $request,
                        "You have reached your {$capCfg['period']} message limit of {$capCfg['cap']}. Coach will be available again {$resetLabel}.",
                        'plan_cap'
                    );
                }
            }
        }

        // Banned topics — keyword-match the user's message
        $banned = AiEngineSettings::askCoachBannedTopics();
        if ($banned) {
            $lower = mb_strtolower($message);
            foreach ($banned as $topic) {
                if (mb_stripos($lower, mb_strtolower($topic)) !== false) {
                    $decline = "I'm sorry, that topic is outside what I can help with here.";
                    $note    = AiEngineSettings::askCoachEscalationNote();
                    if ($note !== '') $decline .= ' ' . $note;
                    return $this->preflightError($request, $decline, 'banned_topic');
                }
            }
        }

        return null;
    }

    /** Return the appropriate error response for a pre-flight failure. */
    protected function preflightError(Request $request, string $message, string $code): mixed
    {
        if ($this->wantsStream($request) || $request->wantsJson()) {
            return response()->json(['error' => ['message' => $message, 'code' => $code]], 422);
        }
        return back()->with('error', $message);
    }

    /**
     * Build the system prompt for the current turn, combining the
     * admin-configured base prompt with any behavior directives (tone,
     * length, language) from the settings.
     */
    protected function buildSystemPrompt(): string
    {
        $base       = AiEngineSettings::askCoachSystemPrompt();
        $directives = AiEngineSettings::askCoachBehaviorDirectives();
        return $base . $directives;
    }

    /**
     * Map of data snapshot category slugs → the underlying tool names
     * they cover. Used to filter the tool registry based on admin toggles.
     */
    protected const SNAPSHOT_CATEGORY_TOOLS = [
        'links'     => ['biolinks', 'links'],
        'analytics' => ['analytics'],
        'audience'  => ['audience'],
        'billing'   => ['payments', 'account'],
        'events'    => ['event_lookup'],
    ];

    /**
     * Resolve the set of tool names currently enabled by the admin's
     * snapshot-category toggles. An empty setting means all categories
     * are on (the platform default).
     *
     * @return list<string>
     */
    protected function enabledTools(): array
    {
        $categories = AiEngineSettings::askCoachSnapshotCategories();
        if (empty($categories)) {
            return array_keys($this->tools->tools());
        }
        $enabled = [];
        foreach ($categories as $cat) {
            foreach (self::SNAPSHOT_CATEGORY_TOOLS[$cat] ?? [] as $tool) {
                $enabled[] = $tool;
            }
        }
        return array_values(array_unique($enabled));
    }

    /**
     * Return only the function definitions for enabled snapshot categories.
     * Passed to the native tool-calling loop so the model never requests
     * data from a disabled category.
     */
    protected function filteredFunctionDefinitions(): array
    {
        $enabled = $this->enabledTools();
        return array_values(array_filter(
            $this->tools->functionDefinitions(),
            fn($def) => in_array($def['function']['name'], $enabled, true)
        ));
    }

    /**
     * Keyword-router picks filtered to only enabled snapshot categories.
     * Used by the SSE stream path and the native-tool-calling fallback.
     *
     * @return list<string>
     */
    protected function filteredPickTools(string $question): array
    {
        $enabled = $this->enabledTools();
        $picked  = $this->tools->pickToolsForQuestion($question);
        $filtered = array_values(array_filter($picked, fn($t) => in_array($t, $enabled, true)));
        // If filtering zeroed out all picks (disabled cats covered everything),
        // return an empty list rather than the default fallback so we don't
        // inadvertently invoke a disabled category.
        return $filtered;
    }

    /**
     * Charge the admin-configured credit multiplier surcharge on top of
     * the base coin cost already collected by OpenAiService. Returns the
     * surcharge amount (0 when no surcharge applies or the debit fails).
     */
    protected function applyCreditMultiplierSurcharge($user, int $baseCredits): int
    {
        $multiplier = AiEngineSettings::askCoachCreditMultiplier();
        if ($multiplier <= 1.0 || $baseCredits <= 0) return 0;

        $surcharge = (int) ceil($baseCredits * ($multiplier - 1.0));
        if ($surcharge <= 0) return 0;

        try {
            app(\App\Services\Billing\WalletService::class)->debit($user, $surcharge, [
                'feature' => 'ask_coach.multiplier_surcharge',
                'reason'  => 'Coach admin credit multiplier surcharge',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Ask Coach multiplier surcharge debit failed: ' . $e->getMessage());
            return 0;
        }

        return $surcharge;
    }

    /**
     * Resolve the user's picked Minds (own + optional platform default,
     * ownership validated server-side) and retrieve the most relevant
     * KB chunks for $query. Picking none returns an empty context so
     * the caller's prompt and spend are unchanged.
     *
     * @return array{selectedMinds:array<int,AiMind>,kbContext:string,citations:array,creditsSpent:int,mindStats:array}
     */
    protected function resolveKb($user, array $mindIds, bool $includePlatform, string $query): array
    {
        $selectedMinds = $this->minds->resolveMindsForUser($user, $mindIds, $includePlatform);
        $kbContext = ''; $citations = []; $creditsSpent = 0; $mindStats = [];
        if ($selectedMinds) {
            try {
                $retrieved   = $this->minds->retrieveContext($user, $selectedMinds, $query);
                $kbContext   = $retrieved['context'];
                $citations   = $retrieved['citations'];
                $creditsSpent = (int) $retrieved['credits_spent'];
                $mindStats   = $retrieved['mind_stats'] ?? [];
            } catch (InsufficientCoinsForAiException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::warning('Ask Coach Mind retrieval failed: ' . $e->getMessage());
            }
        }
        return compact('selectedMinds', 'kbContext', 'citations', 'creditsSpent', 'mindStats');
    }

    /** Append the retrieved KB context to a system prompt (no-op when empty). */
    protected function appendKbContext(string $prompt, string $kbContext): string
    {
        if (trim($kbContext) === '') return $prompt;
        return $prompt
            . "\n\nWhen relevant, ground your answer in the Knowledge Base context below — "
            . "reuse its terminology, products and audience details. Do not invent facts "
            . "that are not in the context.\n\n"
            . "Knowledge Base context:\n" . $kbContext;
    }

    /**
     * Shape the selected Minds + retrieval stats for persistence in the
     * assistant message meta (mirrors Coach's `minds_used`).
     *
     * @param array<int,AiMind> $selectedMinds
     */
    protected function mindsUsed(array $selectedMinds, array $mindStats): array
    {
        return array_map(
            fn(AiMind $m) => [
                'id'          => (int) $m->id,
                'name'        => (string) $m->name,
                'is_platform' => $m->isPlatform(),
                'chunks_used' => (int) ($mindStats[(int) $m->id]['chunks_used'] ?? 0),
                'top_score'   => (float) ($mindStats[(int) $m->id]['top_score'] ?? 0.0),
            ],
            $selectedMinds,
        );
    }

    /** @return \Illuminate\Support\Collection<int,AiMind> */
    protected function userMinds($user)
    {
        return AiMind::where('user_id', $user->id)
            ->where('is_disabled', false)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function platformMind(): ?AiMind
    {
        return AiMind::whereNull('user_id')
            ->where('is_default', true)
            ->where('is_disabled', false)
            ->first(['id', 'name']);
    }
}
