@php
    $dash = $operations['dashboard'];
    $profiles = $operations['profiles'];
    $designationNames = $operations['designations']->pluck('name', 'id');
    $isInternal = $routePrefix === 'admin.attendance';
    $mayImpersonate = auth('admin')->user()?->hasPermission('users.impersonate') && \App\Modules\Admin\Support\StaffPermissionCatalog::fullIdentity(auth('admin')->user());
    $directoryAccess = $isInternal
        ? (new \App\Modules\Admin\Support\StaffNavigation(auth('admin')->user()))->allows('admin.staff.index')
        : ((int)$ws->owner_user_id === (int)auth()->id() || auth()->user()?->membershipFor($ws)?->role === 'admin');
@endphp
@if($tab === 'overview')
<section class="card" id="att-overview">
    <div class="row between">
<h2>{{ $reviewer ? 'Team overview' : 'Your overview' }}</h2>
<span class="badge">Live records · {{ now($policy['timezone'])->format('j M Y') }}</span>
</div>
    <div class="grid space">
<div>
<small>WORKING / ON BREAK</small>
<div class="metric">{{ $dash['working'] }} / {{ $dash['break'] }}</div>
</div>
<div>
<small>REQUESTS AWAITING REVIEW</small>
<div class="metric">{{ $dash['pending'] }}</div>
</div>
<div>
<small>EVIDENCE AWAITING REVIEW</small>
<div class="metric">{{ $dash['reviews'] }}</div>
</div>
</div>
    <h3 class="space">Recorded work hours · last 14 days</h3>
    @php($maxHours = max(1, max(array_column($dash['chart'], 'hours'))))
    @if(array_sum(array_column($dash['chart'], 'hours')) > 0)<div class="att-chart" role="img" aria-label="Recorded work hours by day">
<div class="att-bars">@foreach($dash['chart'] as $day)<div class="att-chart-day">
<span>{{ $day['hours'] }}h</span>
<div class="att-chart-bar" style="height:{{ max(2, (int)round(100*$day['hours']/$maxHours)) }}px" title="{{ $day['date'] }}: {{ $day['hours'] }} hours">
</div>
<small>{{ $day['date'] }}</small>
</div>@endforeach</div>
</div>@else<p class="note">No recorded work hours in the last 14 days.</p>@endif
    @if($dash['pending'] || $dash['reviews'])<p class="note">{{ $dash['pending'] }} pending requests and {{ $dash['reviews'] }} evidence items need a decision. @if($reviewer)<a href="{{ route($routePrefix.'.index', ['tab'=>'requests']) }}">Review employee requests</a> or <a href="{{ route($routePrefix.'.leave') }}">open Leave Management</a>.@elseYour reviewers can see these items.@endif</p>@endif
</section>
@endif
@include('user.attendance.workforce')
@if($tab === 'notices')
<section class="card" id="att-notices">
<div class="row between">
<h2>{{ $isInternal ? 'Staff notices' : 'Team notices' }}</h2>
<span class="badge">{{ $operations['notices']->count() }} active</span>
</div>
    @forelse($operations['notices'] as $notice)<article class="note">
<strong>{{ $notice->title }}</strong>
<p style="white-space:pre-wrap">{{ $notice->body }}</p>
<small>{{ $notice->starts_on }}@if($notice->ends_on) – {{ $notice->ends_on }}@endif</small>
</article>@empty<p class="space">No active staff notices.</p>@endforelse
    @if($canManage)<details>
<summary>Create notice</summary>
<form method="POST" action="{{ route($routePrefix.'.setup.save') }}">@csrf<input type="hidden" name="kind" value="notice">
<div class="fields">
<label>Title<input required name="title" maxlength="160">
</label>
<label>Publish from<input type="date" required name="starts_on" value="{{ now($policy['timezone'])->toDateString() }}">
</label>
<label>Publish until<input type="date" name="ends_on">
</label>
</div>
<label>Notice<textarea name="body" required maxlength="10000">
</textarea>
</label>
<label>Audit note<input name="note" required minlength="3" maxlength="2000">
</label>
<button class="btn-primary space" type="submit">Publish notice</button>
</form>
</details>
    <details>
