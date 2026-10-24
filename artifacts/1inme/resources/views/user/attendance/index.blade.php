@extends($layout)
@section('title', 'Attendance, Shifts & Leave')
@section('content')
<style>
.att{max-width:1300px;margin:auto;color:var(--text-primary)}.att h1{font-size:30px;font-weight:750;letter-spacing:-1px}.att h2{font-size:18px;font-weight:700}.att p,.att small{color:var(--text-muted)}.att .card{background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:18px;padding:24px;margin-bottom:20px}.att .row{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.att .between{justify-content:space-between}.att .grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.att .metric{font-size:32px;font-weight:700;letter-spacing:-1px}.att .badge{padding:5px 10px;border-radius:20px;background:var(--bg-hover);font-size:12px}.att button,.att .action{padding:10px 16px;border:1px solid var(--border-subtle);border-radius:10px;background:var(--bg-hover);font-weight:600}.att button.primary{background:var(--accent,#3d6bff);color:white;border-color:transparent}.att button:disabled{opacity:.45;cursor:not-allowed}.att label{display:flex;flex-direction:column;gap:6px;font-size:13px}.att input,.att select,.att textarea{background:var(--bg-input,var(--bg-card));border:1px solid var(--border-subtle);border-radius:9px;padding:9px;color:var(--text-primary);min-width:0;width:100%}.att textarea{min-height:70px}.att .fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:16px 0}.att table{width:100%;border-collapse:collapse;font-size:13px}.att th,.att td{text-align:left;padding:14px 10px;border-bottom:1px solid var(--border-subtle);vertical-align:top}.att th{color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.06em}.att .scroll{overflow-x:auto}.att .note{padding:14px;border-radius:10px;background:var(--bg-hover);margin:12px 0;font-size:13px}.att details{margin-top:12px}.att summary{cursor:pointer;font-weight:600}.att dialog{background:var(--bg-card,#fff);color:var(--text-primary,#182033);border:1px solid var(--border-subtle,#ccd);border-radius:20px;padding:24px;width:min(520px,94vw);max-height:90vh;overflow:auto;margin:auto}.att dialog::backdrop{background:#050b20b3}.att video,.att .capture-photo{width:100%;max-height:320px;object-fit:contain;border-radius:12px;background:#111;margin:12px 0}.att [hidden]{display:none!important}.att .evidence{width:180px;border-radius:10px;margin-top:8px}.att .error{color:#dc2626}.att .muted{font-size:12px;color:var(--text-muted)}.att .space{margin-top:16px}@media(max-width:700px){.att .grid{grid-template-columns:1fr}.att .card{padding:16px}.att h1{font-size:26px}.att td,.att th{padding:10px 7px}}
.att input[type=checkbox]{width:auto}.att-chart{overflow-x:auto;padding-top:18px}.att-bars{display:flex;gap:12px;align-items:flex-end;min-width:600px;min-height:155px}.att-chart-day{flex:1;text-align:center;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:6px}.att-chart-day span{font-size:10px}.att-chart-bar{background:var(--accent,#3d6bff);width:75%;border-radius:5px 5px 0 0}.att-chart-day small{font-size:10px;white-space:nowrap}.att nav a{padding:6px 10px;border-radius:8px;background:var(--bg-hover)}.att section[id]{scroll-margin-top:90px}@media(max-width:700px){.att #att-calendar .grid>div{grid-column:auto!important}}
</style>
<div class="att" id="attendance-app" data-index-url="{{ route($routePrefix.'.index') }}" data-qr-session-url="{{ route($routePrefix.'.qr-session') }}" data-scan-base="{{ route($routePrefix.'.scan', ['token'=>'__TOKEN__']) }}" data-qr-token="{{ request()->attributes->get('attendance_qr_token', '') }}" data-challenge-url="{{ route($routePrefix.'.challenge') }}" data-punch-url="{{ route($routePrefix.'.punch') }}">
    <div class="row between" style="margin-bottom:24px">
<div>
<small>PEOPLE &amp; OPERATIONS · {{ $ws->name }}</small>
<h1>Attendance, Shifts &amp; Leave</h1>
<p>Your working day, recorded clearly.</p>
</div>
<span class="badge">{{ $policy['timezone'] }}</span>
</div>
    @if(session('success'))<div class="note" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="note error" role="alert">{{ $errors->first() }}</div>@endif
    @if($canManage && !config('attendance.evidence_bucket'))<div class="note" role="status">Standard clock-in/out is ready. To enable pose selfies, an operator must configure the existing S3 user-file connection, then require photos in Attendance policy. Attendance photos are encrypted before upload.</div>@endif
    <div class="grid">
        <div class="card">
<small>YOUR STATUS</small>
<div class="metric">{{ ['out'=>'Off duty','working'=>'Working','break'=>'On break'][$participant->state] }}</div>
<p>Saved across devices and page reloads.</p>
</div>
        <div class="card">
<small>RECORDED TODAY</small>
<div class="metric">{{ intdiv($seconds,3600) }}h {{ intdiv($seconds % 3600,60) }}m</div>
<p>Breaks excluded · {{ now($policy['timezone'])->format('D, j M') }}</p>
</div>
        <div class="card">
<small>CAPTURE POLICY</small>
<h2>{{ $policy['require_photo'] ? 'Pose selfie' : 'Standard punch' }}</h2>
<p>{{ $policy['radius_m'] ? 'Location within the configured work area' : ($policy['require_location'] ? 'Location required' : 'Location optional') }}</p>
</div>
    </div>
    <section class="card">
<div class="row between">
<div>
<h2>Your next action</h2>
<p>Multiple sessions per day are supported.</p>
</div>
<div class="row">
        @if($canPunch)
        @foreach(['in'=>'Clock in','break'=>'Start break','resume'=>'End break','out'=>'Clock out'] as $action=>$label)
            <button type="button" data-attendance-action="{{ $action }}" class="{{ in_array($action,['in','resume']) ? 'primary' : '' }}" @disabled(!in_array($action, ['out'=>['in'],'working'=>['break','out'],'break'=>['resume','out']][$participant->state]))>{{ $label }}</button>
        @endforeach
        @else<p>Your role can view attendance. Ask a manager for permission to record it.</p>@endif
    </div>
</div>
<p class="muted space">Camera and location are requested only when you choose to capture. Selfies are manually reviewed; this does not perform face recognition or prove liveness. Offline punches are not queued.</p>
</section>
    @include('user.attendance.operations')
    <section class="card" id="att-history">
<div class="row between">
<div>
<h2>{{ $reviewer ? 'Team attendance' : 'Your attendance' }}</h2>
<p>Server receipt times and reviewable evidence.</p>
</div>
<button type="button" onclick="window.print()">Print</button>
</div>
        <form method="GET" class="row space"><label>From<input type="date" name="from" value="{{ request('from') }}"></label><label>To<input type="date" name="to" value="{{ request('to') }}"></label>@if($reviewer)<label>Staff<select name="staff"><option value="">All staff</option>@foreach($directory as $person)<option value="{{ $person->id }}" @selected(request('staff') == $person->id)>{{ $person->name }}</option>@endforeach</select></label>@endif<button type="submit">Filter</button><a class="action" href="{{ route($routePrefix.'.index') }}">Clear</a></form>
        <div class="scroll">
<table>
<thead>
<tr>
<th>Staff</th>
<th>Action / time</th>
<th>Evidence</th>
<th>Review</th>
</tr>
</thead>
<tbody>
        @forelse($punches as $punch)<tr>
<td>@if($reviewer && $punch->participant_id !== $participant->id && $punch->review_status === 'pending')<input type="checkbox" form="att-bulk-punch" name="ids[]" value="{{ $punch->id }}" aria-label="Select punch {{ $punch->id }}">@endif {{ $punch->participant->staff?->name ?? 'Removed staff' }}<br>
<small>#{{ $punch->id }}</small>
</td>
<td>{{ ['in'=>'Clock in','out'=>'Clock out','break'=>'Break started','resume'=>'Break ended'][$punch->action] }}<br>{{ $punch->occurred_at->setTimezone($policy['timezone'])->format('j M Y, H:i:s') }}<br>
<small>Received {{ $punch->created_at->setTimezone($policy['timezone'])->format('j M, H:i:s') }}</small>
</td>
            <td>@if($punch->photo_path)<details>
<summary>View capture</summary>
<p>{{ $punch->pose }}</p>
<img class="evidence" loading="lazy" src="{{ route($routePrefix.'.evidence',$punch->id) }}" alt="Attendance selfie for {{ $punch->participant->staff?->name }}">
<p>Captured {{ $punch->captured_at?->setTimezone($policy['timezone'])->format('j M, H:i:s') }}</p>
</details>@else<span class="muted">No retained photo</span>@endif
            <p class="muted">Source: {{ str_replace('_',' ',$punch->source) }}</p>@if($punch->latitude !== null)<p class="muted">{{ $punch->latitude }}, {{ $punch->longitude }}<br>Accuracy ±{{ $punch->accuracy }} m</p>@endif</td>
            <td>
<span class="badge">{{ str_replace('_',' ',$punch->review_status) }}</span>
            @if($reviewer && $punch->participant_id !== $participant->id && $punch->review_status === 'pending')<details>
<summary>Review</summary>
<form method="POST" action="{{ route($routePrefix.'.review',$punch->id) }}">@csrf<label>Decision<select name="decision">
<option value="approved">Approve</option>
<option value="rejected">Reject</option>
</select>
</label>
<label>Review note<textarea name="note" required minlength="3" maxlength="2000">
</textarea>
</label>
<button type="submit">Save review</button>
</form>
</details>@endif</td>
</tr>
        @empty<tr>
<td colspan="4">
<p>No attendance recorded yet. Clock in to start your first session.</p>
</td>
</tr>@endforelse
        </tbody>
</table>
</div>
<div class="space">{{ $punches->links() }}</div>
@if($reviewer) @include('user.attendance.bulk-review', ['bulkKind'=>'punch']) @endif
    </section>


    <section class="card">
<h2>{{ $reviewer ? 'Team requests' : 'Your requests' }}</h2>
<div class="scroll">
<table>
<thead>
<tr>
<th>Staff / request</th>
<th>Dates / details</th>
<th>Status</th>
</tr>
</thead>
<tbody>@forelse($requests as $item)<tr>
<td>@if($reviewer && $item->participant_id !== $participant->id && $item->status === 'pending')<input type="checkbox" form="att-bulk-request" name="ids[]" value="{{ $item->id }}" aria-label="Select request {{ $item->id }}">@endif {{ $item->participant->staff?->name }}<br>{{ ucfirst($item->type) }}</td>
<td>{{ $item->starts_on }} – {{ $item->ends_on }}<p>{{ $item->reason }}</p><strong>{{ $item->metadata['title'] ?? '' }}</strong>@if($item->metadata['provider'] ?? null)<p>{{ $item->metadata['provider'] }}</p>@endif @if($item->metadata['training_url'] ?? null)<p><a href="{{ $item->metadata['training_url'] }}" target="_blank" rel="noopener noreferrer">Training details</a></p>@endif @if($item->requested_days)<p>{{ $item->requested_days }} days · {{ $item->leave_year }}</p>@endif @if($item->minutes)<small>{{ $item->minutes }} minutes</small>@endif @if($item->punch_id)<small>Punch #{{ $item->punch_id }} → {{ $item->proposed_at?->setTimezone($policy['timezone'])->format('j M Y H:i') }}</small>@endif</td>
<td>{{ ucfirst($item->status) }}@if($item->settled_at)<p>Settlement recorded: {{ $item->settlement_currency }} {{ number_format($item->settlement_amount_minor/100,2) }} · {{ $item->settlement_reference }}</p>@elseif($canManage && $item->type==='encashment' && $item->status==='approved' && $item->participant_id!==$participant->id)<details><summary>Record external settlement</summary><form method="POST" action="{{ route($routePrefix.'.requests.settle',$item->id) }}">@csrf<label>Total amount<input name="amount" type="number" required min="0.01" step="0.01"></label><label>Currency<input name="currency" required minlength="3" maxlength="3" placeholder="INR"></label><label>External payment reference<input name="reference" required maxlength="160"></label><label>Audit note<input name="note" required minlength="3" maxlength="2000"></label><button type="submit">Record settlement</button><p class="muted">Record an already completed external payment. This does not send funds.</p></form></details>@endif @if($reviewer && $item->participant_id !== $participant->id && $item->status === 'pending')<form method="POST" action="{{ route($routePrefix.'.requests.review',$item->id) }}">@csrf<label>Decision<select name="decision">
<option value="approved">Approve</option>
<option value="rejected">Reject</option>
</select>
</label>
<label>Decision note<textarea required name="note" minlength="3" maxlength="2000">
</textarea>
</label>
<button type="submit">Save decision</button>
</form>@endif</td>
</tr>@empty<tr>
<td colspan="3">No requests submitted.</td>
</tr>@endforelse</tbody>
</table>
</div>
@if($reviewer) @include('user.attendance.bulk-review', ['bulkKind'=>'request']) @endif
<p class="muted">Showing the latest 50 requests. Approved overtime is exported separately from recorded hours.</p>
</section>
    @if($canExport)<section class="card" id="att-reports"><h2>Individual &amp; summary reports</h2><p>Recorded hours, approved overtime and leave. Up to 93 days per export.</p><form class="row space" action="{{ route($routePrefix.'.export') }}"><label>From<input type="date" name="from" required value="{{ now($policy['timezone'])->startOfMonth()->toDateString() }}"></label><label>To<input type="date" name="to" required value="{{ now($policy['timezone'])->toDateString() }}"></label><label>Report<select name="mode"><option value="daily">Individual / daily</option><option value="summary">Summary by employee</option></select></label>@if($reviewer)<label>Employee<select name="staff"><option value="">All employees</option>@foreach($directory as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label>@endif<label>File type<select name="format"><option value="csv">CSV</option><option value="xlsx">Excel (.xlsx)</option><option value="pdf">PDF</option></select></label><button type="submit">Download report</button></form></section>@endif

    @if($canManage)<section class="card">
<details>
<summary>Attendance policy</summary>
<form method="POST" action="{{ route($routePrefix.'.settings') }}">@csrf @method('PUT')<div class="fields">
<label>Timezone<input name="timezone" required value="{{ $policy['timezone'] }}" placeholder="Asia/Kolkata">
</label>
<label>Require assigned roster<select name="enforce_roster"><option value="0" @selected(!$policy['enforce_roster'])>No</option><option value="1" @selected($policy['enforce_roster'])>Yes</option></select></label><label>Early clock-in window (minutes)<input type="number" name="early_checkin_minutes" min="0" max="720" value="{{ $policy['early_checkin_minutes'] }}"></label><label>Auto-out at assigned shift end<select name="auto_out_at_shift_end"><option value="0" @selected(!$policy['auto_out_at_shift_end'])>No</option><option value="1" @selected($policy['auto_out_at_shift_end'])>Yes</option></select></label>
<label>Selfie on clock in / out<select name="require_photo">
<option value="1" @selected($policy['require_photo'])>Required</option>
<option value="0" @selected(!$policy['require_photo'])>Optional</option>
</select>
</label>
<label>Location<select name="require_location">
<option value="1" @selected($policy['require_location'])>Required</option>
<option value="0" @selected(!$policy['require_location'])>Optional</option>
</select>
</label>
<label>Work area latitude<input type="number" step="any" name="latitude" value="{{ $policy['latitude'] }}">
</label>
<label>Work area longitude<input type="number" step="any" name="longitude" value="{{ $policy['longitude'] }}">
</label>
<label>Allowed radius (metres)<input type="number" name="radius_m" min="50" max="100000" value="{{ $policy['radius_m'] }}">
</label>
<label>Evidence retention (days)<input type="number" name="retention_days" min="7" max="365" required value="{{ $policy['retention_days'] }}">
</label>
</div>
<label>Reason for policy change<textarea name="note" minlength="3" required maxlength="2000">
</textarea>
</label>
<button class="space" type="submit">Save policy</button>
<p class="muted space">Leave the radius blank to disable geofencing. Location accuracy is checked against the boundary. Evidence retention removes photos and coordinates; attendance times and audit history remain. Roster enforcement checks clock-in against the stored shift window. Shift-end automatic out uses the earlier of the shift end and any employee cutoff.</p>
</form>
</details>
</section>@endif
    <section class="card">
<details>
<summary>Review &amp; policy history</summary>@forelse($reviews as $review)<div class="note">
<strong>{{ ucfirst($review->decision) }} · {{ $review->subject_type }} #{{ $review->subject_id }}</strong>
<p>{{ $review->note }}</p>
<small>{{ $review->created_at->setTimezone($policy['timezone'])->format('j M Y H:i') }} · {{ $review->actor_guard }} reviewer #{{ $review->actor_user_id }}</small>
</div>@empty<p class="space">No decisions recorded yet.</p>@endforelse</details>
</section>
    <section class="card">
<h2>Biometric devices</h2>
<p>No device is connected. Device imports require a brand-specific adapter, credentials, and a staff mapping. No device punches are generated by this page.</p>
</section>
    <dialog id="attendance-capture" aria-labelledby="attendance-capture-title">
<div class="row between">
<h2 id="attendance-capture-title">Record attendance</h2>
<button type="button" data-close-capture aria-label="Close capture">✕</button>
</div>
<p id="attendance-pose" class="space">
</p>
<p id="attendance-expiry" class="muted">
</p>
<video id="attendance-video" autoplay muted playsinline hidden>
</video>
<img id="attendance-photo" class="capture-photo" alt="Your captured selfie" hidden>
<div class="row space">
<button type="button" id="attendance-enable">Enable camera &amp; location</button>
<button type="button" id="attendance-take" hidden>Take selfie</button>
<button type="button" id="attendance-retake" hidden>Retake</button>
</div>
<p id="attendance-capture-status" class="note" role="status" aria-live="polite">
</p>
<button type="button" id="attendance-submit" class="primary" disabled>Save attendance</button>
<p class="muted space">Your photo and location, when captured, are shared with authorized attendance reviewers and retained under this team’s policy.</p>
</dialog>
</div>
<script defer src="{{ asset('js/vendor/jsqr.min.js') }}"></script>
<script defer src="{{ asset('js/attendance.js') }}?v=2"></script>
@endsection
