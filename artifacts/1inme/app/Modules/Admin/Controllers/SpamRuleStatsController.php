<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Services\SpamChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide view of spam-rule activity. Aggregates across every account
 * so the admin can spot which built-in keywords are universally over-aggressive
 * (candidates for removal from SpamChecker::BLOCKED_KEYWORDS) versus the ones
 * actually doing useful work.
 */
class SpamRuleStatsController extends Controller
{
    private const ALLOWED_WINDOWS = [7, 30, 90, 365];
    private const DEFAULT_WINDOW = 30;

    public function index(Request $request)
    {
        $days = (int) $request->query('days', self::DEFAULT_WINDOW);
        if (!in_array($days, self::ALLOWED_WINDOWS, true)) {
            $days = self::DEFAULT_WINDOW;
        }
        $since = now()->subDays($days);

        // Single aggregated read per source: GROUP BY spam_reason and SUM the
        // counts in PHP. Two queries (one per inbox table) — not one per user.
        $rows = collect()
            ->concat($this->countsFor(FormSubmission::query(), $since))
            ->concat($this->countsFor(Subscriber::query(), $since));

        $reasonCounts = [];
        foreach ($rows as $row) {
            $reasonCounts[$row->reason] = ($reasonCounts[$row->reason] ?? 0) + (int) $row->n;
        }

        $ruleHits = [
            'blocked_keyword' => 0,
            'too_many_links'  => 0,
            'rate_limit'      => 0,
            'honeypot'        => 0,
        ];
        $keywordHits = []; // keyword (lowercased) => count

        foreach ($reasonCounts as $reason => $count) {
            $p = SpamChecker::parseReason($reason);
            if (!$p) continue;
            $code = $p['code'];
            if (isset($ruleHits[$code])) {
                $ruleHits[$code] += $count;
            }
            if ($code === 'blocked_keyword' && $p['detail']) {
                $k = mb_strtolower($p['detail']);
                $keywordHits[$k] = ($keywordHits[$k] ?? 0) + $count;
            }
        }
        arsort($keywordHits);

        // Split keyword hits into defaults vs custom user-added keywords so
        // the admin can immediately see which built-ins to consider pruning.
        $defaultsLower = array_map('mb_strtolower', SpamChecker::BLOCKED_KEYWORDS);
        $defaultKeywordHits = [];
        $customKeywordHits = [];
        foreach ($keywordHits as $kw => $count) {
            if (in_array($kw, $defaultsLower, true)) {
                $defaultKeywordHits[$kw] = $count;
            } else {
                $customKeywordHits[$kw] = $count;
            }
        }

        // Show every default keyword (even with 0 hits) so the admin can spot
        // built-ins that never fire and consider removing them.
        $defaultKeywordRows = [];
        foreach (SpamChecker::BLOCKED_KEYWORDS as $kw) {
            $k = mb_strtolower($kw);
            $defaultKeywordRows[] = ['keyword' => $kw, 'count' => $defaultKeywordHits[$k] ?? 0];
        }
        usort($defaultKeywordRows, fn($a, $b) => $b['count'] <=> $a['count']);

        return view('admin.spam-rule-stats.index', [
            'days'               => $days,
            'allowedWindows'     => self::ALLOWED_WINDOWS,
            'ruleHits'           => $ruleHits,
            'totalRuleHits'      => array_sum($ruleHits),
            'defaultKeywordRows' => $defaultKeywordRows,
            'customKeywordHits'  => $customKeywordHits,
        ]);
    }

    /**
     * Group spam_reason values across the whole table since $since.
     * Returns rows with `reason` and `n` columns.
     */
    private function countsFor($query, $since)
    {
        return $query
            ->where('is_spam', true)
            ->whereNotNull('spam_reason')
            ->where('created_at', '>=', $since)
            ->select('spam_reason as reason', DB::raw('COUNT(*) as n'))
            ->groupBy('spam_reason')
            ->get();
    }
}
