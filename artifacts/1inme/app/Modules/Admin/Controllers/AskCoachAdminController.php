<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\AiCreditTransaction;
use App\Modules\User\Models\AskCoachMessage;
use App\Modules\User\Models\AskCoachThread;
use App\Services\AI\AiEngineSettings;
use Illuminate\Http\Request;

/**
 * Admin dashboard for the Ask Coach chatbot:
 *
 *   GET  /admin/ask-coach            usage + quality report.
 *   PUT  /admin/ask-coach/settings   edit central system prompt + per-plan toggle.
 *
 * The numbers are deliberately simple — total spend, top thumbs-down
 * messages — so the support team can spot model regressions and
 * abusive prompts without spinning up a full BI tool.
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

        $creditsSpent = (int) AiCreditTransaction::query()
            ->where('feature', 'like', 'ask_coach%')
            ->where('kind', 'spend')
            ->where('created_at', '>=', $since)
            ->sum('credits');
        $creditsSpent = abs($creditsSpent);

        $recentDowns = AskCoachMessage::query()
            ->where('feedback', 'down')
            ->where('created_at', '>=', $since)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'thread_id', 'content', 'feedback_note', 'created_at']);

        $plans = Plan::query()->orderBy('sort_order')->get(['id', 'name', 'slug']);

        return view('admin.ask-coach.index', [
            'days'              => $days,
            'threads'           => $threads,
            'messages'          => $messages,
            'assistantMessages' => $assistantMessages,
            'creditsSpent'      => $creditsSpent,
            'upCount'           => $upCount,
            'downCount'         => $downCount,
            'recentDowns'       => $recentDowns,
            'systemPrompt'      => AiEngineSettings::askCoachSystemPrompt(),
            'enabledPlans'      => AiEngineSettings::askCoachEnabledPlans(),
            'allPlans'          => $plans,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'system_prompt' => 'nullable|string|max:8000',
            'plans'         => 'nullable|array',
            'plans.*'       => 'string|max:64',
        ]);
        AiEngineSettings::setAskCoachSystemPrompt($data['system_prompt'] ?? null);
        AiEngineSettings::setAskCoachEnabledPlans($data['plans'] ?? []);
        return redirect()->route('admin.ask-coach.index')->with('success', 'Ask Coach settings saved.');
    }
}
