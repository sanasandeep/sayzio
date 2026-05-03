<?php

namespace App\Modules\Common\Services\Carbon;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\PageSession;
use Illuminate\Support\Carbon as CarbonDate;

/**
 * Per-period carbon footprint estimator for a single biolink.
 *
 * Model: Sustainable Web Design v4 (simplified) —
 *
 *     grams_co2 = page_views
 *               × bytes_per_view
 *               × kwh_per_byte
 *               × grid_intensity_g_per_kwh
 *               × device_factor
 *
 * where:
 *   - bytes_per_view comes from a tier table keyed off the link's
 *     biolink complexity (block count + has_video). We deliberately
 *     don't call the public page during snapshotting — that would
 *     make the job network-bound and skew with cache state. The
 *     "Methodology QA" doc references real-byte sampling that should
 *     be used to recalibrate this table over time.
 *   - kwh_per_byte is SWD v4's published transmission constant.
 *   - device_factor weights mobile (efficient cellular & small
 *     screens) vs desktop (heavier render path) per device share.
 *
 * Returns BOTH the estimated grams and the breakdown the popover
 * surfaces, so the methodology page can show the math.
 */
class CarbonEmissionsModel
{
    public const MODEL_VERSION = 'swd-v4';

    /** SWD v4: 0.81 kWh/GB across full network. */
    public const KWH_PER_BYTE = 0.81 / 1_000_000_000.0;

    /** Energy-use factor relative to baseline (desktop=1.0). */
    public const DEVICE_FACTOR = [
        'desktop' => 1.00,
        'mobile'  => 0.75,
        'tablet'  => 0.85,
    ];

    /** Fallback bytes/view if the link's complexity isn't classifiable. */
    public const DEFAULT_BYTES_PER_VIEW = 1_500_000; // 1.5 MB

    /**
     * Bytes-per-view tier table keyed by active-block count band.
     * (Block-aware sizing is a coarse but stable proxy for page
     * weight; the QA doc explains how to recalibrate from sampled
     * real loads.)
     */
    public const COMPLEXITY_TIERS = [
        ['max_blocks' => 5,  'bytes' => 800_000],   // sparse landing
        ['max_blocks' => 12, 'bytes' => 1_400_000],
        ['max_blocks' => 25, 'bytes' => 2_400_000],
        ['max_blocks' => 999,'bytes' => 4_000_000], // heavy media-rich
    ];

    /**
     * Estimate one full calendar period for a link from PageSession
     * data. Returns a snapshot-ready payload.
     *
     * @return array{
     *   page_views:int, avg_bytes_per_view:int, device_mix:array,
     *   country_mix:array, grid_intensity_g_per_kwh:float,
     *   grams_co2:float, model_breakdown:array, model_version:string
     * }
     */
    public function estimateForLink(Link $link, CarbonDate $periodStart, CarbonDate $periodEnd): array
    {
        $sessions = PageSession::query()
            ->where('link_id', $link->id)
            ->whereBetween('started_at', [$periodStart->copy()->startOfDay(), $periodEnd->copy()->endOfDay()]);

        $views = (clone $sessions)->count();

        $deviceMix = (clone $sessions)
            ->selectRaw('device_type, COUNT(*) as c')
            ->groupBy('device_type')
            ->pluck('c', 'device_type')
            ->all();

        $countryMix = (clone $sessions)
            ->selectRaw('UPPER(country_code) as cc, COUNT(*) as c')
            ->whereNotNull('country_code')->where('country_code', '!=', '')
            ->groupBy('cc')
            ->orderByDesc('c')
            ->limit(20)
            ->pluck('c', 'cc')
            ->all();

        $bytes        = $this->bytesPerViewFor($link);
        $deviceFactor = $this->weightedDeviceFactor($deviceMix);
        $intensity    = GridIntensityTable::weightedAverage(
            !empty($countryMix) ? $countryMix : [GridIntensityTable::GLOBAL_AVG_G_PER_KWH > 0 ? 'XX' : '' => 1]
        );

        $kwh   = $views * $bytes * self::KWH_PER_BYTE * $deviceFactor;
        $grams = round($kwh * $intensity, 2);

        return [
            'page_views'               => $views,
            'avg_bytes_per_view'       => $bytes,
            'device_mix'               => $this->normaliseShare($deviceMix),
            'country_mix'              => $this->normaliseShare($countryMix),
            'grid_intensity_g_per_kwh' => $intensity,
            'grams_co2'                => $grams,
            'model_breakdown'          => [
                'kwh_per_byte'        => self::KWH_PER_BYTE,
                'device_factor_used'  => round($deviceFactor, 3),
                'kwh_total'           => round($kwh, 6),
                'bytes_per_view_tier' => $bytes,
                'block_count'         => (int) $link->biolinkBlocks()->where('is_active', true)->count(),
            ],
            'model_version' => self::MODEL_VERSION,
        ];
    }

    /** Bytes/view tier for a link's complexity. */
    public function bytesPerViewFor(Link $link): int
    {
        if ($link->type !== 'biolink') return self::DEFAULT_BYTES_PER_VIEW;
        $blocks = (int) $link->biolinkBlocks()->where('is_active', true)->count();
        foreach (self::COMPLEXITY_TIERS as $tier) {
            if ($blocks <= $tier['max_blocks']) return (int) $tier['bytes'];
        }
        return self::DEFAULT_BYTES_PER_VIEW;
    }

    public function weightedDeviceFactor(array $deviceMix): float
    {
        if (empty($deviceMix)) return self::DEVICE_FACTOR['desktop'];
        $total = array_sum($deviceMix);
        if ($total <= 0) return self::DEVICE_FACTOR['desktop'];
        $weighted = 0.0;
        foreach ($deviceMix as $device => $count) {
            $factor = self::DEVICE_FACTOR[strtolower((string) $device)] ?? self::DEVICE_FACTOR['desktop'];
            $weighted += $factor * ((int) $count / $total);
        }
        return $weighted;
    }

    private function normaliseShare(array $counts): array
    {
        $total = array_sum($counts);
        if ($total <= 0) return [];
        $out = [];
        foreach ($counts as $key => $count) {
            if (!$key) continue;
            $out[$key] = round(((int) $count / $total) * 100, 2);
        }
        return $out;
    }
}
