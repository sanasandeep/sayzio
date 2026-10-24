<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\AttendanceParticipant;
use App\Modules\User\Models\AttendancePunch;
use App\Modules\User\Models\AttendanceRequest;
use App\Modules\User\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AttendanceWorkforce
{
    public function shiftBounds(string $date, object $shift, string $zone): array
    {
        $start = CarbonImmutable::parse($date.' '.$shift->starts_at, $zone);
        $end = CarbonImmutable::parse($date.' '.$shift->ends_at, $zone);
        if ($end->lte($start)) {
            $end = $end->addDay();
        }
        abort_if((int) $shift->break_minutes >= $start->diffInMinutes($end), 422, 'The planned break must be shorter than the shift.');

        return [$start->utc(), $end->utc()];
    }

    public function rosterForCheckIn(AttendanceParticipant $person, Workspace $ws): ?object
    {
        $policy = app(AttendanceService::class)->policy($ws);
        $early = (int) $policy['early_checkin_minutes'];
        $roster = DB::table('attendance_rosters')->where('workspace_id', $ws->id)->where('participant_id', $person->id)->where('kind', 'work')->where('starts_at', '<=', now()->addMinutes($early))->where('ends_at', '>', now())->orderBy('starts_at')->first();
        if (!$roster && $policy['schedule_mode']==='fixed') {
            $local = CarbonImmutable::now($policy['timezone']);
            foreach ([$local->subDay(), $local] as $date) {
                if (!in_array($date->dayOfWeek,$policy['working_days']) || DB::table('attendance_holidays')->where('workspace_id',$ws->id)->where('date',$date->toDateString())->exists()) continue;
                [$start,$end] = $this->shiftBounds($date->toDateString(),(object)['starts_at'=>$policy['fixed_start'],'ends_at'=>$policy['fixed_end'],'break_minutes'=>0],$policy['timezone']);
                if ($local->lt($start->subMinutes($early)) || $local->gte($end)) continue;
                DB::table('attendance_rosters')->insertOrIgnore(['workspace_id'=>$ws->id,'participant_id'=>$person->id,'work_date'=>$date->toDateString(),'kind'=>'work','shift_name'=>'Fixed working hours','starts_at'=>$start->utc(),'ends_at'=>$end->utc(),'created_at'=>now(),'updated_at'=>now()]);
                $roster = DB::table('attendance_rosters')->where('participant_id',$person->id)->where('work_date',$date->toDateString())->where('kind','work')->where('starts_at','<=',now()->addMinutes($early))->where('ends_at','>',now())->first();
                if ($roster) break;
            }
        }
        abort_if(($policy['enforce_roster'] || $policy['schedule_mode']==='fixed') && ! $roster, 422, 'Clock-in requires a scheduled shift within its check-in window. Ask your manager to check your roster.');

        return $roster;
    }

    public function validateEncashment(int $scope, AttendanceParticipant $person, array &$data, ?int $exclude = null): void
    {
        if ($data['type'] !== 'encashment') {
            return;
        }
        $type = DB::table('attendance_leave_types')->where('workspace_id', $scope)->where('id', $data['leave_type_id'])->first();
        abort_unless($type, 404);
        abort_unless($type->encashment_enabled, 422, 'Encashment is not enabled for this leave type.');
        $year = (int) $data['leave_year'];
        $days = (float) $data['requested_days'];
        abort_if($days <= 0 || fmod($days, .5) !== 0.0, 422, 'Request whole or half days for encashment.');
        $base = AttendanceRequest::where('participant_id', $person->id)->where('leave_type_id', $type->id)->where('leave_year', $year)->whereIn('status', ['pending', 'approved'])->when($exclude, fn ($q) => $q->where('id', '!=', $exclude));
        abort_if((clone $base)->sum('requested_days') + $days > app(PeoplePolicy::class)->entitlement($type,$person,$year), 422, 'Encashment exceeds the available leave balance.');
        abort_if((clone $base)->where('type', 'encashment')->sum('requested_days') + $days > $type->max_encash_days, 422, 'Encashment exceeds the annual encashment limit.');
    }

    public function employeeData(Workspace $ws, AttendanceParticipant $person, bool $reviewer, bool $manager = false, array $filters = []): array
    {
        $zone = app(AttendanceService::class)->policy($ws)['timezone'];
        $today = CarbonImmutable::now($zone)->toDateString();
        $from = $filters['roster_from'] ?? CarbonImmutable::now($zone)->subDays(7)->toDateString();
        $to = $filters['roster_to'] ?? CarbonImmutable::now($zone)->addDays(45)->toDateString();
        abort_if(CarbonImmutable::parse($from)->gt(CarbonImmutable::parse($to)) || CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > 93, 422, 'Choose a roster period of up to 93 days.');
        $roster = DB::table('attendance_rosters')->where('workspace_id', $ws->id)->whereBetween('work_date', [$from, $to]);
        $own = (clone $roster)->where('participant_id', $person->id)->orderBy('work_date')->get();
        $team = ($reviewer || $manager) ? (clone $roster)->when(! empty($filters['roster_staff']), fn ($q) => $q->whereIn('participant_id', AttendanceParticipant::where('workspace_id', $ws->id)->where('user_id', $filters['roster_staff'])->select('id')))->orderBy('work_date')->orderBy('participant_id')->limit(500)->get() : collect();
        $rosterIds = $own->pluck('id')->merge($team->pluck('id'))->unique();
        $events = AttendancePunch::whereIn('roster_id', $rosterIds)->orderBy('occurred_at')->orderBy('id')->get()->groupBy('roster_id');
        $metrics = [];
        foreach ($own->merge($team)->unique('id') as $row) {
            $list = $events->get($row->id, collect());
            $first = $list->firstWhere('action', 'in');
            $last = $list->where('action', 'out')->last();
            $metrics[$row->id] = ['late_minutes' => $first && $row->starts_at ? max(0, (int) CarbonImmutable::parse($row->starts_at)->diffInMinutes($first->occurred_at, false) - $row->late_grace_minutes) : null,
                'early_minutes' => $last && $row->ends_at && CarbonImmutable::parse($row->ends_at)->isPast() ? max(0, (int) $last->occurred_at->diffInMinutes(CarbonImmutable::parse($row->ends_at), false) - $row->early_grace_minutes) : null];
        }
        $counts = AttendanceRequest::where('participant_id', $person->id)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return ['roster_from' => $from, 'roster_to' => $to, 'my_pending_count' => $counts['pending'] ?? 0, 'my_approved_count' => $counts['approved'] ?? 0, 'roster_metrics' => $metrics, 'shifts' => DB::table('attendance_shifts')->where('workspace_id', $ws->id)->orderBy('name')->get(), 'my_roster' => $own, 'team_roster' => $team,
            'my_requests' => AttendanceRequest::where('participant_id',$person->id)->latest()->limit(50)->get(),
            'my_balances' => app(AttendanceOperations::class)->leaveBalances($ws->id,$person->id,(int) substr($today,0,4))];
    }
}
