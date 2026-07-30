<?php

namespace App\Services\AI;

use App\Modules\User\Models\AiCompanion;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\MarketingProfile;
use App\Modules\User\Models\MarketingStrategy;
use App\Modules\User\Models\MarketingStrategyScore;
use App\Modules\User\Models\MarketingStrategySuggestion;
use App\Modules\User\Models\Pixel;
use App\Modules\User\Models\User;
use App\Services\AI\AskCoach\AskCoachToolRegistry;
use App\Services\AI\Marketing\MarketingDiagnosisService;
use App\Services\AI\Marketing\MarketingForecastService;
use App\Services\AI\Marketing\MarketingOutcomeService;
use App\Services\AI\Marketing\MarketingScorecardService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Task #3060 — AI Digital Performer / Marketing Strategist.
 *
 * Turns a creator's goal + parameters + a chosen set of their OWN data
 * sources into a structured organic + paid marketing strategy built
 * around real Sayzio features, plus a short list of one-click applyable
 * suggestions (create link / add biolink block / attach pixel / draft a
 * scheduled creator post).
 *
 * Spend is metered through the shared {@see OpenAiService::chat} pipeline
 * (charges on a successful API call). Everything after the charge —
 * JSON parse + validation — is wrapped so a failed generation is auto
 * refunded and never nets a charge, mirroring AiBrandKitService.
 */
class MarketingStrategistService
{
    public const FEATURE = 'marketing_strategist';

    /** Sub-feature tag for chat-refine spend (admin reporting), like `ask_coach.chat`. */
    public const CHAT_FEATURE = 'marketing_strategist.chat';

    /** Task #3281 — sub-feature tag for the premium AI PDF exec-summary spend. */
    public const REPORT_FEATURE = 'marketing_strategist.report';

    /** Output ceiling for a full strategy. */
    public const MAX_OUTPUT_TOKENS = 2200;

    /** Task #3281 — output ceiling for the premium report exec-summary. */
    public const REPORT_MAX_OUTPUT_TOKENS = 1100;

    /**
     * Task #3281 — creative 5-step analysis depth. Each level adds a layer of
     * deterministic analysis (computed in PHP) plus more room for the AI to
     * explain it. Higher depth ⇒ a bigger (more expensive) generation.
     *
     *   1 Quick Scan   · plan only
     *   2 +Diagnosis    · grounded strengths / gaps
     *   3 +Scorecard    · 0-100 Reach/Engagement/Conversion/Consistency
     *   4 +Forecast     · three-scenario projection
     *   5 +Deep/Compete · multi-step funnels + optional competitor grounding
     *
     * @var array<int,array{key:string,label:string,tokens:int,blurb:string}>
     */
    public const DEPTH_LEVELS = [
        1 => ['key' => 'quick',     'label' => 'Quick Scan',    'tokens' => 900,  'blurb' => 'A fast organic + paid plan with one-click actions.'],
        2 => ['key' => 'diagnosis', 'label' => 'Diagnosis',     'tokens' => 1300, 'blurb' => 'Adds a grounded read of what is working and what is leaking.'],
        3 => ['key' => 'scorecard', 'label' => 'Scorecard',     'tokens' => 1700, 'blurb' => 'Adds a 0-100 marketing scorecard across four axes.'],
        4 => ['key' => 'forecast',  'label' => 'Forecast',      'tokens' => 2100, 'blurb' => 'Adds a three-scenario forecast for your goal metric.'],
        5 => ['key' => 'deep',      'label' => 'Deep Dive',     'tokens' => 2600, 'blurb' => 'Adds multi-step funnels and optional competitor grounding.'],
    ];

    /**
     * Selectable data sources the creator can toggle on. Each maps to a
     * builder that returns a compact, PII-free text snapshot fed to the
     * model. Keys double as the persisted `sources` flags.
     *
     * `selectable` marks sources that contain individual items the creator
     * can narrow down to (picking none of them = "use all"). Aggregate
     * sources (analytics, audience) stay simple on/off — they have no items.
     *
     * Note: the `minds` key is INTERNAL/unchanged; its user-facing label is
     * "Knowledge Bases" (the AiMind model/table/routes stay as-is).
     *
     * @var array<string,array{label:string,description:string,selectable:bool}>
     */
    public const SOURCES = [
        'links'       => ['label' => 'Links & types',       'description' => 'Your links, their types and lifetime clicks.', 'selectable' => true],
        'analytics'   => ['label' => 'Analytics',           'description' => 'Recent click trends and device split.',        'selectable' => false],
        'audience'    => ['label' => 'Followers & subscribers', 'description' => 'Audience size and growth.',                 'selectable' => false],
        'pixels'      => ['label' => 'Tracking pixels',      'description' => 'Ad pixels you already have connected.',        'selectable' => true],
        'minds'       => ['label' => 'AI Minds',             'description' => 'Your AI Minds (names only).',           'selectable' => true],
        'brand_kits'  => ['label' => 'Brand Kits',           'description' => 'Your brand palette, voice and taglines.',      'selectable' => true],
        'personas'    => ['label' => 'AI Personas',          'description' => 'Your saved AI persona agents.',                'selectable' => true],
        'companions'  => ['label' => 'AI Companions',        'description' => 'Your published AI chat companions.',           'selectable' => true],
    ];

    /** Source keys that expose individually selectable items. */
    public const SELECTABLE_SOURCES = ['links', 'pixels', 'minds', 'brand_kits', 'personas', 'companions'];

    public function __construct(
        protected OpenAiService $openai,
        protected AiUsageCharger $credits,
        protected AskCoachToolRegistry $tools,
        protected MarketingDiagnosisService $diagnosisSvc,
        protected MarketingScorecardService $scorecardSvc,
        protected MarketingForecastService $forecastSvc,
        protected MarketingOutcomeService $outcomeSvc,
    ) {}

    /** Clamp an arbitrary depth input to the supported 1-5 range. */
    public function normalizeDepth($depth): int
    {
        $d = is_numeric($depth) ? (int) $depth : 3;
        return max(1, min(5, $d));
    }

    /** Output-token budget for a generation at the given depth. */
    public function depthTokens(int $depth): int
    {
        return self::DEPTH_LEVELS[$this->normalizeDepth($depth)]['tokens'] ?? self::MAX_OUTPUT_TOKENS;
    }

    /** Normalise an arbitrary list of source keys to the known set. */
    public function normalizeSources(array $sources): array
    {
        $known = array_keys(self::SOURCES);
        $clean = array_values(array_intersect($known, array_map('strval', $sources)));
        return $clean ?: ['links', 'analytics', 'audience'];
    }

    /**
     * Normalise the per-source item selection. Returns a map keyed by the
     * selectable source key → a de-duplicated list of integer item IDs.
     * Empty / unknown sources are dropped; an empty list means "use all".
     *
     * @param  array<string,mixed>  $selections
     * @return array<string,list<int>>
     */
    public function normalizeSelections(array $selections, ?array $sources = null): array
    {
        $out = [];
        foreach ($selections as $key => $ids) {
            $key = (string) $key;
            if (!in_array($key, self::SELECTABLE_SOURCES, true)) continue;
            if ($sources !== null && !in_array($key, $sources, true)) continue;
            if (!is_array($ids)) continue;
            $clean = [];
            foreach ($ids as $id) {
                if (is_numeric($id)) {
                    $n = (int) $id;
                    if ($n > 0) $clean[$n] = $n;
                }
            }
            if ($clean) {
                $out[$key] = array_values($clean);
            }
        }
        return $out;
    }

    /**
     * The creator's own pickable items for each selectable source, so the
     * builder can offer per-item selection. Keyed by source key; each item
     * is `{id, label, sub}` (PII-free). Sources with no items are omitted.
     *
     * @param  array<int,string>|null  $sources  limit to these source keys
     * @return array<string,list<array{id:int,label:string,sub:string}>>
     */
    public function selectableItems(User $user, ?array $sources = null): array
    {
        $wanted = $sources === null
            ? self::SELECTABLE_SOURCES
            : array_values(array_intersect(self::SELECTABLE_SOURCES, $sources));

        $out = [];
        foreach ($wanted as $key) {
            try {
                $items = match ($key) {
                    'links'      => $this->itemsLinks($user),
                    'pixels'     => $this->itemsPixels($user),
                    'minds'      => $this->itemsMinds($user),
                    'brand_kits' => $this->itemsBrandKits($user),
                    'personas'   => $this->itemsPersonas($user),
                    'companions' => $this->itemsCompanions($user),
                    default      => [],
                };
            } catch (\Throwable $e) {
                $items = [];
            }
            $out[$key] = $items;
        }
        return $out;
    }

    /**
     * Assemble the data context for the toggled sources, optionally narrowed
     * to a specific set of item IDs per source (empty per-source = use all).
     *
     * @param  array<string,list<int>>  $selections
     * @return array{context:string,snapshot:array<string,string>}
     */
    public function buildContext(User $user, array $sources, array $selections = []): array
    {
        $sources    = $this->normalizeSources($sources);
        $selections = $this->normalizeSelections($selections, $sources);
        $snapshot   = [];

        foreach ($sources as $src) {
            $ids  = $selections[$src] ?? null;
            $text = '';
            try {
                $text = match ($src) {
                    'links'      => $this->snapshotLinks($user, $ids),
                    'analytics'  => $this->snapshotTool($user, 'analytics'),
                    'audience'   => $this->snapshotTool($user, 'audience'),
                    'pixels'     => $this->snapshotPixels($user, $ids),
                    'minds'      => $this->snapshotMinds($user, $ids),
                    'brand_kits' => $this->snapshotBrandKits($user, $ids),
                    'personas'   => $this->snapshotPersonas($user, $ids),
                    'companions' => $this->snapshotCompanions($user, $ids),
                    default      => '',
                };
            } catch (\Throwable $e) {
                $text = '';
            }
            if (trim($text) !== '') {
                $snapshot[$src] = trim($text);
            }
        }

        $parts = [];
        foreach ($snapshot as $src => $text) {
            $label = self::SOURCES[$src]['label'] ?? ucfirst($src);
            $parts[] = "[{$label}]\n{$text}";
        }
        $context = $parts
            ? implode("\n\n", $parts)
            : 'The creator did not share any account data for this strategy.';

        return ['context' => $context, 'snapshot' => $snapshot];
    }

