@php($d = $contact?->details ?? [])
<form method="post" action="{{ route('user.links.directory.save', [$link, 'contact']) }}">@csrf
@if($contact)<input type="hidden" name="id" value="{{ $contact->id }}">@endif
<div class="fields">
<label>Name / department<input name="name" value="{{ $contact?->name }}" required maxlength="150"></label>
<label>Category<select name="category_id"><option value="">General</option>@foreach($directory->categories as $cat)<option value="{{ $cat->id }}" @selected($contact?->category_id === $cat->id)>{{ $cat->name }}</option>@endforeach</select></label>
@foreach(['role'=>'Role / person','phone'=>'Phone','extension'=>'Extension','alternate_phone'=>'Alternate / after-hours phone','whatsapp'=>'WhatsApp (country code + digits)','email'=>'Email','website'=>'Website','support_url'=>'Support link','appointment_url'=>'Appointment link','photo_url'=>'Photo URL','location'=>'Location / address'] as $key=>$label)
<label>{{ $label }}<input name="details[{{ $key }}]" value="{{ $d[$key] ?? '' }}" @if(str_ends_with($key,'url') || $key==='website') type="url" @elseif($key==='email') type="email" @endif></label>@endforeach
<label>Icon<select name="details[icon]"><option value="">None</option>@foreach(['address-book','bell','building','headset','shield','briefcase','utensils','taxi'] as $icon)<option @selected(($d['icon'] ?? '') === $icon)>{{ $icon }}</option>@endforeach</select></label>
<label>Sort order<input type="number" min="0" name="sort_order" value="{{ $contact?->sort_order ?? $directory->contacts->count() }}" required></label>
</div>
<label>Description<textarea name="details[description]" maxlength="1000">{{ $d['description'] ?? '' }}</textarea></label>
<label>Prefilled WhatsApp / SMS message<textarea name="details[message]" maxlength="1000">{{ $d['message'] ?? '' }}</textarea></label>
<div class="fields"><label>Opening time<input type="time" name="details[opens]" value="{{ $d['opens'] ?? '' }}"></label><label>Closing time<input type="time" name="details[closes]" value="{{ $d['closes'] ?? '' }}"></label><label>Working days (0 Sunday to 6 Saturday, comma separated)<input name="details[days]" placeholder="1,2,3,4,5" value="{{ $d['days'] ?? '' }}"></label><label>Holiday dates (comma separated)<input name="details[holidays]" placeholder="2026-12-25" value="{{ $d['holidays'] ?? '' }}"></label></div>
<label>Closed instructions<input name="details[closed_note]" value="{{ $d['closed_note'] ?? '' }}"></label>
<label><input type="checkbox" name="is_pinned" value="1" @checked($contact?->is_pinned)> Pin this contact</label>
<label><input type="checkbox" name="is_active" value="1" @checked($contact?->is_active)> Publish contact</label>
<button>Save contact</button>
</form>
