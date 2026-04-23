<?php

namespace App\Services\AI;

use App\Modules\Admin\Models\AppSetting;

/**
 * Typed accessor for the site-wide AI assistant configuration.
 * All values live in the shared app_settings store.
 */
class SiteAssistantSettings
{
    public const KEY = 'site_assistant.config';

    public static function defaults(): array
    {
        return [
            'enabled_marketing' => true,
            'enabled_app'       => true,
            'launcher_position' => 'bottom-right', // bottom-right|bottom-left
            'accent_color'      => '#7c3aed',
            'avatar_url'        => null,
            'greeting'          => "Hi! I'm your 1INME assistant. Ask me anything about the platform or what you can do on this page.",
            'system_prompt'     => self::defaultSystemPrompt(),
            'model'             => '', // empty = fall back to feature-mapped chat model
            'mind_ids'          => [], // empty = use platform-default minds
            'temperature'       => 0.4,
            'max_tokens'        => 800,
            'billing_user_id'   => null, // platform user that pays for anonymous turns
            'monthly_budget_credits' => 0, // 0 = unlimited
            'session_rate_per_minute' => 12,
            'handoff_enabled'   => true,
            'handoff_freeze_after' => true,
            'starter_prompts'   => [
                'What can I do on this page?',
                'How does pricing work?',
                'Talk to a human',
            ],
        ];
    }

    public static function defaultSystemPrompt(): string
    {
        return <<<'P'
You are the 1INME Assistant, a helpful, concise guide that lives on every
page of the 1INME web app. Visitors may be browsing marketing pages or
signed in to their dashboard. Always:

- Use the "Page context" block to ground your answer in what the user
  is currently looking at, and suggest the most relevant next action.
- When grounded knowledge is provided in the "Knowledge" block, prefer
  it over your prior knowledge. If the answer isn't in there and isn't
  general 1INME knowledge, say so politely and offer to connect with
  the support team.
- Keep replies short (under ~120 words) and friendly. Use short
  paragraphs or compact bullet lists.
- Never invent prices, plan limits, URLs, or user data. If unsure, say
  so and suggest contacting support.
- If the user asks to speak with a human, offer the "Talk to a human"
  action.
P;
    }

    public static function get(): array
    {
        $stored = AppSetting::get(self::KEY, []);
        if (!is_array($stored)) $stored = [];
        return array_replace(self::defaults(), $stored);
    }

    public static function update(array $data): array
    {
        $current = self::get();
        $merged = array_replace($current, $data);
        AppSetting::put(self::KEY, $merged);
        return $merged;
    }

    public static function isEnabledFor(string $surface): bool
    {
        $cfg = self::get();
        if ($surface === 'marketing') return (bool) $cfg['enabled_marketing'];
        if ($surface === 'app')       return (bool) $cfg['enabled_app'];
        return false;
    }

    /**
     * Total credits charged to the assistant in the current calendar
     * month — covers BOTH chat completions and embeddings/retrieval as
     * long as those calls were tagged with feature='site_assistant'.
     * Sums absolute spend (debits are negative deltas in the ledger).
     */
    public static function monthlySpend(): int
    {
        try {
            $sum = (int) \DB::table('ai_credit_transactions')
                ->where('feature', 'site_assistant')
                ->where('type', 'spend')
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum(\DB::raw('ABS(delta_credits)'));
            if ($sum > 0) return $sum;
        } catch (\Throwable $e) {
            // table may be missing in early test envs — fall back below
        }
        return (int) \App\Modules\Common\Models\SiteAssistantMessage::where(
            'created_at', '>=', now()->startOfMonth()
        )->sum('credits_spent');
    }

    public static function isOverBudget(): bool
    {
        $cfg = self::get();
        $cap = (int) ($cfg['monthly_budget_credits'] ?? 0);
        if ($cap <= 0) return false;
        return self::monthlySpend() >= $cap;
    }
}