    /** Worst-case credit cost shown before the user clicks Generate (depth-scaled). */
    public function estimateCredits(User $user, string $goal, array $parameters, string $context): int
    {
        $depth    = $this->normalizeDepth($parameters['depth'] ?? null);
        $model    = AiEngineSettings::featureModel(self::FEATURE, $user);
        $messages = $this->buildMessages($goal, $parameters, $context, [], $depth);
        return $this->openai->estimateChatCoins($model, $messages, $this->depthTokens($depth), $user);
    }

    /**
     * Task #3281 — worst-case credit cost for the premium AI report exec-summary.
     * Free tiers (Markdown / Rich PDF / CSV) never call this.
     */
    public function estimateReportCredits(User $user, MarketingStrategy $strategy): int
    {
        $model    = AiEngineSettings::featureModel(self::FEATURE, $user);
        $messages = $this->buildReportMessages($strategy);
        return $this->openai->estimateChatCoins($model, $messages, self::REPORT_MAX_OUTPUT_TOKENS, $user);
    }

    /**
     * Run the generation: call the model, parse the JSON into a saved
     * MarketingStrategy + its suggestions. On any parse/validation
     * failure the exact credits charged are refunded.
     *
     * @return array{strategy:MarketingStrategy,credits_spent:int,model:string}
     */
    public function generate(User $user, string $goal, array $parameters, array $sources, ?int $workspaceId = null, array $selections = [], ?int $profileId = null): array
    {
        $sources    = $this->normalizeSources($sources);
        $selections = $this->normalizeSelections($selections, $sources);
        $assembled  = $this->buildContext($user, $sources, $selections);

        // Task #3302 — resolve the named project this plan is built for. Its
        // snapshot both grounds the AI (project block) and is recorded on the
        // strategy so the plan can be traced back to (and re-run against) it.
        $profile         = $this->resolveProfile($profileId, $user, $workspaceId);
        $projectSnapshot = $profile ? $profile->toSnapshot() : [];

        // Task #3281 — depth governs how much deterministic analysis we compute
        // and how much room the model has to explain it. Persist it so re-opens
        // and re-scores know the plan's depth.
        $depth = $this->normalizeDepth($parameters['depth'] ?? null);
        $parameters['depth'] = $depth;

        $goalMetric = $this->diagnosisSvc->normalizeMetric($parameters['goal_metric'] ?? 'clicks');
        $parameters['goal_metric'] = $goalMetric;

        // Deterministic analysis: PHP computes every number from real tracking
        // data; the AI only explains it. Fully guarded — a missing table or a
        // cold-start account must never break generation.
        $analysis = $this->computeAnalysis($user, $depth, $goalMetric, $parameters, $workspaceId);

        $messages = $this->buildMessages($goal, $parameters, $assembled['context'], $analysis, $depth, $projectSnapshot);
        $model    = AiEngineSettings::featureModel(self::FEATURE, $user);

        $result = $this->openai->chat($user, $model, $messages, [
            'temperature'     => 0.6,
            'max_tokens'      => $this->depthTokens($depth),
            'response_format' => ['type' => 'json_object'],
            'feature'         => self::FEATURE,
            'reason'          => 'AI Marketing Strategist generation',
            'meta'            => [
                'goal_excerpt' => mb_substr(trim($goal), 0, 160),
                'sources'      => implode(',', $sources),
                'depth'        => $depth,
            ],
        ]);

        $creditsSpent = (int) ($result['credits_spent'] ?? 0);

        try {
            $parsed = json_decode((string) $result['content'], true);
            if (!is_array($parsed)) {
                throw new RuntimeException('The assistant returned an unexpected response. Please try again.');
            }

            $plan = $this->normalizeStrategy($parsed);

            $title = trim((string) ($parsed['title'] ?? ''));
            if ($title === '') {
                $title = 'Marketing Strategy';
            }
            $title = mb_substr($title, 0, 180);

            // Fold the AI's narrative into the deterministic analysis payloads
            // (the AI explains; PHP owns the numbers).
            $diagnosis = $analysis['diagnosis'];
            if (is_array($diagnosis) && $diagnosis !== []) {
                $diagnosis['narrative'] = $this->narrativeList($parsed['diagnosis_narrative'] ?? ($parsed['diagnosis'] ?? []), 6, 400);
            }
            $forecast = $analysis['forecast'];
            if (is_array($forecast) && $forecast !== []) {
                $forecast['narrative'] = mb_substr(trim((string) ($parsed['forecast_narrative'] ?? '')), 0, 1200);
            }
            $competitor = $depth >= 5 ? $this->normalizeCompetitor($parsed['competitor_analysis'] ?? []) : null;

            $strategy = new MarketingStrategy();
            $strategy->user_id          = $user->id;
            $strategy->workspace_id      = $workspaceId;
            $strategy->profile_id        = $profile?->id;
            $strategy->title             = $title;
            $strategy->goal              = mb_substr(trim($goal), 0, 4000);
            $strategy->status            = 'ready';
            $strategy->sources           = $sources;
            $strategy->source_items      = $selections;
            $strategy->parameters        = $parameters;
            $strategy->profile_snapshot  = ($projectSnapshot ?: null);
            $strategy->context_snapshot  = $assembled['snapshot'];
            $strategy->strategy          = $plan;
            $strategy->goal_metric       = $goalMetric;
            $strategy->diagnosis         = ($diagnosis ?: null);
            $strategy->scorecard         = ($analysis['scorecard'] ?: null);
            $strategy->forecast          = ($forecast ?: null);
            $strategy->competitor_analysis = ($competitor ?: null);
            $strategy->baseline          = ($analysis['baseline'] ?: null);
            $strategy->model             = (string) ($result['model'] ?? $model);
            $strategy->credits_spent     = $creditsSpent;
            $strategy->save();

            $this->persistSuggestions($strategy, $parsed['suggestions'] ?? []);

            // Snapshot the first scorecard so the dashboard can chart it over time.
            if (is_array($analysis['scorecard']) && $analysis['scorecard'] !== []) {
                $this->snapshotScore($strategy, $analysis['scorecard']);
            }
        } catch (\Throwable $e) {
            if ($creditsSpent > 0) {
                $this->credits->refund($user, $creditsSpent, [
                    'feature' => self::FEATURE,
                    'reason'  => 'AI Marketing Strategist generation failed — auto refund',
                ]);
            }
            throw $e;
        }

        return [
            'strategy'      => $strategy,
            'credits_spent' => $creditsSpent,
            'model'         => (string) ($result['model'] ?? $model),
        ];
    }

    /**
     * Task #3281 — compute the deterministic analysis for a generation.
     * Every number here comes from real tracking data via the analysis
     * services; the AI never sets them. Depth gates which layers are built.
     * A baseline is ALWAYS captured (even at depth 1) so outcome tracking has
     * something to compare against later.
     *
     * @return array{diagnosis:?array,scorecard:?array,forecast:?array,baseline:?array}
     */
    protected function computeAnalysis(User $user, int $depth, string $goalMetric, array $parameters, ?int $workspaceId): array
    {
        $out = ['diagnosis' => null, 'scorecard' => null, 'forecast' => null, 'baseline' => null];

        try {
            $diagnosis = $this->diagnosisSvc->diagnose($user, $workspaceId);
        } catch (\Throwable $e) {
            $diagnosis = [];
        }

        try {
            $baseValue = $this->diagnosisSvc->metricValue($user, $goalMetric, MarketingDiagnosisService::WINDOW_DAYS);
        } catch (\Throwable $e) {
            $baseValue = 0;
        }
        $out['baseline'] = [
            'metric'      => $goalMetric,
            'value'       => (int) $baseValue,
            'window_days' => MarketingDiagnosisService::WINDOW_DAYS,
            'captured_at' => Carbon::now()->toIso8601String(),
        ];

        if ($depth >= 2 && is_array($diagnosis) && $diagnosis !== []) {
            $out['diagnosis'] = $diagnosis;
        }

        if ($depth >= 3) {
            try {
                $out['scorecard'] = $this->scorecardSvc->score(is_array($diagnosis) ? $diagnosis : []);
            } catch (\Throwable $e) {
                $out['scorecard'] = null;
            }
        }

        if ($depth >= 4) {
            $horizon = (int) ($parameters['horizon_days'] ?? 30);
            try {
                $out['forecast'] = $this->forecastSvc->forecast(
                    (int) $baseValue,
                    $goalMetric,
                    $horizon,
                    is_array($diagnosis) ? $diagnosis : [],
                    is_array($out['scorecard']) ? $out['scorecard'] : []
                );
            } catch (\Throwable $e) {
                $out['forecast'] = null;
            }
        }

        return $out;
    }

