<?php

namespace App\Services\AI;

use App\Modules\User\Models\User;
use App\Modules\User\Support\DashboardPresets;
use App\Modules\User\Support\DashboardWidgetCatalog;
use RuntimeException;

/**
 * Task #3525 — "Design my dashboard with AI".
 *
 * Mirrors {@see \App\Services\Biolink\AiBiolinkBuilderService}: a short
 * structured questionnaire is turned into a chat prompt constrained to the
 * real {@see DashboardWidgetCatalog} keys, the model's JSON reply is
 * validated against that catalog, and the call is charged/refunded via
 * OpenAiService/AiUsageCharger exactly like every other AI feature — no new
 * currency path, no new widgets, no ability to invent a catalog key.
 */
class DashboardAiDesignerService
{
    public const FEATURE = 'dashboard_designer';

    private const MAX_OUTPUT_TOKENS = 400;
    private const MAX_TEXT_LEN = 800;

    public function __construct(
        protected OpenAiService $openai,
        protected AiUsageCharger $credits,
    ) {}

    /**
     * @param  array{goal?:string, priorities?:list<string>, density?:string, notes?:string}  $answers
     * @return list<array{role:string,content:string}>
     */
    public function buildMessages(array $answers): array
    {
        $goal = trim((string) ($answers['goal'] ?? ''));
        if ($goal === '') {
            throw new RuntimeException('Tell us what you want your dashboard to focus on first.');
        }
        $goal = mb_substr($goal, 0, self::MAX_TEXT_LEN);

        $priorities = array_values(array_filter(array_map('strval', $answers['priorities'] ?? []), fn ($p) => trim($p) !== ''));
        $priorities = array_slice($priorities, 0, 10);

        $density = trim((string) ($answers['density'] ?? 'balanced'));
        if (!in_array($density, ['minimal', 'balanced', 'detailed'], true)) {
            $density = 'balanced';
        }

        $notes = trim((string) ($answers['notes'] ?? ''));
        $notes = mb_substr($notes, 0, self::MAX_TEXT_LEN);

        $catalogLines = [];
        foreach (DashboardWidgetCatalog::WIDGETS as $key => $meta) {
            $catalogLines[] = "- {$key} ({$meta['tab']} tab): {$meta['label']} — {$meta['description']}";
        }

        $densityHint = match ($density) {
            'minimal'  => 'Pick a LEAN set — roughly 4-6 widgets covering only what matters most.',
            'detailed' => 'Pick a RICH set — roughly 8-11 widgets so nothing relevant is missing.',
            default    => 'Pick a BALANCED set — roughly 6-8 widgets.',
        };

        $system = "You design a personalised widget layout for a creator's link-management dashboard. "
            . "You may ONLY select from the widgets listed below — never invent a new widget key.\n\n"
            . "AVAILABLE WIDGETS:\n" . implode("\n", $catalogLines) . "\n\n"
            . $densityHint . "\n"
            . "Return STRICT JSON with this exact shape (no markdown, no commentary, no extra keys):\n"
            . "{\n  \"widgets\": [string, ...]\n}\n"
            . "Rules:\n"
            . "- Every entry in `widgets` MUST be one of the widget keys listed above, spelled exactly.\n"
            . "- Order the array by importance to the stated goal — most important first.\n"
            . "- Do not repeat a key.";

        $userParts = ["GOAL:\n{$goal}"];
        if ($priorities) {
            $userParts[] = "PRIORITY METRICS (in the user's own words):\n- " . implode("\n- ", $priorities);
        }
        if ($notes !== '') {
            $userParts[] = "ADDITIONAL NOTES:\n{$notes}";
        }

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => implode("\n\n", $userParts)],
        ];
    }

    public function estimateCredits(User $user, array $answers): int
    {
        $model = AiEngineSettings::featureModel(self::FEATURE);
        $messages = $this->buildMessages($answers);
        return $this->openai->estimateChatCoins($model, $messages, self::MAX_OUTPUT_TOKENS, $user);
    }

    /**
     * Run the designer, validate the model's widget list, persist it as the
     * user's custom layout, and return what was spent + the resolved
     * widgets. Auto-refunds the exact charge if the model's reply can't be
     * parsed into at least one valid widget.
     *
     * @param  array{goal?:string, priorities?:list<string>, density?:string, notes?:string}  $answers
     * @return array{credits_spent:int, widgets:list<string>, model:string}
     */
    public function generate(User $user, array $answers): array
    {
        $messages = $this->buildMessages($answers);
        $model = AiEngineSettings::featureModel(self::FEATURE);

        $result = $this->openai->chat($user, $model, $messages, [
            'temperature'     => 0.3,
            'max_tokens'      => self::MAX_OUTPUT_TOKENS,
            'response_format' => ['type' => 'json_object'],
            'feature'         => self::FEATURE,
            'reason'          => 'AI dashboard designer',
            'meta'            => [
                'goal_excerpt' => mb_substr((string) ($answers['goal'] ?? ''), 0, 160),
                'density'      => (string) ($answers['density'] ?? 'balanced'),
            ],
        ]);

        $creditsSpent = (int) ($result['credits_spent'] ?? 0);

        try {
            $parsed = json_decode((string) $result['content'], true);
            if (!is_array($parsed)) {
                throw new RuntimeException('The assistant returned an unexpected response. Please try again.');
            }

            $widgets = DashboardWidgetCatalog::sanitize(is_array($parsed['widgets'] ?? null) ? $parsed['widgets'] : []);
            if (empty($widgets)) {
                throw new RuntimeException('The assistant could not design a dashboard from that description. Add more detail and try again.');
            }

            DashboardPresets::applyCustom($user, $widgets, 'ai');
        } catch (\Throwable $e) {
            if ($creditsSpent > 0) {
                $this->credits->refund($user, $creditsSpent, [
                    'feature' => self::FEATURE,
                    'reason'  => 'AI dashboard designer failed — auto refund',
                ]);
            }
            throw $e;
        }

        return [
            'credits_spent' => $creditsSpent,
            'widgets'       => $widgets,
            'model'         => (string) ($result['model'] ?? $model),
        ];
    }
}
