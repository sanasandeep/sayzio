<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AskCoachMessage;
use App\Modules\User\Models\AskCoachThread;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AskCoach\AskCoachToolRegistry;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\AI\OpenAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mobile parity for the Ask Coach web feature. Same persistence and
 * runtime as {@see \App\Modules\User\Controllers\AI\AskCoachController}
 * — only the I/O is JSON.
 */
class AskCoachController extends Controller
{
    protected const MAX_PROMPT_TURNS = 10;

    public function __construct(
        protected OpenAiService $ai,
        protected AiUsageCharger $credits,
        protected AskCoachToolRegistry $tools,
    ) {}

    public function threads(Request $request): JsonResponse
    {
        // Entry-point loader: degrade gracefully like the web "AI is off"
        // view (Task #1999). The mobile Ask Coach screen keeps its nav
        // entry visible even when the engine is off, so its loader must get
        // an informative 200 (`ai_enabled:false`) — mirroring AiChatController
        // — instead of the hard 404 the mutating actions throw. Without this
        // the screen would alert and bounce the user straight back out.
        if (!AiEngineSettings::isEnabled()) {
            return response()->json(['threads' => [], 'ai_enabled' => false]);
        }
        $this->ensureEnabled($request);
        $items = AskCoachThread::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'title', 'last_message_at']);

        // Worst-case per-turn coin cost + wallet balance so the mobile screen
        // can show the shared "Uses up to N coins · Balance: X" hint and
        // disable Send BEFORE a turn the wallet can't cover (same pattern as
        // the analytics payload's audience_estimate_coins + coin_balance).
        $coinCost = 0;
        try {
            $coinCost = (int) (app(\App\Services\AI\AiCostEstimator::class)
                ->estimate($request->user(), 'ask_coach', '')['coins'] ?? 0);
        } catch (\Throwable $e) {
            $coinCost = 0;
        }

        return response()->json([
            'threads'      => $items,
            'ai_enabled'   => true,
            'coin_cost'    => $coinCost,
            'coin_balance' => $this->credits->getBalance($request->user()),
        ]);
    }

    public function createThread(Request $request): JsonResponse
    {
        $this->ensureEnabled($request);
        $thread = AskCoachThread::create([
            'user_id' => $request->user()->id,
            'title'   => 'New chat',
        ]);
        return response()->json(['thread' => $thread], 201);
    }

    public function messages(Request $request, int $thread): JsonResponse
    {
        $this->ensureEnabled($request);
        $t = AskCoachThread::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $thread)
            ->firstOrFail();
        $msgs = $t->messages()->get(['id', 'role', 'content', 'meta', 'feedback', 'created_at']);
        return response()->json(['thread' => $t, 'messages' => $msgs]);
    }

    public function send(Request $request, int $thread)
    {
        $this->ensureEnabled($request);
        $data = $request->validate([
            'message' => 'required|string|min:1|max:2000',
        ]);

        $user = $request->user();
        $t = AskCoachThread::query()
            ->where('user_id', $user->id)
            ->where('id', $thread)
            ->firstOrFail();

        if ($this->wantsStream($request)) {
            return $this->sendStream($request, $t, $data['message']);
        }

        AskCoachMessage::create([
            'thread_id'  => $t->id,
            'role'       => 'user',
            'content'    => $data['message'],
            'created_at' => now(),
        ]);

        $picks = $this->tools->pickToolsForQuestion($data['message']);
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
            ->where('thread_id', $t->id)
            ->orderByDesc('id')
            ->limit(self::MAX_PROMPT_TURNS * 2)
            ->get(['role', 'content'])
            ->reverse()
            ->values();

        $systemPrompt = AiEngineSettings::askCoachSystemPrompt();
        if ($invocations) {
            $systemPrompt .= "\n\nSnapshots from the user's data:\n";
            foreach ($invocations as $inv) {
                $systemPrompt .= "\n[{$inv['tool']}]\n" . $inv['summary'] . "\n";
            }
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $recent->map(fn($m) => ['role' => $m->role, 'content' => $m->content])->all(),
        );

        try {
            $out = $this->ai->chat($user, AiEngineSettings::featureModel('ask_coach', $user), $messages, [
                'feature'     => 'ask_coach.chat',
                'temperature' => 0.4,
                'max_tokens'  => 600,
                'reason'      => 'Ask Coach (mobile)',
            ]);
        } catch (InsufficientCoinsForAiException $e) {
            return response()->json(['error' => 'insufficient_credits', 'message' => $e->getMessage()], 402);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => 'ai_unavailable', 'message' => $e->getMessage()], 503);
        }

        $assistant = AskCoachMessage::create([
            'thread_id'  => $t->id,
            'role'       => 'assistant',
            'content'    => $out['content'],
            'meta'       => [
                'credits_spent' => (int) $out['credits_spent'],
                'tools_used'    => $picks,
                'citations'     => $citations,
                'insights'      => $insights,
                'actions'       => array_values(array_filter($actions)),
            ],
            'created_at' => now(),
        ]);

        $updates = ['last_message_at' => now()];
        if ($t->title === 'New chat') {
            $updates['title'] = \Illuminate\Support\Str::limit(trim($data['message']), 80, '…');
        }
        $t->forceFill($updates)->save();

        return response()->json([
            'message' => $assistant,
            'balance' => $this->credits->getBalance($user),
        ]);
    }

    /**
     * SSE variant of send(). Mirrors the web controller's stream
     * frames (open/token/done/error) so the mobile screen can render
     * tokens word-by-word while still receiving the final message
     * record (with citations, insights, actions) in the closing frame.
     */
    protected function sendStream(Request $request, AskCoachThread $t, string $message): StreamedResponse
    {
        $user = $request->user();

        AskCoachMessage::create([
            'thread_id'  => $t->id,
            'role'       => 'user',
            'content'    => $message,
            'created_at' => now(),
        ]);

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
            ->where('thread_id', $t->id)
            ->orderByDesc('id')
            ->limit(self::MAX_PROMPT_TURNS * 2)
            ->get(['role', 'content'])
            ->reverse()
            ->values();

        $systemPrompt = AiEngineSettings::askCoachSystemPrompt();
        if ($invocations) {
            $systemPrompt .= "\n\nSnapshots from the user's data:\n";
            foreach ($invocations as $inv) {
                $systemPrompt .= "\n[{$inv['tool']}]\n" . $inv['summary'] . "\n";
            }
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $recent->map(fn($m) => ['role' => $m->role, 'content' => $m->content])->all(),
        );

        $response = new StreamedResponse(function () use ($user, $messages, $t, $picks, $citations, $insights, $actions, $message) {
            $emit = function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: ' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
                if (function_exists('ob_get_level') && ob_get_level() > 0) @ob_flush();
                @flush();
            };
            $emit('open', ['ok' => true]);

            try {
                $out = $this->ai->chatStream(
                    $user,
                    AiEngineSettings::featureModel('ask_coach', $user),
                    $messages,
                    [
                        'feature'     => 'ask_coach.chat',
                        'temperature' => 0.4,
                        'max_tokens'  => 600,
                        'reason'      => 'Ask Coach (mobile, streamed)',
                    ],
                    function (string $delta) use ($emit) {
                        $emit('token', ['delta' => $delta]);
                    },
                );
            } catch (InsufficientCoinsForAiException $e) {
                $emit('error', ['code' => 'insufficient_credits', 'message' => $e->getMessage()]);
                return;
            } catch (\RuntimeException $e) {
                Log::warning('Ask Coach (api) stream failed: ' . $e->getMessage());
                $emit('error', ['code' => 'ai_unavailable', 'message' => 'Coach could not reply right now.']);
                return;
            }

            $assistant = AskCoachMessage::create([
                'thread_id'  => $t->id,
                'role'       => 'assistant',
                'content'    => $out['content'],
                'meta'       => [
                    'credits_spent' => (int) $out['credits_spent'],
                    'tools_used'    => $picks,
                    'citations'     => $citations,
                    'insights'      => $insights,
                    'actions'       => array_values(array_filter($actions)),
                    'streamed'      => true,
                ],
                'created_at' => now(),
            ]);

            $updates = ['last_message_at' => now()];
            if ($t->title === 'New chat') {
                $updates['title'] = \Illuminate\Support\Str::limit(trim($message), 80, '…');
            }
            $t->forceFill($updates)->save();

            $emit('done', [
                'message' => $assistant->only(['id', 'role', 'content', 'meta', 'feedback', 'created_at']),
                'thread'  => ['id' => $t->id, 'title' => $t->title],
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

    public function feedback(Request $request, int $message): JsonResponse
    {
        $this->ensureEnabled($request);
        $data = $request->validate([
            'feedback' => 'required|in:up,down,clear',
            'note'     => 'nullable|string|max:500',
        ]);
        $msg = AskCoachMessage::query()
            ->whereHas('thread', fn ($q) => $q->where('user_id', $request->user()->id))
            ->where('role', 'assistant')
            ->findOrFail($message);
        $msg->feedback = $data['feedback'] === 'clear' ? null : $data['feedback'];
        $msg->feedback_note = $data['feedback'] === 'down' ? ($data['note'] ?? null) : null;
        $msg->save();
        return response()->json(['message' => $msg]);
    }

    public function destroy(Request $request, int $thread): JsonResponse
    {
        $this->ensureEnabled($request);
        $t = AskCoachThread::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $thread)
            ->firstOrFail();
        $t->delete();
        return response()->json(['ok' => true]);
    }

    protected function ensureEnabled(Request $request): void
    {
        abort_unless(AiEngineSettings::isEnabled(), 404);
        $u = $request->user();
        abort_unless($u && AiEngineSettings::askCoachAllowedFor($u), 403, 'Ask Coach is not on your plan.');
    }
}
