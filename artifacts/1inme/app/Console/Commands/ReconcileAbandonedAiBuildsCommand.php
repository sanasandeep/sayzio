<?php

namespace App\Console\Commands;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Services\AI\AiUsageCharger;
use App\Services\Biolink\AiBiolinkBuilderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Refund AI page builds that were charged for and never delivered.
 *
 * AiBiolinkBuilderService::generate() charges inside OpenAiService::chat()
 * and refunds in a catch block if anything downstream fails. That covers
 * every failure the builder can *see*. It cannot cover the process simply
 * ending -- a worker timeout, an OOM kill, a deploy mid-request -- because
 * a catch block in a dead process never runs. When that happens the
 * creator's coins are gone, the spend is on the ledger, and all they have
 * to show for it is an empty link. That is exactly what happened to
 * link 3003 and 25 coins.
 *
 * No new bookkeeping for this. The wallet ledger is already the durable,
 * authoritative record of every charge and refund, and a second table
 * written by the same request would be lost in the same crash. So this
 * reads what is already there: a builder spend, old enough that no build
 * could still be running, with no refund against the same link, whose
 * link is missing or still has no blocks on it.
 *
 * Double-refunding is prevented twice over: by looking for an existing
 * refund on the same link, and by a deterministic idempotency_key, which
 * is UNIQUE on wallet_transactions. The second is what actually
 * guarantees it -- the first is only a query, and two copies of this
 * command racing would both pass it.
 */
class ReconcileAbandonedAiBuildsCommand extends Command
{
    protected $signature = 'ai:reconcile-abandoned-builds
        {--minutes=30 : Only consider spends older than this, so a build still running is never touched}
        {--user= : Limit to one user id}
        {--dry-run : Report what would be refunded without touching anything}';

    protected $description = 'Refund AI biolink builds that were charged but never produced a page, and remove the empty links they left behind.';

    public function handle(AiUsageCharger $credits): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $dry     = (bool) $this->option('dry-run');
        $cutoff  = now()->subMinutes($minutes);

        $spends = WalletTransaction::query()
            ->where('type', 'spend')
            ->where('meta->feature', AiBiolinkBuilderService::FEATURE)
            ->where('created_at', '<', $cutoff)
            ->when($this->option('user'), fn ($q, $u) => $q->where('user_id', $u))
            ->orderBy('id')
            ->get();

        $refunded = 0;
        $coins    = 0;
        $deleted  = 0;
        $skipped  = 0;

        foreach ($spends as $spend) {
            $linkId = (int) ($spend->meta['related_id'] ?? 0);
            if ($linkId <= 0) {
                $skipped++;
                continue;
            }

            // The builder's own catch-block refund carries no idempotency
            // key, so it can only be recognised by its subject. Any refund
            // already standing against this link means this spend has been
            // settled and is none of our business.
            $alreadyRefunded = WalletTransaction::query()
                ->where('type', 'refund')
                ->where('user_id', $spend->user_id)
                ->where('meta->feature', AiBiolinkBuilderService::FEATURE)
                ->where('meta->related_id', $linkId)
                ->exists();

            if ($alreadyRefunded) {
                $skipped++;
                continue;
            }

            $link = Link::withoutGlobalScopes()->find($linkId);

            // A build that delivered put blocks on the page. Blocks are the
            // only honest evidence of delivery -- not the link's existence,
            // which the builder creates before it charges anything.
            if ($link && $link->biolinkBlocks()->exists()) {
                $skipped++;
                continue;
            }

            $amount = abs((int) $spend->delta_coins);
            if ($amount <= 0) {
                $skipped++;
                continue;
            }

            $this->line(sprintf(
                '%s user %d: %d coin(s) for link %d (%s)',
                $dry ? 'would refund' : 'refunding',
                $spend->user_id,
                $amount,
                $linkId,
                $link ? 'empty link, will remove' : 'link already gone'
            ));

            if ($dry) {
                $refunded++;
                $coins += $amount;
                continue;
            }

            $user = User::withoutGlobalScopes()->find($spend->user_id);
            if (!$user) {
                $skipped++;
                continue;
            }

            try {
                $credits->refund($user, $amount, [
                    'feature'    => AiBiolinkBuilderService::FEATURE,
                    'related_id' => $linkId,
                    'reason'     => 'AI page build was charged but never delivered — automatic refund',
                    // Deterministic and unique: keyed on the spend itself, so
                    // a second run, a concurrent run, or a retry all collapse
                    // onto one refund row.
                    'idempotency_key' => 'abandoned-ai-build:' . $spend->id,
                    'meta'            => ['reconciled_spend_id' => $spend->id],
                ]);
            } catch (\Throwable $e) {
                report($e);
                $this->error("  refund failed for user {$spend->user_id}, spend {$spend->id} — left alone");
                $skipped++;
                continue;
            }

            $refunded++;
            $coins += $amount;

            // Only ever the empty shell. A link with blocks was never a
            // candidate, so nothing anyone has worked on can be reached
            // from here.
            if ($link && !$link->biolinkBlocks()->exists()) {
                $link->delete();
                $deleted++;
            }

            Log::info('Refunded an abandoned AI build', [
                'user_id'  => $spend->user_id,
                'link_id'  => $linkId,
                'coins'    => $amount,
                'spend_id' => $spend->id,
            ]);
        }

        $this->info(sprintf(
            '%s %d build(s), %d coin(s); removed %d empty link(s); left %d alone.',
            $dry ? 'Would refund' : 'Refunded',
            $refunded,
            $coins,
            $deleted,
            $skipped
        ));

        return self::SUCCESS;
    }
}
