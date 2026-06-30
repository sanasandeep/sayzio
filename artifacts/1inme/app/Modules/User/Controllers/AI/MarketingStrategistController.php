<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Task #3060 — AI Digital Performer / Marketing Strategist (web).
 *
 * Creators toggle which of their OWN data to feed in, set a goal +
 * parameters, and the assistant generates an organic + paid marketing
 * strategy built around real Sayzio features. They can then chat-refine
 * (streamed, metered) and one-click apply suggestions (create link /
 * add block / attach pixel / draft a scheduled creator post).
 *
 * Spend flows through the shared {@see OpenAiService} pipeline tagged
 * `feature => 'marketing_strategist'` with auto-refund on failure.
 */
class MarketingStrategistController extends Controller
{
    /** Cap turns sent to the model on chat-refine. Older turns stay in the DB. */
    protected const MAX_PROMPT_TURNS = 8;

    public function __construct(
        protected MarketingStrategistService $strategist,
        protected MarketingSuggestionApplier $applier,
        protected OpenAiService $ai,
        protected AiUsageCharger $credits,
    ) {}

    /** List the creator's saved strategies. */
    public function index(Request $request)
    {
        if ($gate = $this->gateView($request)) return $gate;

        $strategies = $this->scopeOwned($request)
            ->orderByDesc('id')
            ->get();

        return view('user.ai.marketing-strategist.index', [
            'strategies' => $strategies,
            'balance'    => $this->credits->getBalance($request->user()),
        ]);
    }

    /** The builder form: pick data sources, goal and parameters. */
    public function create(Request $request)
    {
        if ($gate = $this->gateView($request)) return $gate;

        return view('user.ai.marketing-strategist.create', [
            'sources' => MarketingStrategistService::SOURCES,
            'old'     => session('ai.marketing_strategist.input', []),
            'balance' => $this->credits->getBalance($request->user()),
        ]);
    }

    /** Worst-case credit estimate shown before Generate (AJAX). */
    public function estimate(Request $request)
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

