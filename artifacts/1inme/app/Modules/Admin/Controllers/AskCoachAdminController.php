<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\WalletTransaction;
use App\Modules\User\Models\AskCoachMessage;
use App\Modules\User\Models\AskCoachThread;
use App\Services\AI\AiEngineSettings;
use Illuminate\Http\Request;

/**
 * Admin dashboard for the Ask Coach chatbot:
 *
 *   GET  /admin/ask-coach            usage + quality report.
 *   PUT  /admin/ask-coach/settings   edit all Coach settings (behavior,
 *                                    limits, content, model & data).
 *
 * Settings are stored in `app_settings` under `ai.ask_coach.*` keys.
 * Blank values always restore the platform default.
 */
class AskCoachAdminController extends Controller
{
    public function index(Request $request)
    {
        $days = max(1, min(180, (int) $request->query('days', 30)));
        $since = now()->subDays($days);

        $threads = AskCoachThread::query()
            ->where('created_at', '>=', $since)
            ->count();
        $messages = AskCoachMessage::query()
            ->where('created_at', '>=', $since)
            ->count();
        $assistantMessages = AskCoachMessage::query()
            ->where('role', 'assistant')
            ->where('created_at', '>=', $since)
            ->count();
        $upCount = AskCoachMessage::query()
            ->where('created_at', '>=', $since)
            ->where('feedback', 'up')->count();
        $downCount = AskCoachMessage::query()
            ->where('created_at', '>=', $since)
            ->where('feedback', 'down')->count();

        $creditsSpent = (int) WalletTransaction::query()
            ->where('meta->ai', true)
            ->where('meta->feature', 'like', 'ask_coach%')
            ->where('type', 'spend')
            ->where('created_at', '>=', $since)
            ->sum('delta_coins');
        $creditsSpent = abs($creditsSpent);

        $recentDowns = AskCoachMessage::query()
            ->where('feedback', 'down')
            ->where('created_at', '>=', $since)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'thread_id', 'content', 'feedback_note', 'created_at']);

        $plans = Plan::query()->orderBy('sort_order')->get(['id', 'name', 'slug']);

        $enabledModels = array_filter(
            AiEngineSettings::models(),
            fn($m) => ($m['kind'] ?? '') === 'chat' && ($m['enabled'] ?? false)
        );

        return view('admin.ask-coach.index', [
            'days'              => $days,
            'threads'           => $threads,
            'messages'          => $messages,
            'assistantMessages' => $assistantMessages,
            'creditsSpent'      => $creditsSpent,
            'upCount'           => $upCount,
            'downCount'         => $downCount,
            'recentDowns'       => $recentDowns,
            'allPlans'          => $plans,
            // existing settings
            'systemPrompt'      => AiEngineSettings::askCoachSystemPrompt(),
            'enabledPlans'      => AiEngineSettings::askCoachEnabledPlans(),
            // behavior
            'coachTone'          => AiEngineSettings::askCoachTone(),
            'responseLength'     => AiEngineSettings::askCoachResponseLength(),
            'replyLanguage'      => AiEngineSettings::askCoachReplyLanguage(),
            'temperature'        => AiEngineSettings::askCoachTemperature(),
            // limits
            'planCaps'           => AiEngineSettings::askCoachPlanCaps(),
            'cooldownSeconds'    => AiEngineSettings::askCoachCooldownSeconds(),
            'creditMultiplier'   => AiEngineSettings::askCoachCreditMultiplier(),
            // content
            'bannedTopics'       => implode("\n", AiEngineSettings::askCoachBannedTopics()),
            'greeting'           => AiEngineSettings::askCoachGreeting(),
            'fallbackMessage'    => AiEngineSettings::askCoachFallbackMessage(),
            'escalationNote'     => AiEngineSettings::askCoachEscalationNote(),
            // model & data
            'coachModel'         => AiEngineSettings::featureModel('ask_coach'),
            'maxTokens'          => AiEngineSettings::askCoachMaxTokens(),
            'snapshotCategories' => AiEngineSettings::askCoachSnapshotCategories(),
            'allCategories'      => AiEngineSettings::ASK_COACH_SNAPSHOT_CATEGORIES,
            'enabledModels'      => array_values($enabledModels),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'system_prompt'       => 'nullable|string|max:8000',
            'plans'               => 'nullable|array',
            'plans.*'             => 'string|max:64',
            // behavior
            'tone'                => 'nullable|string|in:friendly,professional,concise,playful',
            'response_length'     => 'nullable|string|in:short,medium,long',
            'reply_language'      => 'nullable|string|max:16',
            'temperature'         => 'nullable|numeric|min:0|max:1.5',
            // limits
            'plan_caps'           => 'nullable|array',
            'plan_caps.*.period'  => 'nullable|string|in:daily,monthly',
            'plan_caps.*.cap'     => 'nullable|integer|min:0',
            'cooldown_seconds'    => 'nullable|integer|min:0|max:86400',
            'credit_multiplier'   => 'nullable|numeric|min:1|max:10',
            // content
            'banned_topics'       => 'nullable|string|max:4000',
            'greeting'            => 'nullable|string|max:1000',
            'fallback_message'    => 'nullable|string|max:1000',
            'escalation_note'     => 'nullable|string|max:500',
            // model & data
            'coach_model'         => 'nullable|string|max:100',
            'max_tokens'          => 'nullable|integer|min:100|max:4000',
            'snapshot_categories' => 'nullable|array',
            'snapshot_categories.*' => 'string|in:links,analytics,audience,billing,events',
        ]);

        // existing settings
        AiEngineSettings::setAskCoachSystemPrompt($data['system_prompt'] ?? null);
        AiEngineSettings::setAskCoachEnabledPlans($data['plans'] ?? []);

        // behavior
        AiEngineSettings::setAskCoachTone($data['tone'] ?? null);
        AiEngineSettings::setAskCoachResponseLength($data['response_length'] ?? null);
        AiEngineSettings::setAskCoachReplyLanguage(!empty($data['reply_language']) ? $data['reply_language'] : null);
        AiEngineSettings::setAskCoachTemperature(isset($data['temperature']) ? (float) $data['temperature'] : null);

        // limits
        $caps = [];
        foreach ((array) ($data['plan_caps'] ?? []) as $slug => $cfg) {
            $cap = (int) ($cfg['cap'] ?? 0);
            if ($cap > 0 && is_string($slug) && $slug !== '') {
                $caps[$slug] = [
                    'period' => in_array($cfg['period'] ?? '', ['daily', 'monthly'], true) ? $cfg['period'] : 'daily',
                    'cap'    => $cap,
                ];
            }
        }
        AiEngineSettings::setAskCoachPlanCaps($caps);
        AiEngineSettings::setAskCoachCooldownSeconds((int) ($data['cooldown_seconds'] ?? 0));
        AiEngineSettings::setAskCoachCreditMultiplier(isset($data['credit_multiplier']) ? (float) $data['credit_multiplier'] : null);

        // content
        $topics = array_filter(
            array_map('trim', explode("\n", (string) ($data['banned_topics'] ?? ''))),
            fn($s) => $s !== ''
        );
        AiEngineSettings::setAskCoachBannedTopics(array_values($topics));
        AiEngineSettings::setAskCoachGreeting($data['greeting'] ?? null);
        AiEngineSettings::setAskCoachFallbackMessage($data['fallback_message'] ?? null);
        AiEngineSettings::setAskCoachEscalationNote($data['escalation_note'] ?? null);

        // model & data
        $model = trim((string) ($data['coach_model'] ?? ''));
        if ($model !== '') {
            $admin = auth()->guard('admin')->user();
            AiEngineSettings::setFeatureModels(
                ['ask_coach' => $model],
                $admin?->id,
                $admin?->name ?? 'Admin'
            );
        }
        AiEngineSettings::setAskCoachMaxTokens(isset($data['max_tokens']) ? (int) $data['max_tokens'] : null);
        AiEngineSettings::setAskCoachSnapshotCategories($data['snapshot_categories'] ?? []);

        return redirect()->route('admin.ask-coach.index')->with('success', 'Ask Coach settings saved.');
    }
}