    /**
     * System + user messages for a fresh generation. The model is told to
     * answer ONLY with the strict JSON envelope so parsing is reliable.
     */
    public function buildMessages(string $goal, array $parameters, string $context, array $analysis = [], int $depth = 1, array $projectSnapshot = []): array
    {
        $depth      = $this->normalizeDepth($depth);
        $planMonths = $this->normalizePlanMonths($parameters['plan_months'] ?? null);
        $system = <<<'PROMPT'
You are Sayzio Marketing Strategist, an expert growth marketer for creators,
businesses and individuals who use the Sayzio link-management platform.

Sayzio lets people create short links, Link-in-Bio pages (with blocks),
QR codes, file/event/vCard links, forms, digital cards, a creator feed with
scheduled posts, subscribers (email + WhatsApp), reviews, and attach tracking
pixels (Facebook, Google Analytics, GTM, TikTok, etc.) to links.

Your job: turn the creator's GOAL + PARAMETERS + their own DATA into a
practical marketing strategy that is built AROUND real Sayzio features.

Rules:
- Ground every recommendation in the data provided. Never invent metrics,
  link URLs, follower counts or revenue. If data is missing, say what to set up.
- Give BOTH an organic plan and a paid plan. Each play must name the concrete
  Sayzio feature(s) it uses.
- Keep it specific and actionable, not generic marketing fluff.
- Honour the PARAMETERS. If a "Region" or "Geographic market" is given, weight
  the plan toward channels, partnerships and audiences that are locally relevant
  to that region (e.g. local/regional digital newspapers, regional creators,
  local events, area hashtags) and call out the local angle explicitly.
- If "Content types" are given, build the plays around those formats; if
  "Paid media" channels are given (which may include local or digital
  newspapers), prefer those for the paid plan. If a "Plan type" of "organic
  only" or "paid only" is given, focus on that side (still acknowledge the
  other briefly). Respect any "Avoid" / hard constraints — never recommend
  something the creator asked to avoid.
- Then propose a SHORT list (max 5) of one-click, applyable suggestions the
  creator can act on inside Sayzio right now.

Respond with ONLY a JSON object in exactly this shape (no markdown, no prose):
{
  "title": "short plan title",
  "summary": "2-3 sentence overview of the strategy",
  "organic": [
    {"channel":"e.g. Link-in-Bio","title":"play title","rationale":"why","steps":["step","step"],"sayzio_features":["feature"]}
  ],
  "paid": [
    {"channel":"e.g. Meta Ads","title":"play title","budget_hint":"e.g. $5-10/day","rationale":"why","steps":["step","step"],"sayzio_features":["feature"]}
  ],
  "kpis": ["metric to watch", "metric to watch"],
  "suggestions": [
    {"type":"create_link","title":"...","description":"...","payload":{"long_url":"https://...","title":"...","alias":"optional-slug"}},
    {"type":"add_block","title":"...","description":"...","payload":{"target_alias":"<one of the creator's existing Link-in-Bio aliases>","block_type":"link|text|heading","content":"text or label","url":"https://... (only for link blocks)"}},
    {"type":"attach_pixel","title":"...","description":"...","payload":{"pixel_name":"<one of the creator's existing pixel names>","target_alias":"<one of the creator's existing link aliases>"}},
    {"type":"draft_post","title":"...","description":"...","payload":{"title":"post title","body":"post body","schedule_in_days":3}}
  ]
}

Only emit suggestion types that make sense for this creator's data. For
add_block and attach_pixel you MUST reference aliases / pixel names that appear
in the provided data — never invent new ones. Omit suggestions you cannot ground.
PROMPT;

        // Task #3281 — depth-specific addendum. The COMPUTED ANALYSIS below is
        // authoritative: the model must explain it, never restate different
        // numbers. Higher depths unlock extra JSON fields.
        $system .= "\n\n" . $this->depthPromptAddendum($depth);

        // Task #3302 — an agency-style, month-by-month execution plan spanning
        // exactly the number of months the creator asked for.
        $system .= "\n\n" . $this->executionPlanAddendum($planMonths);

        $userParts = [];
        $goal = trim($goal);
        $userParts[] = 'GOAL:' . "\n" . ($goal !== '' ? $goal : 'Grow my audience and engagement on Sayzio.');

        // Task #3302 — ground the plan in the named project it is built for.
        $projectBlock = $this->projectPromptBlock($projectSnapshot);
        if ($projectBlock !== '') {
            $userParts[] = $projectBlock;
        }

        $paramLines = [];
        foreach ($parameters as $k => $v) {
            if ($v === null || $v === '' || (is_array($v) && !$v)) continue;
            if ($k === 'depth') continue; // internal
            if ($k === 'plan_months') continue; // rendered via the execution-plan addendum
            $label = ucwords(str_replace('_', ' ', (string) $k));
            $val   = is_array($v) ? implode(', ', array_map('strval', $v)) : (string) $v;
            $paramLines[] = "- {$label}: {$val}";
        }
        if ($paramLines) {
            $userParts[] = "PARAMETERS:\n" . implode("\n", $paramLines);
        }

        $analysisBlock = $this->analysisPromptBlock($analysis, $depth);
        if ($analysisBlock !== '') {
            $userParts[] = $analysisBlock;
        }