<summary>Edit or delete notices</summary>@foreach($operations['all_notices'] as $notice)<form method="POST" class="note" action="{{ route($routePrefix.'.setup.save') }}">@csrf<input type="hidden" name="kind" value="notice">
<input type="hidden" name="id" value="{{ $notice->id }}">
<div class="fields">
<label>Title<input name="title" required value="{{ $notice->title }}" maxlength="160">
</label>
<label>From<input type="date" name="starts_on" required value="{{ $notice->starts_on }}">
</label>
<label>Until<input type="date" name="ends_on" value="{{ $notice->ends_on }}">
</label>
</div>
<label>Notice<textarea required name="body" maxlength="10000">{{ $notice->body }}</textarea>
</label>
<label>Audit note<input required name="note" minlength="3" maxlength="2000">
</label>
<div class="row space">
<button type="submit" class="btn-primary">Save</button>
<button type="submit" name="delete" value="1" class="btn-ghost">Delete notice</button>
</div>
</form>@endforeach</details>@endif
</section>
@endif
@if(in_array($tab, ['balances', 'types']))
<section class="card" id="att-leave">
<h2>Leave management</h2>
<p>{{ now($policy['timezone'])->year }} annual balances · non-working days and workspace holidays excluded.</p>
    @if($tab === 'balances')<div class="grid space">@forelse($operations['leave_balances'] as $balance)<div class="note">
<strong>{{ $balance['name'] }} · {{ $balance['paid'] ? 'Paid' : 'Unpaid' }}</strong>
<div class="metric">{{ $balance['remaining'] }} days</div>
<p>{{ $balance['approved']-$balance['encashed'] }} leave · {{ $balance['encashed'] }} encashed · {{ $balance['pending'] }} pending · {{ $balance['allowance'] }} available allowance</p>
</div>@empty<p>No leave types configured. General leave requests remain available.</p>@endforelse</div>
    @endif
    @if($tab === 'types' && $canManage) @include('user.attendance.leave-templates')<details open>
<summary>Configure leave types and available allowances</summary>
<form method="POST" action="{{ route($routePrefix.'.setup.save') }}">@csrf<input type="hidden" name="kind" value="leave_type">
<div class="fields">
<label>Leave type<input name="name" required maxlength="120" placeholder="Annual leave">
</label>
<label>Days each year<input type="number" name="annual_days" required min="0" max="366" value="20">
</label>
<label>Pay category<select name="paid">
<option value="1">Paid</option>
<option value="0">Unpaid</option>
</select>
</label><label>Allow encashment<select name="encashment_enabled"><option value="0">No</option><option value="1">Yes</option></select></label><label>Maximum encash days per year<input type="number" name="max_encash_days" min="0" max="366" value="0"></label>
@include('user.attendance.accrual-fields', ['leaveType'=>null])
<label>Audit note<input name="note" required minlength="3" maxlength="2000">
</label>
</div>
<button type="submit" class="btn-primary">Add leave type</button>
</form>
    @foreach($operations['leave_types'] as $type)<form class="note" method="POST" action="{{ route($routePrefix.'.setup.save') }}">@csrf<input type="hidden" name="kind" value="leave_type">
<input type="hidden" name="id" value="{{ $type->id }}">
<div class="fields">
<label>Name<input name="name" value="{{ $type->name }}" required maxlength="120">
</label>
<label>Annual days<input type="number" name="annual_days" value="{{ $type->annual_days }}" required min="0" max="366">
</label>
<label>Pay category<select name="paid">
<option value="1" @selected($type->paid)>Paid</option>
<option value="0" @selected(!$type->paid)>Unpaid</option>
</select>
</label><label>Allow encashment<select name="encashment_enabled"><option value="0" @selected(!$type->encashment_enabled)>No</option><option value="1" @selected($type->encashment_enabled)>Yes</option></select></label>@include('user.attendance.accrual-fields', ['leaveType'=>$type])<label>Max encash days per year<input type="number" name="max_encash_days" min="0" max="366" value="{{ $type->max_encash_days }}"></label>
<label>Audit note<input name="note" required minlength="3" maxlength="2000">
</label>
</div>
<div class="row">
<button type="submit" class="btn-primary">Save type</button>
<button type="submit" name="delete" value="1" class="btn-ghost">Delete unused type</button>
</div>
</form>@endforeach</details>@endif
</section>
@endif
@if($reviewer || $canManage)@if($tab === 'people')
<section class="card" id="att-people">
<div class="row between">
<div>
<h2>{{ $isInternal ? 'Internal staff' : 'Employment settings' }}</h2>
<p>Uses the {{ $isInternal ? 'internal admin staff' : 'workspace team' }} directory. Attendance settings are scoped to this team.</p>
</div>@if($directoryAccess)<a class="btn-ghost" href="{{ route($isInternal ? 'admin.staff.index' : 'user.team.index') }}">{{ $isInternal ? 'Manage staff accounts' : 'Manage workspace members' }}</a>@endif</div>
    <div class="scroll">
