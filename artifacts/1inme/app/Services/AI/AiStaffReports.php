<?php

namespace App\Services\AI;

use App\Modules\User\Models\AiStaff;
use App\Modules\User\Models\AiStaffSuggestion;
use App\Modules\User\Models\WalletTransaction;

class AiStaffReports
{
    public function data(?int $userId, string $from, string $to): array
    {
        $staff = AiStaff::query()->when($userId, fn ($q) => $q->where('user_id', $userId))->get();
        $suggestions = AiStaffSuggestion::query()->when($userId, fn ($q) => $q->where('user_id', $userId))->whereBetween('created_at', [$from, $to]);
        $charges = WalletTransaction::where('type', 'spend')->where('meta->feature', 'like', 'ai_staff_%')->when($userId, fn ($q) => $q->where('user_id', $userId))->whereBetween('created_at', [$from, $to]);

        return ['staff' => $staff, 'staffCount' => $staff->count(), 'activeCount' => $staff->where('is_disabled', false)->count(), 'coins' => abs((int) (clone $charges)->sum('delta_coins')), 'chargeCount' => (clone $charges)->count(),
            'statuses' => (clone $suggestions)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'byFeature' => (clone $charges)->selectRaw("meta->>'feature' as feature, count(*) as charges, abs(sum(delta_coins)) as coins")->groupByRaw("meta->>'feature'")->get(),
            'byStaff' => (clone $charges)->selectRaw("meta->>'ai_staff_id' as staff_id, count(*) as charges, abs(sum(delta_coins)) as coins")->groupByRaw("meta->>'ai_staff_id'")->get(),
            'daily' => (clone $charges)->selectRaw('DATE(created_at) as day, count(*) as charges, abs(sum(delta_coins)) as coins')->groupByRaw('DATE(created_at)')->orderBy('day')->get(),
            'activity' => (clone $suggestions)->with('aiStaff')->latest()->paginate(30)->withQueryString(),
            'from' => $from, 'to' => $to];
    }
}
