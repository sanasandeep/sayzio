<?php

namespace App\Services\CreatorPayouts;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Lightweight per-provider adapter for the creator-payout flow.
 *
 * Each adapter speaks the same four-method contract:
 *
 *   - startOnboarding($user, $returnUrl): string
 *       Build the hosted-onboarding URL the creator is redirected to.
 *       Real adapters issue a server-side API call to mint a fresh
 *       account-link / referral / activation URL; if the provider's
 *       credentials aren't yet configured (env keys missing), the
 *       adapter returns a transparent placeholder URL so the UI flow
 *       remains testable without leaking faux credentials.
 *
 *   - handleReturn($connection, $request): void
 *       Called after the creator returns from the hosted flow. Kicks
 *       off a status sync. Real adapters parse signed redirect params.
 *
 *   - syncStatus($connection): void
 *       Re-fetch the provider's view of the connection (KYC, payout
 *       state) and write the canonical status_reason + payouts_enabled
 *       flags back onto the row.
 *
 *   - dashboardUrl($connection): ?string
 *       Where the creator clicks "Manage" → opens the provider's own
 *       portal in a new tab. Returns null if the provider doesn't
 *       expose one.
 *
 * This task connects the wiring end-to-end and leaves real provider
 * API calls behind feature flags / env-key checks. Subscriptions and
 * tipping (the next task) will fill in the per-provider charge logic.
 */
abstract class PayoutProviderAdapter
{
    public function __construct(protected array $provider) {}

    public function slug(): string { return $this->provider['slug']; }
    public function name(): string { return $this->provider['name']; }
    public function adultFriendly(): bool { return (bool) ($this->provider['adult_friendly'] ?? false); }
    public function descriptor(): array { return $this->provider; }

    abstract public function startOnboarding(User $user, string $returnUrl): string;

    /**
     * Default return-handler stamps a sync. Adapters can override to
     * extract a connected-account id from the redirect query string.
     */
    public function handleReturn(CreatorPaymentConnection $connection, \Illuminate\Http\Request $request): void
    {
        // Most hosted providers expose the connected-account id either
        // before the redirect (we already stored it) or via webhook,
        // not in the redirect itself. We just refresh status here.
        $this->syncStatus($connection);
    }

    /**
     * Default sync stamps the row as "active" if any account_id is
     * present and the env keys aren't configured. Real adapters replace
     * this with a live call to the provider's accounts API.
     */
    public function syncStatus(CreatorPaymentConnection $connection): void
    {
        if (!$this->credentialsConfigured()) {
            // No credentials → leave the row in a clear "preview" state
            // so the UI can prompt the workspace owner to add keys
            // without lying about KYC status.
            $connection->status         = $connection->account_id ? 'pending' : 'pending';
            $connection->status_reason  = 'Provider keys not configured — onboarding is in preview mode.';
            $connection->payouts_enabled = false;
            $connection->charges_enabled = false;
            $connection->last_sync_at   = now();
            $connection->save();
            return;
        }
        // With credentials present, we still don't make a live call in
        // this task (subscriptions/tipping will introduce that). Mark
        // the row as pending until the provider's webhook flips it.
        $connection->status         = $connection->status ?: 'pending';
        $connection->status_reason  = $connection->status_reason ?: 'Awaiting provider verification.';
        $connection->last_sync_at   = now();
        $connection->save();
    }

    public function dashboardUrl(CreatorPaymentConnection $connection): ?string
    {
        return $this->provider['docs_url'] ?? null;
    }

    /** True iff every env key the provider declared is set. */
    public function credentialsConfigured(): bool
    {
        foreach (($this->provider['env_keys'] ?? []) as $key) {
            if (!env($key)) return false;
        }
        return true;
    }

    /**
     * Build the hosted-checkout URL for a fan starting a subscription
     * to a creator. The platform takes 0% — `application_fee_amount`
     * (or its provider-equivalent) is always 0 in real implementations.
     *
     * Default: returns a preview-mode hand-off URL so the full UX flows
     * end-to-end without provider keys (matching the onboarding pattern
     * established in Task #1208). Real adapters will override this once
     * provider credentials are wired.
     *
     * The $context array always carries a 'reference' key (the
     * creator_subscription id) and a 'return_url' key the provider must
     * redirect back to on success.
     */
    public function createSubscriptionCheckout(
        CreatorPaymentConnection $connection,
        array $context,
    ): string {
        return URL::signedRoute('checkout.preview', [
            'provider'  => $this->slug(),
            'kind'      => 'subscription',
            'reference' => $context['reference'] ?? '',
            'token'     => $context['token'] ?? '',
        ]);
    }

    /**
     * Build the hosted-checkout URL for a one-off charge — used both
     * for per-post unlocks and tips. Same preview behaviour and 0%
     * platform fee policy as createSubscriptionCheckout().
     */
    public function createOneTimeCheckout(
        CreatorPaymentConnection $connection,
        array $context,
    ): string {
        return URL::signedRoute('checkout.preview', [
            'provider'  => $this->slug(),
            'kind'      => $context['kind'] ?? 'one_time',
            'reference' => $context['reference'] ?? '',
            'token'     => $context['token'] ?? '',
        ]);
    }

    /**
     * Apply a refund to an in-flight charge. The default is a no-op
     * stub — concrete adapters issue the provider-side refund call.
     * The caller (MonetizationCheckout::refund) is still responsible
     * for revoking access locally (clearing post_unlocks.refunded_at,
     * marking subscription canceled, etc.) and emitting the ledger event.
     */
    public function refundCharge(string $chargeId, ?int $amountCents = null): bool
    {
        return true;
    }
}
