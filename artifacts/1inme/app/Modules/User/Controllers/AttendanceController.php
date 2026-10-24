<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Support\StaffNavigation;
use App\Modules\User\Controllers\Concerns\AttendanceManagement;
use App\Modules\User\Controllers\Concerns\AttendanceSelfService;
use App\Modules\User\Models\AttendanceParticipant;
use App\Modules\User\Models\AttendancePunch;
use App\Modules\User\Models\AttendanceRequest;
use App\Modules\User\Models\AttendanceReview;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\AttendanceOperations;
use App\Modules\User\Services\AttendanceReport;
use App\Modules\User\Services\AttendanceService;
use App\Modules\User\Services\AttendanceWorkforce;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    use AttendanceManagement;
    use AttendanceSelfService;

    public function __construct(private AttendanceService $attendance) {}

    protected function workspace(Request $request, string $ability = 'view'): Workspace
    {
        abort_if(! in_array($ability, ['view', 'export']) && session()->has('impersonate_user_id'), 403, 'Employee actions cannot be submitted while impersonating another user.');
        $ws = app('current_workspace');
        $member = $request->user()->membershipFor($ws);
        abort_unless((int) $ws->owner_user_id === (int) $this->actor($request)->id || ($member && ! $member->isSuspended()), 403);
        abort_unless($this->can($request, $ws, $ability), 403);

        return $ws;
    }

    protected function actor(Request $request)
    {
        return $request->user();
    }

    protected function can(Request $request, Workspace $ws, string $ability): bool
    {
        return $this->actor($request)->canInWorkspace($ws, 'attendance.'.$ability);
    }

    protected function routePrefix(): string
    {
        return 'user.attendance';
    }

    protected function layout(): string
    {
        return 'user.layouts.app';
    }

    protected function staffRelation(): string
    {
        return 'user';
    }

    protected function actorGuard(): string
    {
        return 'web';
    }

    protected function scopedPunch(Workspace $ws, int $id): AttendancePunch
    {
        return AttendancePunch::whereHas('participant', fn ($q) => $q->where('workspace_id', $ws->id))->findOrFail($id);
    }

    protected function directory(Workspace $ws)
    {
        return User::where(function ($q) use ($ws) {
            $q->where('id', $ws->owner_user_id)->orWhereIn('id', $ws->members()->whereNull('suspended_at')->select('user_id'));
        })->orderBy('name')->get(['id', 'name', 'email']);
    }

    public function index(Request $request)
    {
        $ws = $this->workspace($request);
        $policy = $this->attendance->policy($ws);
        $participant = $this->attendance->participant($ws, $this->actor($request)->id);
        $reviewer = $this->can($request, $ws, 'review');
        $canManage = $this->can($request, $ws, 'manage');
        $canExport = $this->can($request, $ws, 'export');
        $canRequest = $this->can($request, $ws, 'create');
        $canPunch = $this->can($request, $ws, 'create') && $participant->attendance_enabled;
        $area = $request->route('area', 'attendance');
        $tabs = $area === 'leave'
            ? ['requests' => 'Requests & approvals', 'balances' => 'My balances', 'encashments' => 'Encashments']
            : ['overview' => 'Overview', 'history' => 'Attendance records', 'self-service' => 'My workspace', 'requests' => 'Employee requests'];
        if ($area === 'leave' && $canManage) {
            $tabs['types'] = 'Leave types & allowances';
        }
        if ($area === 'attendance') {
            if ($canExport) {
                $tabs['reports'] = 'Reports';
            }
            $tabs['qr'] = 'QR clock-in';
            if ($canManage) {
                $tabs['policy'] = 'Policy';
            }
            $tabs['audit'] = 'Audit history';

        }
        if ($area === 'team') {
            $tabs = ($reviewer || $canManage) ? ['people' => $this->actorGuard() === 'admin' ? 'Internal staff' : 'Team members'] : [];
            $tabs += ['shifts' => 'Shifts & rosters', 'calendar' => 'Designations & holidays', 'notices' => 'Notices', 'access' => 'Roles & permissions'];
        }
        $tab = $request->query('tab', array_key_first($tabs));
        abort_unless(is_string($tab) && array_key_exists($tab, $tabs), 404);
        $isInternal = $this->actorGuard() === 'admin';
        $mayManageRoles = $isInternal ? $this->actor($request)->hasPermission('roles.view') : ((int) $ws->owner_user_id === (int) $this->actor($request)->id || $this->actor($request)->membershipFor($ws)?->role === 'admin');
        $directoryAccess = $isInternal ? (new StaffNavigation($this->actor($request)))->allows('admin.staff.index') : $mayManageRoles;
        $accessPermissions = collect(['create', 'review', 'manage', 'export'])->mapWithKeys(fn ($ability) => [$ability => $this->can($request, $ws, $ability)])->all();

        $query = AttendancePunch::with('participant.'.$this->staffRelation())->whereHas('participant', fn ($q) => $q->where('workspace_id', $ws->id)->when(! $reviewer, fn ($q) => $q->where('user_id', $this->actor($request)->id)));
        $filters = $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'staff' => 'nullable|integer|min:1']);
        if (! empty($filters['from'])) {
            $query->where('occurred_at', '>=', CarbonImmutable::parse($filters['from'], $policy['timezone'])->startOfDay()->utc());
        }
        if (! empty($filters['to'])) {
            $query->where('occurred_at', '<', CarbonImmutable::parse($filters['to'], $policy['timezone'])->addDay()->startOfDay()->utc());
        }
        if ($reviewer && ! empty($filters['staff'])) {
            $query->whereHas('participant', fn ($q) => $q->where('user_id', $filters['staff']));
        }
        $punches = $query->orderByDesc('occurred_at')->orderByDesc('id')->paginate(30)->withQueryString();
        $requests = AttendanceRequest::whereIn('type', $area === 'leave' ? ($tab === 'encashments' ? ['encashment'] : ['leave']) : ['correction', 'overtime', 'training', 'general'])->with('participant.'.$this->staffRelation())->whereHas('participant', fn ($q) => $q->where('workspace_id', $ws->id)->when(! $reviewer, fn ($q) => $q->where('user_id', $this->actor($request)->id)))->latest()->limit(50)->get();
        $today = CarbonImmutable::now($policy['timezone'])->startOfDay();
        $seconds = $this->attendance->workedSeconds($participant->punches()->orderBy('occurred_at')->orderBy('id')->get(), $today, $today->addDay());
        $reviews = AttendanceReview::where('workspace_id', $ws->id)->when(! $reviewer, fn ($q) => $q->where(function ($q) use ($participant) {
            $q->where(fn ($q) => $q->where('subject_type', 'punch')->whereIn('subject_id', $participant->punches()->select('id')))
                ->orWhere(fn ($q) => $q->where('subject_type', 'request')->whereIn('subject_id', AttendanceRequest::where('participant_id', $participant->id)->select('id')));
        }))->latest('created_at')->limit(30)->get();
        $directory = ($reviewer || $canManage) ? $this->directory($ws) : collect();
        $states = AttendanceParticipant::where('workspace_id', $ws->id)->pluck('state', 'user_id');
        $operations = $this->operationsData($request, $ws, $participant, $reviewer);
        $routePrefix = $this->routePrefix();
        $layout = $this->layout();

        return view('user.attendance.index', compact('area', 'tabs', 'tab', 'isInternal', 'mayManageRoles', 'directoryAccess', 'accessPermissions', 'canRequest', 'operations', 'routePrefix', 'layout', 'directory', 'states', 'ws', 'policy', 'participant', 'reviewer', 'canManage', 'canExport', 'canPunch', 'punches', 'requests', 'seconds', 'reviews'));
    }

    public function challenge(Request $request)
    {
        $ws = $this->workspace($request, 'create');
        abort_if($this->actorGuard() === 'web' && session()->has('impersonate_user_id'), 403, 'Attendance cannot be recorded while impersonating another user.');
        $data = $request->validate(['action' => ['required', Rule::in(AttendanceService::ACTIONS)], 'qr_token' => 'nullable|uuid']);
        $challenge = $this->attendance->challenge($this->attendance->participant($ws, $this->actor($request)->id), $data['action'], $request->ip(), $data['qr_token'] ?? null);

        return response()->json(['id' => $challenge->id, 'pose' => $challenge->pose, 'expires_at' => $challenge->expires_at->toIso8601String(),
            'photo_available' => (bool) config('attendance.evidence_bucket'),
            'require_photo' => $this->attendance->policy($ws)['require_photo'] && in_array($data['action'], ['in', 'out']),
            'require_location' => $this->attendance->policy($ws)['require_location'] || (bool) $this->attendance->policy($ws)['radius_m']]);
    }

    public function punch(Request $request)
    {
        $ws = $this->workspace($request, 'create');
        abort_if($this->actorGuard() === 'web' && session()->has('impersonate_user_id'), 403, 'Attendance cannot be recorded while impersonating another user.');
        $data = $request->validate(['challenge_id' => 'required|uuid', 'photo' => 'nullable|image|mimes:jpeg,png,webp|max:3072|dimensions:max_width=1920,max_height=1920',
            'captured_at' => 'nullable|date', 'latitude' => 'nullable|required_with:longitude,accuracy|numeric|between:-90,90', 'longitude' => 'nullable|required_with:latitude,accuracy|numeric|between:-180,180', 'accuracy' => 'nullable|required_with:latitude,longitude|numeric|between:0,100000']);
        $data['ip_address'] = $request->ip();
        $punch = $this->attendance->punch($this->attendance->participant($ws, $this->actor($request)->id), $ws, $data, $request->file('photo'));

        return response()->json(['id' => $punch->id, 'message' => 'Attendance saved.', 'received_at' => $punch->created_at->toIso8601String()]);
    }

    public function evidence(Request $request, int $punch)
    {
        $ws = $this->workspace($request);
        $record = $this->scopedPunch($ws, $punch);
        abort_unless($record->participant->user_id == $this->actor($request)->id || $this->can($request, $ws, 'review'), 403);
        abort_unless($record->photo_path, 404);

        $encrypted = Storage::disk('attendance_evidence')->get($record->photo_path);
        abort_unless($encrypted, 404);
        $bytes = Crypt::decryptString($encrypted);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp']), 422);

        return response($bytes, 200, ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function review(Request $request, int $punch)
    {
        $ws = $this->workspace($request, 'review');
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'note' => 'required|string|min:3|max:2000']);
        DB::transaction(function () use ($ws, $punch, $request, $data) {
            $record = $this->scopedPunch($ws, $punch);
            $record = AttendancePunch::whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_if($record->participant->user_id == $this->actor($request)->id, 403, 'A different reviewer must review your attendance.');
            abort_unless($record->review_status === 'pending', 409, 'This evidence was already reviewed or does not require review.');
            $record->update(['review_status' => $data['decision']]);
            $this->audit($ws, $request, 'punch', $record->id, $data);
        });

        return back()->with('success', 'Evidence review saved.');
    }

    public function settings(Request $request)
    {
        $ws = $this->workspace($request, 'manage');
        $data = $request->validate(['timezone' => 'required|timezone', 'require_photo' => 'required|boolean', 'require_location' => 'required|boolean',
            'latitude' => 'nullable|numeric|between:-90,90|required_with:radius_m', 'longitude' => 'nullable|numeric|between:-180,180|required_with:radius_m',
            'radius_m' => 'nullable|integer|between:50,100000', 'retention_days' => 'required|integer|between:7,365', 'enforce_roster' => 'nullable|boolean', 'early_checkin_minutes' => 'nullable|integer|between:0,720', 'auto_out_at_shift_end' => 'nullable|boolean', 'note' => 'required|string|min:3|max:2000']);
        abort_if($data['require_photo'] && ! config('attendance.evidence_bucket'), 422, 'Configure S3 storage before requiring selfies.');
        DB::transaction(function () use ($ws, $request, $data) {
            // Scope 0 belongs only to the internal admin directory; real workspace IDs start at 1.
            $values = collect($data)->except('note')->all();
            $values['require_photo'] = (bool) $data['require_photo'];
            $values['require_location'] = (bool) $data['require_location'];
            $previous = $this->attendance->policy($ws);
            $values = array_replace($previous, $values);
            $values['early_checkin_minutes'] = (int) ($values['early_checkin_minutes'] ?? 60);
            foreach (['enforce_roster', 'auto_out_at_shift_end'] as $flag) {
                $values[$flag] = (bool) $values[$flag];
            }
            DB::table('attendance_policies')->updateOrInsert(['workspace_id' => $ws->id], ['settings' => json_encode($values), 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($ws, $request, 'policy', $ws->id, ['decision' => 'updated', 'note' => $data['note']], ['before' => $previous, 'after' => $values]);
        });

        return back()->with('success', 'Attendance policy saved.');
    }

    public function storeRequest(Request $request)
    {
        $ws = $this->workspace($request, 'create');
        if ($request->input('type') === 'encashment') {
            $date = now($this->attendance->policy($ws)['timezone'])->toDateString();
            $request->merge(['starts_on' => $date, 'ends_on' => $date]);
        }
        $data = $request->validate(['type' => 'required|in:leave,correction,overtime,encashment,training,general', 'starts_on' => 'required|date_format:Y-m-d', 'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on', 'reason' => 'required|string|min:3|max:2000',
            'punch_id' => 'exclude_unless:type,correction|required|integer', 'proposed_at' => 'exclude_unless:type,correction|required|date', 'minutes' => 'exclude_unless:type,overtime|required|integer|between:1,1440',
            'leave_type_id' => 'exclude_unless:type,leave,encashment|nullable|required_if:type,encashment|integer', 'requested_days' => 'exclude_unless:type,encashment|required|numeric|between:0.5,366',
            'title' => 'exclude_unless:type,training,general|required|string|max:160', 'provider' => 'exclude_unless:type,training|nullable|string|max:160', 'training_url' => 'exclude_unless:type,training|nullable|url:http,https|max:1000', 'category' => 'exclude_unless:type,general|required|in:hr,document,equipment,other']);
        if ($data['type'] === 'encashment') {
            $data['leave_year'] = (int) substr($data['starts_on'], 0, 4);
        }
        if (in_array($data['type'], ['training', 'general'])) {
            $data['metadata'] = collect($data)->only(['title', 'provider', 'training_url', 'category'])->all();
            foreach (['title', 'provider', 'training_url', 'category'] as $key) {
                unset($data[$key]);
            }
        }

        abort_if($data['type'] === 'overtime' && $data['starts_on'] !== $data['ends_on'], 422, 'Submit overtime for one date at a time.');
        $participant = $this->attendance->participant($ws, $this->actor($request)->id);
        if ($data['type'] === 'correction') {
            $punch = $this->scopedPunch($ws, $data['punch_id']);
            abort_unless($punch->participant_id === $participant->id, 403);
            $proposed = CarbonImmutable::parse($data['proposed_at'])->utc();
            $data['proposed_at'] = $proposed;
            abort_if($proposed->isFuture() || $proposed->diffInDays($punch->occurred_at, true) > 7, 422, 'Correction must be in the past and within seven days of the original punch.');
        }
        DB::transaction(function () use ($participant, $data, $ws) {
            AttendanceParticipant::whereKey($participant->id)->lockForUpdate()->firstOrFail();
            app(AttendanceOperations::class)->validateLeave($ws->id, $participant, $data);
            app(AttendanceWorkforce::class)->validateEncashment($ws->id, $participant, $data);
            $existing = AttendanceRequest::where('participant_id', $participant->id)->where('type', $data['type'])->whereIn('status', ['pending', 'approved']);
            if ($data['type'] === 'correction') {
                $existing->where('punch_id', $data['punch_id']);
            } else {
                $existing->where('starts_on', '<=', $data['ends_on'])->where('ends_on', '>=', $data['starts_on']);
            }
            if (in_array($data['type'], ['training', 'general'])) {
                $existing->where('metadata->title', $data['metadata']['title']);
            }
            if ($data['type'] === 'encashment') {
                $existing->where('leave_type_id', $data['leave_type_id']);
            }
            abort_if($existing->exists(), 409, 'A pending or approved request already covers this event or date range.');
            AttendanceRequest::create(array_merge($data, ['participant_id' => $participant->id]));
        });

        return back()->with('success', 'Request submitted for review.');
    }

    public function decideRequest(Request $request, int $attendanceRequest)
    {
        $ws = $this->workspace($request, 'review');
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'note' => 'required|string|min:3|max:2000']);
        DB::transaction(function () use ($ws, $request, $attendanceRequest, $data) {
            $record = AttendanceRequest::whereHas('participant', fn ($q) => $q->where('workspace_id', $ws->id))->findOrFail($attendanceRequest);
            // All timeline mutations take the same participant lock as punches.
            AttendanceParticipant::whereKey($record->participant_id)->lockForUpdate()->firstOrFail();
            $record = AttendanceRequest::whereKey($record->id)->lockForUpdate()->firstOrFail();
            abort_if($record->participant->user_id == $this->actor($request)->id, 403, 'A different reviewer must decide your request.');
            abort_unless($record->status === 'pending', 409, 'Request already decided.');
            if ($data['decision'] === 'approved' && $record->type === 'leave') {
                $leave = $record->toArray();
                app(AttendanceOperations::class)->validateLeave($ws->id, $record->participant, $leave, $record->id);
                $record->fill(collect($leave)->only(['requested_days', 'leave_year'])->all());
            }
            if ($data['decision'] === 'approved' && $record->type === 'encashment') {
                $encashment = $record->toArray();
                app(AttendanceWorkforce::class)->validateEncashment($ws->id, $record->participant, $encashment, $record->id);
            }
            $details = [];
            if ($data['decision'] === 'approved' && $record->type === 'correction') {
                $punch = AttendancePunch::where('participant_id', $record->participant_id)->findOrFail($record->punch_id);
                $previous = AttendancePunch::where('participant_id', $record->participant_id)->where('id', '<', $punch->id)->orderByDesc('id')->first();
                $next = AttendancePunch::where('participant_id', $record->participant_id)->where('id', '>', $punch->id)->orderBy('id')->first();
                abort_if(($previous && $record->proposed_at->lte($previous->occurred_at)) || ($next && $record->proposed_at->gte($next->occurred_at)) || $record->proposed_at->isFuture(), 422, 'Correction would reorder attendance events.');
                $details = ['punch_id' => $punch->id, 'before' => $punch->occurred_at->toIso8601String(), 'after' => $record->proposed_at->toIso8601String()];
                $punch->update(['occurred_at' => $record->proposed_at]);
            }
            $record->update(['status' => $data['decision']]);
            $this->audit($ws, $request, 'request', $record->id, $data, $details);
        });

        return back()->with('success', 'Request decision saved.');
    }

    protected function audit(Workspace $ws, Request $request, string $type, int $id, array $data, array $details = []): void
    {
        AttendanceReview::create(['workspace_id' => $ws->id, 'actor_user_id' => $this->actor($request)->id, 'actor_guard' => $this->actorGuard(),
            'subject_type' => $type, 'subject_id' => $id, 'decision' => $data['decision'], 'note' => $data['note'], 'details' => $details, 'created_at' => now()]);
    }

    public function export(Request $request)
    {
        $ws = $this->workspace($request, 'export');
        $data = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'format' => 'nullable|in:csv,pdf,xlsx', 'mode' => 'nullable|in:daily,summary', 'staff' => 'nullable|integer|min:1']);
        $zone = $this->attendance->policy($ws)['timezone'];
        $from = CarbonImmutable::parse($data['from'], $zone)->startOfDay();
        $to = CarbonImmutable::parse($data['to'], $zone)->addDay()->startOfDay();
        abort_if($from->diffInDays($to) > 93, 422, 'Export up to 93 days at a time.');
        $reviewer = $this->can($request, $ws, 'review');
        if ($reviewer) {
            foreach ($this->directory($ws) as $employee) {
                $this->attendance->participant($ws, $employee->id);
            }
        }
        $people = AttendanceParticipant::with($this->staffRelation())->where('workspace_id', $ws->id)->when(! $reviewer, fn ($q) => $q->where('user_id', $this->actor($request)->id))->when(! empty($data['staff']), fn ($q) => $q->where('user_id', $data['staff']))->get();
        abort_if($people->count() * $from->diffInDays($to) > 50000, 422, 'Select fewer employees or a shorter report period.');
        $mode = $data['mode'] ?? 'daily';
        $format = $data['format'] ?? 'csv';
        $report = app(AttendanceReport::class);
        $rows = $report->rows($people, $from, $to, $zone, $mode);
        abort_if($format === 'pdf' && count($rows) > 3000, 422, 'PDF exports are limited to 3,000 rows. Use summary mode, a shorter period, or Excel/CSV.');
        $meta = ['scope' => $ws->name, 'from' => $data['from'], 'to' => $data['to'], 'timezone' => $zone, 'mode' => $mode];
        $bytes = $format === 'pdf' ? $report->pdf($rows, $meta) : ($format === 'xlsx' ? $report->xlsx($rows) : $report->csv($rows));
        $mime = ['csv' => 'text/csv; charset=UTF-8', 'pdf' => 'application/pdf', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'][$format];

        return response()->streamDownload(fn () => print ($bytes), 'attendance-'.$mode.'-'.$data['from'].'-'.$data['to'].'.'.$format, ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store']);
    }
}
