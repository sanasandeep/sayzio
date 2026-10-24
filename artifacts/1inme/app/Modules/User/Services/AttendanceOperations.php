<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\AttendanceParticipant;
use App\Modules\User\Models\AttendancePunch;
use App\Modules\User\Models\AttendanceRequest;
use App\Modules\User\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\IpUtils;

class AttendanceOperations
{
    public function checkPunchAccess(AttendanceParticipant $person, ?string $ip): void
    {
        abort_unless($person->attendance_enabled, 403, 'Attendance is disabled for this employee. Contact your manager.');
        if ($person->allowed_ips) {
            abort_unless($ip && IpUtils::checkIp($ip, $person->allowed_ips), 403, 'Attendance is restricted to the employee’s permitted IP addresses.');
        }
    }

    public function parseIps(?string $text): array
    {
        $items = array_values(array_unique(preg_split('/[\s,]+/', trim($text ?? ''), -1, PREG_SPLIT_NO_EMPTY)));
        if (count($items) > 30) {
            throw ValidationException::withMessages(['allowed_ips' => 'Use at most 30 IP addresses or CIDR ranges.']);
        }
        foreach ($items as $item) {
            $parts = explode('/', $item);
            $max = str_contains($parts[0], ':') ? 128 : 32;
            if (count($parts) > 2 || ! filter_var($parts[0], FILTER_VALIDATE_IP) || (isset($parts[1]) && (! ctype_digit($parts[1]) || (int) $parts[1] > $max))) {
                throw ValidationException::withMessages(['allowed_ips' => 'Invalid IP address or CIDR range: '.$item]);
            }
        }

        return $items;
    }

    public function leaveDays(int $scope, string $from, string $to): int
    {
        $a = CarbonImmutable::parse($from);
        $b = CarbonImmutable::parse($to);
        abort_if($a->diffInDays($b) > 366, 422, 'Split long leave requests into shorter periods.');
        $holidays = DB::table('attendance_holidays')->where('workspace_id', $scope)->whereBetween('date', [$from, $to])->pluck('date')->all();
        $days = 0;
        for ($day = $a; $day->lte($b); $day = $day->addDay()) {
            if (! $day->isWeekend() && ! in_array($day->toDateString(), $holidays)) {
                $days++;
            }
        }

        return $days;
    }

    public function leaveBalances(int $scope, int $participant, int $year): array
    {
        return DB::table('attendance_leave_types')->where('workspace_id', $scope)->orderBy('name')->get()->map(function ($type) use ($participant, $year) {
            $usage = AttendanceRequest::where('participant_id', $participant)->where('leave_type_id', $type->id)->where('leave_year', $year)->selectRaw('status, SUM(requested_days) as days')->groupBy('status')->pluck('days', 'status');

            return ['id' => $type->id, 'name' => $type->name, 'paid' => (bool) $type->paid, 'allowance' => $type->annual_days, 'approved' => (float) ($usage['approved'] ?? 0), 'pending' => (float) ($usage['pending'] ?? 0), 'remaining' => max(0, $type->annual_days - ($usage['approved'] ?? 0) - ($usage['pending'] ?? 0))];
        })->all();
    }

    public function validateLeave(int $scope, AttendanceParticipant $participant, array &$data, ?int $exclude = null): void
    {
        if ($data['type'] !== 'leave') {
            return;
        }
        if (empty($data['leave_type_id'])) {
            abort_if(DB::table('attendance_leave_types')->where('workspace_id', $scope)->exists(), 422, 'Select a leave type.');

            return;
        }
        $type = DB::table('attendance_leave_types')->where('workspace_id', $scope)->where('id', $data['leave_type_id'])->first();
        abort_unless($type, 404);
        $year = (int) substr($data['starts_on'], 0, 4);
        abort_if($year !== (int) substr($data['ends_on'], 0, 4), 422, 'Submit separate leave requests for each calendar year.');
        $days = $this->leaveDays($scope, $data['starts_on'], $data['ends_on']);
        abort_if($days === 0, 422, 'This period contains no working days. Weekends and holidays are excluded.');
        $used = AttendanceRequest::where('participant_id', $participant->id)->where('leave_type_id', $type->id)->where('leave_year', $year)->whereIn('status', ['pending', 'approved'])->when($exclude, fn ($q) => $q->where('id', '!=', $exclude))->sum('requested_days');
        abort_if($used + $days > $type->annual_days, 422, 'This request exceeds the remaining annual leave allowance.');
        $data['leave_year'] = $year;
        $data['requested_days'] = $days;
    }

    public function closeSession(AttendanceParticipant $participant, string $source, $time = null): ?AttendancePunch
    {
        if ($participant->state === 'out') {
            return null;
        }
        $punch = AttendancePunch::create(['participant_id' => $participant->id, 'action' => 'out', 'occurred_at' => $time ?? now(), 'source' => $source, 'review_status' => 'not_required']);
        $participant->update(['state' => 'out', 'auto_punch_out_at' => null]);

        return $punch;
    }

    public function autoOutDeadline(AttendanceParticipant $participant, Workspace $ws): ?CarbonImmutable
    {
        if (! $participant->auto_out_time) {
            return null;
        }
        $zone = app(AttendanceService::class)->policy($ws)['timezone'];
        $now = CarbonImmutable::now($zone);
        $cutoff = $now->setTimeFromTimeString($participant->auto_out_time);
        if ($cutoff->lte($now)) {
            $cutoff = $cutoff->addDay();
        }

return $cutoff->utc();
    }

    public function dashboard(Workspace $ws, ?int $person): array
    {
        $zone = app(AttendanceService::class)->policy($ws)['timezone'];
        $today = CarbonImmutable::now($zone)->startOfDay();
        $people = AttendanceParticipant::where('workspace_id', $ws->id)->when($person, fn ($q) => $q->where('id', $person))->get();
        $ids = $people->pluck('id');
        $pending = AttendanceRequest::whereIn('participant_id', $ids)->where('status', 'pending')->count();
        $reviews = AttendancePunch::whereIn('participant_id', $ids)->where('review_status', 'pending')->count();
        $punches = AttendancePunch::whereIn('participant_id', $ids)->where('occurred_at', '>=', $today->subDays(14)->utc())->orderBy('occurred_at')->orderBy('id')->get()->groupBy('participant_id');
        $anchorIds = AttendancePunch::whereIn('participant_id', $ids)->where('occurred_at', '<', $today->subDays(14)->utc())->selectRaw('MAX(id)')->groupBy('participant_id');
        $anchors = AttendancePunch::whereIn('id', $anchorIds)->get()->keyBy('participant_id');
        $chart = [];
        for ($day = $today->subDays(13); $day->lte($today); $day = $day->addDay()) {
            $seconds = 0;
            foreach ($people as $employee) {
                $events = $punches->get($employee->id, collect());
                $anchor = $anchors->get($employee->id);
                if ($anchor) {
                    $events = collect([$anchor])->concat($events);
                }$seconds += app(AttendanceService::class)->workedSeconds($events, $day, $day->addDay());
            }
            $chart[] = ['date' => $day->format('j M'), 'hours' => round($seconds / 3600,2)];
        }

        return ['working' => $people->where('state','working')->count(), 'break' => $people->where('state','break')->count(), 'pending' => $pending, 'reviews' => $reviews, 'chart' => $chart];
    }
}
