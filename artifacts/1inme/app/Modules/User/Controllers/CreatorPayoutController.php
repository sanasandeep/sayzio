<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CreatorPaymentConnection;
use App\Services\CreatorPayouts\PayoutProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Earnings & Payouts" dashboard area (Task #1208).
 *
 * Lets a creator browse payout providers, kick off the provider's
 * hosted onboarding, and pick a single default that future paid
 * features will route through. The platform takes 0% of creator
 * earnings — fees are entirely the provider's, surfaced from the
 * registry so the trade-offs are clear up front.
 */
class CreatorPayoutController extends Controller
{
    public function show()
    {
        $user = Auth::user();
        $includeAdult = $user->isAdultProfile();
        $providers = PayoutProviderRegistry::all($includeAdult);

        $connections = $user->paymentConnections()->get()->keyBy('provider');
        $defaultConn = $user->defaultPaymentConnection();

        return view('user.payouts.index', [
            'user'        => $user,
            'providers'   => $providers,
            'connections' => $connections,
            'default'     => $defaultConn,
            'adultEnabled'=> $user->isAdultProfile(),
            'adultFriendlySlugs' => PayoutProviderRegistry::adultFriendlySlugs(),
        ]);
    }

    /**
     * Start (or re-resume) the hosted onboarding for the requested
     * provider. Creates a stub creator_payment_connections row keyed on
     * (user, provider) so we have something to write status against
     * when the creator returns.
     */
    public function connect(Request $request, string $provider)
    {
        $user = Auth::user();
        $descriptor = PayoutProviderRegistry::get($provider);
        abort_if(!$descriptor, 404, 'Unknown payout provider.');

        // Adult-only providers stay invisible to SFW creators. We still
        // 403 here as a hard backstop in case the link is dug out of
        // the dashboard HTML by a stale tab.
        if ($descriptor['adult_friendly'] && !$user->isAdultProfile()) {
            abort(403, 'Adult-friendly processors require enabling 18+ content first.');
        }

        $conn = CreatorPaymentConnection::firstOrNew([
            'user_id'  => $user->id,
            'provider' => $provider,
        ]);
        $conn->fill([
            'adult_friendly' => (bool) $descriptor['adult_friendly'],
            'status'         => $conn->status ?: 'pending',
            'status_reason'  => $conn->status_reason ?: 'Onboarding started — awaiting provider confirmation.',
        ]);
        $conn->save();

        // Make this the default if no other default is set.
        if (!$user->paymentConnections()->where('is_default', true)->exists()) {
            PayoutProviderRegistry::setDefault($user, $conn);
        }

        $adapter   = PayoutProviderRegistry::adapter($provider);
        $returnUrl = route('user.payouts.return', ['provider' => $provider]);
        $url       = $adapter->startOnboarding($user, $returnUrl);

        return redirect()->away($url);
    }

    /**
     * Hosted-onboarding return URL. Each provider redirects here after
     * the creator completes (or cancels) their flow. We just refresh
     * the local status row and bounce back to the dashboard.
     */
    public function returnFrom(Request $request, string $provider)
    {
        $user = Auth::user();
        $conn = $user->paymentConnections()->where('provider', $provider)->first();
        abort_if(!$conn, 404);

        PayoutProviderRegistry::adapter($provider)->handleReturn($conn, $request);
        return redirect()->route('user.payouts.show')
            ->with('success', 'Returned from ' . PayoutProviderRegistry::get($provider)['name'] . '. We\'ll keep status in sync.');
    }

    /**
     * Re-fetch this connection's status from the provider. UI button
     * "Refresh status" inside each connection card.
     */
    public function sync(Request $request, CreatorPaymentConnection $connection)
    {
        $this->authorizeOwnership($connection);
        PayoutProviderRegistry::adapter($connection->provider)->syncStatus($connection);
        return back()->with('success', 'Status refreshed.');
    }

    /**
     * Pick this connection as the default. When the creator has 18+
     * enabled, only adult-friendly providers may be the default.
     */
    public function setDefault(Request $request, CreatorPaymentConnection $connection)
    {
        $this->authorizeOwnership($connection);
        $user = Auth::user();
        if ($user->isAdultProfile() && !$connection->adult_friendly) {
            return back()->with('error', 'Adult-content profiles must use an adult-friendly default processor (CCBill or Segpay).');
        }
        PayoutProviderRegistry::setDefault($user, $connection);
        return back()->with('success', PayoutProviderRegistry::get($connection->provider)['name'] . ' is now your default payout method.');
    }

    public function destroy(Request $request, CreatorPaymentConnection $connection)
    {
        $this->authorizeOwnership($connection);
        $user = Auth::user();
        $wasDefault = (bool) $connection->is_default;
        $connection->delete();

        // Promote another connection to default if we just removed it.
        if ($wasDefault) {
            $next = $user->paymentConnections()->orderBy('id')->first();
            if ($next) PayoutProviderRegistry::setDefault($user, $next);
        }
        return back()->with('success', 'Payout connection removed.');
    }

    /**
     * Hosted-onboarding preview page. Used while the provider's real
     * env keys are not yet set — explains the flow to the creator and
     * lets them simulate completing onboarding so they can keep moving
     * through the rest of the product. Production keys flip the live
     * adapter into a real redirect.
     */
    public function preview(Request $request)
    {
        $slug = (string) $request->query('provider');
        $descriptor = PayoutProviderRegistry::get($slug);
        abort_if(!$descriptor, 404);
        $returnUrl = (string) $request->query('r', route('user.payouts.show'));
        return view('user.payouts.preview', [
            'provider'  => $descriptor,
            'returnUrl' => urldecode($returnUrl),
        ]);
    }

    /**
     * Used by the preview page's "I finished onboarding" button to
     * simulate a successful return. Stamps the connection as active
     * with a clear, honest reason so QA / staging environments can
     * exercise the rest of the paid-features flow without leaking
     * fake provider credentials into the row.
     */
    public function previewComplete(Request $request, string $provider)
    {
        $user = Auth::user();
        $descriptor = PayoutProviderRegistry::get($provider);
        abort_if(!$descriptor, 404);

        $conn = CreatorPaymentConnection::firstOrNew([
            'user_id' => $user->id, 'provider' => $provider,
        ]);
        $conn->fill([
            'account_id'      => $conn->account_id ?: 'preview_' . substr(bin2hex(random_bytes(6)), 0, 12),
            'country'         => $conn->country ?: ($user->country ?: 'US'),
            'status'          => 'active',
            'status_reason'   => 'Preview onboarding completed (no live provider keys).',
            'payouts_enabled' => true,
            'charges_enabled' => true,
            'adult_friendly'  => (bool) $descriptor['adult_friendly'],
            'last_sync_at'    => now(),
        ]);
        $conn->save();

        if (!$user->paymentConnections()->where('is_default', true)->exists()) {
            PayoutProviderRegistry::setDefault($user, $conn);
        }
        return redirect()->route('user.payouts.show')
            ->with('success', 'Marked ' . $descriptor['name'] . ' as connected (preview mode).');
    }

    private function authorizeOwnership(CreatorPaymentConnection $connection): void
    {
        abort_unless((int) $connection->user_id === (int) Auth::id(), 403);
    }
}
