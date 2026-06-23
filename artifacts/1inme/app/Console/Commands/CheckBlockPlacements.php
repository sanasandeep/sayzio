<?php

namespace App\Console\Commands;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Support\BlockRenderCoverage;
use Illuminate\Console\Command;

/**
 * Scans LIVE, user-built biolink pages for the placement-aware blank-render
 * gap that the template validators (templates:check-designs +
 * TemplateSnapshotValidator) only catch for author-controlled templates.
 *
 * A creator can drag a child-only block (e.g. buy_me_coffee) to the page root,
 * or a top-level-only block (e.g. image_slider / one_time_offer) into a card
 * container, and it would silently render blank or as a generic placeholder.
 * This command reuses {@see BlockRenderCoverage::flatRowGaps()} — which derives
 * coverage from the actual blade renderers — to report every such block,
 * resolving the owning link so a human can jump straight to the offending page.
 *
 * Exits non-zero when any gap is found so it can be wired into a diagnostic or
 * CI step; pass --link to restrict the scan to a single link.
 */
class CheckBlockPlacements extends Command
{
    protected $signature = 'biolink:check-block-placements {--link= : Restrict the scan to a single link id}';

    protected $description = 'Scan live biolink blocks for any whose type has no renderer in the placement (page-root vs container-child) it occupies, so it would render blank.';

    public function handle(): int
    {
        $query = BiolinkBlock::query()->select(['id', 'link_id', 'type', 'parent_id']);

        if ($linkId = $this->option('link')) {
            $query->where('link_id', (int) $linkId);
        }

        // Stream rows to bound memory over the distant RDS; we only keep the
        // four scalar columns needed to derive placement.
        $rows = [];
        $linkById = [];
        $query->orderBy('id')->chunk(2000, function ($chunk) use (&$rows, &$linkById) {
            foreach ($chunk as $r) {
                $rows[] = ['id' => $r->id, 'type' => $r->type, 'parent_id' => $r->parent_id];
                $linkById[$r->id] = $r->link_id;
            }
        });

        $total = count($rows);
        if ($total === 0) {
            $this->info('No biolink blocks found to scan.');
            return self::SUCCESS;
        }

        $gaps = BlockRenderCoverage::flatRowGaps($rows);

        if (empty($gaps)) {
            $this->info("Scanned {$total} block(s) — every block renders in the placement it occupies.");
            return self::SUCCESS;
        }

        // Resolve link aliases so a human can jump straight to the offending page.
        $linkIds = array_values(array_unique(array_map(
            fn ($g) => $linkById[$g['id']] ?? null,
            $gaps
        )));
        $links = Link::whereIn('id', array_filter($linkIds, fn ($id) => $id !== null))
            ->get(['id', 'alias', 'title'])
            ->keyBy('id');

        $this->error(count($gaps) . " of {$total} block(s) would render blank in their current placement:");
        foreach ($gaps as $g) {
            $linkId = $linkById[$g['id']] ?? null;
            $link = $linkId !== null ? $links->get($linkId) : null;
            $where = $link
                ? "link #{$linkId} (" . ($link->alias ?: $link->title ?: 'no alias') . ')'
                : ($linkId !== null ? "link #{$linkId}" : 'unknown link');

            $this->line("  • block #{$g['id']} type \"{$g['type']}\" on {$where} — {$g['message']}");
        }

        return self::FAILURE;
    }
}
