<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\MarketingStrategy;
use App\Modules\User\Models\MarketingStrategyMessage;
use App\Modules\User\Models\MarketingStrategySuggestion;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\InsufficientCoinsForAiException;
use App\Services\AI\MarketingStrategistService;
use App\Services\AI\MarketingSuggestionApplier;
use App\Services\AI\OpenAiService;
use App\Services\AI\SuggestionNotPendingException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Task #3060 — AI Marketing Strategist REST parity.
 *
 * Mirrors {@see \App\Modules\User\Controllers\AI\MarketingStrategistController}
 * — same persistence, service pipeline and metering; only the I/O is JSON.
 * The strategist grounds its plan in the creator's OWN data, generates an
 * organic + paid plan around real Sayzio features, supports streamed
 * chat-refine and one-click apply of suggestions.
 *
 * JSON responses use the unified `{data}` / `{error}` envelope via the
 * {@see ApiResponses} trait (SSE frames keep their own event format), and
 * strategies are resolved into the caller's active workspace via
 * {@see ApiResponses::resolveWorkspaceId()} so rows created on mobile land
 * in the SAME workspace the website reads from (the Sanctum path never runs
 * SetActiveWorkspace, so without this they would be created null-workspace
 * and appear "missing" in workspace-scoped web views).
 */
class MarketingStrategistController extends Controller
{
    use ApiResponses;

    protected const MAX_PROMPT_TURNS = 8;

    public function __construct(
        protected MarketingStrategistService $strategist,
        protected MarketingSuggestionApplier $applier,
        protected OpenAiService $ai,
        protected AiUsageCharger $credits,
    ) {}

    /** List the creator's saved strategies. */
    public function index(Request $request): JsonResponse
    {
        // Entry-point loader degrades gracefully when the engine is off,
        // mirroring the Ask Coach API: an informative 200 instead of a 404
        // so the client can show an "AI is off" state without bouncing.
        if (!AiEngineSettings::isEnabled()) {
            return $this->ok(['strategies' => [], 'ai_enabled' => false]);
        }
        $this->ensureEnabled($request);

        $items = $this->scopeOwned($request)
            ->orderByDesc('id')
            ->get()
            ->map(fn (MarketingStrategy $s) => $this->summary($s));

        return $this->ok([
            'strategies' => $items,
            'ai_enabled' => true,
            'sources'    => $this->sourceCatalog(),
            'balance'    => $this->credits->getBalance($request->user()),
        ]);
    }

    /** Worst-case credit estimate shown before generate. */
    public function estimate(Request $request): JsonResponse
    {
        $this->ensureEnabled($request);
        $data = $this->validateBuilder($request);

        $assembled = $this->strategist->buildContext($request->user(), $data['sources']);
        $cost = $this->strategist->estimateCredits(
            $request->user(),
            $data['goal'],
            $data['parameters'],
            $assembled['context'],
        );

        return $this->ok([
            'estimate' => $cost,
            'balance'  => $this->credits->getBalance($request->user()),
        ]);
    }

    /** Generate a strategy. */
    public function store(Request $request): JsonResponse
    {
        $this->ensureEnabled($request);
        $user = $request->user();
        $data = $this->validateBuilder($request);

        $current = $this->scopeOwned($request)->count();
        if (!AiPlanAccess::underQuantityCap($user, 'marketing_strategies', $current)) {
            return $this->planGate(
                AiPlanAccess::quantityLimitMessage($user, 'marketing_strategies', 'marketing strategies', $current),
                'marketing_strategies',
                $user,
                422,
                'plan_limit',
                $current,
            );
        }

        try {
            $result = $this->strategist->generate(
                $user,
                $data['goal'],
                $data['parameters'],
                $data['sources'],
                $this->workspaceId($request),
            );
        } catch (InsufficientCoinsForAiException $e) {
            return $this->fail($e->getMessage(), 402, 'insufficient_credits');
        } catch (\Throwable $e) {
            Log::warning('Marketing Strategist (api) generation failed: ' . $e->getMessage());
            return $this->fail('The strategy could not be generated right now.', 503, 'ai_unavailable');
        }

        return $this->created([
            'strategy'      => $this->detail($result['strategy']),
            'credits_spent' => (int) ($result['credits_spent'] ?? 0),
            'balance'       => $this->credits->getBalance($user),
        ]);
    }

