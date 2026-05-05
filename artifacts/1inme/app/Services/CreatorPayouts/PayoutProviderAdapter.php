<?php

namespace App\Services\CreatorPayouts;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\User;

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
}