        return response()->json([
            'estimate' => $cost,
            'balance'  => $this->credits->getBalance($request->user()),
        ]);
    }

    /** Generate a strategy from the builder form. */
    public function store(Request $request)
    {
        $this->ensureEnabled($request);
        $user = $request->user();
        $data = $this->validateBuilder($request);

        $current = $this->scopeOwned($request)->count();
        if (!AiPlanAccess::underQuantityCap($user, 'marketing_strategies', $current)) {
            $msg = AiPlanAccess::quantityLimitMessage($user, 'marketing_strategies', 'marketing strategies', $current);
            return back()->withInput()->with('error', $msg);
        }

        session(['ai.marketing_strategist.input' => $request->only(['goal', 'sources', 'parameters'])]);

        try {
            $result = $this->strategist->generate(
                $user,
                $data['goal'],
                $data['parameters'],
                $data['sources'],
                $this->workspaceId(),
            );
        } catch (InsufficientCoinsForAiException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::warning('Marketing Strategist generation failed: ' . $e->getMessage());
            return back()->withInput()->with('error', 'The strategy could not be generated right now. Please try again.');
        }

        session()->forget('ai.marketing_strategist.input');

        return redirect()
            ->route('user.ai.marketing-strategist.show', $result['strategy']->id)
            ->with('status', 'Your marketing strategy is ready.');
    }

    /** View a single strategy with its plan, suggestions and chat. */
    public function show(Request $request, int $strategy)
    {
        if ($gate = $this->gateView($request)) return $gate;

        $model = $this->findOwned($request, $strategy);

        return view('user.ai.marketing-strategist.show', [
            'strategy'    => $model,
            'suggestions' => $model->suggestions()->get(),
            'messages'    => $model->messages()->get(),
            'balance'     => $this->credits->getBalance($request->user()),
        ]);
    }

    /** Delete a strategy (and its messages + suggestions via the model hook). */
    public function destroy(Request $request, int $strategy)
    {
        $this->ensureEnabled($request);
        $this->findOwned($request, $strategy)->delete();

        return redirect()
            ->route('user.ai.marketing-strategist.index')
            ->with('status', 'Strategy deleted.');
    }

    /** Download the strategy as Markdown (default) or PDF (`?format=pdf`). */
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

        return response()->streamDownload(function () use ($model) {
            echo $this->strategist->toMarkdown($model);
        }, $base . '.md', ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    /** Chat-refine the strategy (streamed SSE, metered). */
    public function chat(Request $request, int $strategy): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        $this->ensureEnabled($request);
        $model = $this->findOwned($request, $strategy);

        $message = trim((string) $request->input('message', ''));
        if ($message === '') {
            return response()->json(['error' => ['message' => 'Type a message first.']], 422);
        }
        $message = mb_substr($message, 0, 4000);

        return $this->chatStream($request, $model, $message);
    }

    /** Apply a single suggestion, building the real owned object. */
    public function applySuggestion(Request $request, int $suggestion)
    {
        $this->ensureEnabled($request);
        $model = $this->findOwnedSuggestion($request, $suggestion);

        if (!$model->isPending()) {
            return response()->json([
                'error' => ['message' => 'This suggestion is no longer pending.'],
            ], 422);
        }

        // Atomically claim + apply so two near-simultaneous requests (double-tap,
        // retry, web+mobile) can't both pass the pending check above and both
        // build the owned object. The loser of the race gets a clean 422.
        try {
            $result = $this->applier->claimAndApply($request->user(), $model);
        } catch (SuggestionNotPendingException $e) {
            return response()->json([
                'error' => ['message' => 'This suggestion is no longer pending.'],
            ], 422);
        } catch (\Throwable $e) {
            // claimAndApply already flipped the row to `error`; $model->status
            // reflects that committed state.
            return response()->json([
                'error'  => ['message' => $e->getMessage()],
                'status' => $model->status,
            ], 422);
        }

        return response()->json([
            'status'  => $model->status,
            'message' => $result['message'],
            'url'     => $result['url'] ?? null,
        ]);
    }

    /** Dismiss a suggestion without applying it. */
    public function dismissSuggestion(Request $request, int $suggestion)
    {
        $this->ensureEnabled($request);
        $model = $this->findOwnedSuggestion($request, $suggestion);

        if ($model->isPending()) {
            $model->forceFill(['status' => MarketingStrategySuggestion::STATUS_DISMISSED])->save();
        }

        return response()->json(['status' => $model->status]);
    }

    // ── streaming ──────────────────────────────────────────────────

    protected function chatStream(Request $request, MarketingStrategy $strategy, string $message): StreamedResponse
    {
        $user = $request->user();

        MarketingStrategyMessage::create([
            'strategy_id' => $strategy->id,
            'role'        => 'user',
            'content'     => $message,
            'created_at'  => now(),
        ]);

        $recent = $strategy->messages()
            ->orderByDesc('id')
            ->limit(self::MAX_PROMPT_TURNS * 2)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();

        $messages = $this->strategist->buildRefineMessages($strategy, $recent);

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
                        'reason'      => 'Marketing Strategist: chat refine',
                    ],
                    function (string $delta) use ($emit) {
                        $emit('token', ['delta' => $delta]);
                    },
                );
            } catch (InsufficientCoinsForAiException $e) {
                $emit('error', ['code' => 'insufficient_credits', 'message' => $e->getMessage()]);
                return;
            } catch (\Throwable $e) {
                Log::warning('Marketing Strategist chat failed: ' . $e->getMessage());
                $emit('error', ['code' => 'ai_unavailable', 'message' => 'The strategist could not reply right now. Please try again.']);
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
                'message' => [
                    'id'      => $assistant->id,
                    'role'    => 'assistant',
                    'content' => $assistant->content,
                    'meta'    => $assistant->meta,
                ],
                'balance' => $this->credits->getBalance($user),
            ]);
        });

        $response->headers->set('Content-Type', 'text/event-stream; charset=UTF-8');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Connection', 'keep-alive');
        return $response;
    }

    // ── helpers ────────────────────────────────────────────────────

    /** @return array{goal:string,sources:array<int,string>,parameters:array<string,mixed>} */
    protected function validateBuilder(Request $request): array
    {
        $validated = $request->validate([
            'goal'              => 'required|string|max:4000',
            'sources'           => 'nullable|array',
            'sources.*'         => 'string',
            'parameters'        => 'nullable|array',
            'parameters.budget' => 'nullable|string|max:120',
            'parameters.audience' => 'nullable|string|max:300',
            'parameters.timeframe' => 'nullable|string|max:120',
            'parameters.tone'   => 'nullable|string|max:120',
            'parameters.channels' => 'nullable|string|max:300',
        ]);

        return [
            'goal'       => trim((string) $validated['goal']),
            'sources'    => $this->strategist->normalizeSources((array) ($validated['sources'] ?? [])),
            'parameters' => array_filter((array) ($validated['parameters'] ?? []), fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /** Scope a query to the current owner + workspace. */
    protected function scopeOwned(Request $request)
    {
        $wsId = $this->workspaceId();

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
        $wsId = $this->workspaceId();

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

    /**
     * View-returning gate: master switch off OR plan not allowed →
     * render the shared self-serve upgrade page instead of a bare error.
     */
    protected function gateView(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) {
            return view('user.ai.disabled', ['title' => 'Marketing Strategist']);
        }
        if (!AiPlanAccess::featureAllowed($request->user(), MarketingStrategistService::FEATURE)) {
            return view('user.ai.disabled', [
                'title'       => 'Marketing Strategist',
                'upgradePlan' => AiPlanAccess::featureUpgradePlan($request->user(), MarketingStrategistService::FEATURE),
            ]);
        }
        return null;
    }

    /** Hard gate for write/stream actions. */
    protected function ensureEnabled(Request $request): void
    {
        if (!AiEngineSettings::isEnabled()) abort(404);
        $user = $request->user();
        if ($user && !AiPlanAccess::featureAllowed($user, MarketingStrategistService::FEATURE)) {
            abort(403, 'Marketing Strategist is not available on your current plan.');
        }
    }

    protected function workspaceId(): ?int
    {
        return app()->bound('current_workspace')
            ? (int) app('current_workspace')->id
            : null;
    }
}