<table>
<thead>
<tr>
<th>Employee</th>
<th>Designation</th>
<th>Attendance access</th>
<th>Automatic out</th>
<th>IP restrictions</th>@if($mayImpersonate)<th>Admin access</th>@endif</tr>
</thead>
<tbody>@foreach($directory as $person)@php($profile = $profiles->get($person->id))<tr>
<td>{{ $profile?->display_name ?: $person->name }}<br>
<small>{{ $profile?->employee_code ?: $person->email }}</small>
</td>
<td>{{ $designationNames[$profile?->designation_id] ?? 'Unassigned' }}</td>
<td>{{ $profile && !$profile->attendance_enabled ? 'Disabled' : 'Enabled' }}</td>
<td>{{ $profile?->auto_out_time ?: 'Manual only' }}</td>
<td>{{ implode(', ', $profile?->allowed_ips ?? []) ?: 'Any IP' }}</td>@if($mayImpersonate)<td>@php($linkedUser = $isInternal ? $person->user_id : $person->id)@if($linkedUser)<form method="POST" action="{{ route('admin.users.impersonate',$linkedUser) }}">@csrf<button type="submit" class="btn-primary">Login as user</button>
</form>@else<span class="muted">No linked user account</span>@endif</td>@endif</tr>@endforeach</tbody>
</table>
</div>
    @if($canManage)<details>
<summary>Edit employees / bulk assign designations</summary>
<form method="POST" action="{{ route($routePrefix.'.employees.save') }}">@csrf
    <p class="note">Select employees and tick only the fields to change. Disabling attendance closes an open session as a system event and preserves history. Manage account creation/deletion through the linked staff directory.</p>
    <label>Select a staff group<select data-attendance-group>
<option value="">Choose manually</option>
<option value="all">All active staff</option>@foreach($operations['staff_groups']->unique() as $group)<option value="{{ $group }}">{{ $isInternal ? 'Role #' : 'Role: ' }}{{ $group }}</option>@endforeach</select>
</label>
    <label class="space">Employees<select name="staff_ids[]" multiple size="6" required data-attendance-staff>@foreach($directory as $person)<option value="{{ $person->id }}" data-group="{{ $operations['staff_groups'][$person->id] ?? 'owner' }}">{{ $person->name }} · {{ $person->email }}</option>@endforeach</select>
</label>
    <div class="fields">
