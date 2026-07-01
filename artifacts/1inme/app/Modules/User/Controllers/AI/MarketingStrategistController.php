<?php

namespace App\Modules\User\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\MarketingProfile;
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

        $profiles = MarketingProfile::listForOwner($request->user()->id, $this->workspaceId());

        return view('user.ai.marketing-strategist.create', [
            'sources'         => MarketingStrategistService::SOURCES,
            'items'           => $this->strategist->selectableItems($request->user()),
            'old'             => session('ai.marketing_strategist.input', []),
            'balance'         => $this->credits->getBalance($request->user()),
            'profileDefaults' => $this->profileDefaults($request),
            'profiles'        => $profiles,
            'profilesData'    => $this->profilesForPicker($profiles),
        ]);
    }

    /**
     * Task #3302 — a JS-friendly map of the owner's project profiles so the New
     * Strategy picker can pre-fill the form when one is chosen. PII-free.
     *
     * @param  \Illuminate\Support\Collection<int,MarketingProfile> $profiles
     * @return array<int,array<string,mixed>>
     */
    protected function profilesForPicker($profiles): array
    {
        $join = fn ($bag) => implode(', ', array_slice(array_filter((array) $bag), 0, 12));

        return $profiles->mapWithKeys(fn (MarketingProfile $p) => [$p->id => [
            'name'       => $p->displayName(),
            'goal'       => implode("\n", array_slice(array_filter((array) $p->expectations), 0, 8)),
            'audience'   => $join($p->target_audience),
            'avoid'      => $join($p->constraints),
            'budget'     => (string) ($p->budget ?? ''),
            'currency'   => (string) ($p->currency ?? ''),
            'main_offer' => (string) ($p->main_offer ?? ''),
        ]])->all();
    }

    /**
     * Pre-fill values sourced from the reusable Marketing Profile intake. These
     * are fallbacks only — anything the user typed (session `old`) wins.
     */
    protected function profileDefaults(Request $request): array
    {
        $profile = MarketingProfile::forOwner($request->user()->id, $this->workspaceId());
        if (!$profile) {
            return ['has_profile' => false, 'goal' => '', 'audience' => '', 'avoid' => ''];
        }

        $join = fn ($bag) => implode(', ', array_slice(array_filter((array) $bag), 0, 12));

        return [
            'has_profile' => $profile->isFilled(),
            'goal'        => implode("\n", array_slice(array_filter((array) $profile->expectations), 0, 8)),
            'audience'    => $join($profile->target_audience),
            'avoid'       => $join($profile->constraints),
        ];
    }

    /** Worst-case credit estimate shown before Generate (AJAX). */
    public function estimate(Request $request)
    {
        $this->ensureEnabled($request);
        $data = $this->validateBuilder($request);

        $assembled = $this->strategist->buildContext($request->user(), $data['sources'], $data['selections']);
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

        session(['ai.marketing_strategist.input' => $request->only(['goal', 'sources', 'source_items', 'parameters', 'profile_id', 'profile_choice', 'new_profile_name'])]);

        $profileId = $this->resolveStoreProfileId($request, $user, $data);

        try {
            $result = $this->strategist->generate(
                $user,
                $data['goal'],
                $data['parameters'],
                $data['sources'],
                $this->workspaceId(),
                $data['selections'],
                $profileId,
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

    /**
     * Free download tiers: Markdown (default), Rich PDF (`?format=pdf`) or
     * CSV (`?format=csv`). The Premium AI PDF tier — which spends coins — is a
     * separate POST at {@see report()}.
     */
    public function export(Request $request, int $strategy)
    {
        $this->ensureEnabled($request);
        $model = $this->findOwned($request, $strategy);

        $base   = $this->reportBase($model);
        $format = strtolower((string) $request->query('format'));

        if ($format === 'pdf') {
            return $this->pdfResponse($this->strategist->toHtml($model), $base);
        }

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($model) {
                echo $this->strategist->toCsv($model);
            }, $base . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($model) {
            echo $this->strategist->toMarkdown($model);
        }, $base . '.md', ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    /**
     * Premium AI PDF tier — generates a fresh AI executive summary (metered,
     * auto-refunded on failure) then streams a branded PDF that embeds it.
     */
    public function report(Request $request, int $strategy)
    {
        $this->ensureEnabled($request);
        $model = $this->findOwned($request, $strategy);

        try {
            $report = $this->strategist->generatePremiumReport($request->user(), $model);
        } catch (InsufficientCoinsForAiException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::warning('Marketing Strategist premium report failed: ' . $e->getMessage());
            return back()->with('error', 'The premium report could not be generated right now. Please try again.');
        }

        $html = $this->strategist->toHtml($model, (string) ($report['summary'] ?? ''));
        return $this->pdfResponse($html, $this->reportBase($model) . '-premium');
    }

    /** Free, PHP-only re-score of the strategy from CURRENT tracking data. */
    public function rescore(Request $request, int $strategy)
    {
        $this->ensureEnabled($request);
        $model = $this->findOwned($request, $strategy);

        $scorecard = $this->strategist->recomputeScore($model, $request->user());
        if ($scorecard === null) {
            return back()->with('error', 'Could not re-score right now — there is not enough tracking data yet.');
        }

        return back()->with('status', 'Marketing health re-scored from your latest data.');
    }

    /** Free, PHP-only outcome refresh (did the plan move the goal metric?). */
    public function refreshOutcome(Request $request, int $strategy)
    {
        $this->ensureEnabled($request);
        $model = $this->findOwned($request, $strategy);

        $outcome = $this->strategist->refreshOutcome($model, $request->user());
        if ($outcome === null) {
            return back()->with('error', 'No baseline to measure against yet. Apply a suggestion or two, then check back after some activity.');
        }

        return back()->with('status', 'Outcome updated from your latest metrics.');
    }

    /** Backward-compatible entry point → the project list. */
    public function profile(Request $request)
    {
        return redirect()->route('user.ai.marketing-strategist.projects.index');
    }

    /** List the creator's named project profiles. */
    public function projectsIndex(Request $request)
    {
        if ($gate = $this->gateView($request)) return $gate;

        return view('user.ai.marketing-strategist.projects.index', [
            'profiles' => MarketingProfile::listForOwner($request->user()->id, $this->workspaceId()),
            'balance'  => $this->credits->getBalance($request->user()),
        ]);
    }

    /** New-project form. */
    public function projectCreate(Request $request)
    {
        if ($gate = $this->gateView($request)) return $gate;

        return view('user.ai.marketing-strategist.projects.form', [
            'profile'   => new MarketingProfile(),
            'brandKits' => $this->brandKitOptions($request),
            'balance'   => $this->credits->getBalance($request->user()),
        ]);
    }

    /** Persist a new project profile. */
    public function projectStore(Request $request)
    {
        $this->ensureEnabled($request);

        $profile = new MarketingProfile();
        $profile->user_id = $request->user()->id;
        $profile->workspace_id = $this->workspaceId();
        $profile->fill($this->validateProject($request));
        $profile->save();

        return redirect()
            ->route('user.ai.marketing-strategist.projects.index')
            ->with('status', 'Project “' . $profile->displayName() . '” saved. New strategies can use it.');
    }

    /** Edit-project form. */
    public function projectEdit(Request $request, int $project)
    {
        if ($gate = $this->gateView($request)) return $gate;

        return view('user.ai.marketing-strategist.projects.form', [
            'profile'   => $this->findOwnedProfile($request, $project),
            'brandKits' => $this->brandKitOptions($request),
            'balance'   => $this->credits->getBalance($request->user()),
        ]);
    }

    /** Update an existing project profile. */
    public function projectUpdate(Request $request, int $project)
    {
        $this->ensureEnabled($request);

        $profile = $this->findOwnedProfile($request, $project);
        $profile->fill($this->validateProject($request));
        $profile->save();

        return redirect()
            ->route('user.ai.marketing-strategist.projects.index')
            ->with('status', 'Project “' . $profile->displayName() . '” updated.');
    }

    /** Delete a project profile. Strategies keep their stored snapshot. */
    public function projectDestroy(Request $request, int $project)
    {
        $this->ensureEnabled($request);

        $profile = $this->findOwnedProfile($request, $project);
        $name = $profile->displayName();
        $profile->delete();

        return redirect()
            ->route('user.ai.marketing-strategist.projects.index')
            ->with('status', 'Project “' . $name . '” deleted.');
    }

    /**
     * Validate + normalise a project profile form into fillable attributes.
     *
     * @return array<string,mixed>
     */
    protected function validateProject(Request $request): array
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:120',
            'business_name'   => 'nullable|string|max:160',
            'industry'        => 'nullable|string|max:160',
            'brand_kit_id'    => 'nullable|integer',
            'main_offer'      => 'nullable|string|max:300',
            'budget'          => 'nullable|string|max:120',
            'currency'        => 'nullable|string|max:40',
            'target_audience' => 'nullable|string|max:4000',
            'expectations'    => 'nullable|string|max:4000',
            'constraints'     => 'nullable|string|max:4000',
        ]);

        $toLines = function ($value): array {
            $lines = preg_split('/\r\n|\r|\n/', (string) $value) ?: [];
            $lines = array_values(array_filter(array_map('trim', $lines), fn ($v) => $v !== ''));
            return array_slice($lines, 0, 40);
        };

        // Only accept a brand kit that belongs to the owner.
        $brandKitId = null;
        if (!empty($validated['brand_kit_id']) && $this->ownsBrandKit($request, (int) $validated['brand_kit_id'])) {
            $brandKitId = (int) $validated['brand_kit_id'];
        }

        return [
            'name'            => trim((string) $validated['name']),
            'business_name'   => trim((string) ($validated['business_name'] ?? '')) ?: null,
            'industry'        => trim((string) ($validated['industry'] ?? '')) ?: null,
            'brand_kit_id'    => $brandKitId,
            'main_offer'      => trim((string) ($validated['main_offer'] ?? '')) ?: null,
            'budget'          => trim((string) ($validated['budget'] ?? '')) ?: null,
            'currency'        => trim((string) ($validated['currency'] ?? '')) ?: null,
            'target_audience' => $toLines($validated['target_audience'] ?? ''),
            'expectations'    => $toLines($validated['expectations'] ?? ''),
            'constraints'     => $toLines($validated['constraints'] ?? ''),
        ];
    }

    /**
     * Task #3302 — resolve which project profile a generation runs against:
     * an inline-created one (profile_choice='new' + new_profile_name, seeded
     * from the form fields), an explicitly chosen existing one, else none.
     */
    protected function resolveStoreProfileId(Request $request, $user, array $data): ?int
    {
        $choice = (string) $request->input('profile_choice', '');

        if ($choice === 'new') {
            $name = trim((string) $request->input('new_profile_name', ''));
            if ($name === '') {
                return null;
            }
            $params = $data['parameters'];
            $profile = new MarketingProfile();
            $profile->user_id = $user->id;
            $profile->workspace_id = $this->workspaceId();
            $profile->fill([
                'name'            => mb_substr($name, 0, 120),
                'main_offer'      => (string) ($params['main_offer'] ?? '') ?: null,
                'budget'          => (string) ($params['budget'] ?? '') ?: null,
                'currency'        => (string) ($params['currency'] ?? '') ?: null,
                'target_audience' => array_values(array_filter([trim((string) ($params['audience'] ?? ''))])),
                'expectations'    => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['goal']) ?: []))),
                'constraints'     => array_values(array_filter([trim((string) ($params['avoid'] ?? ''))])),
            ]);
            $profile->save();
            return $profile->id;
        }

        $chosen = (int) $request->input('profile_id', 0);
        if ($chosen > 0) {
            $exists = MarketingProfile::query()
                ->where('id', $chosen)
                ->where('user_id', $user->id)
                ->where('workspace_id', $this->workspaceId())
                ->exists();
            if ($exists) {
                return $chosen;
            }
        }

        return null;
    }

    /** Owner-scoped project profile lookup or 404. */
    protected function findOwnedProfile(Request $request, int $project): MarketingProfile
    {
        $model = MarketingProfile::query()
            ->whereKey($project)
            ->where('user_id', $request->user()->id)
            ->where('workspace_id', $this->workspaceId())
            ->first();

        if (!$model) abort(404);
        return $model;
    }

    /**
     * The owner's Brand Kits for the project form's brand picker.
     *
     * @return array<int,string>
     */
    protected function brandKitOptions(Request $request): array
    {
        try {
            return \App\Modules\User\Models\BrandKit::query()
                ->where('user_id', $request->user()->id)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** True when the given brand kit belongs to the current owner. */
    protected function ownsBrandKit(Request $request, int $brandKitId): bool
    {
        try {
            return \App\Modules\User\Models\BrandKit::query()
                ->whereKey($brandKitId)
                ->where('user_id', $request->user()->id)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Mint (or reuse) a public share link for the strategy report. */
    public function share(Request $request, int $strategy)
    {
        $this->ensureEnabled($request);
        $model = $this->findOwned($request, $strategy);

        if (!$model->isShared()) {
            $model->forceFill(['share_token' => Str::random(40)])->save();
        }

        return response()->json([
            'shared' => true,
            'url'    => route('public.ai-report', $model->share_token),
        ]);
    }

    /** Revoke a public share link. */
    public function unshare(Request $request, int $strategy)
    {
        $this->ensureEnabled($request);
        $model = $this->findOwned($request, $strategy);

        if ($model->isShared()) {
            $model->forceFill(['share_token' => null])->save();
        }

        return response()->json(['shared' => false]);
    }

    /** Download an APPROXIMATE sample report so creators can preview the format. */
    public function sample(Request $request)
    {
        if (!AiEngineSettings::isEnabled()) abort(404);

        $summary = 'This is an APPROXIMATE sample report. It shows the format and depth '
            . "of a real AI Marketing Strategist plan — the numbers here are illustrative, "
            . 'not based on your account. Generate your own strategy to get grounded, '
            . 'personalised analysis, forecasts and one-click actions.';

        $html = $this->strategist->toHtml($this->sampleStrategy(), $summary);
        return $this->pdfResponse($html, 'sayzio-sample-marketing-report');
    }

    // ── report helpers ─────────────────────────────────────────────

    /** A stable, dated base filename for a strategy's downloads. */
    protected function reportBase(MarketingStrategy $model): string
    {
        return (Str::slug($model->title) ?: 'marketing-strategy') . '-' . now()->format('Ymd-His');
    }

    /** Render branded HTML to a downloadable PDF response. */
    protected function pdfResponse(string $html, string $base): \Illuminate\Http\Response
    {
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response((string) $dompdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $base . '.pdf"',
        ]);
    }

    /** An unsaved, canned strategy used only to render the sample PDF. */
    protected function sampleStrategy(): MarketingStrategy
    {
        $model = new MarketingStrategy([
            'title'      => 'Sample — Grow newsletter & link clicks',
            'goal'       => 'Grow newsletter subscribers and drive more clicks to my link-in-bio over the next month.',
            'goal_metric' => 'subscribers',
            'parameters' => ['depth' => 4],
        ]);

        $model->strategy = [
            'summary' => 'A balanced organic + paid plan focused on capturing bio-link visitors into your newsletter, then nurturing them toward your paid offer.',
            'organic' => [
                ['title' => 'Weekly value thread', 'channel' => 'Instagram', 'rationale' => 'Turn your best-performing link topics into a recurring content series.', 'steps' => ['Repurpose your top link into a 5-slide carousel', 'Add a clear CTA to your bio link', 'Pin the post for the week'], 'sayzio_features' => ['Biolink', 'Analytics']],
                ['title' => 'Lead-magnet capture', 'channel' => 'Biolink', 'rationale' => 'Add an email capture block above the fold to convert existing traffic.', 'steps' => ['Add a Subscribe block', 'Offer a one-page checklist', 'Route confirmations via email']],
            ],
            'paid' => [
                ['title' => 'Retarget bio-link visitors', 'channel' => 'Instagram Ads', 'budget_hint' => '≈ $5/day', 'rationale' => 'Warm audiences convert cheapest — retarget people who already clicked.', 'steps' => ['Install your pixel', 'Build a 30-day visitor audience', 'Run a single-image subscribe ad']],
            ],
            'kpis' => ['New subscribers / week', 'Bio-link click-through rate', 'Cost per subscriber'],
        ];
        $model->diagnosis = ['narrative' => [
            'Your traffic is healthy but only a small share converts to subscribers.',
            'Engagement peaks midweek — concentrate posting there.',
            'Paid retargeting is currently unused, leaving warm traffic on the table.',
        ]];
        $model->scorecard = ['overall' => 62, 'reach' => 70, 'engagement' => 66, 'conversion' => 48, 'consistency' => 64, 'reasons' => ['Strong reach relative to peers', 'Conversion lags — add a capture block', 'Posting cadence is slightly irregular']];
        $model->forecast = ['metric' => 'subscribers', 'narrative' => 'The realistic band assumes you ship the capture block and post 3×/week.', 'bands' => [
            'pessimistic' => ['value' => 120, 'delta_pct' => 10, 'label' => 'Pessimistic'],
            'realistic'   => ['value' => 180, 'delta_pct' => 65, 'label' => 'Realistic'],
            'optimistic'  => ['value' => 240, 'delta_pct' => 120, 'label' => 'Optimistic'],
        ]];

        return $model;
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

    /** @return array{goal:string,sources:array<int,string>,selections:array<string,list<int>>,parameters:array<string,mixed>} */
    protected function validateBuilder(Request $request): array
    {
        $validated = $request->validate([
            'goal'              => 'required|string|max:4000',
            'sources'           => 'nullable|array',
            'sources.*'         => 'string',
            'source_items'      => 'nullable|array',
            'source_items.*'    => 'array',
            'source_items.*.*'  => 'integer',
            'parameters'        => 'nullable|array',
            'parameters.budget'       => 'nullable|string|max:120',
            'parameters.currency'     => 'nullable|string|max:40',
            'parameters.region'       => 'nullable|string|max:160',
            'parameters.audience'     => 'nullable|string|max:300',
            'parameters.timeframe'    => 'nullable|string|max:120',
            'parameters.cadence'      => 'nullable|string|max:120',
            'parameters.tone'         => 'nullable|string|max:120',
            'parameters.brand_voice'  => 'nullable|string|max:300',
            'parameters.competitors'  => 'nullable|string|max:400',
            'parameters.main_offer'   => 'nullable|string|max:300',
            'parameters.avoid'        => 'nullable|string|max:400',
            'parameters.channels'     => 'nullable|string|max:300',
            'parameters.plan_type'    => 'nullable|string|in:both,organic,paid',
            'parameters.depth'        => 'nullable|integer|min:1|max:5',
            'parameters.goal_metric'  => 'nullable|string|in:clicks,views,subscribers,followers,orders,revenue',
            'parameters.horizon_days' => 'nullable|integer|min:7|max:365',
            'parameters.plan_months'  => 'nullable|integer|min:1|max:12',
            'parameters.content_types'   => 'nullable|array',
            'parameters.content_types.*' => 'string|max:80',
            'parameters.paid_media'      => 'nullable|array',
            'parameters.paid_media.*'    => 'string|max:80',
        ]);

        $sources = $this->strategist->normalizeSources((array) ($validated['sources'] ?? []));

        return [
            'goal'       => trim((string) $validated['goal']),
            'sources'    => $sources,
            'selections' => $this->strategist->normalizeSelections((array) ($validated['source_items'] ?? []), $sources),
            'parameters' => $this->cleanParameters((array) ($validated['parameters'] ?? [])),
        ];
    }

    /** Drop empty scalars and empty arrays from the parameter bag. */
    protected function cleanParameters(array $parameters): array
    {
        $out = [];
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $value = array_values(array_filter(array_map('strval', $value), fn ($v) => trim($v) !== ''));
                if ($value) $out[$key] = $value;
                continue;
            }
            if ($value !== null && trim((string) $value) !== '') {
                $out[$key] = $value;
            }
        }
        return $out;
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
