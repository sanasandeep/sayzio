<?php

namespace App\Console\Commands;

use App\Modules\User\Models\SocialAccountConnection;
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
        }

        $this->info("Done. ok={$ok} error={$err} skipped={$skip}");
        return self::SUCCESS;
    }
}
