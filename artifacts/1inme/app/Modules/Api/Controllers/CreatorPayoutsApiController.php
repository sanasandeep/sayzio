<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\CreatorPaymentConnection;
use App\Services\CreatorPayouts\PayoutProviderRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile / 3rd-party API surface for the Creator Payouts dashboard
 * (Task #1208). Mirrors the web routes:
 *
 *   GET    /api/v1/payouts                    list providers + connections
 *   POST   /api/v1/payouts/{provider}/connect mark as connected (preview / hosted bounce)
 *   POST   /api/v1/payouts/{conn}/sync        re-fetch status
 *   POST   /api/v1/payouts/{conn}/default     pick as default
 *   DELETE /api/v1/payouts/{conn}             remove
 *
 *   GET    /api/v1/adult-content              show toggle state
 *   POST   /api/v1/adult-content              enable / disable
 *
 * Mobile flows mostly POST `connect` to receive the hosted-onboarding
 * URL, then open it in the system browser. Webhook + return parsing
 * remains a server-side responsibility.
 */
class CreatorPayoutsApiController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $user = $request->user();
        $includeAdult = $user->isAdultProfile();
        $providers = PayoutProviderRegistry::all($includeAdult);

        $connections = $user->paymentConnections()->get()
            ->map(fn ($c) => $this->serializeConnection($c))->values();

        return $this->ok([
            'providers'         => array_values(array_map(function ($p) {
                return [
                    'slug'           => $p['slug'],
                    'name'           => $p['name'],
                    'short'          => $p['short'],
                    'countries'      => $p['countries'],
                    'payout_speed'   => $p['payout_speed'],
                    'fees'           => $p['fees'],
                    'adult_friendly' => (bool) $p['adult_friendly'],
                    'docs_url'       => $p['docs_url'] ?? null,
                ];
            }, $providers)),
            'connections'       => $connections,
            'adult_enabled'     => $user->isAdultProfile(),
            'adult_friendly_slugs' => PayoutProviderRegistry::adultFriendlySlugs(),
        ]);
    }

    /**
     * Returns the hosted-onboarding URL for the requested provider.
     * The mobile app opens it in an in-app browser; on return we sync
     * via the dedicated `sync` endpoint.
     */
    public function connect(Request $request, string $provider)
    {
        $user = $request->user();
        $descriptor = PayoutProviderRegistry::get($provider);
        if (!$descriptor) return $this->fail('Unknown payout provider.', 404, 'unknown_provider');
        if ($descriptor['adult_friendly'] && !$user->isAdultProfile()) {
            return $this->fail('Adult-friendly processors require enabling 18+ content first.', 403, 'requires_adult');
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

        if (!$user->paymentConnections()->where('is_default', true)->exists()) {
            PayoutProviderRegistry::setDefault($user, $conn);
        }

        $adapter   = PayoutProviderRegistry::adapter($provider);
        $returnUrl = url('/user/payouts/return/' . $provider);
        $url       = $adapter->startOnboarding($user, $returnUrl);
        return $this->ok([
            'onboarding_url' => $url,
            'connection'     => $this->serializeConnection($conn->fresh()),
        ]);
    }

    public function sync(Request $request, int $connection)
    {
        $conn = $this->ownConnection($request, $connection);
        if (!$conn) return $this->fail('Connection not found.', 404);
        PayoutProviderRegistry::adapter($conn->provider)->syncStatus($conn);
        return $this->ok(['connection' => $this->serializeConnection($conn->fresh())]);
    }

    public function setDefault(Request $request, int $connection)
    {
        $conn = $this->ownConnection($request, $connection);
        if (!$conn) return $this->fail('Connection not found.', 404);
        $user = $request->user();
        if ($user->isAdultProfile() && !$conn->adult_friendly) {
            return $this->fail('Adult profiles must use an adult-friendly default (CCBill or Segpay).', 422, 'requires_adult_default');
        }
        PayoutProviderRegistry::setDefault($user, $conn);
        return $this->ok(['connection' => $this->serializeConnection($conn->fresh())]);
    }

    public function destroy(Request $request, int $connection)
    {
        $conn = $this->ownConnection($request, $connection);
        if (!$conn) return $this->fail('Connection not found.', 404);
        $user = $request->user();
        $wasDefault = (bool) $conn->is_default;
        $conn->delete();
        if ($wasDefault) {
            $next = $user->paymentConnections()->orderBy('id')->first();
            if ($next) PayoutProviderRegistry::setDefault($user, $next);
        }
        return $this->ok(['removed' => true]);
    }

    public function adultShow(Request $request)
    {
        $user = $request->user();
        return $this->ok([
            'enabled'                => $user->isAdultProfile(),
            'enabled_at'             => optional($user->adult_content_enabled_at)->toIso8601String(),
            'age_verified_at'        => optional($user->age_verified_at)->toIso8601String(),
            'flag_suspended'         => !empty($user->adult_flag_suspended_at),
            'flag_suspended_reason'  => $user->adult_flag_suspended_reason,
            'has_adult_provider'     => $user->paymentConnections()->where('adult_friendly', true)->exists(),
        ]);
    }

    public function adultUpdate(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'enable'             => ['required', 'boolean'],
            'confirm_age'        => ['sometimes', 'boolean'],
            'confirm_legal'      => ['sometimes', 'boolean'],
            'confirm_processor'  => ['sometimes', 'boolean'],
        ]);

        if (!$data['enable']) {
            $user->adult_content_enabled = false;
            $user->save();
            return $this->ok(['enabled' => false]);
        }

        if (!empty($user->adult_flag_suspended_at)) {
            return $this->fail('Adult flag is currently suspended by moderation. Contact support.', 422, 'flag_suspended');
        }

        $missing = collect(['confirm_age', 'confirm_legal', 'confirm_processor'])
            ->filter(fn ($k) => !($data[$k] ?? false))->values();
        if ($missing->isNotEmpty()) {
            return $this->fail('You must confirm all three statements to enable adult content.', 422, 'consent_required', $missing);
        }

        $user->adult_content_enabled    = true;
        $user->adult_content_enabled_at = $user->adult_content_enabled_at ?: now();
        $user->age_verified_at          = now();
        $user->save();

        // Demote SFW default if necessary, just like the web flow.
        $default = $user->defaultPaymentConnection();
        if ($default && !$default->adult_friendly) {
            $default->is_default = false;
            $default->save();
        }
        return $this->ok([
            'enabled'    => true,
            'enabled_at' => $user->adult_content_enabled_at->toIso8601String(),
            'needs_adult_provider' => !$user->paymentConnections()->where('adult_friendly', true)->exists(),
        ]);
    }

    private function ownConnection(Request $request, int $id): ?CreatorPaymentConnection
    {
        return CreatorPaymentConnection::where('id', $id)
            ->where('user_id', $request->user()->id)->first();
    }

    private function serializeConnection(CreatorPaymentConnection $c): array
    {
        return [
            'id'              => $c->id,
            'provider'        => $c->provider,
            'status'          => $c->status,
            'status_label'    => $c->statusLabel(),
            'status_reason'   => $c->status_reason,
            'payouts_enabled' => (bool) $c->payouts_enabled,
            'charges_enabled' => (bool) $c->charges_enabled,
            'is_default'      => (bool) $c->is_default,
            'adult_friendly'  => (bool) $c->adult_friendly,
            'last_sync_at'    => optional($c->last_sync_at)->toIso8601String(),
            'account_id'      => $c->account_id,
            'country'         => $c->country,
        ];
    }
}
