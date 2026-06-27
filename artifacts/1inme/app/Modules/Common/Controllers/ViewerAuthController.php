<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\OtpService;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * AJAX-style auth used by the modal "Sign in with Sayzio" entrypoint
 * in the public biolink branding strip. Wraps OtpService and stores
 * the verified visitor in a separate ViewerSession (NOT the dashboard
 * auth guard) so creators and viewers stay isolated.
 */
class ViewerAuthController extends Controller
{
    public function sendOtp(Request $request)
    {
        $data = $request->validate([
            'identifier' => 'required|string|max:191',
            'type'       => 'required|in:email,mobile',
        ]);

        $user = $data['type'] === 'email'
            ? User::where('email', $data['identifier'])->first()
            : User::where('mobile', $data['identifier'])->first();

        if (!$user) {
            $freePlan = Plan::defaultPlan();
            $user = User::create([
                'name'     => $data['type'] === 'email' ? Str::before($data['identifier'], '@') : 'Visitor',
                'email'    => $data['type'] === 'email'  ? $data['identifier'] : ($data['identifier'] . '@viewer.1inme.local'),
                'mobile'   => $data['type'] === 'mobile' ? $data['identifier'] : null,
                'password' => Hash::make(Str::random(48)),
                'plan_id'  => $freePlan?->id,
                'status'   => 'active',
                'discoverable' => false,
            ]);
        }

        $otp = new OtpService();
        $code = $otp->generate($data['identifier'], $data['type'], 'login', 'web');
        try {
            if ($data['type'] === 'email') $otp->sendEmail($data['identifier'], $code);
            else                            $otp->sendSms($data['identifier'], $code);
        } catch (\Throwable $e) {
            \Log::warning('viewer OTP send failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Could not send your code. Please try again or use the other channel.',
            ], 502);
        }

        return response()->json([
            'success'     => true,
            'demo_reveal' => \App\Modules\Common\Support\AuthMethods::demoRevealMessage($code),
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'identifier' => 'required|string',
            'type'       => 'required|in:email,mobile',
            'code'       => 'required|string|size:6',
        ]);

        $otp = new OtpService();
        if (!$otp->verify($data['identifier'], $data['code'], $data['type'], 'login', 'web')) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired code.'], 422);
        }

        $user = $data['type'] === 'email'
            ? User::where('email', $data['identifier'])->first()
            : User::where('mobile', $data['identifier'])->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        // Use viewer session, not the dashboard auth guard.
        ViewerSession::login($user);
        $user->update(['last_login_at' => now()]);

        return response()->json([
            'success' => true,
            'user' => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'avatar'    => $user->avatar,
                'initials'  => $user->getInitials(),
                'handle'    => $user->publicHandle(),
            ],
        ]);
    }

    public function me(Request $request)
    {
        $u = ViewerSession::user();
        if (!$u) return response()->json(['authenticated' => false]);
        return response()->json([
            'authenticated' => true,
            'user' => [
                'id'       => $u->id,
                'name'     => $u->name,
                'email'    => $u->email,
                'avatar'   => $u->avatar,
                'initials' => $u->getInitials(),
                'handle'   => $u->publicHandle(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        ViewerSession::logout();
        return response()->json(['success' => true]);
    }

    public function toggleFollow(Request $request, int $creatorId)
    {
        $me = ViewerSession::user();
        if (!$me) return response()->json(['success' => false, 'message' => 'Not signed in.'], 401);

        if ((int) $me->id === (int) $creatorId) {
            return response()->json(['success' => false, 'message' => "You can't follow yourself."], 422);
        }
        $creator = User::find($creatorId);
        if (!$creator) return response()->json(['success' => false], 404);

        $existing = Follow::where('follower_id', $me->id)->where('creator_id', $creatorId)->first();
        if ($existing) {
            $existing->delete();
            $creator->decrement('followers_count');
            return response()->json(['success' => true, 'following' => false, 'followers_count' => max(0, $creator->followers_count)]);
        }

        // Respect creator's "allow followers" setting.
        if (!($creator->allow_followers ?? true)) {
            return response()->json(['success' => false, 'message' => 'This creator is not accepting new followers.'], 403);
        }

        Follow::create(['follower_id' => $me->id, 'creator_id' => $creatorId, 'created_at' => now()]);
        $creator->increment('followers_count');

        // Paid DMs (Task #1210): fire any welcome-message rules the
        // creator has configured for new followers.
        try {
            app(\App\Services\Dm\DmDispatcher::class)->triggerNewFollower($creator, $me);
        } catch (\Throwable $e) { /* swallow — welcome rules must never block follow */ }

        // Always store an in-app notification; email only if opted in.
        UserNotification::create([
            'user_id' => $creator->id,
            'type'    => 'new_follower',
            'data'    => ['follower_id' => $me->id, 'follower_name' => $me->name, 'follower_avatar' => $me->avatar],
            'created_at' => now(),
        ]);
        if ($creator->notify_new_follower) {
            try {
                \App\Modules\Common\Services\Emailer::send('follow.new_follower', $creator->email, [
                    'follower_name' => $me->name,
                ], ['user' => $creator->id, 'related' => $me]);
            } catch (\Throwable $e) {}
        }

        return response()->json(['success' => true, 'following' => true, 'followers_count' => $creator->followers_count]);
    }
}