    /** A single strategy with its plan, suggestions and chat. */
    public function show(Request $request, int $strategy): JsonResponse
    {
        $this->ensureEnabled($request);
        $model = $this->findOwned($request, $strategy);

        return $this->ok([
            'strategy'    => $this->detail($model),
            'suggestions' => $model->suggestions()->get()->map(fn ($s) => $this->suggestion($s)),
            'messages'    => $model->messages()->get(['id', 'role', 'content', 'meta', 'created_at']),
            'balance'     => $this->credits->getBalance($request->user()),
        ]);
    }

    /** Delete a strategy. */
    public function destroy(Request $request, int $strategy): JsonResponse
    {
        $this->ensureEnabled($request);
        $this->findOwned($request, $strategy)->delete();
        return $this->ok(['deleted' => true]);
    }

    /** Download the strategy as Markdown (default) or PDF (`?format=pdf`), parity with web. */
    public function export(Request $request, int $strategy)
    {
        $this->ensureEnabled($request);
        $model = $this->findOwned($request, $strategy);

        $base = (Str::slug($model->title) ?: 'marketing-strategy') . '-' . now()->format('Ymd-His');

        if (strtolower((string) $request->query('format')) === 'pdf') {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');
            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($this->strategist->toHtml($model), 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return response((string) $dompdf->output(), 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $base . '.pdf"',
            ]);
        }

        return response($this->strategist->toMarkdown($model), 200, [
            'Content-Type'        => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $base . '.md"',
        ]);
    }

    /** Chat-refine the strategy (streamed SSE by default, metered). */
    public function chat(Request $request, int $strategy)
    {
        $this->ensureEnabled($request);
        $model = $this->findOwned($request, $strategy);

        $data = $request->validate(['message' => 'required|string|min:1|max:4000']);
        $message = trim($data['message']);

        if ($this->wantsStream($request)) {
            return $this->chatStream($request, $model, $message);
        }

        return $this->chatJson($request, $model, $message);
    }

    /** Apply a single suggestion, building the real owned object. */
    public function applySuggestion(Request $request, int $suggestion): JsonResponse
    {
        $this->ensureEnabled($request);
        $model = $this->findOwnedSuggestion($request, $suggestion);

        if (!$model->isPending()) {
            return $this->fail('This suggestion is no longer pending.', 422, 'not_pending', ['status' => $model->status]);
        }

        // Applying performs a real, state-changing action (creates a link /
        // block / draft post or attaches a pixel). Require an explicit
        // `confirm` flag so a one-tap client can't fire it accidentally;
        // without it we return a 409 + a preview the client confirms against.
        if (!$request->boolean('confirm')) {
            return $this->fail(
                'Confirm before applying this suggestion.',
                409,
                'confirmation_required',
                ['suggestion' => $this->suggestion($model)],
            );
        }

        // Atomically claim + apply so two near-simultaneous requests (double-tap,
        // retry, web+mobile) can't both pass the pending check above and both
        // build the owned object. The loser of the race gets a clean 422.
        try {
            $result = $this->applier->claimAndApply($request->user(), $model);
        } catch (SuggestionNotPendingException $e) {
            return $this->fail('This suggestion is no longer pending.', 422, 'not_pending', ['status' => $model->status]);
        } catch (\Throwable $e) {
            // claimAndApply already flipped the row to `error`; $model->status
            // reflects that committed state.
            return $this->fail($e->getMessage(), 422, 'apply_failed', ['status' => $model->status]);
        }

        return $this->ok([
            'status'  => $model->status,
            'message' => $result['message'],
            'url'     => $result['url'] ?? null,
        ]);
    }

    /** Dismiss a suggestion without applying it. */
    public function dismissSuggestion(Request $request, int $suggestion): JsonResponse
    {
        $this->ensureEnabled($request);
        $model = $this->findOwnedSuggestion($request, $suggestion);

        if ($model->isPending()) {
            $model->forceFill(['status' => MarketingStrategySuggestion::STATUS_DISMISSED])->save();
        }

        return $this->ok(['status' => $model->status]);
    }

    // ── chat ───────────────────────────────────────────────────────

