<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prune stale event_contact_exchanges rows that were never accepted.
 *
 * A row is a directed contact-swap request between two attendees at the same
 * event. Pending requests can only be accepted while the event is live plus a
 * short grace window (EventContactExchangeController::ACCEPT_GRACE_HOURS), so
 * once an event has ended well in the past, pending rows are dead weight —
 * they can never transition and just accumulate forever (like
 * event_discoverability before events:prune-discoverability).
 *
 * Retention decisions (explicit):
 *   - accepted rows are kept for a LONG but FINITE window: --accepted-days
 *     (0 = keep forever; the production schedule passes 730, i.e. 2 years
 *     after acceptance). Rationale: once accepted, the actual contact rows
 *     already exist in each user's address book, so the exchange row itself
 *     is only an audit trail of the handshake. Keeping a permanent pairing
 *     of two user IDs plus the event link forever conflicts with
 *     data-minimisation; two years comfortably covers dispute/audit needs.
 *     Age is measured from accepted_at (created_at fallback for legacy rows
 *     with a null accepted_at).
 *   - pending and declined rows are deleted once the event's end date is more
 *     than --days (default 30) in the past.
 *   - for events with no end date (or no ICS row at all), pending/declined
 *     rows are deleted once the row itself is older than --fallback-days
 *     (default 90), since "the event ended" cannot be determined.
 *
 * Safety (mirrors events:prune-discoverability): deletes are id-keyed chunks
 * (Postgres has no DELETE ... LIMIT) capped by --max-batches.
 */
class PruneEventContactExchanges extends Command
{
    protected $signature = 'events:prune-contact-exchanges
        {--days=30 : Delete non-accepted rows for events that ended more than this many days ago}
        {--fallback-days=90 : For events with no end date, delete non-accepted rows older than this many days}
        {--accepted-days=0 : Delete ACCEPTED rows this many days after acceptance (0 = keep forever)}
        {--chunk=1000 : Rows to delete per batch}
        {--max-batches=5000 : Safety cap on batches per run}
        {--dry-run : Report what would be deleted without changing anything}';

    protected $description = 'Delete stale pending/declined event contact-exchange rows for long-ended events. Accepted rows are kept. Chunked + capped.';

    public function handle(): int
    {
        if (!Schema::hasTable('event_contact_exchanges')) {
            $this->warn('event_contact_exchanges table does not exist yet — nothing to prune.');
            return self::SUCCESS;
        }

        $days         = max(0, (int) $this->option('days'));
        $fallbackDays = max(0, (int) $this->option('fallback-days'));
        $acceptedDays = max(0, (int) $this->option('accepted-days'));
        $chunk        = max(1, (int) $this->option('chunk'));
        $maxBatches   = max(1, (int) $this->option('max-batches'));

        $endCutoff      = now()->subDays($days);
        $createdCutoff  = now()->subDays($fallbackDays);
        $acceptedCutoff = $acceptedDays > 0 ? now()->subDays($acceptedDays) : null;

        $staleQuery = function () use ($endCutoff, $createdCutoff) {
            return DB::table('event_contact_exchanges as ece')
                ->leftJoin('ics_data as ics', 'ics.link_id', '=', 'ece.link_id')
                ->where('ece.status', '!=', 'accepted')
                ->where(function ($q) use ($endCutoff, $createdCutoff) {
                    $q->where(function ($q2) use ($endCutoff) {
                        $q2->whereNotNull('ics.end_date')
                           ->where('ics.end_date', '<', $endCutoff);
                    })->orWhere(function ($q2) use ($createdCutoff) {
                        $q2->whereNull('ics.end_date')
                           ->where('ece.created_at', '<', $createdCutoff);
                    });
                });
        };

        $expiredAcceptedQuery = function () use ($acceptedCutoff) {
            return DB::table('event_contact_exchanges as ece')
                ->where('ece.status', 'accepted')
                ->whereRaw('COALESCE(ece.accepted_at, ece.created_at) < ?', [$acceptedCutoff]);
        };

        if ($this->option('dry-run')) {
            $count = (int) $staleQuery()->count();
            $this->line("Dry run: {$count} stale non-accepted exchange row(s) would be deleted (event ended > {$days} day(s) ago, or no end date and row older than {$fallbackDays} day(s)).");

            if ($acceptedCutoff !== null) {
                $acceptedCount = (int) $expiredAcceptedQuery()->count();
                $this->line("Dry run: {$acceptedCount} accepted exchange row(s) would be deleted (accepted more than {$acceptedDays} day(s) ago).");
            } else {
                $this->line('Dry run: accepted rows are kept forever (--accepted-days=0).');
            }

            return self::SUCCESS;
        }

        $deleteChunked = function (callable $query) use ($chunk, $maxBatches): int {
            $total = 0;
            for ($batch = 0; $batch < $maxBatches; $batch++) {
                $ids = $query()
                    ->orderBy('ece.id')
                    ->limit($chunk)
                    ->pluck('ece.id')
                    ->all();

                if (empty($ids)) {
                    break;
                }

                $total += DB::table('event_contact_exchanges')->whereIn('id', $ids)->delete();

                if (count($ids) < $chunk) {
                    break;
                }
            }

            return $total;
        };

        $total = $deleteChunked($staleQuery);
        $this->info("Deleted {$total} stale non-accepted contact-exchange row(s) (event ended > {$days} day(s) ago; no-end-date fallback {$fallbackDays} day(s)).");

        if ($acceptedCutoff !== null) {
            $acceptedTotal = $deleteChunked($expiredAcceptedQuery);
            $this->info("Deleted {$acceptedTotal} accepted contact-exchange row(s) past the {$acceptedDays}-day retention window.");
        } else {
            $this->info('Accepted rows kept forever (--accepted-days=0).');
        }

        return self::SUCCESS;
    }
}
