<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Referral;
use App\Modules\User\Models\ReferralReward;
use App\Modules\User\Models\User;
use App\Modules\User\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReferralController extends Controller
{
    public function __construct(protected ReferralService $service) {}

    public function index()
    {
        $totals = [
            'clicks' => Referral::count(),
            'signups' => Referral::whereNotNull('referred_user_id')->count(),
            'conversions' => Referral::where('status', 'rewarded')->count(),
            'days_granted' => (int) ReferralReward::sum('days_granted'),
        ];

        $topReferrers = User::query()
            ->select('users.id', 'users.name', 'users.email', 'users.referral_code')
            ->selectSub(function ($q) {
                $q->from('referrals')->selectRaw('count(*)')
                  ->whereColumn('referrals.referrer_id', 'users.id')
                  ->whereNotNull('referred_user_id');
            }, 'signups')
            ->selectSub(function ($q) {
                $q->from('referrals')->selectRaw('count(*)')
                  ->whereColumn('referrals.referrer_id', 'users.id')
                  ->where('status', 'rewarded');
            }, 'conversions')
            ->selectSub(function ($q) {
                $q->from('referral_rewards')->selectRaw('coalesce(sum(days_granted),0)')
                  ->whereColumn('referral_rewards.user_id', 'users.id');
            }, 'days_earned')
            ->whereExists(function ($q) {
                $q->from('referrals')->whereColumn('referrals.referrer_id', 'users.id');
            })
            ->orderByDesc('conversions')
            ->orderByDesc('signups')
            ->limit(20)
            ->get();

        $recentConversions = Referral::with(['referrer:id,name,email', 'referredUser:id,name,email'])
            ->where('status', 'rewarded')
            ->orderByDesc('converted_at')
            ->limit(20)
            ->get();

        $enabled = $this->service->isEnabled();

        return view('admin.referrals.index', compact('totals', 'topReferrers', 'recentConversions', 'enabled'));
    }

    public function toggle(Request $request)
    {
        $request->validate(['enabled' => 'required|boolean']);
        $this->service->setEnabled((bool) $request->boolean('enabled'));
        return back()->with('success', 'Referral program ' . ($request->boolean('enabled') ? 'enabled' : 'disabled') . '.');
    }
}
