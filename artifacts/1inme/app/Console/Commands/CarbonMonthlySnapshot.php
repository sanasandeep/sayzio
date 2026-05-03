<?php

namespace App\Console\Commands;

use App\Modules\Common\Services\Carbon\CarbonEmissionsModel;
use App\Modules\Common\Services\Carbon\CarbonOffsetService;
use App\Modules\Common\Services\Carbon\CarbonSettingsResolver;
use App\Modules\User\Models\BiolinkCarbonSnapshot;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Monthly: estimate per-biolink CO2 for the prior calendar month and
 * (for opted-in links) auto-purchase offsets via the configured
 * provider. Idempotent on (link, period_start) — re-running for an
 * already-snapshotted month overwrites grams but preserves the offset
 * purchase row.
 */
class CarbonMonthlySnapshot extends Command
{
    protected $signature = 'carbon:snapshot-monthly
        {--month= : YYYY-MM (default: previous month)}
        {--link= : Optional link id to scope the run}
        {--dry-run : Compute and write snapshots but skip offset purchases}';

    protected $description = 'Snapshot prior-month carbon footprint and auto-offset opted-in biolinks.';

    public function __construct(
        private CarbonEmissionsModel $model,
        private CarbonSettingsResolver $settings,
        private CarbonOffsetService $offsets,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end   = $month->copy()->endOfMonth();

        // Carbon snapshots are a per-BIOLINK feature only. Short-link
        // / file / vcard / ics types redirect off-domain or download
        // a payload, so the SWD per-view bytes model doesn't apply
        // and offsetting them would misbill the workspace.
        $q = Link::query()->where('is_active', true)->where('type', 'biolink');
        if ($this->option('link')) $q->where('id', $this->option('link'));

        $written = 0; $offset = 0; $skipped = 0;

        $q->chunkById(200, function ($links) use ($start, $end, &$written, &$offset, &$skipped) {
            foreach ($links as $link) {
                $estimate = $this->model->estimateForLink($link, $start, $end);

                $snap = BiolinkCarbonSnapshot::query()->withoutGlobalScope('workspace')
                    ->updateOrCreate(
                        ['link_id' => $link->id, 'period_start' => $start->toDateString()],
                        [
                            'workspace_id'             => $link->workspace_id,
                            'period_end'               => $end->toDateString(),
                            'page_views'               => $estimate['page_views'],
                            'avg_bytes_per_view'       => $estimate['avg_bytes_per_view'],
                            'device_mix'               => $estimate['device_mix'],
                            'country_mix'              => $estimate['country_mix'],
                            'grid_intensity_g_per_kwh' => $estimate['grid_intensity_g_per_kwh'],
                            'grams_co2'                => $estimate['grams_co2'],
                            'model_breakdown'          => $estimate['model_breakdown'],
                            'model_version'            => $estimate['model_version'],
                        ]
                    );
                $written++;

                if ($this->option('dry-run'))                   { $skipped++; continue; }
                if (!$snap->workspace_id)                       { $skipped++; continue; }

                $ws = Workspace::find($snap->workspace_id);
                if (!$ws) { $skipped++; continue; }
                if (!($this->settings->effectiveFor($ws, $link)['enabled'] ?? false)) {
                    $skipped++; continue;
                }

                $purchase = $this->offsets->offsetSnapshot($snap);
                if ($purchase) $offset++;
            }
        });

        $this->info("Wrote {$written} snapshot(s); offset {$offset}; skipped {$skipped}.");
        return self::SUCCESS;
    }
}
