<?php

namespace App\Modules\User\Controllers\Concerns;

use App\Modules\User\Models\AttendanceParticipant;
use App\Modules\User\Models\AttendancePunch;
use App\Modules\User\Models\AttendanceRequest;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\AttendanceOperations;
use App\Modules\User\Services\AttendanceWorkforce;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

trait AttendanceManagement
{
    protected function operationsData(Request $request, Workspace $ws, AttendanceParticipant $participant, bool $reviewer): array
    {
        $zone = $this->attendance->policy($ws)['timezone'];
        $today = CarbonImmutable::now($zone)->toDateString();
        $op = app(AttendanceOperations::class);

        return ['workforce' => app(AttendanceWorkforce::class)->employeeData($ws, $participant, $reviewer, $this->can($request, $ws, 'manage'), $request->validate(['roster_from' => 'nullable|date_format:Y-m-d', 'roster_to' => 'nullable|date_format:Y-m-d', 'roster_staff' => 'nullable|integer|min:1'])), 'dashboard' => $op->dashboard($ws, $reviewer ? null : $participant->id),
            'designations' => DB::table('attendance_designations')->where('workspace_id', $ws->id)->orderBy('name')->get(),
            'holidays' => DB::table('attendance_holidays')->where('workspace_id', $ws->id)->where('date', '>=', substr($today, 0, 4).'-01-01')->orderBy('date')->limit(500)->get(),
            'notices' => DB::table('attendance_notices')->where('workspace_id', $ws->id)->where('starts_on', '<=', $today)->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today))->latest()->get(),
            'all_notices' => $this->can($request, $ws, 'manage') ? DB::table('attendance_notices')->where('workspace_id', $ws->id)->latest()->limit(50)->get() : collect(),
            'leave_types' => DB::table('attendance_leave_types')->where('workspace_id', $ws->id)->orderBy('name')->get(),
            'leave_balances' => $op->leaveBalances($ws->id, $participant->id, (int) substr($today, 0, 4)),
            'staff_groups' => $ws->id === 0 ? DB::table('admins')->pluck('role_id', 'id') : DB::table('workspace_members')->where('workspace_id', $ws->id)->pluck('role', 'user_id'),
            'profiles' => ($reviewer || $this->can($request, $ws, 'manage')) ? AttendanceParticipant::where('workspace_id', $ws->id)->get()->keyBy('user_id') : collect()];
    }

    public function saveEmployee(Request $request)
    {
        $ws = $this->workspace($request, 'manage');
        $data = $request->validate(['staff_ids' => 'required|array|min:1|max:100', 'staff_ids.*' => 'required|integer|distinct',
            'designation_id' => ['nullable', 'integer', Rule::exists('attendance_designations', 'id')->where('workspace_id', $ws->id)],
            'attendance_enabled' => 'nullable|boolean', 'auto_out_time' => 'nullable|date_format:H:i', 'allowed_ips' => 'nullable|string|max:2000',
            'employee_code' => 'nullable|string|max:80', 'display_name' => 'nullable|string|max:120', 'fields' => 'required|array|min:1', 'fields.*' => 'in:designation_id,attendance_enabled,auto_out_time,allowed_ips,employee_code,display_name', 'note' => 'required|string|min:3|max:2000']);
        $allowed = $this->directory($ws)->pluck('id')->map(fn ($v) => (int) $v)->all();
        foreach ($data['staff_ids'] as $id) {
            abort_unless(in_array((int) $id, $allowed, true), 404);
        }
        $values = [];
        foreach ($data['fields'] as $field) {
            $values[$field] = $data[$field] ?? null;
        }
        if (array_key_exists('allowed_ips', $values)) {
            $values['allowed_ips'] = app(AttendanceOperations::class)->parseIps($values['allowed_ips']);
        }
        if (array_key_exists('attendance_enabled', $values)) {
            $values['attendance_enabled'] = (bool) $values['attendance_enabled'];
        }
        abort_if(count($data['staff_ids']) > 1 && (array_key_exists('employee_code', $values) || array_key_exists('display_name', $values)), 422, 'Edit names and employee codes one employee at a time.');
        DB::transaction(function () use ($data, $values, $ws, $request) {
            $ids = $data['staff_ids'];
            sort($ids);
            foreach ($ids as $id) {
                $person = $this->attendance->participant($ws, (int) $id);
                $person = AttendanceParticipant::whereKey($person->id)->lockForUpdate()->firstOrFail();
                $before = $person->only(array_keys($values));
                if (isset($values['attendance_enabled']) && ! $values['attendance_enabled']) {
                    app(AttendanceOperations::class)->closeSession($person, 'system_disabled');
                }
                $person->fill($values);
                if (array_key_exists('auto_out_time', $values) && $person->state !== 'out') {
                    $person->auto_punch_out_at = app(AttendanceOperations::class)->autoOutDeadline($person, $ws);
                }
                $person->save();
                $this->audit($ws, $request, 'employee', $person->id, ['decision' => 'updated', 'note' => $data['note']], ['before' => $before, 'after' => $values]);
            }
        });

        return back()->with('success', 'Employee attendance settings saved.');
    }

    public function saveResource(Request $request)
    {
        $ws = $this->workspace($request, 'manage');
        $kind = $request->validate(['kind' => 'required|in:designation,holiday,notice,leave_type'])['kind'];
        $tables = ['designation' => 'attendance_designations', 'holiday' => 'attendance_holidays', 'notice' => 'attendance_notices', 'leave_type' => 'attendance_leave_types'];
        $table = $tables[$kind];
        $base = $request->validate(['id' => 'nullable|integer', 'delete' => 'nullable|boolean', 'note' => 'required|string|min:3|max:2000']);
        $id = $base['id'] ?? null;
        if ($id) {
            abort_unless(DB::table($table)->where('workspace_id', $ws->id)->where('id', $id)->exists(), 404);
        }
        DB::transaction(function () use ($request, $ws, $kind, $table, $base, $id) {
            if (! empty($base['delete'])) {
                abort_unless($id, 422);
                if ($kind === 'leave_type') {
                    abort_if(AttendanceRequest::where('leave_type_id', $id)->exists(), 409, 'A leave type used by requests cannot be deleted. Rename it instead.');
                }
                if ($kind === 'designation') {
                    AttendanceParticipant::where('workspace_id', $ws->id)->where('designation_id', $id)->update(['designation_id' => null]);
                }
                DB::table($table)->where('workspace_id', $ws->id)->where('id', $id)->delete();
                $this->audit($ws, $request, $kind, $id, ['decision' => 'deleted', 'note' => $base['note']]);

                return;
            }
            if ($kind === 'notice') {
                $values = $request->validate(['title' => 'required|string|max:160', 'body' => 'required|string|max:10000', 'starts_on' => 'required|date_format:Y-m-d', 'ends_on' => 'nullable|date_format:Y-m-d|after_or_equal:starts_on']);
            } elseif ($kind === 'holiday') {
                $values = $request->validate(['name' => 'required|string|max:120', 'starts_on' => 'required|date_format:Y-m-d', 'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on', 'weekdays' => 'nullable|array', 'weekdays.*' => 'integer|between:0,6']);
            } else {
                $rules = ['name' => ['required', 'string', 'max:120', Rule::unique($table, 'name')->where('workspace_id', $ws->id)->ignore($id)]];
                if ($kind === 'leave_type') {
                    $rules += ['annual_days' => 'required|integer|between:0,366', 'paid' => 'required|boolean', 'encashment_enabled' => 'nullable|boolean', 'max_encash_days' => 'nullable|integer|between:0,366'];
                }$values = $request->validate($rules);
            }
            if ($kind === 'leave_type' && empty($base['delete'])) {
                $values['encashment_enabled'] = (bool) ($values['encashment_enabled'] ?? false);
                $values['max_encash_days'] = (int) ($values['max_encash_days'] ?? 0);
                abort_if($values['encashment_enabled'] && ($values['max_encash_days'] < 1 || $values['max_encash_days'] > $values['annual_days']), 422, 'The encashment limit must be between one day and the annual allowance.');
            }
            if ($kind === 'holiday') {
                $from = CarbonImmutable::parse($values['starts_on']);
                $to = CarbonImmutable::parse($values['ends_on']);
                abort_if($from->diffInDays($to) > 366, 422, 'Create at most one year of holidays at a time.');
                if ($id) {
                    DB::table($table)->where('id', $id)->where('workspace_id', $ws->id)->delete();
                }
                for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                    if (! empty($values['weekdays']) && ! in_array($day->dayOfWeek, array_map('intval', $values['weekdays']), true)) {
                        continue;
                    }
                    DB::table($table)->updateOrInsert(['workspace_id' => $ws->id, 'date' => $day->toDateString()], ['name' => $values['name'], 'created_at' => now(), 'updated_at' => now()]);
                }
            } else {
                if ($kind === 'notice') {
                    $values += ['author_id' => $this->actor($request)->id, 'author_guard' => $this->actorGuard()];
                }
                $values += ['workspace_id' => $ws->id, 'updated_at' => now()];
                if ($id) {
                    DB::table($table)->where('id', $id)->where('workspace_id', $ws->id)->update($values);
                } else {
                    $id = DB::table($table)->insertGetId($values + ['created_at' => now()]);
                }
            }
            $this->audit($ws, $request, $kind, $id ?? $ws->id, ['decision' => 'saved', 'note' => $base['note']], $values);
        });

        return back()->with('success', 'Attendance setup updated.');
    }

    public function bulkReview(Request $request)
    {
        $this->workspace($request, 'review');
        $data = $request->validate(['kind' => 'required|in:punch,request', 'ids' => 'required|array|min:1|max:100', 'ids.*' => 'integer|distinct', 'decision' => 'required|in:approved,rejected,shift', 'shift_minutes' => 'nullable|required_if:decision,shift|integer|between:-1440,1440', 'note' => 'required|string|min:3|max:2000']);
        if ($data['decision'] === 'shift') {
            return $this->shiftPunches($request, $data);
        }
        DB::transaction(function () use ($request, $data) {
            $ids = $data['ids'];
            sort($ids);
            foreach ($ids as $id) {
                if ($data['kind'] === 'punch') {
                    $this->review($request, (int) $id);
                } else {
                    $this->decideRequest($request, (int) $id);
                }
            }
        });

        return back()->with('success', 'Selected records reviewed. Every decision is recorded in the audit history.');
    }

    protected function shiftPunches(Request $request, array $data)
    {
        $ws = $this->workspace($request, 'review');
        abort_unless($data['kind'] === 'punch', 422, 'Only punch times can be shifted.');
        DB::transaction(function () use ($request, $data, $ws) {
            $records = AttendancePunch::whereIn('id', $data['ids'])->whereHas('participant', fn ($q) => $q->where('workspace_id', $ws->id))->get();
            abort_unless($records->count() === count($data['ids']), 404);
            $people = AttendanceParticipant::whereIn('id', $records->pluck('participant_id')->unique())->orderBy('id')->lockForUpdate()->get();
            foreach ($people as $person) {
                abort_if((int) $person->user_id === (int) $this->actor($request)->id, 403, 'Another reviewer must modify your attendance.');
                $timeline = $person->punches()->orderBy('id')->lockForUpdate()->get();
                $previous = null;
                foreach ($timeline as $punch) {
                    $selected = in_array($punch->id, array_map('intval', $data['ids']), true);
                    $time = $selected ? $punch->occurred_at->addMinutes((int) $data['shift_minutes']) : $punch->occurred_at;
                    abort_if(($previous && $time->lt($previous)) || $time->isFuture(), 422, 'The change would reorder attendance events or create a future punch.');
                    $previous = $time;
                    if ($selected) {
                        $before = $punch->occurred_at->toIso8601String();
                        $punch->update(['occurred_at' => $time]);
                        $this->audit($ws, $request, 'punch', $punch->id, ['decision' => 'modified', 'note' => $data['note']], ['before' => $before, 'after' => $time->toIso8601String(), 'shift_minutes' => (int) $data['shift_minutes']]);
                    }
                }
            }
        });

        return back()->with('success', 'Selected punch times modified. Original receipt times and evidence remain unchanged.');
    }

    public function qrSession(Request $request)
    {
        $ws = $this->workspace($request, 'manage');
        $id = (string) Str::uuid();
        $expiry = now()->addSeconds(90);
        DB::table('attendance_qr_sessions')->insert(['id' => $id, 'workspace_id' => $ws->id, 'issuer_id' => $this->actor($request)->id, 'issuer_guard' => $this->actorGuard(), 'expires_at' => $expiry, 'created_at' => now(), 'updated_at' => now()]);
        $url = route($this->routePrefix().'.scan', ['token' => $id]);
        $svg = QrCode::format('svg')->size(240)->margin(2)->generate($url)->__toString();

        return response()->json(['url' => $url, 'svg' => $svg, 'expires_at' => $expiry->toIso8601String()]);
    }

    public function scan(Request $request, string $token)
    {
        $ws = $this->workspace($request, 'create');
        abort_unless(Str::isUuid($token), 404);
        $person = $this->attendance->participant($ws, $this->actor($request)->id);
        $this->attendance->qrSession($token, $person);
        $request->attributes->set('attendance_qr_token', $token);

        return $this->index($request);
    }
}
