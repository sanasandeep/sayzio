<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\ExternalReview;
use App\Modules\User\Models\Review;

/**
 * Computes a rating summary (average + per-star breakdown + counts) across
 * both native approved reviews and imported 3rd-party reviews for a creator,
 * optionally narrowed to the reviews left on a single link.
 */
class ReviewSummaryService
{
    /**
     * @return array{
     *   average: float, total: int, native: int, external: int,
     *   breakdown: array<int,int>, percent: array<int,int>
     * }
     * @param array<int,string> $providers When non-empty, imported reviews are
     *        restricted to these provider slugs so the summary matches a
     *        provider-filtered feed. Empty = all.
     */
    public function summary(int $userId, ?int $linkId = null, string $source = 'both', array $providers = []): array
    {
        $breakdown = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $sum = 0;
        $rated = 0;
        $native = 0;
        $external = 0;

        if ($source === 'native' || $source === 'both') {
            $q = Review::query()->public()->forUser($userId);
            if ($linkId !== null) {
                $q->where('link_id', $linkId);
            }
            $native = (clone $q)->count();
            foreach ((clone $q)->whereNotNull('rating')->get(['rating']) as $r) {
                $star = max(1, min(5, (int) $r->rating));
                $breakdown[$star]++;
                $sum += $star;
                $rated++;
            }
        }

        if ($source === 'external' || $source === 'both') {
            $q = ExternalReview::query()->forUser($userId);
            if (!empty($providers)) {
                $q->whereIn('provider', $providers);
            }
            $external = (clone $q)->count();
            foreach ((clone $q)->whereNotNull('rating')->get(['rating']) as $r) {
                $star = max(1, min(5, (int) $r->rating));
                $breakdown[$star]++;
                $sum += $star;
                $rated++;
            }
        }

        $average = $rated > 0 ? round($sum / $rated, 1) : 0.0;
        $total = $native + $external;

        $percent = [];
        foreach ($breakdown as $star => $count) {
            $percent[$star] = $rated > 0 ? (int) round(($count / $rated) * 100) : 0;
        }

        return [
            'average'   => $average,
            'total'     => $total,
            'native'    => $native,
            'external'  => $external,
            'rated'     => $rated,
            'breakdown' => $breakdown,
            'percent'   => $percent,
        ];
    }
}
