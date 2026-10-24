<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AttendanceParticipant;
use App\Modules\User\Models\AttendancePunch;
use App\Modules\User\Models\AttendanceRequest;
use App\Modules\User\Models\AttendanceReview;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance) {}

    protected function workspace(Request $request, string $ability = 'view'): Workspace
    {
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

    private function scopedPunch(Workspace $ws, int $id): AttendancePunch
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
        $canPunch = $this->can($request, $ws, 'create');
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
        $requests = AttendanceRequest::with('participant.'.$this->staffRelation())->whereHas('participant', fn ($q) => $q->where('workspace_id', $ws->id)->when(! $reviewer, fn ($q) => $q->where('user_id', $this->actor($request)->id)))->latest()->limit(50)->get();
        $today = CarbonImmutable::now($policy['timezone'])->startOfDay();
        $seconds = $this->attendance->workedSeconds($participant->punches()->orderBy('occurred_at')->orderBy('id')->get(), $today, $today->addDay());
        $reviews = AttendanceReview::where('workspace_id', $ws->id)->when(! $reviewer, fn ($q) => $q->where(function ($q) use ($participant) {
            $q->where(fn ($q) => $q->where('subject_type', 'punch')->whereIn('subject_id', $participant->punches()->select('id')))
                ->orWhere(fn ($q) => $q->where('subject_type', 'request')->whereIn('subject_id', AttendanceRequest::where('participant_id', $participant->id)->select('id')));
        }))->latest('created_at')->limit(30)->get();
        $directory = $reviewer ? $this->directory($ws) : collect();
        $states = AttendanceParticipant::where('workspace_id', $ws->id)->pluck('state', 'user_id');
        $routePrefix = $this->routePrefix();
        $layout = $this->layout();

        return view('user.attendance.index', compact('routePrefix', 'layout', 'directory', 'states', 'ws', 'policy', 'participant', 'reviewer', 'canManage', 'canExport', 'canPunch', 'punches', 'requests', 'seconds', 'reviews'));
    }

    public function challenge(Request $request)
    {
        $ws = $this->workspace($request, 'create');
        $data = $request->validate(['action' => ['required', Rule::in(AttendanceService::ACTIONS)]]);
        $challenge = $this->attendance->challenge($this->attendance->participant($ws, $this->actor($request)->id), $data['action']);

        return response()->json(['id' => $challenge->id, 'pose' => $challenge->pose, 'expires_at' => $challenge->expires_at->toIso8601String(),
            'photo_available' => (bool) config('attendance.evidence_bucket'),
            'require_photo' => $this->attendance->policy($ws)['require_photo'] && in_array($data['action'], ['in', 'out']),
            'require_location' => $this->attendance->policy($ws)['require_location'] || (bool) $this->attendance->policy($ws)['radius_m']]);
    }

    public function punch(Request $request)
    {
        $ws = $this->workspace($request, 'create');
        $data = $request->validate(['challenge_id' => 'required|uuid', 'photo' => 'nullable|image|mimes:jpeg,png,webp|max:3072|dimensions:max_width=1920,max_height=1920',
            'captured_at' => 'nullable|date', 'latitude' => 'nullable|required_with:longitude,accuracy|numeric|between:-90,90', 'longitude' => 'nullable|required_with:latitude,accuracy|numeric|between:-180,180', 'accuracy' => 'nullable|required_with:latitude,longitude|numeric|between:0,100000']);
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
            'radius_m' => 'nullable|integer|between:50,100000', 'retention_days' => 'required|integer|between:7,365', 'note' => 'required|string|min:3|max:2000']);
        abort_if($data['require_photo'] && ! config('attendance.evidence_bucket'), 422, 'Configure S3 storage before requiring selfies.');
        DB::transaction(function () use ($ws, $request, $data) {
            // Scope 0 belongs only to the internal admin directory; real workspace IDs start at 1.
            $values = collect($data)->except('note')->all();
            $values['require_photo'] = (bool) $data['require_photo'];
            $values['require_location'] = (bool) $data['require_location'];
            $previous = $this->attendance->policy($ws);
            DB::table('attendance_policies')->updateOrInsert(['workspace_id' => $ws->id], ['settings' => json_encode($values), 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($ws, $request, 'policy', $ws->id, ['decision' => 'updated', 'note' => $data['note']], ['before' => $previous, 'after' => $values]);
        });

        return back()->with('success', 'Attendance policy saved.');
    }

    public function storeRequest(Request $request)
    {
        $ws = $this->workspace($request, 'create');
        $data = $request->validate(['type' => 'required|in:leave,correction,overtime', 'starts_on' => 'required|date_format:Y-m-d', 'ends_on' => 'required|date_format:Y-m-d|after_or_equal:starts_on',
            'reason' => 'required|string|min:3|max:2000', 'punch_id' => 'exclude_unless:type,correction|required|integer',
            'proposed_at' => 'exclude_unless:type,correction|required|date', 'minutes' => 'exclude_unless:type,overtime|required|integer|between:1,1440']);
        abort_if($data['type'] === 'overtime' && $data['starts_on'] !== $data['ends_on'], 422, 'Submit overtime for one date at a time.');
        $participant = $this->attendance->participant($ws, $this->actor($request)->id);
        if ($data['type'] === 'correction') {
            $punch = $this->scopedPunch($ws, $data['punch_id']);
            abort_unless($punch->participant_id === $participant->id, 403);
            $proposed = CarbonImmutable::parse($data['proposed_at'])->utc();
            $data['proposed_at'] = $proposed;
            abort_if($proposed->isFuture() || $proposed->diffInDays($punch->occurred_at, true) > 7, 422, 'Correction must be in the past and within seven days of the original punch.');
        }
        DB::transaction(function () use ($participant, $data) {
            AttendanceParticipant::whereKey($participant->id)->lockForUpdate()->firstOrFail();
            $existing = AttendanceRequest::where('participant_id', $participant->id)->where('type', $data['type'])->whereIn('status', ['pending', 'approved']);
            if ($data['type'] === 'correction') {
                $existing->where('punch_id', $data['punch_id']);
            } else {
                $existing->where('starts_on', '<=', $data['ends_on'])->where('ends_on', '>=', $data['starts_on']);
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

    private function audit(Workspace $ws, Request $request, string $type, int $id, array $data, array $details = []): void
    {
        AttendanceReview::create(['workspace_id' => $ws->id, 'actor_user_id' => $this->actor($request)->id, 'actor_guard' => $this->actorGuard(),
            'subject_type' => $type, 'subject_id' => $id, 'decision' => $data['decision'], 'note' => $data['note'], 'details' => $details, 'created_at' => now()]);
    }

    public function export(Request $request)
    {
        $ws = $this->workspace($request, 'export');
        $data = $request->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        $zone = $this->attendance->policy($ws)['timezone'];
        $from = CarbonImmutable::parse($data['from'], $zone)->startOfDay();
        $to = CarbonImmutable::parse($data['to'], $zone)->addDay()->startOfDay();
        abort_if($from->diffInDays($to) > 93, 422, 'Export up to 93 days at a time.');
        $reviewer = $this->can($request, $ws, 'review');
        $participants = AttendanceParticipant::with($this->staffRelation())->where('workspace_id', $ws->id)->when(! $reviewer, fn ($q) => $q->where('user_id', $this->actor($request)->id))->get();

        return response()->streamDownload(function () use ($participants, $from, $to, $zone) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Staff', 'Email', 'Date', 'Timezone', 'Recorded hours', 'Approved overtime minutes', 'Approved leave requests'], ',', '"', '');
            foreach ($participants as $participant) {
                $punches = $participant->punches()->where('occurred_at', '<', $to->utc())->orderBy('occurred_at')->orderBy('id')->get();
                $requests = AttendanceRequest::where('participant_id', $participant->id)->where('status', 'approved')->where('starts_on', '<', $to->toDateString())->where('ends_on', '>=', $from->toDateString())->get();
                for ($day = $from; $day->lt($to); $day = $day->addDay()) {
                    $date = $day->toDateString();
                    $safe = fn ($value) => preg_match('/^[=+@\-\t\r\n]/u', (string) $value) ? "'".$value : $value;
                    $hours = round($this->attendance->workedSeconds($punches, $day, $day->addDay()) / 3600, 2);
                    fputcsv($file, array_map($safe, [$participant->staff?->name, $participant->staff?->email, $date, $zone, $hours,
                        $requests->where('type', 'overtime')->where('starts_on', $date)->sum('minutes'),
                        $requests->where('type', 'leave')->filter(fn ($r) => $r->starts_on <= $date && $r->ends_on >= $date)->count()]), ',', '"', '');
                }
            }
            fclose($file);
        }, 'attendance-'.$data['from'].'-'.$data['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
