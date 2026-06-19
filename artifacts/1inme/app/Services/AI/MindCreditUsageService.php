<?php

namespace App\Services\AI;

use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Per-Mind coin usage analytics. Reads the coin wallet ledger
 * (`wallet_transactions`) and groups AI spend tagged meta.feature='mind'
 * by the originating Mind (and source for the ingestion side), so the
 * UI can show users which Mind / which source is burning coins.
 *
 * Attribution lives in the transaction `meta` payload:
 *   - meta.ai         — true for AI charges
 *   - meta.feature    — 'mind'
 *   - meta.mind_id    — the Mind the spend belongs to
 *   - meta.kind       — 'ingest' or 'query'
 *   - meta.related_id — for ingestion, the source_id; for queries, the
 *                       focused mind_id.
 *
 * Only `spend` rows are counted. Refunds are netted in via
 * |delta_coins| with their natural sign.
 */
class MindCreditUsageService
{
    public const DEFAULT_WINDOW_DAYS = 30;

    /**
     * Total coins spent on a Mind in the given window, split by
     * ingestion vs. live questions.
     *
     * @return array{ingest:int, query:int, total:int, days:int}
     */
    public function usageForMind(int $mindId, int $days = self::DEFAULT_WINDOW_DAYS): array
    {
        $since = Carbon::now()->subDays($days);

        $rows = WalletTransaction::query()
            ->where('meta->ai', true)
            ->where('meta->feature', 'mind')
            ->where('type', 'spend')
            ->where('meta->mind_id', $mindId)
            ->where('created_at', '>=', $since)
            ->get(['delta_coins', 'meta']);

        $ingest = 0;
        $query  = 0;
        foreach ($rows as $tx) {
            $kind = (string) data_get($tx->meta, 'kind', '');
            $cost = (int) abs((int) $tx->delta_coins);
            if ($kind === 'query')  $query  += $cost;
            else                    $ingest += $cost;
        }

        return [
            'ingest' => $ingest,
            'query'  => $query,
            'total'  => $ingest + $query,
            'days'   => $days,
        ];
    }

    /**
     * Coins spent ingesting each source of a Mind in the window.
     * Returns a map of source_id => coins.
     *
     * @return array<int,int>
     */
    public function ingestionBySource(int $mindId, int $days = self::DEFAULT_WINDOW_DAYS): array
    {
        $since = Carbon::now()->subDays($days);

        $rows = WalletTransaction::query()
            ->where('meta->ai', true)
            ->where('meta->feature', 'mind')
            ->where('type', 'spend')
            ->where('meta->mind_id', $mindId)
            ->where('meta->kind', 'ingest')
            ->where('created_at', '>=', $since)
            ->whereNotNull('meta->related_id')
            ->get(['delta_coins', 'meta']);

        $out = [];
        foreach ($rows as $tx) {
            $sid = (int) data_get($tx->meta, 'related_id', 0);
            if ($sid <= 0) continue;
            $out[$sid] = ($out[$sid] ?? 0) + (int) abs((int) $tx->delta_coins);
        }
        return $out;
    }

    /**
     * Daily-bucketed coin spend for one Mind, split by ingestion vs.
     * questions. Always returns exactly $days rows in chronological
     * order, padded with zeros so the UI can render a fixed-width chart.
     *
     * @return array<int,array{date:string, ingest:int, query:int}>
     */
    public function dailySpendForMind(int $mindId, int $days = self::DEFAULT_WINDOW_DAYS): array
    {
        $since = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = WalletTransaction::query()
            ->where('meta->ai', true)
            ->where('meta->feature', 'mind')
            ->where('type', 'spend')
            ->where('meta->mind_id', $mindId)
            ->where('created_at', '>=', $since)
            ->get(['delta_coins', 'meta', 'created_at']);

        return $this->bucketByDay($rows, $since, $days);
    }

    /**
     * Daily-bucketed coin spend across every Mind on the platform.
     *
     * @return array<int,array{date:string, ingest:int, query:int}>
     */
    public function dailySpendGlobal(int $days = self::DEFAULT_WINDOW_DAYS): array
    {
        $since = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = WalletTransaction::query()
            ->where('meta->ai', true)
            ->where('meta->feature', 'mind')
            ->where('type', 'spend')
            ->where('created_at', '>=', $since)
            ->get(['delta_coins', 'meta', 'created_at']);

        return $this->bucketByDay($rows, $since, $days);
    }

    /**
     * @param  iterable<int,WalletTransaction>  $rows
     * @return array<int,array{date:string, ingest:int, query:int}>
     */
    private function bucketByDay(iterable $rows, Carbon $since, int $days): array
    {
        $buckets = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $since->copy()->addDays($i)->toDateString();
            $buckets[$d] = ['date' => $d, 'ingest' => 0, 'query' => 0];
        }
        foreach ($rows as $tx) {
            $d = Carbon::parse($tx->created_at)->toDateString();
            if (!isset($buckets[$d])) continue;
            $kind = (string) data_get($tx->meta, 'kind', '');
            $cost = (int) abs((int) $tx->delta_coins);
            if ($kind === 'query')  $buckets[$d]['query']  += $cost;
            else                    $buckets[$d]['ingest'] += $cost;
        }
        return array_values($buckets);
    }

    /**
     * Top Minds by coin spend in the window. Each row carries the
     * Mind model (with owner) plus split totals.
     *
     * @return Collection<int,array{mind:AiMind, ingest:int, query:int, total:int}>
     */
    public function topMinds(int $limit = 10, int $days = self::DEFAULT_WINDOW_DAYS): Collection
    {
        $since = Carbon::now()->subDays($days);

        $rows = WalletTransaction::query()
            ->where('meta->ai', true)
            ->where('meta->feature', 'mind')
            ->where('type', 'spend')
            ->where('created_at', '>=', $since)
            ->get(['delta_coins', 'meta']);

        $totals = [];
        foreach ($rows as $tx) {
            $mid = (int) data_get($tx->meta, 'mind_id', 0);
            if ($mid <= 0) continue;
            $kind = (string) data_get($tx->meta, 'kind', '');
            $cost = (int) abs((int) $tx->delta_coins);
            if (!isset($totals[$mid])) {
                $totals[$mid] = ['ingest' => 0, 'query' => 0];
            }
            if ($kind === 'query')  $totals[$mid]['query']  += $cost;
            else                    $totals[$mid]['ingest'] += $cost;
        }

        if (!$totals) return collect();

        // Sort by combined total desc and slice.
        uasort($totals, fn($a, $b) => ($b['ingest'] + $b['query']) <=> ($a['ingest'] + $a['query']));
        $totals = array_slice($totals, 0, $limit, true);

        $minds = AiMind::with('user:id,name,email')->whereIn('id', array_keys($totals))->get()->keyBy('id');

        $out = collect();
        foreach ($totals as $mid => $row) {
            $mind = $minds->get($mid);
            if (!$mind) continue;
            $out->push([
                'mind'   => $mind,
                'ingest' => $row['ingest'],
                'query'  => $row['query'],
                'total'  => $row['ingest'] + $row['query'],
            ]);
        }
        return $out;
    }
}
