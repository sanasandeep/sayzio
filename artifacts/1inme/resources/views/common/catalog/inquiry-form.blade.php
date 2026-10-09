@php($restore=(string)old('entry_id')===(string)$entry->id)
<details class="inquiry-form" id="inquiry-{{ $entry->id }}" @if((string)old('entry_id')===(string)$entry->id) open @endif><summary>{{ $property ? 'Viewing request' : 'Enrollment inquiry' }}</summary>
<form method="post" action="{{ route('public.catalog.inquiry',['alias'=>$link->alias]) }}">@csrf
<input type="hidden" name="entry_id" value="{{ $entry->id }}">
<label>Your name<input name="name" value="{{ $restore ? old('name') : '' }}" autocomplete="name" required maxlength="150"></label>
<label>Email<input type="email" name="email" value="{{ $restore ? old('email') : '' }}" autocomplete="email" required maxlength="255"></label>
<label>Phone (optional)<input type="tel" name="phone" value="{{ $restore ? old('phone') : '' }}" autocomplete="tel" maxlength="40"></label>
@if($property)<label>Preferred viewing date (optional)<input type="date" name="preferred_date" value="{{ $restore ? old('preferred_date') : '' }}" min="{{ now()->format('Y-m-d') }}"></label>
@elseif(!empty($d['batches']))<label>Batch<select name="batch"><option value="">Discuss the best batch</option>@foreach($d['batches'] as $batch)@if(($batch['status'] ?? '')==='open' && (!isset($batch['seats']) || (int)$batch['seats']>0))<option value="{{ $batch['id'] }}" @selected($restore && old('batch')===$batch['id'])>{{ $batch['name'] }} @if(!empty($batch['start_date']))· {{ $batch['start_date'] }}@endif</option>@endif @endforeach</select></label>@endif
<label>Message (optional)<textarea name="message" maxlength="3000" rows="3">{{ $restore ? old('message') : '' }}</textarea></label>
<div class="honeypot" aria-hidden="true"><label>Leave blank<input name="company_website" tabindex="-1" autocomplete="off"></label></div>
<label class="consent"><input type="checkbox" name="consent" value="1" required> I agree to share these details with {{ $title }} so they can respond to my request.</label>
<p class="request-note">{{ $property ? 'The agent will confirm the date and time with you.' : 'The institute will contact you about availability and next steps. This does not reserve a seat.' }}</p><button type="submit">Send request</button>
</form></details>
