<?php

namespace App\Console\Commands;

use App\Modules\User\Models\SocialAccountConnection;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Services\SocialFollowers\FollowerFetcherRegistry;
use Illuminate\Console\Command;

class RefreshSocialFollowerCounts extends Command
{
    protected $signature   = 'socials:refresh-follower-counts
                              {--connection= : Refresh only this connection id}
                              {--platform= : Restrict to a single platform}
                              {--stale-hours=4 : Only refresh connections whose cache is older than this}
                              {--all : Refresh every connection regardless of age}';

    protected $description = 'Refresh cached follower counts for all connected social accounts.';

    public function handle(FollowerFetcherRegistry $registry): int
    {
        $q = SocialAccountConnection::query();

        if ($id = $this->option('connection')) $q->where('id', $id);
        if ($p = $this->option('platform'))    $q->where('platform', $p);

        if (! $this->option('all') && ! $this->option('connection')) {
            $hours = max(1, (int) $this->option('stale-hours'));
            $q->where(function ($w) use ($hours) {
                $w->whereNull('last_refreshed_at')
                  ->orWhere('last_refreshed_at', '<', now()->subHours($hours));
            });
        }

        $cs = $q->get();
        $this->info("Refreshing {$cs->count()} connection(s)...");

        $ok = $err = $skip = 0;
        foreach ($cs as $c) {
            $status = $registry->refresh($c);
            $this->line("  #{$c->id} {$c->platform} {$c->handle}: {$status} " .
                ($c->follower_count !== null ? "({$c->follower_count} followers)" : ''));
            if ($status === 'ok')           $ok++;
            elseif ($status === 'error')    $err++;
            else                            $skip++;

            // Surface a one-time in-app nudge once a connection has failed N
            // consecutive refreshes — keeps creators in the loop without
            // re-pinging them on every failed attempt.
            if ($status === 'error') $this->maybeNotify($c);
        }

        $this->info("Done. ok={$ok} error={$err} skipped={$skip}");
        return self::SUCCESS;
    }

    private function maybeNotify(SocialAccountConnection $c): void
    {
        if ($c->consecutive_failures < SocialAccountConnection::FAILURE_NUDGE_THRESHOLD) return;
        if ($c->last_failure_notified_at) return; // already nudged for this run of failures

        UserNotification::create([
            'user_id'    => $c->user_id,
            'type'       => 'social_connection_broken',
            'data'       => [
                'platform'       => $c->platform,
                'platform_label' => SocialAccountConnection::platformLabel($c->platform),
                'handle'         => $c->handle,
                'error'          => $c->last_refresh_error,
                'fix_url'        => route('user.social-accounts.index'),
                'message'        => SocialAccountConnection::platformLabel($c->platform)
                                    . ' (@' . $c->handle . ') hasn\'t refreshed in '
                                    . $c->consecutive_failures . ' attempts — reconnect to keep your follower count live.',
            ],
            'created_at' => now(),
        ]);

        $c->update(['last_failure_notified_at' => now()]);
    }
}
