<?php

namespace App\Services\AI;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Estimates a probabilistic visitor-persona mix for a biolink using
 * aggregate, anonymised first-party signals that Sayzio already captures
 * (referrer host, geo, device/OS/browser, language, time-of-day, and
 * which blocks visitors engaged with).
 *
 * Privacy guarantees:
 *  - Input to the model is always AGGREGATE counts, never individual
 *    rows or user-identifiable data.
 *  - Output is presented as a % estimate, never as a hard claim about
 *    any specific person.
 *  - No browser history, no third-party cookies, no data-broker enrichment.
 *
 * Billing: charged via `OpenAiService` / `AiUsageCharger` against the
 * `audience_type_estimation` feature key; auto-refund on parse failure.
 * Gate via `AiPlanAccess::featureAllowed($user, 'audience_type_estimation')`.
 */
class AudienceTypeEstimationService
{
    public const FEATURE_KEY = 'audience_type_estimation';

    public const PERSONA_TYPES = [
        'student'      => 'Student',
        'professional' => 'Professional / Employee',
        'business'     => 'Business Owner',
        'creator'      => 'Creator / Artist',
        'other'        => 'Other',
    ];

    public function __construct(
        protected OpenAiService  $ai,
        protected AiUsageCharger $charger,
    ) {}

    /**
     * Run the estimation for a link over a date window.
     *
     * Returns:
     * [
     *   'estimated'   => [['type'=>'student','label'=>'Student','pct'=>35], ...],
     *   'tokens_in'   => int,
     *   'tokens_out'  => int,
     *   'credits_spent' => int,
     * ]
     *
     * Throws on AI engine off or insufficient coins; caller must catch.
     */
    public function estimate(User $user, Link $link, \Carbon\Carbon $from, \Carbon\Carbon $to): array
    {
        $signals = $this->gatherSignals($link->id, $from, $to);
        $totalSessions = $signals['totalSessions'];

        if ($totalSessions === 0) {
            return $this->emptyResult();
        }

        $prompt = $this->buildPrompt($signals);

        $messages = [
            [
                'role'    => 'system',
                'content' => 'You are an audience-analysis assistant. Based on aggregate, anonymous web analytics signals, estimate the probable mix of visitor personas for a biolink page. Respond only with valid JSON in the exact schema requested. Never identify individuals — all inputs are aggregate counts.',
            ],
            [
                'role'    => 'user',
                'content' => $prompt,
            ],
        ];

        $result = $this->ai->chat($user, AiEngineSettings::DEFAULT_FEATURE_MODEL, $messages, [
            'feature'         => self::FEATURE_KEY,
            'related_id'      => $link->id,
            'max_tokens'      => 400,
            'temperature'     => 0.2,
            'response_format' => ['type' => 'json_object'],
            'reason'          => 'Audience type estimation for link ' . $link->alias,
        ]);

        $parsed = $this->parseResponse($result['content']);

        if ($parsed === null) {
            $this->charger->refund($user, max(1, $result['credits_spent']), [
                'feature' => self::FEATURE_KEY,
                'reason'  => 'Audience estimation parse failure — auto-refund',
            ]);
            return $this->emptyResult();
        }

        return [
            'estimated'      => $parsed,
            'tokens_in'      => $result['tokens_in'],
            'tokens_out'     => $result['tokens_out'],
            'credits_spent'  => $result['credits_spent'],
        ];
    }

