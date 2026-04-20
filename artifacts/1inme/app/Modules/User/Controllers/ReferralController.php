<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\GeoIpService;
use App\Modules\User\Models\Referral;
use App\Modules\User\Models\ReferralReward;
use App\Modules\User\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class ReferralController extends Controller
{
    public function __construct(protected ReferralService $service) {}

    /** Public: /r/{code} — record click, set cookie, redirect to signup. */
    public function track(Request $request, string $code)
    {
        if (!$this->service->isEnabled()) {
            return redirect()->route('user.register');
        }

        $referrer = $this->service->findReferrerByCode($code);
        $code = strtolower(trim($code));

        if ($referrer) {
            // Click row: referred_user_id null until signup.
            Referral::create([
                'referrer_id' => $referrer->id,
                'referred_user_id' => null,
                'code_used' => $code,
                'status' => 'clicked',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent() ? substr($request->userAgent(), 0, 500) : null,
            ]);

            // Signed cookie so we can attribute on signup.
            Cookie::queue(
                Cookie::make(ReferralService::COOKIE_NAME, $code, ReferralService::COOKIE_MINUTES, '/', null, false, true)
            );
        }

        return redirect()->route('user.register', ['ref' => $code]);
    }

    /** Public availability check used by signup AND user-dashboard rename. */
    public function check(Request $request)
    {
        $code = (string) $request->query('code', '');
        $ignore = Auth::check() ? Auth::id() : null;
        $err = $this->service->validateCode($code, $ignore);
        return response()->json([
            'ok' => $err === null,
            'message' => $err ?? 'Available',
        ]);
    }

    /** Authenticated: dashboard for the user's own referral program. */
    public function index()
    {
        $user = Auth::user();
        if (!$user->referral_code) {
            $user->update(['referral_code' => $this->service->generateUniqueCode()]);
            $user->refresh();
        }

        $referrals = Referral::where('referrer_id', $user->id)
            ->whereNotNull('referred_user_id')
            ->with('referredUser:id,name,email')
            ->orderByDesc('created_at')
            ->paginate(20);

        $stats = [
            'clicks' => Referral::where('referrer_id', $user->id)->count(),
            'signups' => Referral::where('referrer_id', $user->id)->whereNotNull('referred_user_id')->count(),
            'conversions' => Referral::where('referrer_id', $user->id)->where('status', 'rewarded')->count(),
            'days_earned' => (int) ReferralReward::where('user_id', $user->id)->sum('days_granted'),
        ];

        $referralUrl = $this->service->buildReferralUrl($user->referral_code);
        $enabled = $this->service->isEnabled();

        return view('user.referrals.index', compact('user', 'referrals', 'stats', 'referralUrl', 'enabled'));
    }

    /** Update the user's referral code. */
    public function updateCode(Request $request)
    {
        $user = Auth::user();
        $request->validate(['code' => 'required|string|max:32']);
        $err = $this->service->validateCode($request->input('code'), $user->id);
        if ($err) {
            return back()->withErrors(['code' => $err])->withInput();
        }
        $user->update(['referral_code' => strtolower(trim($request->input('code')))]);
        return back()->with('success', 'Your referral code is now ' . $user->referral_code . '.');
    }
}
