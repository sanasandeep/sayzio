@if($canRequest)
<details class="space" id="att-request-form"><summary>Apply for leave, encashment, training, or another request</summary>
<form method="POST" action="{{ route($routePrefix.'.requests.store') }}" data-self-service-form>@csrf
<div class="fields"><label>Request type<select name="type" data-request-type><option value="leave">Leave</option><option value="encashment">Leave encashment</option><option value="training">Training</option><option value="general">HR / employee request</option><option value="correction">Attendance correction</option><option value="overtime">Overtime</option></select></label>
<label data-request-for="leave,encashment">Leave type<select name="leave_type_id"><option value="">General leave</option>@foreach($operations['leave_types'] as $type)<option value="{{ $type->id }}">{{ $type->name }}{{ $type->encashment_enabled ? ' · encashment available' : '' }}</option>@endforeach</select></label>
<label data-request-for="encashment">Days to encash<input type="number" name="requested_days" min="0.5" max="366" step="0.5"><small>Current year only. Approval reserves these days; payment is recorded separately.</small></label>
<label data-request-for="leave,training,general,correction,overtime">From<input type="date" name="starts_on" required value="{{ now($policy['timezone'])->toDateString() }}"></label>
<label data-request-for="leave,training,general,correction,overtime">To<input type="date" name="ends_on" required value="{{ now($policy['timezone'])->toDateString() }}"></label>
<label data-request-for="training,general">Title<input name="title" maxlength="160"></label>
<label data-request-for="training">Training provider<input name="provider" maxlength="160"></label>
<label data-request-for="training">Course URL<input type="url" name="training_url" maxlength="1000" placeholder="https://"></label>
<label data-request-for="general">Request category<select name="category"><option value="hr">HR support</option><option value="document">Document request</option><option value="equipment">Equipment</option><option value="other">Other</option></select></label>
<label data-request-for="overtime">Overtime minutes<input type="number" name="minutes" min="1" max="1440"></label>
<label data-request-for="correction">Punch ID<input type="number" name="punch_id" min="1"></label>
<label data-request-for="correction">Corrected time (device timezone)<input type="datetime-local" name="proposed_local" data-correction-local><input type="hidden" name="proposed_at" data-correction-iso></label></div>
<label>Reason / details<textarea name="reason" required minlength="3" maxlength="2000"></textarea></label><button type="submit" class="space">Submit for approval</button>
<p class="muted space">Leave excludes weekends and holidays. Training approval records your team’s decision; it does not enrol you with an external provider.</p>
</form></details>
@endif