    protected function chatJson(Request $request, MarketingStrategy $strategy, string $message): JsonResponse
    {
        $user = $request->user();

        MarketingStrategyMessage::create([
            'strategy_id' => $strategy->id,
            'role'        => 'user',
            'content'     => $message,
            'created_at'  => now(),
        ]);

        $messages = $this->strategist->buildRefineMessages($strategy, $this->recentTurns($strategy));

        try {
            $out = $this->ai->chat($user, AiEngineSettings::featureModel(MarketingStrategistService::FEATURE), $messages, [
                'feature'     => MarketingStrategistService::CHAT_FEATURE,
                'temperature' => 0.5,
                'max_tokens'  => 700,
                'reason'      => 'Marketing Strategist: chat refine (api)',
            ]);
        } catch (InsufficientCoinsForAiException $e) {
            return $this->fail($e->getMessage(), 402, 'insufficient_credits');
        } catch (\Throwable $e) {
            return $this->fail('The strategist could not reply right now.', 503, 'ai_unavailable');
        }

        $assistant = MarketingStrategyMessage::create([
            'strategy_id' => $strategy->id,
            'role'        => 'assistant',
            'content'     => $out['content'],
            'meta'        => [
                'credits_spent' => (int) ($out['credits_spent'] ?? 0),
                'model'         => $out['model'] ?? null,
            ],
            'created_at'  => now(),
        ]);

        return $this->ok([
            'message' => $assistant->only(['id', 'role', 'content', 'meta', 'created_at']),
            'balance' => $this->credits->getBalance($user),
        ]);
    }

