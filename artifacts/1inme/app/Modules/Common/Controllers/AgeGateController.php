<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Visitor 18+ age gate (Task #1208).
 *
 * Posted from the interstitial shown on /@handle when the creator has
 * the adult flag enabled and the visitor hasn't already confirmed 18+.
 * Stores a per-device cookie for 30 days so the visitor isn't asked
 * again on subsequent visits.
 *
 * The corresponding helper class `AgeGate` (below) is the read side
 * — it checks whether a request has already passed the gate.
 */
class AgeGateController extends Controller
{
    public function confirm(Request $request)
    {
        $request->validate([
            'handle'   => 'required|string|max:60',
            'confirm'  => 'required|in:1',
        ]);

        $cookie = Cookie::make(
            AgeGate::COOKIE,
            '1',
            60 * 24 * AgeGate::DAYS,
            '/',
            null,
            $request->isSecure(),
            true,    // httpOnly
            false,
            'lax'
        );

        $back = url('/@' . ltrim($request->input('handle'), '@'));
        return redirect($back)->withCookie($cookie);
    }

    public function leave(Request $request)
    {
        // Sent away if they answer "I'm under 18". We bounce them off
        // the creator page entirely and back to the directory with a
        // generic message — no PII / age recorded for unauthenticated
        // visitors beyond the cookie they didn't get.
        return redirect()->route('creators.index')
            ->with('error', 'You must be 18 or older to view this profile.');
    }
}
