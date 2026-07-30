<?php

namespace App\Modules\User\Services\Inbox;

use App\Modules\User\Models\InboxThread;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * LLM-backed thread triage: one cheap call returns category, priority and
 * a one-line summary so the unified list can render rich, scannable rows
 * without re-reading the body each time.
 *
 * Always degrades safely: when the AI Engine is off, the owner's plan
 * doesn't unlock the Inbox Agent, the workspace turned AI triage off, or
 * the call/parse fails, it falls back to the deterministic
 * {@see InboxClassifier} and derives a heuristic priority + summary. This
 * method therefore NEVER throws — callers (sync, autopilot) rely on that.
 */
class InboxAiTriage
{
    public const FEATURE = 'inbox_agent';

    public function __construct(
        protected InboxClassifier $classifier,
        protected OpenAiService $openai,
        protected AiUsageCharger $charger,
    ) {}

    /**
     * @return array{category:string,confidence:float,priority:string,summary:string,reason:string,source:string}
     */
    public function triage(
        string $body,
        ?string $context,
        string $channel,
        bool $knownSpam,
        ?User $chargeUser = null,
        ?Workspace $ws = null,
    ): array {
        $rule = $this->classifier->classify($body, $context, $channel, $knownSpam);
        $fallback = [
            'category'   => $rule['category'],
            'confidence' => (float) $rule['confidence'],
            'priority'   => $this->heuristicPriority($rule['category'], $body),
            'summary'    => $this->heuristicSummary($body),
            'reason'     => $rule['reason'] ?? 'rule',
            'source'     => 'rule',
        ];

        if (!$this->aiUsable($chargeUser, $ws) || trim($body) === '') {
            return $fallback;
        }

        try {
            $model = AiEngineSettings::featureModel(self::FEATURE, $chargeUser);
            $res = $this->openai->chat($chargeUser, $model, $this->messages($body, $context, $channel), [
                'feature'         => self::FEATURE . '.triage',
                'response_format' => ['type' => 'json_object'],
                'temperature'     => 0.1,
                'max_tokens'      => 200,
                'reason'          => 'Inbox Agent triage',
            ]);

            $parsed = $this->parse($res['content'] ?? '');
            if ($parsed === null) {
                if (($res['credits_spent'] ?? 0) > 0) {
                    $this->refund($chargeUser, (int) $res['credits_spent'], 'parse_failed');
                }
                return $fallback;
            }

            return [
                'category'   => $parsed['category'],
                'confidence' => $parsed['confidence'],
                'priority'   => $parsed['priority'],
                'summary'    => $parsed['summary'] !== '' ? $parsed['summary'] : $fallback['summary'],
                'reason'     => 'ai',
                'source'     => 'ai',
            ];
        } catch (\Throwable $e) {
            // Disabled engine, missing key, insufficient coins, network — all
            // degrade silently to the deterministic classifier.
            Log::info('Inbox Agent triage fell back to rules: ' . $e->getMessage());
            return $fallback;
        }
    }

    protected function aiUsable(?User $user, ?Workspace $ws): bool
    {
        if (!$user || !AiEngineSettings::isEnabled()) {
            return false;
        }
        if (!AiPlanAccess::featureAllowed($user, self::FEATURE)) {
            return false;
        }
        if ($ws) {
            $cfg = InboxAgentSettings::for($ws);
            if (!($cfg['ai_triage'] ?? true)) {
                return false;
            }
        }
        return true;
    }

    /** @return array{category:string,confidence:float,priority:string,summary:string}|null */
    protected function parse(string $content): ?array
    {
        $json = json_decode(trim($content), true);
        if (!is_array($json)) {
            return null;
        }

        $category = (string) ($json['category'] ?? '');
        if (!in_array($category, InboxThread::CATEGORIES, true)) {
            return null;
        }

        $priority = (string) ($json['priority'] ?? 'normal');
        if (!in_array($priority, InboxThread::PRIORITIES, true)) {
            $priority = 'normal';
        }

        $confidence = (float) ($json['confidence'] ?? 0.6);
        $confidence = max(0.0, min(1.0, $confidence));

        $summary = trim((string) ($json['summary'] ?? ''));
        $summary = Str::limit(preg_replace('/\s+/', ' ', $summary), 180, '');

        return compact('category', 'confidence', 'priority', 'summary');
    }

    protected function messages(string $body, ?string $context, string $channel): array
    {
        $cats = implode(', ', InboxThread::CATEGORIES);
        $prios = implode(', ', InboxThread::PRIORITIES);

        $system = <<<PROMPT
You are the triage engine for a creator's unified message inbox. Classify a
single inbound message and respond with STRICT JSON only, no prose.

Return an object with exactly these keys:
- "category": one of [{$cats}]
- "priority": one of [{$prios}]
- "confidence": a number 0..1 (your certainty in the category)
- "summary": a neutral one-line summary of what the sender wants (max 18 words)

Guidance:
- "lead": a business/buying/booking enquiry or someone wanting to work with the creator.
- "sponsorship": brand deals, partnerships, paid collaborations, media-kit/rate-card requests.
- "support": a problem, complaint, refund, account/order issue, or help request.
- "fan": appreciation, fan mail, casual chat, no action needed.
- "spam": promotional junk, scams, irrelevant mass outreach.
- priority "urgent": time-sensitive money/legal/angry/at-risk situations.
- priority "high": clear opportunity or unhappy customer needing a prompt reply.
- priority "normal": standard. "low": fan mail / no real action.
PROMPT;

        $userBlock = trim(
            ($channel ? "Channel: {$channel}\n" : '') .
            ($context ? "Context: {$context}\n" : '') .
            "Message:\n" . Str::limit($body, 4000, '')
        );

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $userBlock],
        ];
    }

    protected function heuristicPriority(string $category, string $body): string
    {
        $blob = mb_strtolower($body);
        foreach (['urgent', 'asap', 'immediately', 'refund', 'chargeback', 'lawsuit', 'legal', 'dispute', 'emergency'] as $kw) {
            if (str_contains($blob, $kw)) {
                return 'urgent';
            }
        }
        return match ($category) {
            'sponsorship', 'lead' => 'high',
            'support'             => 'high',
            'spam'                => 'low',
            'fan'                 => 'low',
            default               => 'normal',
        };
    }

    protected function heuristicSummary(string $body): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $body));
        return Str::limit($clean, 140, '…');
    }

    protected function refund(User $user, int $coins, string $why): void
    {
        try {
            $this->charger->refund($user, $coins, [
                'feature' => self::FEATURE . '.triage',
                'reason'  => 'Inbox Agent triage refund (' . $why . ')',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Inbox Agent triage refund failed: ' . $e->getMessage());
        }
    }
}
