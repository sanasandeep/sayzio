<?php

namespace App\Modules\User\Controllers\Concerns;

use App\Modules\User\Models\AttendanceParticipant;
use App\Modules\User\Models\AttendancePunch;
use App\Modules\User\Models\AttendanceRequest;
use App\Modules\User\Services\AttendanceWorkforce;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

trait AttendanceSelfService
{
    public function saveShift(Request $request)
    {
        $ws = $this->workspace($request, 'manage');
        $base = $request->validate(['id' => 'nullable|integer', 'delete' => 'nullable|boolean', 'note' => 'required|string|min:3|max:2000']);
        $id = $base['id'] ?? null;
        if ($id) {
            abort_unless(DB::table('attendance_shifts')->where('workspace_id', $ws->id)->where('id', $id)->exists(), 404);
        }
        DB::transaction(function () use ($request, $base, $ws, $id) {
            if (! empty($base['delete'])) {
                abort_unless($id, 422);
                if (DB::table('attendance_rosters')->where('shift_id', $id)->exists()) {
                    DB::table('attendance_shifts')->where('id', $id)->update(['active' => false, 'updated_at' => now()]);
                } else {
                    DB::table('attendance_shifts')->where('id', $id)->delete();
                }$this->audit($ws, $request, 'shift', $id, ['decision' => 'removed', 'note' => $base['note']]);

                return;
            }
            $values = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('attendance_shifts', 'name')->where('workspace_id', $ws->id)->ignore($id)], 'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i', 'break_minutes' => 'required|integer|between:0,720', 'late_grace_minutes' => 'required|integer|between:0,180', 'early_grace_minutes' => 'required|integer|between:0,180', 'active' => 'required|boolean']);
            app(AttendanceWorkforce::class)->shiftBounds('2000-01-01', (object) $values, 'UTC');
            $before = $id ? DB::table('attendance_shifts')->where('id', $id)->first() : null;
            if ($id) {
                DB::table('attendance_shifts')->where('id', $id)->update($values + ['updated_at' => now()]);
            } else {
                $id = DB::table('attendance_shifts')->insertGetId($values + ['workspace_id' => $ws->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->audit($ws, $request, 'shift', $id, ['decision' => 'saved', 'note' => $base['note']], ['before' => $before, 'after' => $values]);
        });

        return back()->with('success', 'Shift template saved. Existing roster snapshots are unchanged.');
    }

    public function saveRoster(Request $request)
    {
        $ws = $this->workspace($request, 'manage');
        $data = $request->validate(['staff_ids' => 'required|array|min:1|max:100', 'staff_ids.*' => 'integer|distinct', 'kind' => 'required|in:work,off', 'shift_id' => ['nullable', 'required_if:kind,work', 'integer', Rule::exists('attendance_shifts', 'id')->where('workspace_id', $ws->id)->where('active', true)], 'starts_on' => 'required|date_format:Y-m-d', 'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on', 'weekdays' => 'nullable|array', 'weekdays.*' => 'integer|between:0,6', 'note' => 'required|string|min:3|max:2000']);
        $from = CarbonImmutable::parse($data['starts_on']);
        $to = CarbonImmutable::parse($data['ends_on']);
        abort_if($from->diffInDays($to) > 30 || ($from->diffInDays($to) + 1) * count($data['staff_ids']) > 1000, 422, 'Assign up to 31 days and 1,000 employee dates per batch.');
        $allowed = $this->directory($ws)->pluck('id')->map(fn ($v) => (int) $v)->all();
        foreach ($data['staff_ids'] as $id) {
            abort_unless(in_array((int) $id, $allowed, true), 404);
        }
        $shift = $data['kind'] === 'work' ? DB::table('attendance_shifts')->where('id', $data['shift_id'])->where('workspace_id', $ws->id)->first() : null;
        $zone = $this->attendance->policy($ws)['timezone'];
        DB::transaction(function () use ($request, $data, $ws, $from, $to, $shift, $zone) {
            $ids = $data['staff_ids'];
            sort($ids);
            foreach ($ids as $id) {
                $person = $this->attendance->participant($ws, (int) $id);
                $person = AttendanceParticipant::whereKey($person->id)->lockForUpdate()->firstOrFail();
                for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                    if (! empty($data['weekdays']) && ! in_array($day->dayOfWeek, array_map('intval', $data['weekdays']), true)) {
                        continue;
                    }
                    $old = DB::table('attendance_rosters')->where('participant_id', $person->id)->where('work_date', $day->toDateString())->first();
                    if ($old) {
                        abort_if(AttendancePunch::where('roster_id', $old->id)->exists(), 409, 'A roster with recorded attendance cannot be rewritten. Use attendance corrections.');
                    }
                    $values = ['kind' => $data['kind'], 'shift_id' => $shift?->id, 'shift_name' => $shift?->name, 'starts_at' => null, 'ends_at' => null, 'break_minutes' => $shift?->break_minutes ?? 0, 'late_grace_minutes' => $shift?->late_grace_minutes ?? 0, 'early_grace_minutes' => $shift?->early_grace_minutes ?? 0, 'updated_at' => now()];
                    if ($shift) {
                        [$start,$end] = app(AttendanceWorkforce::class)->shiftBounds($day->toDateString(), $shift, $zone);
                        $values['starts_at'] = $start;
                        $values['ends_at'] = $end;
                        $overlap = DB::table('attendance_rosters')->where('participant_id', $person->id)->where('kind', 'work')->when($old, fn ($q) => $q->where('id', '!=', $old->id))->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
                        abort_if($overlap, 422, 'The assignment overlaps another shift for this employee.');
                    }
                    if ($old) {
                        DB::table('attendance_rosters')->where('id', $old->id)->update($values);
                        $rosterId = $old->id;
                    } else {
                        $rosterId = DB::table('attendance_rosters')->insertGetId($values + ['workspace_id' => $ws->id, 'participant_id' => $person->id, 'work_date' => $day->toDateString(), 'created_at' => now()]);
                    }
                    $this->audit($ws, $request, 'roster', $rosterId, ['decision' => 'assigned', 'note' => $data['note']], ['before' => $old, 'after' => $values]);
                }
            }
        });

