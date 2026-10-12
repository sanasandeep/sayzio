@extends('admin.layouts.app')
@section('title', 'Business Starters')
@section('page-title', 'Business Starters')
@section('content')
<p class="mb-5">Edit the five starter templates for each business type. Changes apply to future imports. Customers keep their own editable copies.</p>
@if($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('admin.business-starters.update') }}">@csrf
@foreach($catalog as $type=>$starters)
<details class="mb-4 rounded-xl border p-5"><summary class="font-bold">{{ ucwords(str_replace('_',' ',$type)) }}</summary>
@foreach($starters as $i=>$starter)
<fieldset class="my-4 rounded-xl border p-4"><legend>Starter {{ $i+1 }}</legend>
<input type="hidden" name="catalog[{{ $type }}][{{ $i }}][key]" value="{{ $starter['key'] }}">
<label>Template name<input class="theme-input w-full mb-3" required maxlength="80" name="catalog[{{ $type }}][{{ $i }}][label]" value="{{ old('catalog.'.$type.'.'.$i.'.label', $starter['label']) }}"></label>
@foreach($starter['items'] as $j=>$item)
<div class="grid grid-cols-1 md:grid-cols-2 gap-3 border-t py-3">
@foreach(['category'=>'Category','name'=>'Item / service name','description'=>'Description','price'=>'Sample price','duration'=>'Duration (minutes)'] as $key=>$label)
<label>{{ $label }}<input class="theme-input w-full" type="{{ in_array($key,['price','duration']) ? 'number' : 'text' }}" @if($key==='price') step="0.01" min="0" max="9999999" @elseif($key==='duration') min="5" max="1440" @else maxlength="{{ $key==='description' ? 800 : 150 }}" @endif required name="catalog[{{ $type }}][{{ $i }}][items][{{ $j }}][{{ $key }}]" value="{{ old('catalog.'.$type.'.'.$i.'.items.'.$j.'.'.$key,$item[$key]) }}"></label>
@endforeach
@if(in_array($type, ['community_club','nonprofit_campaign'], true))
<label>Content type<select class="theme-input w-full" name="catalog[{{ $type }}][{{ $i }}][items][{{ $j }}][block_type]">
@foreach(['contact_form'=>'Membership / volunteer request form','paragraph'=>'Text / announcement','link'=>'Membership / event / signup link','donation'=>'External donation link','tip_jar'=>'Integrated contribution checkout'] as $bt=>$bl)
<option value="{{ $bt }}" @selected(old('catalog.'.$type.'.'.$i.'.items.'.$j.'.block_type', $item['block_type'] ?? 'paragraph') === $bt)>{{ $bl }}</option>
@endforeach
</select></label>
@endif
</div>
@endforeach
</fieldset>
@endforeach
</details>
@endforeach
<button type="submit" class="btn-primary">Save starter templates</button>
</form>
@endsection
