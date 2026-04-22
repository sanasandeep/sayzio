<?php

namespace App\Services\AI;

use App\Modules\User\Models\AiCreditTransaction;
use App\Modules\User\Models\AiMind;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Per-Mind credit usage analytics. Reads the existing
 * `ai_credit_transactions` ledger and groups feature='mind' spend by
 * the originating Mind (and source for the ingestion side), so the
 * UI can show users which Mind / which source is burning credits.
 *
 * Attribution lives in the transaction `meta` payload:
 *   - meta.mind_id  — the Mind the spend belongs to
 *   - meta.kind     — 'ingest' or 'query'
 * For ingestion, `related_id` is the source_id (legacy) so we can
 * still break ingestion spend down per source. For queries,
 * `related_id` is the focused mind_id.
 *
 * Only `spend` rows are counted. Refunds are netted in via
 * |delta_credits| with their natural sign.
 */
class MindCreditUsageService
{
    public const DEFAULT_WINDOW_DAYS = 30;

    /**
     * Total credits spent on a Mind in the given window, split by
     * ingestion vs. live questions.
     *
     * @return array{ingest:int, query:int, total:int, days:int}
     */
    public function usageForMind(int $mindId, int $days = self::DEFAULT_WINDOW_DAYS): array
    {
        $since = Carbon::now()->subDays($days);

        $rows = AiCreditTransaction::query()
            ->where('feature', 'mind')
            ->where('type', 'spend')
            ->where('meta->mind_id', $mindId)
            ->where('created_at', '>=', $since)
            ->get(['delta_credits', 'meta']);

        $ingest = 0;
        $query  = 0;
        foreach ($rows as $tx) {
            $kind = (string) data_get($tx->meta, 'kind', '');
            $cost = (int) abs((int) $tx->delta_credits);
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
     * Credits spent ingesting each source of a Mind in the window.
     * Returns a map of source_id => credits.
     *
     * @return array<int,int>
     */
    public function ingestionBySource(int $mindId, int $days = self::DEFAULT_WINDOW_DAYS): array
    {
        $since = Carbon::now()->subDays($days);

        $rows = AiCreditTransaction::query()
            ->where('feature', 'mind')
            ->where('type', 'spend')
            ->where('meta->mind_id', $mindId)
            ->where('meta->kind', 'ingest')
            ->where('created_at', '>=', $since)
            ->whereNotNull('related_id')
            ->get(['delta_credits', 'related_id']);

        $out = [];
        foreach ($rows as $tx) {
            $sid = (int) $tx->related_id;
            if ($sid <= 0) continue;
            $out[$sid] = ($out[$sid] ?? 0) + (int) abs((int) $tx->delta_credits);
        }
        return $out;
    }

    /**
     * Daily-bucketed credit spend for one Mind, split by ingestion vs.
     * questions. Always returns exactly $days rows in chronological
     * order, padded with zeros so the UI can render a fixed-width chart.
     *
     * @return array<int,array{date:string, ingest:int, query:int}>
     */
    public function dailySpendForMind(int $mindId, int $days = self::DEFAULT_WINDOW_DAYS): array
    {
        $since = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = AiCreditTransaction::query()
            ->where('feature', 'mind')
            ->where('type', 'spend')
            ->where('meta->mind_id', $mindId)
            ->where('created_at', '>=', $since)
            ->get(['delta_credits', 'meta', 'created_at']);

        return $this->bucketByDay($rows, $since, $days);
    }

    /**
     * Daily-bucketed credit spend across every Mind on the platform.
     *
     * @return array<int,array{date:string, ingest:int, query:int}>
     */
    public function dailySpendGlobal(int $days = self::DEFAULT_WINDOW_DAYS): array
    {
        $since = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = AiCreditTransaction::query()
            ->where('feature', 'mind')
            ->where('type', 'spend')
            ->whereNotNull('meta')
            ->where('created_at', '>=', $since)
            ->get(['delta_credits', 'meta', 'created_at']);

        return $this->bucketByDay($rows, $since, $days);
    }

    /**
     * @param  iterable<int,AiCreditTransaction>  $rows
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
            $cost = (int) abs((int) $tx->delta_credits);
            if ($kind === 'query')  $buckets[$d]['query']  += $cost;
            else                    $buckets[$d]['ingest'] += $cost;
        }
        return array_values($buckets);
    }

    /**
     * Top Minds by credit spend in the window. Each row carries the
     * Mind model (with owner) plus split totals.
     *
     * @return Collection<int,array{mind:AiMind, ingest:int, query:int, total:int}>
     */
    public function topMinds(int $limit = 10, int $days = self::DEFAULT_WINDOW_DAYS): Collection
    {
        $since = Carbon::now()->subDays($days);

        $rows = AiCreditTransaction::query()
            ->where('feature', 'mind')
            ->where('type', 'spend')
            ->whereNotNull('meta')
            ->where('created_at', '>=', $since)
            ->get(['delta_credits', 'meta']);

        $totals = [];
        foreach ($rows as $tx) {
            $mid = (int) data_get($tx->meta, 'mind_id', 0);
            if ($mid <= 0) continue;
            $kind = (string) data_get($tx->meta, 'kind', '');
            $cost = (int) abs((int) $tx->delta_credits);
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