    /**
     * Collect aggregate signals from link_clicks and page_sessions.
     * All results are counts — no IP addresses, no user agents, no
     * individual rows are included in the returned array.
     */
    protected function gatherSignals(int $linkId, \Carbon\Carbon $from, \Carbon\Carbon $to): array
    {
        $sessionBase = DB::table('page_sessions')
            ->where('link_id', $linkId)
            ->whereBetween('started_at', [$from, $to]);

        $totalSessions = (int) (clone $sessionBase)->count();

        $byDevice = (clone $sessionBase)
            ->selectRaw('device_type, count(*) as cnt')
            ->groupBy('device_type')
            ->pluck('cnt', 'device_type')
            ->toArray();

        $byLanguage = (clone $sessionBase)
            ->selectRaw('language, count(*) as cnt')
            ->whereNotNull('language')
            ->groupBy('language')
            ->orderByDesc('cnt')
            ->limit(8)
            ->pluck('cnt', 'language')
            ->toArray();

        $byCountry = (clone $sessionBase)
            ->selectRaw('country_code, count(*) as cnt')
            ->whereNotNull('country_code')
            ->groupBy('country_code')
            ->orderByDesc('cnt')
            ->limit(10)
            ->pluck('cnt', 'country_code')
            ->toArray();

        $byHour = (clone $sessionBase)
            ->selectRaw("extract(hour from started_at)::int as hr, count(*) as cnt")
            ->groupBy('hr')
            ->orderBy('hr')
            ->pluck('cnt', 'hr')
            ->toArray();

        $byReferrer = DB::table('link_clicks')
            ->where('link_id', $linkId)
            ->whereBetween('clicked_at', [$from, $to])
            ->where('is_bot', false)
            ->whereNotNull('referrer')
            ->selectRaw("regexp_replace(referrer, '^https?://(?:www\\.)?([^/?#]+).*$', '\\1') as host, count(*) as cnt")
            ->groupBy('host')
            ->orderByDesc('cnt')
            ->limit(8)
            ->pluck('cnt', 'host')
            ->toArray();

        $byBlockType = DB::table('block_views')
            ->where('link_id', $linkId)
            ->whereBetween('first_viewed_at', [$from, $to])
            ->selectRaw('block_type, count(*) as cnt')
            ->whereNotNull('block_type')
            ->groupBy('block_type')
            ->orderByDesc('cnt')
            ->limit(10)
            ->pluck('cnt', 'block_type')
            ->toArray();

        return compact(
            'totalSessions', 'byDevice', 'byLanguage',
            'byCountry', 'byHour', 'byReferrer', 'byBlockType'
        );
    }

    protected function buildPrompt(array $signals): string
    {
        $encode = fn($arr) => empty($arr) ? '{}' : json_encode($arr);

        return <<<PROMPT
Aggregate analytics for a biolink page ({$signals['totalSessions']} total sessions):

Device types (count): {$encode($signals['byDevice'])}
Top languages (count): {$encode($signals['byLanguage'])}
Top countries (count): {$encode($signals['byCountry'])}
Sessions by hour-of-day (24h, count): {$encode($signals['byHour'])}
Top referrer hosts (count): {$encode($signals['byReferrer'])}
Block types viewed (count): {$encode($signals['byBlockType'])}

Based on these aggregate signals, estimate the likely visitor persona breakdown.
Use exactly these persona keys: student, professional, business, creator, other.
The percentages must sum to 100.
Respond with JSON:
{"personas": [{"type": "student", "pct": 35}, {"type": "professional", "pct": 40}, {"type": "business", "pct": 10}, {"type": "creator", "pct": 10}, {"type": "other", "pct": 5}]}
PROMPT;
    }

    protected function parseResponse(string $content): ?array
    {
        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data['personas']) || !is_array($data['personas'])) {
            Log::warning('AudienceTypeEstimationService: unexpected JSON shape', ['content' => substr($content, 0, 500)]);
            return null;
        }

        $out = [];
        $sum = 0;
        foreach ($data['personas'] as $p) {
            $type = (string) ($p['type'] ?? '');
            $pct  = (int) ($p['pct'] ?? 0);
            if (!isset(self::PERSONA_TYPES[$type]) || $pct < 0) {
                continue;
            }
            $out[] = [
                'type'  => $type,
                'label' => self::PERSONA_TYPES[$type],
                'pct'   => $pct,
            ];
            $sum += $pct;
        }

        if (empty($out) || $sum < 95 || $sum > 105) {
            Log::warning('AudienceTypeEstimationService: pct sum out of range', ['sum' => $sum]);
            return null;
        }

        usort($out, fn($a, $b) => $b['pct'] <=> $a['pct']);
        return $out;
    }

    protected function emptyResult(): array
    {
        return [
            'estimated'     => [],
            'tokens_in'     => 0,
            'tokens_out'    => 0,
            'credits_spent' => 0,
        ];
    }
}