<label><span><input type="checkbox" name="fields[]" value="employment_started_on"> Change employment start</span><input type="date" name="employment_started_on"><small>Used for earned leave; blank uses the workspace join date.</small></label>
<label>
<span>
<input type="checkbox" name="fields[]" value="designation_id"> Change designation</span>
<select name="designation_id">
<option value="">Unassigned</option>@foreach($operations['designations'] as $designation)<option value="{{ $designation->id }}">{{ $designation->name }}</option>@endforeach</select>
</label>
<label>
<span>
<input type="checkbox" name="fields[]" value="attendance_enabled"> Change access</span>
<select name="attendance_enabled">
<option value="1">Enabled</option>
<option value="0">Disabled</option>
</select>
</label>
<label>
<span>
<input type="checkbox" name="fields[]" value="auto_out_time"> Change auto punch-out</span>
<input type="time" name="auto_out_time">
<small>{{ $policy['timezone'] }} · blank disables auto-out; an earlier clock time applies the next day.</small>
</label>
<label>
<span>
<input type="checkbox" name="fields[]" value="allowed_ips"> Change permitted IPs</span>
<textarea name="allowed_ips" placeholder="203.0.113.10 or 203.0.113.0/24">
</textarea>
<small>Blank allows any IP. Applies to attendance punches, not the rest of Sayzio.</small>
</label>
<label>
<span>
<input type="checkbox" name="fields[]" value="employee_code"> Change employee code</span>
<input name="employee_code" maxlength="80">
<small>Select one person only.</small>
</label>
<label>
<span>
<input type="checkbox" name="fields[]" value="display_name"> Change attendance display name</span>
<input name="display_name" maxlength="120">
<small>Select one person only. Account name is unchanged.</small>
</label>
</div>
<label>Reason for change<input name="note" required minlength="3" maxlength="2000">
</label>
<button type="submit" class="btn-primary space">Apply selected changes</button>
</form>
</details>@endif
</section>
@endif @endif
@if($tab === 'calendar')
<section class="card" id="att-calendar">
<h2>Designations &amp; holidays</h2>
<div class="grid space">
<div>
<h3>Designations</h3>@forelse($operations['designations'] as $designation)<p>{{ $designation->name }}</p>@empty<p>No designations configured.</p>@endforelse</div>
<div style="grid-column:span 2">
<h3>Holiday calendar</h3>@forelse($operations['holidays'] as $holiday)<p>{{ $holiday->date }} · {{ $holiday->name }}</p>@empty<p>No holidays configured.</p>@endforelse</div>
</div>
@if($canManage)<details>
<summary>Manage designations</summary>
<form method="POST" class="row space" action="{{ route($routePrefix.'.setup.save') }}">@csrf<input type="hidden" name="kind" value="designation">
<label>New designation<input name="name" required maxlength="120">
</label>
<label>Audit note<input name="note" required minlength="3" maxlength="2000">
</label>
<button type="submit" class="btn-primary">Create</button>
</form>@foreach($operations['designations'] as $designation)<form method="POST" class="row note" action="{{ route($routePrefix.'.setup.save') }}">@csrf<input type="hidden" name="kind" value="designation">
<input type="hidden" name="id" value="{{ $designation->id }}">
<label>Name<input name="name" required maxlength="120" value="{{ $designation->name }}">
</label>
<label>Audit note<input required name="note" minlength="3" maxlength="2000">
</label>
<button type="submit" class="btn-primary">Save</button>
<button type="submit" name="delete" value="1" class="btn-ghost">Delete designation</button>
</form>@endforeach</details>
<details>
<summary>Create holidays in bulk</summary>
<form method="POST" action="{{ route($routePrefix.'.setup.save') }}">@csrf<input type="hidden" name="kind" value="holiday">
<div class="fields">
<label>Holiday name<input name="name" required maxlength="120">
</label>
<label>From<input type="date" name="starts_on" required>
</label>
<label>To<input type="date" name="ends_on" required>
</label>
<label>Apply only on selected weekdays<select name="weekdays[]" multiple size="4">@foreach(['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $index=>$day)<option value="{{ $index }}">{{ $day }}</option>@endforeach</select>
<small>No selection means every date in the range.</small>
</label>
<label>Audit note<input required name="note" minlength="3" maxlength="2000">
</label>
</div>
<button type="submit" class="btn-primary">Create / update holiday dates</button>
</form>
</details>
<details>
<summary>Edit or delete a holiday</summary>@foreach($operations['holidays'] as $holiday)<form method="POST" class="note" action="{{ route($routePrefix.'.setup.save') }}">@csrf<input type="hidden" name="kind" value="holiday">
<input type="hidden" name="id" value="{{ $holiday->id }}">
<div class="fields">
<label>Name<input name="name" required maxlength="120" value="{{ $holiday->name }}">
</label>
<label>Date<input type="date" name="starts_on" required value="{{ $holiday->date }}">
</label>
<label>End date<input type="date" name="ends_on" required value="{{ $holiday->date }}">
</label>
<label>Audit note<input required name="note" minlength="3" maxlength="2000">
</label>
</div>
<div class="row">
<button type="submit" class="btn-primary">Save holiday</button>
<button type="submit" name="delete" value="1" class="btn-ghost">Delete holiday</button>
</div>
</form>@endforeach</details>@endif
</section>
@endif
@if($tab === 'qr')
<section class="card" id="att-qr">
<div class="row between">
<div>
<h2>QR clock-in</h2>
<p>Scan the current workplace QR, then complete your normal capture checks.</p>
</div>@if($canPunch)<button type="button" data-open-qr-scanner class="btn-ghost">Scan workplace QR</button>@endif</div>
@if($canManage)<details>
<summary>Display a rotating workplace QR</summary>
<p class="muted space">Codes expire after 90 seconds. Keep this page open at the workplace. Scans cannot bypass employee identity, IP, selfie or location checks.</p>
<button class="btn-ghost space" type="button" data-start-qr-station>Start QR display</button>
<div data-qr-station hidden style="text-align:center">
<div data-qr-image style="display:inline-block;background:white;padding:12px;border-radius:12px">
</div>
<p data-qr-expiry class="muted">
</p>
</div>
</details>@endif
@if(request()->attributes->get('attendance_qr_token'))<div class="note">Workplace QR scanned. Use <strong>Clock in</strong> below before the code expires.</div>@endif
<dialog id="attendance-qr-scanner" aria-labelledby="attendance-qr-title">
<div class="row between">
<h2 id="attendance-qr-title">Scan workplace QR</h2>
<button type="button" data-close-qr class="btn-ghost">Close</button>
</div>
<video autoplay muted playsinline data-qr-video>
</video>
<p data-qr-status class="note" role="status">Camera is requested only when you press Start camera.</p>
<div class="row">
<button type="button" data-start-qr-camera class="btn-ghost">Start camera</button>
</div>
<form class="space" data-qr-paste>
<label>Or paste a scanned workplace QR link<input type="url" required data-qr-url>
</label>
<button class="btn-primary space" type="submit">Open QR clock-in</button>
</form>
</dialog>
</section>
@endif
