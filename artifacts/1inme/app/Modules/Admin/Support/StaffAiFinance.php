<?php

namespace App\Modules\Admin\Support;

use App\Modules\Admin\Models\Admin;
use App\Services\AI\AiFeatureCatalog;

final class StaffAiFinance
{
    public static function apply($query, Admin $actor, string $feature, string $mode)
    {
        abort_unless(in_array($feature, AiFeatureCatalog::FEATURES, true) && in_array($mode, ['totals', 'records'], true), 422);
        abort_unless($actor->hasPermission('finance.ai.'.$feature.'.'.$mode), 403);
        $query->where('meta->ai', true)->where(function ($q) use ($feature) {
            $q->where('meta->feature', $feature)->orWhereRaw("substr(meta->>'feature', 1, ?) = ?", [strlen($feature) + 1, $feature.'.']);
        });
        // A separately catalogued subfeature needs its own grant. In particular,
        // strategy access must not disclose chat/report spending by prefix.
        foreach (AiFeatureCatalog::FEATURES as $other) {
            if (str_starts_with($other, $feature.'.')) {
                $query->where('meta->feature', '!=', $other)->whereRaw("substr(meta->>'feature', 1, ?) != ?", [strlen($other) + 1, $other.'.']);
            }
        }
        return $query;
    }

}
