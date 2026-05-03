<?php

namespace App\Console\Commands;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkPerformanceSnapshot;
use App\Modules\User\Models\PageSession;
use App\Modules\User\Models\RoadmapItem;
use App\Modules\User\Models\RoadmapVote;
use App\Modules\User\Services\LinkPerformanceCoach;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Nightly job: persist yesterday's Performance Coach score + component
 * breakdown for every active link. The view renders a 30-day sparkline
 * from these rows so users can see whether their score is trending.
 *
 * Idempotent: uses (link_id, date) uniqueness so re-running the command
 * for the same day simply overwrites the existing snapshot.
 */
class SnapshotLinkPerformance extends Command
{
    protected $signature = 'coach:snapshot-scores
        {--date= : YYYY-MM-DD date to snapshot (default: yesterday)}
        {--link= : Optional link id to snapshot (default: all active links)}';

    protected $description = "Write a daily Performance Coach score snapshot for each active link.";

    public function handle(): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : now()->subDay()->startOfDay();

        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();
        $prevStart = $dayStart->copy()->subDay();
        $prevEnd   = $dayStart->copy()->subSecond();

        $query = Link::query()->where('is_active', true);
        if ($this->option('link')) {
            $query->where('id', $this->option('link'));
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No active links to snapshot.');
            return self::SUCCESS;
        }

        $this->info("Snapshotting {$total} link(s) for {$dayStart->toDateString()}");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $written = 0;
        $query->chunkById(200, function ($links) use ($dayStart, $dayEnd, $prevStart, $prevEnd, &$written, $bar) {
            foreach ($links as $link) {
                $ctx = $this->buildContext($link, $dayStart, $dayEnd, $prevStart, $prevEnd);
                $result = LinkPerformanceCoach::scoreWithComponents($ctx);

                // Mix in roadmap engagement so the daily components
                // payload reflects fan participation, not just clicks.
                // Renders into the existing 30-day analytics surface
                // (see PortalController + LinkController views).
                $components = $result['components'];
                $components['roadmap'] = [
                    'submissions_today' => RoadmapItem::query()->withoutGlobalScope('workspace')
                        ->where('link_id', $link->id)
                        ->whereBetween('created_at', [$dayStart, $dayEnd])
                        ->count(),
                    'votes_today' => RoadmapVote::query()
                        ->whereIn('item_id', RoadmapItem::query()->withoutGlobalScope('workspace')
                            ->where('link_id', $link->id)->pluck('id'))
                        ->whereBetween('created_at', [$dayStart, $dayEnd])
                        ->count(),
                    'shipped_today' => RoadmapItem::query()->withoutGlobalScope('workspace')
                        ->where('link_id', $link->id)
                        ->whereBetween('shipped_at', [$dayStart, $dayEnd])
                        ->count(),
                    'open_ideas' => RoadmapItem::query()->withoutGlobalScope('workspace')
                        ->where('link_id', $link->id)
                        ->where('is_blocked', false)
                        ->whereIn('status', ['ideas', 'planned', 'in_progress'])
                        ->count(),
                ];

                LinkPerformanceSnapshot::updateOrCreate(
                    ['link_id' => $link->id, 'date' => $dayStart->toDateString()],
                    [
                        'score'           => $result['score'],
                        'components_json' => $components,
                    ]
                );
                $written++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Wrote {$written} snapshot(s).");
        return self::SUCCESS;
    }

    /**
     * Build the minimal analytics context LinkPerformanceCoach needs to
     * compute a score. Mirrors the scoring-relevant slice of
     * LinkController::show() but scoped to a single day.
     */
    private function buildContext(Link $link, Carbon $dayStart, Carbon $dayEnd, Carbon $prevStart, Carbon $prevEnd): array
    {
        $clicks = $link->clicks()->whereBetween('clicked_at', [$dayStart, $dayEnd]);

        $totalInRange       = (clone $clicks)->count();
        $uniqueInRange      = (clone $clicks)->distinct('ip_address')->count('ip_address');
        $blockClicksInRange = (clone $clicks)->whereNotNull('block_id')->count();
        $pageVisitsInRange  = (clone $clicks)->whereNull('block_id')->count();

        $blockStats = (clone $clicks)
            ->selectRaw('block_id, COUNT(*) as count')
            ->whereNotNull('block_id')
            ->groupBy('block_id')
            ->orderByDesc('count')
            ->get();

        $topReferrers = (clone $clicks)
            ->selectRaw('referrer, COUNT(*) as count')
            ->whereNotNull('referrer')->where('referrer', '!=', '')
            ->groupBy('referrer')->orderByDesc('count')->limit(10)->get();

        $totalInRangePrev = $link->clicks()
            ->whereBetween('clicked_at', [$prevStart, $prevEnd])
            ->count();

        $sessions = PageSession::where('link_id', $link->id)
            ->whereBetween('started_at', [$dayStart, $dayEnd]);

        $totalSessions     = (clone $sessions)->count();
        $avgSessionSeconds = (int) round((clone $sessions)->avg('duration_seconds') ?? 0);
        $bounceSessions    = (clone $sessions)->where('duration_seconds', '<', 5)->count();
        $bounceRate        = $totalSessions > 0 ? round(($bounceSessions / $totalSessions) * 100, 1) : 0.0;

        $blockInventory = ['clickable' => [], 'has_socials' => false, 'has_qr' => false,
                            'active_count' => 0, 'top_level_active_count' => 0];
        if ($link->type === 'biolink') {
            $nonInteractive = ['heading', 'heading_logo',
                'paragraph', 'paragraph_rich', 'divider', 'spacer',
                'verified_heading', 'verified_avatar', 'alert', 'badge', 'avatar'];
            $socialTypes = ['socials', 'socials_multi', 'socials_custom'];
            $blocks = BiolinkBlock::where('link_id', $link->id)
                ->get(['id', 'type', 'parent_id', 'is_active']);
            foreach ($blocks as $blk) {
                if (!$blk->is_active) continue;
                $blockInventory['active_count']++;
                if ($blk->parent_id === null) {
                    $blockInventory['top_level_active_count']++;
                    if (!in_array($blk->type, $nonInteractive, true)) {
                        $blockInventory['clickable'][] = $blk->id;
                    }
                }
                if (in_array($blk->type, $socialTypes, true)) $blockInventory['has_socials'] = true;
                if ($blk->type === 'qr_code')                  $blockInventory['has_qr']      = true;
            }
        }

        return [
            'link'               => $link,
            'totalInRange'       => $totalInRange,
            'uniqueInRange'      => $uniqueInRange,
            'blockClicksInRange' => $blockClicksInRange,
            'pageVisitsInRange'  => $pageVisitsInRange,
            'totalSessions'      => $totalSessions,
            'avgSessionSeconds'  => $avgSessionSeconds,
            'bounceRate'         => $bounceRate,
            'blockStats'         => $blockStats,
            'topReferrers'       => $topReferrers,
            'totalInRangePrev'   => $totalInRangePrev,
            'aliasFilter'        => null,
            'period'             => 'today',
            'blockInventory'     => $blockInventory,
        ];
    }
}
