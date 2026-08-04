<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\OtpService;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\EventQrConnect;
use App\Modules\User\Models\Follow;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Rsvp;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Event Connect QR (Task #6685) — the scan-to-connect flow behind the
 * `?src=connect_qr` tagged event page. One OTP round-trip completes
 * everything for a visitor: account created if new (the platform's OTP
 * flow already treats login and signup as the same), a "yes" RSVP tied
 * to the user, and an auto-follow of the host's creator profile. All
 * steps are idempotent on repeat scans; badge gating and the event's
 * capacity/waitlist rules are enforced exactly like the manual RSVP form.
 */
class EventConnectQrController extends Controller
{
    /**
     * Step 1 (signed-out visitors): send the OTP. Mirrors the viewer
     * sign-in modal's send endpoint but remembers — in the visitor's
     * session — whether the account was provisioned by THIS flow, so
     * the completion step can attribute "new signup" vs "existing user".
     */
    public function send(Request $request, string $alias)
    {
        $link = $this->resolveEvent($request, $alias);
        if ($link instanceof \Illuminate\Http\JsonResponse) return $link;

        $data = $request->validate([
            'identifier' => 'required|string|max:191',
            'type'       => 'required|in:email,mobile',
        ]);

        $user = $data['type'] === 'email'
            ? User::where('email', $data['identifier'])->first()
            : User::where('mobile', $data['identifier'])->first();

        $wasNew = false;
        if (!$user) {
            $wasNew = true;
            $user = User::create([
                'name'     => $data['type'] === 'email' ? Str::before($data['identifier'], '@') : 'Visitor',
                'email'    => $data['type'] === 'email'  ? $data['identifier'] : ($data['identifier'] . '@viewer.1inme.local'),
                'mobile'   => $data['type'] === 'mobile' ? $data['identifier'] : null,
                'password' => Hash::make(Str::random(48)),
                'plan_id'  => Plan::defaultPlan()?->id,
                'status'   => 'active',
                'discoverable' => false,
            ]);
        }

        // Remember new-vs-existing for the verify step. Keyed by identifier
        // so parallel attempts with different addresses can't cross-tag.
        $request->session()->put(
            'cqr_new_' . sha1($data['type'] . '|' . strtolower($data['identifier'])),
            $wasNew
        );

        $otp  = new OtpService();
        $code = $otp->generate($data['identifier'], $data['type'], 'login', 'web');
        try {
            if ($data['type'] === 'email') $otp->sendEmail($data['identifier'], $code);
            else                            $otp->sendWhatsApp($data['identifier'], $code);
        } catch (\Throwable $e) {
            \Log::warning('connect-qr OTP send failed: ' . $e->getMessage());
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

    /**
     * Step 2 (signed-out visitors): verify the OTP, sign the visitor into
     * the viewer session, then run the connect (RSVP + follow) in one go.
     */
    public function verify(Request $request, string $alias)
    {
        $link = $this->resolveEvent($request, $alias);
        if ($link instanceof \Illuminate\Http\JsonResponse) return $link;

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

        ViewerSession::login($user);
        \App\Jobs\RecordLoginEventJob::dispatch(
            $user->id,
            'viewer_otp',
            (string) ($request->ip() ?? ''),
            (string) ($request->userAgent() ?? ''),
            [],
            true,
            now(),
        );

        $wasNew = (bool) $request->session()->pull(
            'cqr_new_' . sha1($data['type'] . '|' . strtolower($data['identifier'])),
            false
        );

        return $this->connect($request, $link, $user, $wasNew);
    }

    /**
     * Already-signed-in visitors (viewer session or dashboard account):
     * a single confirm tap RSVPs and connects without re-entering anything.
     */
    public function confirm(Request $request, string $alias)
    {
        $link = $this->resolveEvent($request, $alias);
        if ($link instanceof \Illuminate\Http\JsonResponse) return $link;

        $user = ViewerSession::user() ?? $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Not signed in.'], 401);
        }

        return $this->connect($request, $link, $user, false);
    }

    // -------- internal --------

    /** Resolve the alias to an RSVP-able event link, or a JSON error. */
    private function resolveEvent(Request $request, string $alias): Link|\Illuminate\Http\JsonResponse
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        if (!$link || $link->type !== 'ics' || !$link->isAccessible()) {
            return response()->json(['success' => false, 'message' => 'Event not found.'], 404);
        }
        if (!RedirectController::isRsvpAvailable($link)) {
            return response()->json(['success' => false, 'message' => 'RSVPs are not open for this event.'], 422);
        }
        return $link;
    }

    /**
     * Delegate the one-shot connect (badge gate → RSVP → follow →
     * attribution) to the shared EventConnectService — the same rules the
     * mobile API connect endpoint runs (Task #6687).
     */
    private function connect(Request $request, Link $link, User $user, bool $wasNew)
    {
        [$payload, $status] = app(\App\Services\Events\EventConnectService::class)
            ->connect($request, $link, $user, $wasNew);

        return response()->json($payload, $status);
    }
}