        $userParts[] = "CREATOR DATA (read-only, do not invent beyond this):\n" . $context;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => implode("\n\n", $userParts)],
        ];
    }

    /** Depth-specific extra instructions + JSON fields appended to the system prompt. */
    protected function depthPromptAddendum(int $depth): string
    {
        $lines = [];
        $lines[] = 'ANALYSIS DEPTH: ' . $depth . ' (' . (self::DEPTH_LEVELS[$depth]['label'] ?? 'Quick Scan') . ').';
        $lines[] = 'A COMPUTED ANALYSIS block may be provided. Those numbers are';
        $lines[] = 'authoritative and already correct — your job is to EXPLAIN them in';
        $lines[] = 'plain language, never to invent or contradict them.';

        if ($depth >= 2) {
            $lines[] = 'Also return "diagnosis_narrative": a JSON array of up to 6 short,';
            $lines[] = 'grounded sentences interpreting the diagnosis (wins + what is leaking).';
        }
        if ($depth >= 4) {
            $lines[] = 'Also return "forecast_narrative": a short paragraph explaining the';
            $lines[] = 'assumptions behind the pessimistic / realistic / optimistic bands and';
            $lines[] = 'which plays move the realistic case.';
        }
        if ($depth >= 5) {
            $lines[] = 'Also propose multi-step FUNNELS as suggestions of type "funnel" with an';
            $lines[] = 'ordered "steps" array; each step is {"type":"create_link|add_block|attach_pixel|draft_post","title":"...","payload":{...}}';
            $lines[] = 'using the SAME payload shapes as the single-action suggestions above';
            $lines[] = '(3-5 steps that chain into one journey, e.g. capture → nurture → convert).';
            $lines[] = 'Also return "competitor_analysis": {"summary":"...","positioning":["..."],"gaps":["..."],"moves":["..."]}';
            $lines[] = 'grounded ONLY in any competitor context the creator provided; if none was';
            $lines[] = 'provided, give general category positioning and clearly say it is generic.';
        }

        return implode("\n", $lines);
    }

    /**
     * Task #3302 — clamp a requested plan length to a sane 1-12 month window,
     * defaulting to a 3-month plan when nothing (or something invalid) is set.
     */
    protected function normalizePlanMonths($value): int
    {
        $n = (int) $value;
        if ($n < 1) {
            return 3;
        }
        return min($n, 12);
    }

    /**
     * Task #3302 — instruct the model to also return an agency-style,
     * month-by-month "execution_plan" spanning exactly $planMonths months.
     */
    protected function executionPlanAddendum(int $planMonths): string
    {
        $planMonths = max(1, min(12, $planMonths));
        $lines = [];
        $lines[] = 'EXECUTION PLAN: also return an "execution_plan" object — an';
        $lines[] = "agency-style, month-by-month roadmap spanning EXACTLY {$planMonths} month(s):";
        $lines[] = '{';
        $lines[] = '  "overview": "1-2 sentence overview of the whole execution plan",';
        $lines[] = '  "period_months": ' . $planMonths . ',';
        $lines[] = '  "phases": ["short phase name — focus", "..."],';
        $lines[] = '  "months": [';
        $lines[] = '    {"month":1,"theme":"the month\'s focus","budget":"spend for the month in the creator\'s currency",';
        $lines[] = '     "goals":["measurable goal"],"deliverables":["concrete deliverable / checklist item"],';
        $lines[] = '     "automation_flows":["Sayzio automation or flow to set up (e.g. subscriber welcome sequence)"],';
        $lines[] = '     "timeline":["Week 1: ...","Week 2: ..."]}';
        $lines[] = '  ]';
        $lines[] = '}';
        $lines[] = "Produce EXACTLY {$planMonths} month entr" . ($planMonths === 1 ? 'y' : 'ies') . ', numbered 1..' . $planMonths . '.';
        $lines[] = 'Ground every budget in the stated budget / currency and keep deliverables';
        $lines[] = 'built around real Sayzio features. Each month must build on the previous one.';

        return implode("\n", $lines);
    }

    /**
     * Task #3302 — a compact PROJECT block describing the named project profile
     * this plan is built for, so the model grounds the plan in the business.
     */
    protected function projectPromptBlock(array $snapshot): string
    {
        if (!$snapshot) {
            return '';
        }
        $lines = [];
        foreach ([
            'name'          => 'Project',
            'business_name' => 'Business',
            'industry'      => 'Industry',
            'main_offer'    => 'Main offer',
            'budget'        => 'Budget',
            'currency'      => 'Currency',
        ] as $key => $label) {
            $val = trim((string) ($snapshot[$key] ?? ''));
            if ($val !== '') {
                $lines[] = "- {$label}: " . mb_substr($val, 0, 400);
            }
        }
        if (!$lines) {
            return '';
        }
        return "PROJECT (build the plan for this business):\n" . implode("\n", $lines);
    }

    /** Render the computed deterministic analysis as a compact prompt block. */
    protected function analysisPromptBlock(array $analysis, int $depth): string
    {
        $parts = [];

        $diag = $analysis['diagnosis'] ?? null;
        if ($depth >= 2 && is_array($diag) && $diag !== []) {
            $parts[] = 'Diagnosis (computed): ' . $this->compactJson($diag);
        }
        $score = $analysis['scorecard'] ?? null;
        if ($depth >= 3 && is_array($score) && $score !== []) {
            $parts[] = 'Scorecard 0-100 (computed): ' . $this->compactJson($score);
        }
        $fc = $analysis['forecast'] ?? null;
        if ($depth >= 4 && is_array($fc) && $fc !== []) {
            $parts[] = 'Forecast (computed): ' . $this->compactJson($fc);
        }

        return $parts ? "COMPUTED ANALYSIS (authoritative — explain, do not restate differently):\n" . implode("\n", $parts) : '';
    }

    protected function compactJson($value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json === false ? '' : mb_substr($json, 0, 2500);
    }

    /**
     * System + history messages for the chat-refine conversation about an
     * existing strategy. The assistant answers in plain text (advice),
     * grounded in the saved plan + the creator's data snapshot.
     */
    public function buildRefineMessages(MarketingStrategy $strategy, array $recentTurns): array
    {
        $planJson = json_encode($strategy->strategy ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $snapshot = '';
        foreach ((array) ($strategy->context_snapshot ?? []) as $src => $text) {
            $label = self::SOURCES[$src]['label'] ?? ucfirst((string) $src);
            $snapshot .= "\n[{$label}]\n{$text}\n";
        }

        $system = <<<PROMPT
You are Sayzio Marketing Strategist, refining a marketing strategy you already
produced for this creator. Answer their follow-up questions and refinement
requests in clear, concise plain text (no JSON). Stay grounded in the strategy
and the creator's data below. Be specific, keep referencing concrete Sayzio
features, and never invent metrics or URLs.

THE CURRENT STRATEGY (JSON):
{$planJson}

THE CREATOR'S DATA SNAPSHOT:
{$snapshot}
PROMPT;

        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($recentTurns as $t) {
            $messages[] = ['role' => $t['role'], 'content' => $t['content']];
        }
        return $messages;
    }

    // ── parsing / persistence ──────────────────────────────────────

    /** Coerce the model's JSON into a clean, bounded strategy array. */
    protected function normalizeStrategy(array $parsed): array
    {
        $play = function ($row, bool $paid): array {
            $row = is_array($row) ? $row : [];
            $out = [
                'channel'         => mb_substr(trim((string) ($row['channel'] ?? '')), 0, 120),
                'title'           => mb_substr(trim((string) ($row['title'] ?? '')), 0, 200),
                'rationale'       => mb_substr(trim((string) ($row['rationale'] ?? '')), 0, 1000),
                'steps'           => $this->stringList($row['steps'] ?? [], 10, 400),
                'sayzio_features' => $this->stringList($row['sayzio_features'] ?? [], 8, 120),
            ];
            if ($paid) {
                $out['budget_hint'] = mb_substr(trim((string) ($row['budget_hint'] ?? '')), 0, 120);
            }
            return $out;
        };

        $organic = array_slice(array_map(fn($r) => $play($r, false), array_filter((array) ($parsed['organic'] ?? []), 'is_array')), 0, 8);
        $paid    = array_slice(array_map(fn($r) => $play($r, true),  array_filter((array) ($parsed['paid'] ?? []), 'is_array')), 0, 8);

        return [
            'summary'        => mb_substr(trim((string) ($parsed['summary'] ?? '')), 0, 1200),
            'organic'        => array_values($organic),
            'paid'           => array_values($paid),
            'kpis'           => $this->stringList($parsed['kpis'] ?? [], 10, 160),
            'execution_plan' => $this->normalizeExecutionPlan($parsed['execution_plan'] ?? null),
        ];
    }

    /**
     * Task #3302 — coerce the model's month-by-month execution plan into a
     * clean, bounded shape. Returns null (graceful fallback) when the model
     * omitted it or returned nothing usable, so every downstream renderer can
     * cheaply skip the section.
     *
     * @return array{overview:string,period_months:int,phases:list<string>,months:list<array{month:int,theme:string,budget:string,goals:list<string>,deliverables:list<string>,automation_flows:list<string>,timeline:list<string>}>}|null
     */
    protected function normalizeExecutionPlan($value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $months = [];
        foreach ((array) ($value['months'] ?? []) as $m) {
            if (!is_array($m)) continue;
            $num = (int) ($m['month'] ?? (count($months) + 1));
            if ($num < 1) $num = count($months) + 1;
            $months[] = [
                'month'            => min($num, 12),
                'theme'            => mb_substr(trim((string) ($m['theme'] ?? '')), 0, 200),
                'budget'           => mb_substr(trim((string) ($m['budget'] ?? '')), 0, 160),
                'goals'            => $this->stringList($m['goals'] ?? [], 10, 300),
                'deliverables'     => $this->stringList($m['deliverables'] ?? [], 15, 300),
                'automation_flows' => $this->stringList($m['automation_flows'] ?? [], 10, 300),
                'timeline'         => $this->stringList($m['timeline'] ?? [], 12, 300),
            ];
            if (count($months) >= 12) break;
        }

        $overview = mb_substr(trim((string) ($value['overview'] ?? '')), 0, 1200);
        $phases   = $this->stringList($value['phases'] ?? [], 12, 300);

        if ($overview === '' && !$phases && !$months) {
            return null;
        }

        $period = (int) ($value['period_months'] ?? 0);
        if ($months) {
            $period = count($months);
        }

        return [
            'overview'      => $overview,
            'period_months' => max(0, min($period, 12)),
            'phases'        => $phases,
            'months'        => array_values($months),
        ];
    }

    /** @return list<string> */
    protected function stringList($value, int $max, int $len): array
    {
        if (!is_array($value)) return [];
        $out = [];
        foreach ($value as $v) {
            if (is_array($v)) continue;
            $s = trim((string) $v);
            if ($s === '') continue;
            $out[] = mb_substr($s, 0, $len);
            if (count($out) >= $max) break;
        }
        return $out;
    }

    /** Persist the model's suggestions, constrained to the known types. */
    protected function persistSuggestions(MarketingStrategy $strategy, $suggestions): void
    {
        if (!is_array($suggestions)) return;
        $count = 0;
        foreach ($suggestions as $s) {
            if (!is_array($s)) continue;
            $type = (string) ($s['type'] ?? '');
            if (!in_array($type, MarketingStrategySuggestion::TYPES, true)) continue;

            $title = mb_substr(trim((string) ($s['title'] ?? $s['description'] ?? 'Suggestion')), 0, 200);
            if ($title === '') $title = 'Suggestion';

            // Task #3281 — a funnel carries an ordered step list instead of a
            // single payload. Steps are validated down to the known single-action
            // types; a funnel with no valid steps is dropped.
            $steps = null;
            if ($type === MarketingStrategySuggestion::TYPE_FUNNEL) {
                $steps = $this->normalizeFunnelSteps($s['steps'] ?? []);
                if ($steps === []) continue;
            }

            MarketingStrategySuggestion::create([
                'strategy_id' => $strategy->id,
                'type'        => $type,
                'title'       => $title,
                'description' => mb_substr(trim((string) ($s['description'] ?? '')), 0, 1000) ?: null,
                'payload'     => is_array($s['payload'] ?? null) ? $s['payload'] : [],
                'steps'       => $steps,
                'status'      => MarketingStrategySuggestion::STATUS_PENDING,
            ]);

            if (++$count >= 8) break;
        }
    }

    /**
     * Task #3281 — coerce a funnel's step list into a clean, bounded array of
     * single-action steps. Only the known applyable types survive.
     *
     * @return list<array{type:string,title:string,payload:array}>
     */
    protected function normalizeFunnelSteps($steps): array
    {
        if (!is_array($steps)) return [];
        $allowed = [
            MarketingStrategySuggestion::TYPE_CREATE_LINK,
            MarketingStrategySuggestion::TYPE_ADD_BLOCK,
            MarketingStrategySuggestion::TYPE_ATTACH_PIXEL,
            MarketingStrategySuggestion::TYPE_DRAFT_POST,
        ];
        $out = [];
        foreach ($steps as $step) {
            if (!is_array($step)) continue;
            $type = (string) ($step['type'] ?? '');
            if (!in_array($type, $allowed, true)) continue;
            $out[] = [
                'type'    => $type,
                'title'   => mb_substr(trim((string) ($step['title'] ?? '')), 0, 180),
                'payload' => is_array($step['payload'] ?? null) ? $step['payload'] : [],
            ];
            if (count($out) >= 6) break;
        }
        return $out;
    }

    /** @return list<string> narrative sentences bounded for safe storage. */
    protected function narrativeList($value, int $max, int $len): array
    {
        if (is_string($value)) {
            $value = array_filter(array_map('trim', preg_split('/(?<=[.!?])\s+/', $value) ?: []));
        }
        return $this->stringList($value, $max, $len);
    }

    /**
     * Task #3281 — coerce the AI's competitor analysis into a bounded shape.
     * @return array{summary:string,positioning:list<string>,gaps:list<string>,moves:list<string>}
     */
    protected function normalizeCompetitor($value): array
    {
        $v = is_array($value) ? $value : [];
        return [
            'summary'     => mb_substr(trim((string) ($v['summary'] ?? '')), 0, 1200),
            'positioning' => $this->stringList($v['positioning'] ?? [], 6, 300),
            'gaps'        => $this->stringList($v['gaps'] ?? [], 6, 300),
            'moves'       => $this->stringList($v['moves'] ?? [], 6, 300),
        ];
    }

    /**
     * Task #3281 — a compact, reusable snapshot of the creator's Marketing
     * Profile (intake), so a plan records the profile it was built against.
     * Returns [] when no profile exists yet.
     */
    public function profileSnapshot(User $user, ?int $workspaceId = null): array
    {
        try {
            $profile = MarketingProfile::forOwner($user->id, $workspaceId);
        } catch (\Throwable $e) {
            $profile = null;
        }
        return $profile ? $profile->toSnapshot() : [];
    }

    /**
     * Task #3302 — resolve the named project profile a generation is built for.
     * Prefers the explicitly chosen (owner-scoped) profile id, else falls back
     * to the owner's default profile so legacy callers keep working. Fully
     * guarded — a missing table must never break generation.
     */
    protected function resolveProfile(?int $profileId, User $user, ?int $workspaceId): ?MarketingProfile
    {
        try {
            if ($profileId) {
                $chosen = MarketingProfile::query()
                    ->where('id', $profileId)
                    ->where('user_id', $user->id)
                    ->where('workspace_id', $workspaceId)
                    ->first();
                if ($chosen) {
                    return $chosen;
                }
            }
            return MarketingProfile::forOwner($user->id, $workspaceId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Task #3281 — persist a scorecard snapshot row so the dashboard can chart
     * the four axes + overall over time. Idempotent-ish: safe to call after any
     * (re)score; a missing table is swallowed so scoring never breaks a flow.
     */
    public function snapshotScore(MarketingStrategy $strategy, array $scorecard): ?MarketingStrategyScore
    {
        try {
            return MarketingStrategyScore::create([
                'strategy_id' => $strategy->id,
                'overall'     => (int) ($scorecard['overall'] ?? 0),
                'reach'       => (int) ($scorecard['reach'] ?? 0),
                'engagement'  => (int) ($scorecard['engagement'] ?? 0),
                'conversion'  => (int) ($scorecard['conversion'] ?? 0),
                'consistency' => (int) ($scorecard['consistency'] ?? 0),
                'reasons'     => is_array($scorecard['reasons'] ?? null) ? $scorecard['reasons'] : [],
                'created_at'  => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Task #3281 — free, PHP-only re-score of a saved strategy. Recomputes the
     * scorecard from CURRENT tracking data and appends a new history row so the
     * creator can watch their marketing health move without spending coins.
     *
     * @return array|null the fresh scorecard, or null when it can't be computed.
     */
    public function recomputeScore(MarketingStrategy $strategy, User $user): ?array
    {
        try {
            $diagnosis = $this->diagnosisSvc->diagnose($user, $strategy->workspace_id);
            $scorecard = $this->scorecardSvc->score(is_array($diagnosis) ? $diagnosis : []);
        } catch (\Throwable $e) {
            return null;
        }
        if (!is_array($scorecard) || $scorecard === []) {
            return null;
        }

        // Preserve the AI narrative from the last diagnosis, if any.
        $prev = is_array($strategy->diagnosis ?? null) ? $strategy->diagnosis : [];
        if (!empty($prev['narrative'])) {
            $diagnosis['narrative'] = $prev['narrative'];
        }

        $strategy->diagnosis = $diagnosis ?: null;
        $strategy->scorecard = $scorecard;
        $strategy->save();

        $this->snapshotScore($strategy, $scorecard);

        return $scorecard;
    }

    /**
     * Task #3281 — evaluate + persist outcome for a saved strategy (did the
     * plan move the goal metric?). Free/PHP-only. Returns the outcome payload
     * or null when there is no baseline to compare against.
     */
    public function refreshOutcome(MarketingStrategy $strategy, User $user): ?array
    {
        $outcome = $this->outcomeSvc->evaluate($strategy, $user);
        if ($outcome === null) {
            return null;
        }
        $strategy->outcome = $outcome;
        $strategy->save();
        return $outcome;
    }

    // ── data-source snapshots ──────────────────────────────────────

    protected function snapshotTool(User $user, string $tool): string
    {
        $r = $this->tools->run($tool, $user);
        return (string) ($r['summary'] ?? '');
    }

    protected function snapshotLinks(User $user, ?array $ids = null): string
    {
        $rows = Link::query()
            ->where('user_id', $user->id)
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->orderByDesc('total_clicks')
            ->limit(25)
            ->get(['type', 'title', 'alias', 'total_clicks', 'is_active']);

        if ($rows->isEmpty()) {
            return 'No links created yet.';
        }

        $byType = [];
        foreach ($rows as $r) {
            $byType[$r->type] = ($byType[$r->type] ?? 0) + 1;
        }
        $typeLine = 'Link types in use: ' . implode(', ', array_map(
            fn($t, $n) => "{$t} ×{$n}", array_keys($byType), array_values($byType)
        )) . '.';

        $lines = [$typeLine, 'Top links by lifetime clicks:'];
        foreach ($rows->take(12) as $r) {
            $lines[] = sprintf('- [%s] "%s" alias %s — %d clicks (%s)',
                $r->type, $r->title ?: 'Untitled', $r->alias, (int) $r->total_clicks,
                $r->is_active ? 'live' : 'paused');
        }
        return implode("\n", $lines);
    }

    protected function snapshotPixels(User $user, ?array $ids = null): string
    {
        $rows = Pixel::query()->where('user_id', $user->id)
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')->get(['name', 'type']);
        if ($rows->isEmpty()) {
            return 'No tracking pixels connected yet.';
        }
        $lines = ['Connected tracking pixels:'];
        foreach ($rows as $r) {
            $lines[] = sprintf('- %s (%s)', $r->name, $r->type);
        }
        return implode("\n", $lines);
    }

    protected function snapshotMinds(User $user, ?array $ids = null): string
    {
        $rows = AiMind::query()
            ->where('user_id', $user->id)
            ->where('is_disabled', false)
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->limit(25)
            ->get(['name']);
        if ($rows->isEmpty()) {
            return 'No AI Minds yet.';
        }
        return 'AI Minds: ' . $rows->pluck('name')->implode(', ') . '.';
    }

    protected function snapshotBrandKits(User $user, ?array $ids = null): string
    {
        $kits = BrandKit::query()->where('user_id', $user->id)
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->orderByDesc('is_default')->limit(5)->get();
        if ($kits->isEmpty()) {
            return 'No Brand Kits yet.';
        }
        $lines = [];
        foreach ($kits as $kit) {
            $palette  = $kit->palette();
            $voice    = $kit->voice();
            $taglines = $kit->taglines();
            $colors   = [];
            foreach (['primary', 'secondary', 'accent'] as $slot) {
                if (!empty($palette[$slot])) $colors[] = $slot . ' ' . (is_array($palette[$slot]) ? ($palette[$slot]['hex'] ?? '') : $palette[$slot]);
            }
            $line = '- "' . $kit->name . '"';
            if ($colors)   $line .= '; palette: ' . implode(', ', array_filter($colors));
            if (!empty($voice['tone'])) $line .= '; tone: ' . (is_array($voice['tone']) ? implode('/', $voice['tone']) : $voice['tone']);
            if ($taglines) $line .= '; tagline: ' . (is_array($taglines) ? (string) reset($taglines) : (string) $taglines);
            $lines[] = $line;
        }
        return "Brand Kits:\n" . implode("\n", $lines);
    }

    protected function snapshotPersonas(User $user, ?array $ids = null): string
    {
        $rows = AiPersonaAgent::query()->where('user_id', $user->id)
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')->limit(15)->get(['name', 'description', 'tone_preset']);
        if ($rows->isEmpty()) {
            return 'No AI Personas yet.';
        }
        $lines = ['AI Personas:'];
        foreach ($rows as $r) {
            $desc = trim((string) $r->description);
            $lines[] = '- ' . $r->name . ($r->tone_preset ? " (tone: {$r->tone_preset})" : '') . ($desc !== '' ? ' — ' . Str::limit($desc, 120) : '');
        }
        return implode("\n", $lines);
    }

    protected function snapshotCompanions(User $user, ?array $ids = null): string
    {
        $rows = AiCompanion::query()->where('user_id', $user->id)
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')->limit(15)->get(['name', 'placement']);
        if ($rows->isEmpty()) {
            return 'No AI Companions published yet.';
        }
        $lines = ['AI Companions:'];
        foreach ($rows as $r) {
            $lines[] = '- ' . $r->name . ($r->placement ? " (placement: {$r->placement})" : '');
        }
        return implode("\n", $lines);
    }

    // ── selectable item lists (for the per-item builder picker) ────────

    /** @return list<array{id:int,label:string,sub:string}> */
    protected function itemsLinks(User $user): array
    {
        return Link::query()
            ->where('user_id', $user->id)
            ->orderByDesc('total_clicks')
            ->limit(100)
            ->get(['id', 'type', 'title', 'alias', 'total_clicks'])
            ->map(fn ($r) => [
                'id'    => (int) $r->id,
                'label' => trim((string) ($r->title ?: $r->alias)) ?: 'Untitled',
                'sub'   => trim(sprintf('%s · %d clicks', (string) $r->type, (int) $r->total_clicks)),
            ])
            ->all();
    }

    /** @return list<array{id:int,label:string,sub:string}> */
    protected function itemsPixels(User $user): array
    {
        return Pixel::query()->where('user_id', $user->id)->orderBy('name')->limit(100)
            ->get(['id', 'name', 'type'])
            ->map(fn ($r) => [
                'id'    => (int) $r->id,
                'label' => trim((string) $r->name) ?: 'Pixel',
                'sub'   => (string) $r->type,
            ])
            ->all();
    }

    /** @return list<array{id:int,label:string,sub:string}> */
    protected function itemsMinds(User $user): array
    {
        return AiMind::query()->where('user_id', $user->id)->where('is_disabled', false)
            ->orderBy('name')->limit(100)
            ->get(['id', 'name'])
            ->map(fn ($r) => [
                'id'    => (int) $r->id,
                'label' => trim((string) $r->name) ?: 'AI Mind',
                'sub'   => '',
            ])
            ->all();
    }

    /** @return list<array{id:int,label:string,sub:string}> */
    protected function itemsBrandKits(User $user): array
    {
        return BrandKit::query()->where('user_id', $user->id)->orderByDesc('is_default')->orderBy('name')->limit(100)
            ->get(['id', 'name', 'is_default'])
            ->map(fn ($r) => [
                'id'    => (int) $r->id,
                'label' => trim((string) $r->name) ?: 'Brand Kit',
                'sub'   => $r->is_default ? 'Default' : '',
            ])
            ->all();
    }

    /** @return list<array{id:int,label:string,sub:string}> */
    protected function itemsPersonas(User $user): array
    {
        return AiPersonaAgent::query()->where('user_id', $user->id)->orderBy('name')->limit(100)
            ->get(['id', 'name', 'tone_preset'])
            ->map(fn ($r) => [
                'id'    => (int) $r->id,
                'label' => trim((string) $r->name) ?: 'Persona',
                'sub'   => (string) ($r->tone_preset ?? ''),
            ])
            ->all();
    }

    /** @return list<array{id:int,label:string,sub:string}> */
    protected function itemsCompanions(User $user): array
    {
        return AiCompanion::query()->where('user_id', $user->id)->orderBy('name')->limit(100)
            ->get(['id', 'name', 'placement'])
            ->map(fn ($r) => [
                'id'    => (int) $r->id,
                'label' => trim((string) $r->name) ?: 'Companion',
                'sub'   => (string) ($r->placement ?? ''),
            ])
            ->all();
    }

    // ── export rendering (shared by web + API) ─────────────────────

    /** Render a saved strategy as Markdown for download. */
    public function toMarkdown(MarketingStrategy $strategy): string
    {
        $plan = (array) ($strategy->strategy ?? []);
        $out  = "# {$strategy->title}\n\n";
        $out .= '**Goal:** ' . trim((string) $strategy->goal) . "\n\n";

        if (!empty($plan['summary'])) {
            $out .= "## Summary\n\n" . $plan['summary'] . "\n\n";
        }

        $section = function (string $heading, array $plays): string {
            if (!$plays) return '';
            $s = "## {$heading}\n\n";
            foreach ($plays as $p) {
                $p = (array) $p;
                $s .= '### ' . ($p['title'] ?? 'Play');
                if (!empty($p['channel'])) $s .= ' — ' . $p['channel'];
                $s .= "\n\n";
                if (!empty($p['budget_hint'])) $s .= '_Budget: ' . $p['budget_hint'] . "_\n\n";
                if (!empty($p['rationale'])) $s .= $p['rationale'] . "\n\n";
                foreach ((array) ($p['steps'] ?? []) as $step) $s .= "- {$step}\n";
                if (!empty($p['sayzio_features'])) $s .= "\n_Sayzio features: " . implode(', ', (array) $p['sayzio_features']) . "_\n";
                $s .= "\n";
            }
            return $s;
        };

        $out .= $section('Organic plan', (array) ($plan['organic'] ?? []));
        $out .= $section('Paid plan', (array) ($plan['paid'] ?? []));

        // Task #3302 — month-by-month execution plan.
        $exec = (array) ($plan['execution_plan'] ?? []);
        $execMonths = (array) ($exec['months'] ?? []);
        if ($exec && (!empty($execMonths) || !empty($exec['overview']) || !empty($exec['phases']))) {
            $period = (int) ($exec['period_months'] ?? count($execMonths));
            $out .= '## Execution plan' . ($period > 0 ? ' — ' . $period . ' month' . ($period === 1 ? '' : 's') : '') . "\n\n";
            if (!empty($exec['overview'])) $out .= $exec['overview'] . "\n\n";
            foreach ((array) ($exec['phases'] ?? []) as $ph) $out .= "- {$ph}\n";
            if (!empty($exec['phases'])) $out .= "\n";
            foreach ($execMonths as $m) {
                $m = (array) $m;
                $out .= '### Month ' . (int) ($m['month'] ?? 0);
                if (!empty($m['theme'])) $out .= ' — ' . $m['theme'];
                $out .= "\n\n";
                if (!empty($m['budget'])) $out .= '_Budget: ' . $m['budget'] . "_\n\n";
                foreach ([
                    'goals'            => 'Goals',
                    'deliverables'     => 'Deliverables',
                    'automation_flows' => 'Automation flows',
                    'timeline'         => 'Timeline',
                ] as $k => $label) {
                    $items = (array) ($m[$k] ?? []);
                    if ($items) {
                        $out .= "**{$label}**\n\n";
                        foreach ($items as $it) $out .= "- {$it}\n";
                        $out .= "\n";
                    }
                }
            }
        }

        if (!empty($plan['kpis'])) {
            $out .= "## KPIs to watch\n\n";
            foreach ((array) $plan['kpis'] as $kpi) $out .= "- {$kpi}\n";
            $out .= "\n";
        }

        return $out;
    }

    /**
     * Render a saved strategy as a self-contained, branded HTML document for
     * PDF export. `$execSummary` is the optional premium AI exec-summary
     * (empty for the free Rich PDF tier). Every section is grounded in the
     * deterministic analysis stored on the strategy.
     */
    public function toHtml(MarketingStrategy $strategy, string $execSummary = ''): string
    {
        $plan = (array) ($strategy->strategy ?? []);
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $premium = trim($execSummary) !== '';
        $badge = $premium ? '<span class="tag tag-premium">Premium AI report</span>' : '<span class="tag">Strategy report</span>';

        // Task #3302 — brand the report with the creator's active Brand Kit
        // (logo + colours), falling back to the platform identity.
        $brand   = $this->resolveBrandContext($strategy);
        $primary = $brand['primary'];
        $accent  = $brand['accent'];

        $logoHtml = $brand['logo'] !== ''
            ? '<img src="' . $brand['logo'] . '" alt="" class="brandlogo">'
            : '';
        $body  = '<div class="brandbar">' . $logoHtml . '<span class="brand">' . $e($brand['name']) . '</span> ' . $badge . '</div>';
        $body .= '<h1>' . $e($strategy->title) . '</h1>';
        $body .= '<p class="goal"><strong>Goal:</strong> ' . $e(trim((string) $strategy->goal)) . '</p>';
        $body .= '<p class="muted">Generated ' . $e(optional($strategy->created_at)->format('M j, Y')) . ' &middot; depth ' . $e((string) $strategy->depth())
            . ' (' . $e(self::DEPTH_LEVELS[$this->normalizeDepth($strategy->depth())]['label'] ?? 'Quick Scan') . ')</p>';

        if ($premium) {
            $body .= '<div class="exec"><h2>Executive summary</h2>';
            foreach (preg_split('/\n{2,}/', trim($execSummary)) ?: [] as $para) {
                $para = trim($para);
                if ($para !== '') $body .= '<p>' . nl2br($e($para)) . '</p>';
            }
            $body .= '</div>';
        }

        if (!empty($plan['summary'])) {
            $body .= '<h2>Summary</h2><p>' . $e($plan['summary']) . '</p>';
        }

        // Scorecard.
        $scorecard = (array) ($strategy->scorecard ?? []);
        if ($scorecard) {
            $body .= '<h2>Marketing scorecard</h2>';
            $body .= '<p class="score-overall">Overall <strong>' . (int) ($scorecard['overall'] ?? 0) . '</strong> / 100</p>';
            $body .= '<table class="grid"><tr>';
            foreach (['reach' => 'Reach', 'engagement' => 'Engagement', 'conversion' => 'Conversion', 'consistency' => 'Consistency'] as $k => $label) {
                $body .= '<td><div class="axis">' . $e($label) . '</div><div class="axisval">' . (int) ($scorecard[$k] ?? 0) . '</div></td>';
            }
            $body .= '</tr></table>';
            $reasons = (array) ($scorecard['reasons'] ?? []);
            if ($reasons) {
                $body .= '<ul class="reasons">';
                foreach ($reasons as $r) $body .= '<li>' . $e($r) . '</li>';
                $body .= '</ul>';
            }
        }

        // Diagnosis narrative.
        $diagnosis = (array) ($strategy->diagnosis ?? []);
        $narrative = (array) ($diagnosis['narrative'] ?? []);
        if ($narrative) {
            $body .= '<h2>Diagnosis</h2><ul>';
            foreach ($narrative as $n) $body .= '<li>' . $e($n) . '</li>';
            $body .= '</ul>';
        }

        // Forecast.
        $forecast = (array) ($strategy->forecast ?? []);
        $bands    = (array) ($forecast['scenarios'] ?? $forecast['bands'] ?? []);
        if ($bands) {
            $metric = $e((string) ($forecast['metric'] ?? $strategy->goal_metric ?? 'clicks'));
            $body .= '<h2>Forecast &mdash; ' . $metric . '</h2>';
            $body .= '<table class="grid"><tr>';
            foreach ($bands as $name => $band) {
                $band = (array) $band;
                $label = is_string($name) ? ucfirst($name) : ucfirst((string) ($band['label'] ?? 'Scenario'));
                $val   = (int) ($band['value'] ?? $band['projected'] ?? 0);
                $body .= '<td><div class="axis">' . $e($label) . '</div><div class="axisval">' . $val . '</div></td>';
            }
            $body .= '</tr></table>';
            if (!empty($forecast['narrative'])) {
                $body .= '<p>' . $e($forecast['narrative']) . '</p>';
            }
        }

        $section = function (string $heading, array $plays) use ($e): string {
            if (!$plays) return '';
            $s = '<h2>' . $e($heading) . '</h2>';
            foreach ($plays as $p) {
                $p = (array) $p;
                $s .= '<div class="play"><h3>' . $e($p['title'] ?? 'Play');
                if (!empty($p['channel'])) $s .= ' &mdash; ' . $e($p['channel']);
                $s .= '</h3>';
                if (!empty($p['budget_hint'])) $s .= '<p class="muted">Budget: ' . $e($p['budget_hint']) . '</p>';
                if (!empty($p['rationale'])) $s .= '<p>' . $e($p['rationale']) . '</p>';
                $steps = (array) ($p['steps'] ?? []);
                if ($steps) {
                    $s .= '<ul>';
                    foreach ($steps as $step) $s .= '<li>' . $e($step) . '</li>';
                    $s .= '</ul>';
                }
                if (!empty($p['sayzio_features'])) {
                    $s .= '<p class="muted">Sayzio features: ' . $e(implode(', ', (array) $p['sayzio_features'])) . '</p>';
                }
                $s .= '</div>';
            }
            return $s;
        };

        $body .= $section('Organic plan', (array) ($plan['organic'] ?? []));
        $body .= $section('Paid plan', (array) ($plan['paid'] ?? []));

        // Task #3302 — agency-style, month-by-month execution plan.
        $exec = (array) ($plan['execution_plan'] ?? []);
        $execMonths = (array) ($exec['months'] ?? []);
        if ($exec && (!empty($execMonths) || !empty($exec['overview']) || !empty($exec['phases']))) {
            $period = (int) ($exec['period_months'] ?? count($execMonths));
            $body .= '<h2>Execution plan';
            if ($period > 0) {
                $body .= ' &mdash; ' . $period . ' month' . ($period === 1 ? '' : 's');
            }
            $body .= '</h2>';
            if (!empty($exec['overview'])) {
                $body .= '<p>' . $e($exec['overview']) . '</p>';
            }
            $phases = (array) ($exec['phases'] ?? []);
            if ($phases) {
                $body .= '<p class="muted">Phases</p><ul>';
                foreach ($phases as $ph) $body .= '<li>' . $e($ph) . '</li>';
                $body .= '</ul>';
            }
            foreach ($execMonths as $m) {
                $m = (array) $m;
                $body .= '<div class="play"><h3>Month ' . (int) ($m['month'] ?? 0);
                if (!empty($m['theme'])) $body .= ' &mdash; ' . $e($m['theme']);
                $body .= '</h3>';
                if (!empty($m['budget'])) $body .= '<p class="muted">Budget: ' . $e($m['budget']) . '</p>';
                foreach ([
                    'goals'            => 'Goals',
                    'deliverables'     => 'Deliverables',
                    'automation_flows' => 'Automation flows',
                    'timeline'         => 'Timeline',
                ] as $k => $label) {
                    $items = (array) ($m[$k] ?? []);
                    if ($items) {
                        $body .= '<p class="muted">' . $e($label) . '</p><ul>';
                        foreach ($items as $it) $body .= '<li>' . $e($it) . '</li>';
                        $body .= '</ul>';
                    }
                }
                $body .= '</div>';
            }
        }

        // Competitor analysis (depth 5).
        $competitor = (array) ($strategy->competitor_analysis ?? []);
        if ($competitor) {
            $body .= '<h2>Competitor landscape</h2>';
            if (!empty($competitor['summary'])) $body .= '<p>' . $e($competitor['summary']) . '</p>';
            foreach (['positioning' => 'Positioning', 'gaps' => 'Gaps to exploit', 'moves' => 'Recommended moves'] as $k => $label) {
                $items = (array) ($competitor[$k] ?? []);
                if ($items) {
                    $body .= '<h3>' . $e($label) . '</h3><ul>';
                    foreach ($items as $it) $body .= '<li>' . $e($it) . '</li>';
                    $body .= '</ul>';
                }
            }
        }

        // Outcome.
        $outcome = (array) ($strategy->outcome ?? []);
        if ($outcome) {
            $delta   = (int) ($outcome['delta_pct'] ?? 0);
            $verdict = (string) ($outcome['verdict'] ?? '');
            $metric  = (string) ($outcome['goal_metric'] ?? $strategy->goal_metric ?? 'clicks');
            $sign    = $delta > 0 ? '+' : '';
            $body .= '<h2>Outcome</h2>';
            $body .= '<p>' . $e(ucfirst($verdict !== '' ? $verdict : 'measured'))
                . ': ' . $e($metric) . ' moved <strong>' . $sign . $delta . '%</strong> from baseline '
                . (int) ($outcome['baseline_value'] ?? 0) . ' to ' . (int) ($outcome['current_value'] ?? 0)
                . ' over ' . (int) ($outcome['window_days'] ?? 0) . ' days.</p>';
        }

        if (!empty($plan['kpis'])) {
            $body .= '<h2>KPIs to watch</h2><ul>';
            foreach ((array) $plan['kpis'] as $kpi) $body .= '<li>' . $e($kpi) . '</li>';
            $body .= '</ul>';
        }

        if (!$premium) {
            $body .= '<p class="approx">This is an approximate, automatically generated plan. Figures are estimates based on your recent activity.</p>';
        }

        $css = 'body{font-family:DejaVu Sans,sans-serif;color:#1f2937;font-size:12px;line-height:1.5;}'
            . 'h1{font-size:22px;margin:6px 0 4px;}h2{font-size:16px;margin:18px 0 6px;border-bottom:1px solid #e5e7eb;padding-bottom:3px;color:' . $primary . ';}'
            . 'h3{font-size:13px;margin:10px 0 3px;}.goal{margin:0 0 6px;}.play{margin:0 0 8px;}'
            . '.muted{color:#6b7280;font-size:11px;margin:2px 0;}ul{margin:4px 0 4px 18px;padding:0;}li{margin:2px 0;}'
            . '.brandbar{border-bottom:2px solid ' . $accent . ';padding-bottom:6px;margin-bottom:8px;}'
            . '.brandlogo{max-height:28px;max-width:140px;vertical-align:middle;margin-right:8px;}'
            . '.brand{font-size:18px;font-weight:bold;color:' . $primary . ';vertical-align:middle;}'
            . '.tag{float:right;font-size:10px;background:#f1f5f9;color:' . $primary . ';border-radius:8px;padding:2px 8px;}'
            . '.tag-premium{background:' . $primary . ';color:#fff;}'
            . '.exec{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;margin:10px 0;}'
            . '.score-overall{font-size:14px;margin:4px 0;}'
            . 'table.grid{width:100%;border-collapse:collapse;margin:6px 0;}table.grid td{border:1px solid #e5e7eb;text-align:center;padding:6px;width:25%;}'
            . '.axis{font-size:10px;color:#6b7280;text-transform:uppercase;}.axisval{font-size:18px;font-weight:bold;color:' . $primary . ';}'
            . '.reasons{color:#4b5563;}.approx{margin-top:16px;font-size:10px;color:#9ca3af;font-style:italic;}';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $css . '</style></head><body>' . $body . '</body></html>';
    }

    /**
     * Task #3302 — resolve the brand identity a report should wear: the Brand
     * Kit tied to the plan's project, else the plan's snapshot brand kit, else
     * the creator's active Brand Kit, else the platform ("Sayzio"). Fully
     * guarded — any failure falls back to the platform identity so exports
     * never break.
     *
     * @return array{name:string,primary:string,accent:string,logo:string}
     */
    protected function resolveBrandContext(MarketingStrategy $strategy): array
    {
        $platform = ['name' => 'Sayzio', 'primary' => '#4338ca', 'accent' => '#4338ca', 'logo' => ''];

        try {
            $kit = null;

            if ($strategy->profile_id) {
                $profile = $strategy->relationLoaded('profile')
                    ? $strategy->profile
                    : MarketingProfile::find($strategy->profile_id);
                if ($profile && $profile->brand_kit_id) {
                    $kit = BrandKit::find($profile->brand_kit_id);
                }
            }

            if (!$kit) {
                $snap = (array) ($strategy->profile_snapshot ?? []);
                if (!empty($snap['brand_kit_id'])) {
                    $kit = BrandKit::find((int) $snap['brand_kit_id']);
                }
            }

            if (!$kit && $strategy->user_id) {
                $kit = BrandKit::defaultFor((int) $strategy->user_id);
            }

            // Ownership guard: only brand with a kit that belongs to the plan's
            // owner so a stale/foreign brand_kit_id can never leak.
            if ($kit && (int) $kit->user_id !== (int) $strategy->user_id) {
                $kit = null;
            }

            if ($kit) {
                $palette = $kit->palette();
                $primary = $this->safeColor($palette['primary'] ?? '') ?: $platform['primary'];
                $accent  = $this->safeColor($palette['accent'] ?? '')
                    ?: $this->safeColor($palette['secondary'] ?? '')
                    ?: $primary;
                $name = trim((string) $kit->name);

                return [
                    'name'    => $name !== '' ? $name : $platform['name'],
                    'primary' => $primary,
                    'accent'  => $accent,
                    'logo'    => $this->embedImage($kit->logo()),
                ];
            }
        } catch (\Throwable $e) {
            // fall through to the platform identity
        }

        return $platform;
    }

    /** Return a value only when it is a safe CSS hex colour, else ''. */
    protected function safeColor($value): string
    {
        $v = trim((string) $value);
        return preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?$/', $v) ? $v : '';
    }

    /**
     * Task #3302 — turn a logo reference (data URI, http(s) URL or local
     * storage path) into an embeddable base64 data URI so it renders in the
     * PDF without remote fetching (dompdf remote images stay disabled).
     * Fully guarded and size-capped — any failure returns '' (no logo).
     */
    protected function embedImage(?string $src): string
    {
        $src = trim((string) $src);
        if ($src === '') {
            return '';
        }

        $cap = 2_000_000; // ~2MB safety cap

        try {
            if (str_starts_with($src, 'data:image/')) {
                return strlen($src) <= $cap ? $src : '';
            }

            $bytes = null;

            if (preg_match('#^https?://#i', $src)) {
                if (!$this->remoteImageFetchAllowed($src)) {
                    return '';
                }
                $ctx = stream_context_create([
                    // Enforce TLS verification; disable redirects so a public
                    // URL can't bounce the fetch to an internal host (SSRF).
                    'http' => ['timeout' => 4, 'follow_location' => 0, 'max_redirects' => 0],
                    'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
                ]);
                $bytes = @file_get_contents($src, false, $ctx, 0, $cap + 1);
            } else {
                $path = $src;
                if (!is_file($path)) {
                    $rel = ltrim($src, '/');
                    foreach ([
                        public_path($rel),
                        storage_path('app/public/' . preg_replace('#^storage/#', '', $rel)),
                    ] as $cand) {
                        if (is_file($cand)) {
                            $path = $cand;
                            break;
                        }
                    }
                }
                if (is_file($path) && filesize($path) <= $cap) {
                    $bytes = @file_get_contents($path);
                }
            }

            if (!is_string($bytes) || $bytes === '' || strlen($bytes) > $cap) {
                return '';
            }

            $info = @getimagesizefromstring($bytes);
            $mime = is_array($info) && !empty($info['mime']) ? $info['mime'] : '';
            if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
                return '';
            }

            return 'data:' . $mime . ';base64,' . base64_encode($bytes);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * SSRF guard for the remote logo fetch in embedImage(): only allow http(s)
     * hosts that resolve exclusively to public IP addresses. Any private,
     * reserved, or link-local address (or an unresolvable host) is rejected.
     */
    protected function remoteImageFetchAllowed(string $url): bool
    {
        $parts = @parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }
        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'];
        $ips  = [];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            $v4 = @gethostbynamel($host);
            if (is_array($v4)) {
                $ips = array_merge($ips, $v4);
            }
            $aaaa = @dns_get_record($host, DNS_AAAA);
            if (is_array($aaaa)) {
                foreach ($aaaa as $rec) {
                    if (!empty($rec['ipv6'])) {
                        $ips[] = $rec['ipv6'];
                    }
                }
            }
        }

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Task #3281 — free CSV export of a saved strategy: scorecard + score
     * history + forecast + suggestions, flattened into rows. No AI, no coins.
     */
    public function toCsv(MarketingStrategy $strategy): string
    {
        $rows = [];
        $rows[] = ['section', 'key', 'value'];

        $rows[] = ['meta', 'title', (string) $strategy->title];
        $rows[] = ['meta', 'goal', trim((string) $strategy->goal)];
        $rows[] = ['meta', 'goal_metric', (string) ($strategy->goal_metric ?? '')];
        $rows[] = ['meta', 'depth', (string) $strategy->depth()];
        $rows[] = ['meta', 'generated_at', (string) optional($strategy->created_at)->toDateTimeString()];

        $scorecard = (array) ($strategy->scorecard ?? []);
        foreach (['overall', 'reach', 'engagement', 'conversion', 'consistency'] as $k) {
            if (array_key_exists($k, $scorecard)) {
                $rows[] = ['scorecard', $k, (string) (int) $scorecard[$k]];
            }
        }

        $baseline = (array) ($strategy->baseline ?? []);
        if ($baseline) {
            $rows[] = ['baseline', (string) ($baseline['metric'] ?? 'value'), (string) (int) ($baseline['value'] ?? 0)];
        }

        $forecast = (array) ($strategy->forecast ?? []);
        $bands    = (array) ($forecast['scenarios'] ?? $forecast['bands'] ?? []);
        foreach ($bands as $name => $band) {
            $band  = (array) $band;
            $label = is_string($name) ? $name : (string) ($band['label'] ?? 'scenario');
            $rows[] = ['forecast', $label, (string) (int) ($band['value'] ?? $band['projected'] ?? 0)];
        }

        // Task #3302 — month-by-month execution plan, one row per month.
        $exec = (array) (((array) ($strategy->strategy ?? []))['execution_plan'] ?? []);
        foreach ((array) ($exec['months'] ?? []) as $m) {
            $m = (array) $m;
            $summary = trim(implode(' | ', array_filter([
                trim((string) ($m['theme'] ?? '')),
                ($b = trim((string) ($m['budget'] ?? ''))) !== '' ? 'Budget: ' . $b : '',
                count((array) ($m['deliverables'] ?? [])) . ' deliverables',
            ])));
            $rows[] = ['execution_plan', 'month ' . (int) ($m['month'] ?? 0), $summary];
        }

        try {
            foreach ($strategy->scores()->orderBy('created_at')->get() as $snap) {
                $rows[] = ['score_history', (string) optional($snap->created_at)->toDateString(), (string) (int) $snap->overall];
            }
        } catch (\Throwable $e) {
            // score table missing — skip history.
        }

        try {
            foreach ($strategy->suggestions()->get() as $sug) {
                $rows[] = ['suggestion', $sug->typeLabel() . ' — ' . $sug->status, (string) $sug->title];
            }
        } catch (\Throwable $e) {
            // suggestions relation issue — skip.
        }

        $out = '';
        foreach ($rows as $row) {
            $out .= implode(',', array_map([$this, 'csvCell'], $row)) . "\r\n";
        }
        return $out;
    }

    protected function csvCell($value): string
    {
        $v = (string) $value;
        if (preg_match('/[",\r\n]/', $v)) {
            $v = '"' . str_replace('"', '""', $v) . '"';
        }
        return $v;
    }

    /**
     * Task #3281 — build the messages for the premium report exec-summary. The
     * model is given the deterministic analysis and asked to write a concise,
     * board-ready narrative; it owns no numbers.
     *
     * @return list<array{role:string,content:string}>
     */
    protected function buildReportMessages(MarketingStrategy $strategy): array
    {
        $system = 'You are Sayzio Marketing Strategist writing the executive summary for a '
            . 'branded PDF report. Write 3-5 short paragraphs a busy founder can read in a '
            . 'minute: where they stand today, the biggest opportunity, the recommended focus, '
            . 'and the realistic outcome if they execute. Ground every claim in the ANALYSIS '
            . 'provided — never invent numbers. Plain prose only, no markdown, no headings.';

        $payload = [
            'title'       => (string) $strategy->title,
            'goal'        => trim((string) $strategy->goal),
            'goal_metric' => (string) ($strategy->goal_metric ?? ''),
            'scorecard'   => (array) ($strategy->scorecard ?? []),
            'diagnosis'   => (array) ($strategy->diagnosis ?? []),
            'forecast'    => (array) ($strategy->forecast ?? []),
            'competitor'  => (array) ($strategy->competitor_analysis ?? []),
            'plan'        => (array) ($strategy->strategy ?? []),
        ];

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => "ANALYSIS (authoritative):\n" . $this->compactJson($payload)],
        ];
    }

    /**
     * Task #3281 — generate the premium report exec-summary. This is the ONE
     * metered + auto-refunded AI call behind the "Premium AI PDF" download
     * tier. Returns the summary text plus credits spent.
     *
     * @return array{summary:string,credits_spent:int,model:string}
     */
    public function generatePremiumReport(User $user, MarketingStrategy $strategy): array
    {
        $model    = AiEngineSettings::featureModel(self::FEATURE, $user);
        $messages = $this->buildReportMessages($strategy);

        $result = $this->openai->chat($user, $model, $messages, [
            'temperature'  => 0.5,
            'max_tokens'   => self::REPORT_MAX_OUTPUT_TOKENS,
            'feature'      => self::FEATURE,
            'reason'       => 'AI Marketing Strategist premium report',
            'meta'         => ['sub_feature' => self::REPORT_FEATURE, 'strategy_id' => $strategy->id],
        ]);

        $creditsSpent = (int) ($result['credits_spent'] ?? 0);

        try {
            $summary = trim((string) ($result['content'] ?? ''));
            if ($summary === '') {
                throw new RuntimeException('The report summary came back empty. Please try again.');
            }
        } catch (\Throwable $e) {
            if ($creditsSpent > 0) {
                $this->credits->refund($user, $creditsSpent, [
                    'feature' => self::FEATURE,
                    'reason'  => 'AI Marketing Strategist premium report failed — auto refund',
                ]);
            }
            throw $e;
        }

        return [
            'summary'       => mb_substr($summary, 0, 6000),
            'credits_spent' => $creditsSpent,
            'model'         => (string) ($result['model'] ?? $model),
        ];
    }
}
