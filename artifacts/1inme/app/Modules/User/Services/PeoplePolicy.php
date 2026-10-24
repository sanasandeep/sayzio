<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\AttendanceParticipant;
use App\Modules\User\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PeoplePolicy
{
    public const LEAVE_TEMPLATES = [
        'annual' => ['name' => 'Annual leave', 'annual_days' => 12, 'paid' => 1, 'accrual_mode' => 'annual', 'accrual_days' => 1, 'accrual_every_days' => 15, 'opening_days' => 0],
        'earned' => ['name' => 'Earned leave', 'annual_days' => 24, 'paid' => 1, 'accrual_mode' => 'interval', 'accrual_days' => 1, 'accrual_every_days' => 15, 'opening_days' => 0],
        'sick' => ['name' => 'Sick leave', 'annual_days' => 6, 'paid' => 1, 'accrual_mode' => 'annual', 'accrual_days' => 1, 'accrual_every_days' => 15, 'opening_days' => 0],
        'casual' => ['name' => 'Casual leave', 'annual_days' => 6, 'paid' => 1, 'accrual_mode' => 'annual', 'accrual_days' => 1, 'accrual_every_days' => 15, 'opening_days' => 0],
        'unpaid' => ['name' => 'Unpaid leave', 'annual_days' => 30, 'paid' => 0, 'accrual_mode' => 'annual', 'accrual_days' => 1, 'accrual_every_days' => 15, 'opening_days' => 0],
    ];

    public function entitlement(object $type, AttendanceParticipant $person, int $year): float
    {
        if (($type->accrual_mode ?? 'annual') === 'annual') {
            return (float) $type->annual_days;
        }
        $ws = new Workspace;
        $ws->id = $person->workspace_id;
        $zone = app(AttendanceService::class)->policy($ws)['timezone'];
        $today = CarbonImmutable::now($zone)->startOfDay();
        $yearStart = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $zone);
        $end = $today->min($yearStart->endOfYear()->startOfDay());
        $joined = $person->employment_started_on;
        if (! $joined && $person->workspace_id > 0) {
            $joined = DB::table('workspace_members')->where('workspace_id', $person->workspace_id)->where('user_id', $person->user_id)->value('created_at');
            if (! $joined) {
                $joined = DB::table('workspaces')->where('id', $person->workspace_id)->value('created_at');
            }
        }
        if ($joined && str_contains((string) $joined, ' ')) {
            $joined = CarbonImmutable::parse($joined, 'UTC')->setTimezone($zone)->toDateString();
        }
        $start = $yearStart;
        foreach ([$joined ?: $person->created_at, $type->accrual_starts_on ?? null] as $date) {
            if ($date) {
                $start = $start->max(CarbonImmutable::parse($date, $zone)->startOfDay());
            }
        }
        if ($end->lt($start)) {
            return 0;
        }
        $periods = intdiv((int) $start->diffInDays($end), max(1, (int) $type->accrual_every_days));

        return round(min((float) $type->annual_days, (float) $type->opening_days + $periods * (float) $type->accrual_days), 2);
    }

    public function target(AttendanceParticipant $person, Workspace $ws): array
    {
        $p = app(AttendanceService::class)->policy($ws);
        $now = CarbonImmutable::now($p['timezone']);
        $weekly = $p['schedule_mode'] === 'weekly';
        $from = $weekly ? $now->startOfWeek(CarbonImmutable::MONDAY) : $now->startOfDay();
        $to = $weekly ? $from->addWeek() : $from->addDay();
        $seconds = app(AttendanceService::class)->workedSeconds($person->punches()->orderBy('occurred_at')->orderBy('id')->get(), $from, $to);
        $target = $weekly ? (int) $p['weekly_minutes'] : (in_array($now->dayOfWeek, $p['working_days']) ? (int) $p['daily_minutes'] : 0);

        return ['mode' => $p['schedule_mode'], 'period' => $weekly ? 'This week (Mon–Sun)' : 'Today', 'target_minutes' => $target, 'recorded_minutes' => intdiv($seconds, 60), 'remaining_minutes' => max(0, $target - intdiv($seconds, 60))];
    }
}
