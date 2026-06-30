<?php

namespace App\Services\AI;

use App\Modules\User\Models\AiCompanion;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\AiPersonaAgent;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\MarketingStrategy;
use App\Modules\User\Models\MarketingStrategySuggestion;
use App\Modules\User\Models\Pixel;
use App\Modules\User\Models\User;
use App\Services\AI\AskCoach\AskCoachToolRegistry;
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

    /** Output ceiling for a full strategy. */
    public const MAX_OUTPUT_TOKENS = 2200;

    /**
     * Selectable data sources the creator can toggle on. Each maps to a
     * builder that returns a compact, PII-free text snapshot fed to the
     * model. Keys double as the persisted `sources` flags.
     *
     * @var array<string,array{label:string,description:string}>
     */
    public const SOURCES = [
        'links'       => ['label' => 'Links & types',       'description' => 'Your links, their types and lifetime clicks.'],
        'analytics'   => ['label' => 'Analytics',           'description' => 'Recent click trends and device split.'],
        'audience'    => ['label' => 'Followers & subscribers', 'description' => 'Audience size and growth.'],
        'pixels'      => ['label' => 'Tracking pixels',      'description' => 'Ad pixels you already have connected.'],
        'minds'       => ['label' => 'AI Minds',             'description' => 'Your knowledge bases (names only).'],
        'brand_kits'  => ['label' => 'Brand Kits',           'description' => 'Your brand palette, voice and taglines.'],
        'personas'    => ['label' => 'AI Personas',          'description' => 'Your saved AI persona agents.'],
        'companions'  => ['label' => 'AI Companions',        'description' => 'Your published AI chat companions.'],
    ];

    public function __construct(
        protected OpenAiService $openai,
        protected AiUsageCharger $credits,
        protected AskCoachToolRegistry $tools,
    ) {}

    /** Normalise an arbitrary list of source keys to the known set. */
    public function normalizeSources(array $sources): array
    {
        $known = array_keys(self::SOURCES);
        $clean = array_values(array_intersect($known, array_map('strval', $sources)));
        return $clean ?: ['links', 'analytics', 'audience'];
    }

    /**
     * Assemble the data context for the toggled sources.
     *
     * @return array{context:string,snapshot:array<string,string>}
     */
    public function buildContext(User $user, array $sources): array
    {
        $sources  = $this->normalizeSources($sources);
        $snapshot = [];

        foreach ($sources as $src) {
            $text = '';
            try {
                $text = match ($src) {
                    'links'      => $this->snapshotLinks($user),
                    'analytics'  => $this->snapshotTool($user, 'analytics'),
                    'audience'   => $this->snapshotTool($user, 'audience'),
                    'pixels'     => $this->snapshotPixels($user),
                    'minds'      => $this->snapshotMinds($user),
                    'brand_kits' => $this->snapshotBrandKits($user),
                    'personas'   => $this->snapshotPersonas($user),
                    'companions' => $this->snapshotCompanions($user),
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

    /** Worst-case credit cost shown before the user clicks Generate. */
    public function estimateCredits(User $user, string $goal, array $parameters, string $context): int
    {
        $model    = AiEngineSettings::featureModel(self::FEATURE);
        $messages = $this->buildMessages($goal, $parameters, $context);
        return $this->openai->estimateChatCoins($model, $messages, self::MAX_OUTPUT_TOKENS, $user);
    }

    /**
     * Run the generation: call the model, parse the JSON into a saved
     * MarketingStrategy + its suggestions. On any parse/validation
     * failure the exact credits charged are refunded.
     *
     * @return array{strategy:MarketingStrategy,credits_spent:int,model:string}
     */
    public function generate(User $user, string $goal, array $parameters, array $sources, ?int $workspaceId = null): array
    {
        $sources  = $this->normalizeSources($sources);
        $assembled = $this->buildContext($user, $sources);
        $messages = $this->buildMessages($goal, $parameters, $assembled['context']);
        $model    = AiEngineSettings::featureModel(self::FEATURE);

        $result = $this->openai->chat($user, $model, $messages, [
            'temperature'     => 0.6,
            'max_tokens'      => self::MAX_OUTPUT_TOKENS,
            'response_format' => ['type' => 'json_object'],
            'feature'         => self::FEATURE,
            'reason'          => 'AI Marketing Strategist generation',
            'meta'            => [
                'goal_excerpt' => mb_substr(trim($goal), 0, 160),
                'sources'      => implode(',', $sources),
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

            $strategy = new MarketingStrategy();
            $strategy->user_id          = $user->id;
            $strategy->workspace_id      = $workspaceId;
            $strategy->title             = $title;
            $strategy->goal              = mb_substr(trim($goal), 0, 4000);
            $strategy->status            = 'ready';
            $strategy->sources           = $sources;
            $strategy->parameters        = $parameters;
            $strategy->context_snapshot  = $assembled['snapshot'];
            $strategy->strategy          = $plan;
            $strategy->model             = (string) ($result['model'] ?? $model);
            $strategy->credits_spent     = $creditsSpent;
            $strategy->save();

            $this->persistSuggestions($strategy, $parsed['suggestions'] ?? []);
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
     * System + user messages for a fresh generation. The model is told to
     * answer ONLY with the strict JSON envelope so parsing is reliable.
     */
    public function buildMessages(string $goal, array $parameters, string $context): array
    {
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

        $userParts = [];
        $goal = trim($goal);
        $userParts[] = 'GOAL:' . "\n" . ($goal !== '' ? $goal : 'Grow my audience and engagement on Sayzio.');

        $paramLines = [];
        foreach ($parameters as $k => $v) {
            if ($v === null || $v === '' || (is_array($v) && !$v)) continue;
            $label = ucwords(str_replace('_', ' ', (string) $k));
            $val   = is_array($v) ? implode(', ', array_map('strval', $v)) : (string) $v;
            $paramLines[] = "- {$label}: {$val}";
        }
        if ($paramLines) {
            $userParts[] = "PARAMETERS:\n" . implode("\n", $paramLines);
        }

        $userParts[] = "CREATOR DATA (read-only, do not invent beyond this):\n" . $context;

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => implode("\n\n", $userParts)],
        ];
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
            'summary' => mb_substr(trim((string) ($parsed['summary'] ?? '')), 0, 1200),
            'organic' => array_values($organic),
            'paid'    => array_values($paid),
            'kpis'    => $this->stringList($parsed['kpis'] ?? [], 10, 160),
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

            MarketingStrategySuggestion::create([
                'strategy_id' => $strategy->id,
                'type'        => $type,
                'title'       => $title,
                'description' => mb_substr(trim((string) ($s['description'] ?? '')), 0, 1000) ?: null,
                'payload'     => is_array($s['payload'] ?? null) ? $s['payload'] : [],
                'status'      => MarketingStrategySuggestion::STATUS_PENDING,
            ]);

            if (++$count >= 6) break;
        }
    }

    // ── data-source snapshots ──────────────────────────────────────

    protected function snapshotTool(User $user, string $tool): string
    {
        $r = $this->tools->run($tool, $user);
        return (string) ($r['summary'] ?? '');
    }

    protected function snapshotLinks(User $user): string
    {
        $rows = Link::query()
            ->where('user_id', $user->id)
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

    protected function snapshotPixels(User $user): string
    {
        $rows = Pixel::query()->where('user_id', $user->id)->orderBy('name')->get(['name', 'type']);
        if ($rows->isEmpty()) {
            return 'No tracking pixels connected yet.';
        }
        $lines = ['Connected tracking pixels:'];
        foreach ($rows as $r) {
            $lines[] = sprintf('- %s (%s)', $r->name, $r->type);
        }
        return implode("\n", $lines);
    }

    protected function snapshotMinds(User $user): string
    {
        $rows = AiMind::query()
            ->where('user_id', $user->id)
            ->where('is_disabled', false)
            ->orderBy('name')
            ->limit(25)
            ->get(['name']);
        if ($rows->isEmpty()) {
            return 'No AI Minds (knowledge bases) yet.';
        }
        return 'AI Minds (knowledge bases): ' . $rows->pluck('name')->implode(', ') . '.';
    }

    protected function snapshotBrandKits(User $user): string
    {
        $kits = BrandKit::query()->where('user_id', $user->id)->orderByDesc('is_default')->limit(5)->get();
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

    protected function snapshotPersonas(User $user): string
    {
        $rows = AiPersonaAgent::query()->where('user_id', $user->id)->orderBy('name')->limit(15)->get(['name', 'description', 'tone_preset']);
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

    protected function snapshotCompanions(User $user): string
    {
        $rows = AiCompanion::query()->where('user_id', $user->id)->orderBy('name')->limit(15)->get(['name', 'placement']);
        if ($rows->isEmpty()) {
            return 'No AI Companions published yet.';
        }
        $lines = ['AI Companions:'];
        foreach ($rows as $r) {
            $lines[] = '- ' . $r->name . ($r->placement ? " (placement: {$r->placement})" : '');
        }
        return implode("\n", $lines);
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

        if (!empty($plan['kpis'])) {
            $out .= "## KPIs to watch\n\n";
            foreach ((array) $plan['kpis'] as $kpi) $out .= "- {$kpi}\n";
            $out .= "\n";
        }

        return $out;
    }

    /** Render a saved strategy as a self-contained HTML document for PDF export. */
    public function toHtml(MarketingStrategy $strategy): string
    {
        $plan = (array) ($strategy->strategy ?? []);
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $body  = '<h1>' . $e($strategy->title) . '</h1>';
        $body .= '<p class="goal"><strong>Goal:</strong> ' . $e(trim((string) $strategy->goal)) . '</p>';

        if (!empty($plan['summary'])) {
            $body .= '<h2>Summary</h2><p>' . $e($plan['summary']) . '</p>';
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

        if (!empty($plan['kpis'])) {
            $body .= '<h2>KPIs to watch</h2><ul>';
            foreach ((array) $plan['kpis'] as $kpi) $body .= '<li>' . $e($kpi) . '</li>';
            $body .= '</ul>';
        }

        $css = 'body{font-family:DejaVu Sans,sans-serif;color:#1f2937;font-size:12px;line-height:1.5;}'
            . 'h1{font-size:22px;margin:0 0 4px;}h2{font-size:16px;margin:18px 0 6px;border-bottom:1px solid #e5e7eb;padding-bottom:3px;}'
            . 'h3{font-size:13px;margin:10px 0 3px;}.goal{margin:0 0 12px;}.play{margin:0 0 8px;}'
            . '.muted{color:#6b7280;font-size:11px;margin:2px 0;}ul{margin:4px 0 4px 18px;padding:0;}li{margin:2px 0;}';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' . $css . '</style></head><body>' . $body . '</body></html>';
    }
}
