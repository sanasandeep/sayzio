<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CreatorPayouts\PayoutProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Adult content (18+)" toggle + consent dialog (Task #1208).
 *
 * Enabling 18+ requires the creator to confirm:
 *   - they are at least 18 years old, and have legal authority to
 *     publish adult content in their jurisdiction
 *   - the content they will publish does NOT include illegal material
 *     or minors
 *   - they understand monetisation will be locked to an adult-friendly
 *     processor (CCBill or Segpay)
 *
 * On enable we:
 *   - stamp adult_content_enabled / adult_content_enabled_at
 *   - re-stamp age_verified_at (most recent affirmation)
 *   - if the current default payout connection is SFW, clear the
 *     default and ask the creator to connect an adult-friendly one.
 */
class AdultContentController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $hasAdultProvider = $user->paymentConnections()
            ->where('adult_friendly', true)->exists();
        return view('user.adult-content.show', [
            'user'             => $user,
            'hasAdultProvider' => $hasAdultProvider,
            'adultProviders'   => array_filter(
                PayoutProviderRegistry::PROVIDERS,
                fn ($p) => $p['adult_friendly']
            ),
            'isEnabled'        => $user->isAdultProfile(),
            'isSuspended'      => !empty($user->adult_flag_suspended_at),
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'enable'             => 'required|in:0,1',
            'confirm_age'        => 'sometimes|in:0,1',
            'confirm_legal'      => 'sometimes|in:0,1',
            'confirm_processor'  => 'sometimes|in:0,1',
        ]);

        if ($data['enable'] === '0') {
            // Disable. Keep adult_content_enabled_at as the historical
            // first opt-in date — only the live flag flips off.
            $user->adult_content_enabled = false;
            $user->save();
            // If the current default was an adult provider, demote it
            // when the SFW lock no longer applies.
            return redirect()->route('user.adult-content.show')
                ->with('success', 'Adult content disabled. Your profile is no longer tagged 18+.');
        }

        // Enable path requires the three affirmations.
        $missing = collect(['confirm_age', 'confirm_legal', 'confirm_processor'])
            ->filter(fn ($k) => ($data[$k] ?? '0') !== '1')
            ->all();
        if (!empty($missing)) {
            return redirect()->route('user.adult-content.show')
                ->with('error', 'You must confirm all three statements to enable adult content.');
        }

        if (!empty($user->adult_flag_suspended_at)) {
            return redirect()->route('user.adult-content.show')
                ->with('error', 'A moderator has suspended this profile\'s adult flag. Contact support.');
        }

        $user->adult_content_enabled        = true;
        $user->adult_content_enabled_at     = $user->adult_content_enabled_at ?: now();
        $user->age_verified_at              = now();
        $user->save();

        // If the current default payout connection is a SFW processor,
        // clear the default — adult profiles must route through an
        // adult-friendly one. We don't auto-pick to keep the creator
        // explicitly aware of the change.
        $default = $user->defaultPaymentConnection();
        if ($default && !$default->adult_friendly) {
            $default->is_default = false;
            $default->save();
            return redirect()->route('user.payouts.show')
                ->with('success', 'Adult content enabled. Connect an adult-friendly processor (CCBill or Segpay) to receive payouts.');
        }

        return redirect()->route('user.adult-content.show')
            ->with('success', 'Adult content enabled. Your profile is now tagged 18+.');
    }
}
