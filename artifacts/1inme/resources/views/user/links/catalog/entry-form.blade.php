@php
    $restore=old('_catalog_action')==='entry' && (string)old('id','')===(string)($entry?->id ?? '');
    $d=$restore ? (array)old('details',[]) : ($entry?->details ?? []);
    $d['batches']=is_array($d['batches'] ?? null) ? array_values(array_filter($d['batches'],'is_array')) : [];
    $d['photos']=is_array($d['photos'] ?? null) ? $d['photos'] : [];
    $entryTitle=$restore ? old('title','') : $entry?->title;
    $entryCategory=$restore ? old('category_id') : $entry?->category_id;
@endphp
<form method="post" action="{{ route('user.links.catalog.save',[$link,'entry']) }}">@csrf<input type="hidden" name="_catalog_action" value="entry">
@if($entry)<input type="hidden" name="id" value="{{ $entry->id }}">@endif
<div class="fields"><label>{{ $property ? 'Property title' : 'Course title' }}<input name="title" value="{{ $entryTitle }}" required maxlength="200"></label>
<label>Category<select name="category_id"><option value="">General</option>@foreach($catalog->categories as $category)<option value="{{ $category->id }}" @selected((string)$entryCategory===(string)$category->id)>{{ $category->name }}</option>@endforeach</select></label>
<label>Status<select name="details[status]">@foreach($property ? ['available','reserved','sold','rented'] : ['enrolling','full','closed','coming_soon'] as $status)<option value="{{ $status }}" @selected(($d['status'] ?? '')===$status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></label>
<label>{{ $property ? 'Price' : 'Course fee' }} ({{ $catalog->settings['currency'] ?? 'INR' }})<input type="number" step="0.01" min="0" name="details[price]" value="{{ $d['price'] ?? '' }}"><small>Leave blank for price on request. Zero means free.</small></label>
<label>Price note<input name="details[price_note]" placeholder="{{ $property ? 'Per month / negotiable' : 'Total fee / per module' }}" maxlength="80" value="{{ $d['price_note'] ?? '' }}"></label>
<label>Location<input name="details[location]" value="{{ $d['location'] ?? '' }}" maxlength="300"></label>
<label>Order<input type="number" name="sort_order" min="0" value="{{ $restore ? old('sort_order',0) : ($entry?->sort_order ?? $catalog->entries->count()) }}" required></label></div>
<label>Description<textarea name="details[description]" maxlength="5000">{{ $d['description'] ?? '' }}</textarea></label>
@if($property)
<div class="fields"><label>Listing purpose<select name="details[purpose]">@foreach(['sale','rent'] as $purpose)<option @selected(($d['purpose'] ?? '')===$purpose)>{{ $purpose }}</option>@endforeach</select></label>
<label>Property type<select name="details[property_type]">@foreach(['apartment','house','land','commercial'] as $type)<option @selected(($d['property_type'] ?? '')===$type)>{{ $type }}</option>@endforeach</select></label>
@foreach(['bedrooms'=>'Bedrooms','bathrooms'=>'Bathrooms','area'=>'Area'] as $key=>$label)<label>{{ $label }}<input type="number" min="0" @if($key==='area') step="0.01" @endif name="details[{{ $key }}]" value="{{ $d[$key] ?? '' }}"></label>@endforeach
<label>Area unit<select name="details[area_unit]">@foreach(['sq_ft','sq_m','acres'] as $unit)<option @selected(($d['area_unit'] ?? '')===$unit)>{{ $unit }}</option>@endforeach</select></label></div>
<label>Amenities (one per line)<textarea name="details[amenities]" maxlength="2000">{{ $d['amenities'] ?? '' }}</textarea></label>
@else
<div class="fields"><label>Delivery mode<select name="details[mode]">@foreach(['online','in_person','hybrid'] as $mode)<option value="{{ $mode }}" @selected(($d['mode'] ?? '')===$mode)>{{ ucfirst(str_replace('_',' ',$mode)) }}</option>@endforeach</select></label>
<label>Level<select name="details[level]">@foreach(['beginner','intermediate','advanced','all_levels'] as $level)<option value="{{ $level }}" @selected(($d['level'] ?? '')===$level)>{{ ucfirst(str_replace('_',' ',$level)) }}</option>@endforeach</select></label>
<label>Duration<input name="details[duration]" value="{{ $d['duration'] ?? '' }}" placeholder="8 weeks · 24 sessions" maxlength="150"></label><label>Instructor<input name="details[instructor]" value="{{ $d['instructor'] ?? '' }}" maxlength="150"></label></div>
@foreach(['instructor_bio'=>'Instructor background','syllabus'=>'Syllabus (one topic per line)','prerequisites'=>'Prerequisites','certificate'=>'Certificate details'] as $key=>$label)<label>{{ $label }}<textarea name="details[{{ $key }}]">{{ $d[$key] ?? '' }}</textarea></label>@endforeach
<details><summary>Batches and schedules</summary><p>Seats are an owner-maintained availability indicator. An inquiry does not reserve a seat.</p><div x-data="{ count: {{ max(1,count($d['batches'] ?? [])) }} }">
@for($i=0;$i<12;$i++)
@php($batch=$d['batches'][$i] ?? [])
<fieldset class="batch" x-show="count > {{ $i }}" :disabled="count <= {{ $i }}">
<input type="hidden" name="details[batches][{{ $i }}][id]" value="{{ $batch['id'] ?? '' }}"><h3>Batch {{ $i+1 }}</h3><div class="fields">
<label>Batch name<input name="details[batches][{{ $i }}][name]" value="{{ $batch['name'] ?? '' }}" maxlength="150"><small>Leave blank to remove this batch.</small></label>
<label>Start date<input type="date" name="details[batches][{{ $i }}][start_date]" value="{{ $batch['start_date'] ?? '' }}"></label>
<label>End date<input type="date" name="details[batches][{{ $i }}][end_date]" value="{{ $batch['end_date'] ?? '' }}"></label>
<label>Schedule<input name="details[batches][{{ $i }}][schedule]" value="{{ $batch['schedule'] ?? '' }}" placeholder="Tue & Thu, 6–8 PM" maxlength="300"></label>
<label>Remaining seats (optional)<input type="number" name="details[batches][{{ $i }}][seats]" value="{{ $batch['seats'] ?? '' }}" min="0"></label>
<label>Status<select name="details[batches][{{ $i }}][status]">@foreach(['open','full','closed'] as $status)<option @selected(($batch['status'] ?? '')===$status)>{{ $status }}</option>@endforeach</select></label>
</div></fieldset>@endfor
<button type="button" class="ghost" x-show="count < 12" @click="count++">Add another batch</button></div></details>
@endif
<details><summary>Photo gallery (up to 8 photos)</summary>@for($i=0;$i<8;$i++)<label>Photo {{ $i+1 }} URL<input type="url" name="details[photos][{{ $i }}]" value="{{ $d['photos'][$i] ?? '' }}"></label>@endfor</details>
<label>{{ $property ? 'External viewing / agent link (optional)' : 'External enrollment link (optional)' }}<input type="url" name="details[external_url]" value="{{ $d['external_url'] ?? '' }}"></label>
<label><input type="checkbox" name="is_featured" value="1" @checked($restore ? old('is_featured',false) : $entry?->is_featured)> Feature at the top of its category</label>
<label><input type="checkbox" name="is_active" value="1" @checked($restore ? old('is_active',false) : $entry?->is_active)> Publish {{ $property ? 'property' : 'course' }}</label><button>Save {{ $property ? 'property' : 'course' }}</button></form>