        return back()->with('success', 'Roster assignments saved.');
    }

    public function cancelRoster(Request $request, int $roster)
    {
        $ws = $this->workspace($request, 'manage');
        $data = $request->validate(['note' => 'required|string|min:3|max:2000']);
        DB::transaction(function () use ($request, $roster, $ws, $data) {
            $row = DB::table('attendance_rosters')->where('workspace_id', $ws->id)->where('id', $roster)->first();
            abort_unless($row, 404);
            AttendanceParticipant::whereKey($row->participant_id)->lockForUpdate()->firstOrFail();
            abort_if(AttendancePunch::where('roster_id', $row->id)->exists(), 409, 'A roster with recorded attendance cannot be cancelled.');
            DB::table('attendance_rosters')->where('id', $row->id)->update(['kind' => 'cancelled', 'updated_at' => now()]);
            $this->audit($ws, $request, 'roster', $row->id, ['decision' => 'cancelled', 'note' => $data['note']], ['before' => $row]);
        });

        return back()->with('success', 'Roster assignment cancelled.');
    }

    public function withdrawRequest(Request $request, int $attendanceRequest)
    {
        $ws = $this->workspace($request);
        abort_if($this->actorGuard() === 'web' && session()->has('impersonate_user_id'), 403);
        $person = $this->attendance->participant($ws, $this->actor($request)->id);
        $data = $request->validate(['note' => 'required|string|min:3|max:2000']);
        DB::transaction(function () use ($request, $person, $ws, $attendanceRequest, $data) {
            AttendanceParticipant::whereKey($person->id)->lockForUpdate()->firstOrFail();
            $row = AttendanceRequest::where('participant_id', $person->id)->whereKey($attendanceRequest)->lockForUpdate()->firstOrFail();
            abort_unless($row->status === 'pending', 409, 'Only pending requests can be withdrawn.');
            $row->update(['status' => 'withdrawn']);
            $this->audit($ws, $request, 'request', $row->id, ['decision' => 'withdrawn', 'note' => $data['note']]);
        });

        return back()->with('success', 'Request withdrawn and reserved leave released.');
    }

    public function settleEncashment(Request $request, int $attendanceRequest)
    {
        $ws = $this->workspace($request, 'manage');
        $data = $request->validate(['amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'], 'currency' => 'required|alpha|size:3', 'reference' => 'required|string|max:160', 'note' => 'required|string|min:3|max:2000']);
        $parts = explode('.', (string) $data['amount']);
        $minor = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        abort_if($minor < 1, 422, 'Settlement amount must be positive.');
        DB::transaction(function () use ($request, $attendanceRequest, $ws, $data, $minor) {
            $row = AttendanceRequest::whereHas('participant', fn ($q) => $q->where('workspace_id', $ws->id))->findOrFail($attendanceRequest);
            AttendanceParticipant::whereKey($row->participant_id)->lockForUpdate()->firstOrFail();
            $row = AttendanceRequest::whereKey($row->id)->lockForUpdate()->firstOrFail();
            abort_if($row->participant->user_id == $this->actor($request)->id, 403, 'Another manager must record your settlement.');
            abort_unless($row->type === 'encashment' && $row->status === 'approved' && ! $row->settled_at, 409, 'Only approved, unsettled encashments can be settled.');
            $row->update(['settlement_amount_minor' => $minor, 'settlement_currency' => strtoupper($data['currency']), 'settlement_reference' => $data['reference'], 'settled_at' => now()]);
            $this->audit($ws,$request,'request',$row->id,['decision' => 'settlement_recorded', 'note' => $data['note']],['amount_minor' => $minor, 'currency' => strtoupper($data['currency']), 'reference' => $data['reference']]);
        });

        return back()->with('success','External encashment settlement recorded. No payment was initiated by Sayzio.');
    }
}
