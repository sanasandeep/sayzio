<?php

namespace App\Console\Commands;

use App\Modules\User\Models\Link;
use App\Modules\User\Services\InboxAggregator;
use App\Modules\User\Services\InboxForwarder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scan for links that have just expired (by date, max-click cap, or
 * first-click) and whose webhook has not yet been fired, then dispatch
 * `link_expired` notifications to all matching InboxForwardDestinations.
 *
 * Each link fires at most once: `links.webhook_expired_fired_at` is stamped
 * after the first fire and subsequent runs skip it.
 *
 * Scheduled to run every five minutes via publishing-automation.php.
 */
class CheckLinkExpiryWebhooksCommand extends Command
{
    protected $signature   = 'webhooks:check-link-expiry';
    protected $description = 'Fire link_expired webhook/email triggers for newly expired links.';

    public function handle(): void
    {
        if (!Schema::hasColumn('links', 'webhook_expired_fired_at')) {
            $this->warn('webhooks:check-link-expiry: webhook_expired_fired_at column not found — skipping.');
            return;
        }

        // Check whether any destinations are subscribed to link_expired.
        // If none exist, bail early to avoid a full table scan.
        $hasSubscribers = DB::table('inbox_forward_destinations')
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('sources')
                  ->orWhereRaw("sources::text LIKE '%link_expired%'");
            })
            ->exists();

        if (!$hasSubscribers) {
            return;
        }

        $forwarder = app(InboxForwarder::class);
        $now       = now();

        // Batch query: candidate links that are expired AND haven't been stamped yet.
        // We use a conservative chunk to keep memory flat.
        Link::query()
            ->whereNull('webhook_expired_fired_at')
            ->where(function ($q) use ($now) {
                // Date-based expiry
                $q->where(fn ($s) => $s->whereNotNull('expires_at')->where('expires_at', '<=', $now))
                  // max-clicks expiry: settings->max_clicks not null and total_clicks >= that value
                  ->orWhere(fn ($s) => $s
                      ->whereRaw("(settings->>'max_clicks')::int > 0")
                      ->whereRaw('total_clicks >= (settings->>\'max_clicks\')::int')
                  )
                  // expire_on_first_click
                  ->orWhere(fn ($s) => $s
                      ->whereRaw("(settings->>'expire_on_first_click')::boolean IS TRUE")
                      ->where('total_clicks', '>=', 1)
                  );
            })
            ->chunkById(100, function ($links) use ($forwarder) {
                foreach ($links as $link) {
                    if (!$link->isExpired()) {
                        continue;
                    }

                    try {
                        $forwarder->dispatchForLinkExpired($link->user_id, $link);
                    } catch (\Throwable $e) {
                        logger()->warning("link_expired webhook error (link={$link->id}): " . $e->getMessage());
                    }

                    // Stamp regardless of whether any destination was subscribed
                    // so we don't re-check this link on every future run.
                    $link->updateQuietly(['webhook_expired_fired_at' => now()]);
                }
            });
    }
}
