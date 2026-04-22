<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\AiCompanionConversation;
use App\Modules\Common\Models\AiCompanionMessage;
use App\Modules\User\Models\AiCompanion;
use App\Services\AI\CompanionSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin "AI Companions" page:
 *   - Aggregate stats (companions, conversations, monthly turns).
 *   - Platform caps (max companions per user, allow-list size,
 *     visitor rate limit, monthly hard cap).
 *   - Per-companion disable / re-enable for abuse.
 */
class AiCompanionAdminController extends Controller
{
    public function index(Request $request)
    {
        $monthStart = now()->startOfMonth();
        $totals = [
            'companions'    => (int) AiCompanion::count(),
            'disabled'      => (int) AiCompanion::where('is_disabled', true)->count(),
            'conversations' => (int) AiCompanionConversation::count(),
            'turns_month'   => (int) AiCompanionMessage::where('role', 'user')
                ->where('created_at', '>=', $monthStart)->count(),
            'credits_month' => (int) AiCompanionMessage::where('created_at', '>=', $monthStart)
                ->sum('credits_spent'),
        ];

        $topUsers = AiCompanion::query()
            ->select('user_id', DB::raw('COUNT(*) as companion_count'),
                DB::raw('MAX(last_used_at) as last_used'))
            ->groupBy('user_id')
            ->orderByDesc('companion_count')
            ->limit(20)
            ->get();

        $companions = AiCompanion::query()
            ->with(['user:id,name,email', 'persona:id,name'])
            ->withCount('conversations')
            ->latest('updated_at')
            ->paginate(25);

        return view('admin.ai-companions.index', [
            'totals'     => $totals,
            'topUsers'   => $topUsers,
            'companions' => $companions,
            'caps'       => CompanionSettings::caps(),
        ]);
    }

    public function updateCaps(Request $request)
    {
        $defaults = CompanionSettings::capsDefault();
        $rules = [];
        foreach ($defaults as $k => $_) {
            $rules["caps.{$k}"] = 'nullable|integer|min:0|max:1000000';
        }
        $data = $request->validate($rules);
        CompanionSettings::setCaps($data['caps'] ?? []);
        return back()->with('success', 'AI Companion caps updated.');
    }

    public function disable(Request $request, AiCompanion $companion)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);
        $companion->forceFill([
            'is_disabled'     => true,
            'disabled_reason' => $data['reason'],
        ])->save();
        return back()->with('success', 'Companion disabled.');
    }

    public function enable(Request $request, AiCompanion $companion)
    {
        $companion->forceFill([
            'is_disabled'     => false,
            'disabled_reason' => null,
        ])->save();
        return back()->with('success', 'Companion re-enabled.');
    }

    /**
     * Moderation queue: messages flagged for abuse review. Either
     * flagged automatically by upstream content filters (PersonaRuntime
     * marks toxic completions) or marked by the conversation owner /
     * admin via the per-message report action.
     */
    public function moderation(Request $request)
    {
        $tab = $request->string('tab', 'flagged')->toString();
        $q = AiCompanionMessage::query()
            ->with(['conversation:id,companion_id,visitor_token,visitor_email',
                    'conversation.companion:id,public_id,name,user_id'])
            ->latest('created_at');
        if ($tab === 'flagged') $q->where('is_flagged', true);
        $messages = $q->paginate(50)->withQueryString();

        $counts = [
            'flagged' => (int) AiCompanionMessage::where('is_flagged', true)->count(),
            'recent'  => (int) AiCompanionMessage::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        return view('admin.ai-companions.moderation', [
            'messages' => $messages,
            'counts'   => $counts,
            'tab'      => $tab,
        ]);
    }

    public function flagMessage(Request $request, AiCompanionMessage $message)
    {
        $message->forceFill(['is_flagged' => true])->save();
        return back()->with('success', 'Message flagged.');
    }

    public function unflagMessage(Request $request, AiCompanionMessage $message)
    {
        $message->forceFill(['is_flagged' => false])->save();
        return back()->with('success', 'Message cleared.');
    }
}