    protected function chatStream(Request $request, MarketingStrategy $strategy, string $message): StreamedResponse
    {
        $user = $request->user();

        MarketingStrategyMessage::create([
            'strategy_id' => $strategy->id,
            'role'        => 'user',
            'content'     => $message,
            'created_at'  => now(),
        ]);

        $messages = $this->strategist->buildRefineMessages($strategy, $this->recentTurns($strategy));

        $response = new StreamedResponse(function () use ($user, $messages, $strategy) {
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
                    AiEngineSettings::featureModel(MarketingStrategistService::FEATURE),
                    $messages,
                    [
                        'feature'     => MarketingStrategistService::CHAT_FEATURE,
                        'temperature' => 0.5,
                        'max_tokens'  => 700,
                        'reason'      => 'Marketing Strategist: chat refine (api, streamed)',
                    ],
                    function (string $delta) use ($emit) {
                        $emit('token', ['delta' => $delta]);
                    },
                );
            } catch (InsufficientCoinsForAiException $e) {
                $emit('error', ['code' => 'insufficient_credits', 'message' => $e->getMessage()]);
                return;
            } catch (\Throwable $e) {
                Log::warning('Marketing Strategist (api) stream failed: ' . $e->getMessage());
                $emit('error', ['code' => 'ai_unavailable', 'message' => 'The strategist could not reply right now.']);
                return;
            }

            $assistant = MarketingStrategyMessage::create([
                'strategy_id' => $strategy->id,
                'role'        => 'assistant',
                'content'     => $out['content'],
                'meta'        => [
                    'credits_spent' => (int) ($out['credits_spent'] ?? 0),
                    'model'         => $out['model'] ?? null,
                    'streamed'      => true,
                ],
                'created_at'  => now(),
            ]);

            $emit('done', [
                'message' => $assistant->only(['id', 'role', 'content', 'meta', 'created_at']),
                'balance' => $this->credits->getBalance($user),
            ]);
        });

        $response->headers->set('Content-Type', 'text/event-stream; charset=UTF-8');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Connection', 'keep-alive');
        return $response;
    }

    protected function recentTurns(MarketingStrategy $strategy): array
    {
        return $strategy->messages()
            ->orderByDesc('id')
            ->limit(self::MAX_PROMPT_TURNS * 2)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();
    }

    // ── serializers ────────────────────────────────────────────────

    protected function summary(MarketingStrategy $s): array
    {
        return [
            'id'            => $s->id,
            'title'         => $s->title,
            'goal'          => $s->goalSummary(160),
            'sources'       => (array) $s->sources,
            'credits_spent' => (int) $s->credits_spent,
            'created_at'    => optional($s->created_at)->toIso8601String(),
        ];
    }

    protected function detail(MarketingStrategy $s): array
    {
        return [
            'id'            => $s->id,
            'title'         => $s->title,
            'goal'          => $s->goal,
            'parameters'    => (array) $s->parameters,
            'sources'       => (array) $s->sources,
            'strategy'      => (array) $s->strategy,
            'credits_spent' => (int) $s->credits_spent,
            'model'         => $s->model,
            'created_at'    => optional($s->created_at)->toIso8601String(),
        ];
    }

    protected function suggestion(MarketingStrategySuggestion $s): array
    {
        return [
            'id'          => $s->id,
            'type'        => $s->type,
            'type_label'  => $s->typeLabel(),
            'title'       => $s->title,
            'description' => $s->description,
            'status'      => $s->status,
            'applied_ref_type' => $s->applied_ref_type,
            'applied_ref_id'   => $s->applied_ref_id,
            'error'       => $s->error,
        ];
    }

    protected function sourceCatalog(): array
    {
        $out = [];
        foreach (MarketingStrategistService::SOURCES as $key => $meta) {
            $out[] = [
                'key'         => $key,
                'label'       => $meta['label'] ?? $key,
                'description' => $meta['description'] ?? '',
            ];
        }
        return $out;
    }

    // ── helpers ────────────────────────────────────────────────────

    /** @return array{goal:string,sources:array<int,string>,parameters:array<string,mixed>} */
    protected function validateBuilder(Request $request): array
    {
        $validated = $request->validate([
            'goal'                 => 'required|string|max:4000',
            'sources'              => 'nullable|array',
            'sources.*'            => 'string',
            'parameters'           => 'nullable|array',
            'parameters.budget'    => 'nullable|string|max:120',
            'parameters.audience'  => 'nullable|string|max:300',
            'parameters.timeframe' => 'nullable|string|max:120',
            'parameters.tone'      => 'nullable|string|max:120',
            'parameters.channels'  => 'nullable|string|max:300',
        ]);

        return [
            'goal'       => trim((string) $validated['goal']),
            'sources'    => $this->strategist->normalizeSources((array) ($validated['sources'] ?? [])),
            'parameters' => array_filter((array) ($validated['parameters'] ?? []), fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /**
     * Resolve the workspace this Sanctum request reads/writes within.
     *
     * Honours an explicit, access-checked `workspace_id` for multi-workspace
     * callers, otherwise the user's active workspace — keeping API rows in the
     * same workspace bucket the workspace-scoped web views read from.
     */
    protected function workspaceId(Request $request): ?int
    {
        return $this->resolveWorkspaceId($request->user(), $request->input('workspace_id'));
    }

    protected function scopeOwned(Request $request)
    {
        $wsId = $this->workspaceId($request);

        return MarketingStrategy::query()
            ->where('user_id', $request->user()->id)
            ->where(fn ($q) => $wsId === null
                ? $q->whereNull('workspace_id')
                : $q->where('workspace_id', $wsId));
    }

    protected function findOwned(Request $request, int $strategy): MarketingStrategy
    {
        $model = $this->scopeOwned($request)->whereKey($strategy)->first();
        if (!$model) abort(404);
        return $model;
    }

    protected function findOwnedSuggestion(Request $request, int $suggestion): MarketingStrategySuggestion
    {
        $wsId = $this->workspaceId($request);

        $model = MarketingStrategySuggestion::query()
            ->whereKey($suggestion)
            ->whereHas('strategy', fn ($q) => $q
                ->where('user_id', $request->user()->id)
                ->where(fn ($w) => $wsId === null
                    ? $w->whereNull('workspace_id')
                    : $w->where('workspace_id', $wsId)))
            ->first();

        if (!$model) abort(404);
        return $model;
    }

    protected function wantsStream(Request $request): bool
    {
        if ($request->boolean('stream')) return true;
        $accept = (string) $request->header('Accept', '');
        return str_contains(strtolower($accept), 'text/event-stream');
    }

    protected function ensureEnabled(Request $request): void
    {
        abort_unless(AiEngineSettings::isEnabled(), 404);
        $user = $request->user();
        abort_unless(
            $user && AiPlanAccess::featureAllowed($user, MarketingStrategistService::FEATURE),
            403,
            'Marketing Strategist is not available on your current plan.'
        );
    }
}
