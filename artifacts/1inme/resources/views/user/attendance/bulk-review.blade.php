<details class="space">
<summary>Bulk approve / reject selected {{ $bulkKind === 'punch' ? 'evidence' : 'requests' }}</summary>
<form id="att-bulk-{{ $bulkKind }}" method="POST" class="row space" action="{{ route($routePrefix.'.bulk-review') }}">@csrf<input type="hidden" name="kind" value="{{ $bulkKind }}">
<label>Decision<select name="decision">
<option value="approved">Approve selected</option>
<option value="rejected">Reject selected</option>@if($bulkKind === 'punch')<option value="shift">Modify selected punch times</option>@endif</select>
</label>@if($bulkKind === 'punch')<label>Time shift in minutes<input type="number" name="shift_minutes" min="-1440" max="1440" placeholder="e.g. -15 or 15">
<small>Used only for Modify punch times.</small>
</label>@endif<label>Reason for every selected record<input name="note" required minlength="3" maxlength="2000">
</label>
<button type="submit" class="btn-primary">Apply bulk decision</button>
</form>
<p class="muted">Tick pending records above. You cannot select your own records. If any selection is invalid, no decisions are saved.</p>
</details>
