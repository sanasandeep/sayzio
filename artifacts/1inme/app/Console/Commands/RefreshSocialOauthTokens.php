<?php

namespace App\Console\Commands;

use App\Modules\User\Models\SocialAccountConnection;
use App\Modules\User\Services\SocialFollowers\SocialConnectionBrokenMail;
use App\Modules\User\Services\SocialFollowers\SocialOAuthService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Sweeps social_account_connections and uses the stored refresh_token to
 * obtain a new access_token for any connection whose token is about to
 * expire. Connections without a refresh_token (e.g. providers that don't
 * issue one) or whose refresh fails are left alone — the latter will surface
 * a "Reconnect" button on the Connected Accounts page next time the user
 * visits.
 */
class RefreshSocialOauthTokens extends Command
{
    protected $signature   = 'socials:refresh-oauth-tokens
                              {--window-hours=72 : Refresh tokens that expire within this many hours}
                              {--connection= : Refresh only this connection id}
                              {--all : Refresh every connection that has a refresh_token}';

    protected $description = 'Use stored refresh tokens to keep social account access tokens alive before they expire.';

    public function handle(SocialOAuthService $oauth): int
    {
        $q = SocialAccountConnection::query();

        if ($id = $this->option('connection')) {
            $q->where('id', $id);
        } else {
            // Cast a wide net by token presence — every supported strategy needs
            // either a stored refresh_token (LinkedIn / Pinterest / X / TikTok)
            // OR a still-valid access_token to exchange (Meta long-lived tokens).
            $q->where(function ($w) {
                $w->whereNotNull('refresh_token')
                  ->orWhereNotNull('access_token');
            });

            if (! $this->option('all')) {
                $hours = max(1, (int) $this->option('window-hours'));
                $cutoff = now()->addHours($hours);
                $q->where(function ($w) use ($cutoff) {
                    $w->whereNull('token_expires_at')          // unknown -> err on the side of refreshing
                      ->orWhere('token_expires_at', '<=', $cutoff);
                });
            }
        }

        $cs = $q->get()->filter(fn ($c) => $oauth->canRefreshToken($c))->values();
        $this->info("Refreshing tokens for {$cs->count()} connection(s)...");

        $ok = $skipped = $failed = 0;
        foreach ($cs as $c) {
            try {
                $did = $oauth->refreshAccessToken($c);
                if ($did) {
                    // Successful refresh clears any previous "broken" status so the
                    // Reconnect badge in the UI disappears.
                    if ($c->last_refresh_status === 'error') {
                        $c->update(['last_refresh_status' => 'pending', 'last_refresh_error' => null]);
                    }
                    $ok++;
                    $this->line("  #{$c->id} {$c->platform} {$c->handle}: refreshed");
                } else {
                    $skipped++;
                }
            } catch (Throwable $e) {
                $failed++;
                // Capture the prior status BEFORE the update so we can detect
                // the transient -> error transition. We only email on the
                // flip; the weekly throttle inside the mailer is a backstop
                // for break/recover/break cycles, not a weekly reminder for
                // a connection that's been stuck in error all along.
                $wasBroken = $c->last_refresh_status === 'error';
                $c->update([
                    'last_refresh_status' => 'error',
                    'last_refresh_error'  => substr('Token refresh failed: ' . $e->getMessage(), 0, 500),
                ]);
                $this->warn("  #{$c->id} {$c->platform} {$c->handle}: " . $e->getMessage());
                if (! $wasBroken && SocialConnectionBrokenMail::dispatchIfDue($c, $e->getMessage())) {
                    $this->line("    -> emailed reconnect prompt to user #{$c->user_id}");
                }
            }
        }

        $this->info("Done. ok={$ok} skipped={$skipped} failed={$failed}");
        return self::SUCCESS;
    }
}
