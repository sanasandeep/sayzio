@extends('user.layouts.app')
@section('title', $property ? 'Real Estate' : 'Education & Training')
@section('content')
@include('user.links.partials.editor-header', ['activeMainTab'=>'catalog'])
@include('user.links.catalog.editor-css')
<div class="catalog-editor">
@if($errors->any())<section role="alert"><strong>Please correct these fields:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif
<section><h2>{{ $property ? 'Your property portfolio' : 'Your learning catalog' }}</h2><p>{{ $property ? 'Showcase properties and collect viewing requests.' : 'Present courses, explain the curriculum and collect enrollment inquiries.' }}</p>
<a class="action" href="{{ route('user.links.blocks.editor',$link) }}">Page blocks</a><a class="action" href="{{ route('user.links.catalog.inquiries',$link) }}">Inquiries</a><a class="action" href="{{ $link->getShortUrl() }}" target="_blank" rel="noopener">View page</a></section>
<details><summary>Brand, appearance and contact details</summary>
@php($s=$catalog->settings ?? [])
<form method="post" action="{{ route('user.links.catalog.save',[$link,'settings']) }}">@csrf
<label>Business / institute name<input name="business_name" maxlength="150" value="{{ $s['business_name'] ?? '' }}"></label><label>Introduction<textarea name="intro" maxlength="1000">{{ $s['intro'] ?? '' }}</textarea></label><label>Hero photo URL<input type="url" name="hero_url" value="{{ $s['hero_url'] ?? '' }}"></label>
<div class="fields"><label>Currency (three-letter code)<input name="currency" value="{{ $s['currency'] ?? 'INR' }}" maxlength="3" pattern="[A-Z]{3}" required></label>
<label>Timezone<select name="timezone">@foreach(timezone_identifiers_list() as $tz)<option @selected(($s['timezone'] ?? 'Asia/Kolkata')===$tz)>{{ $tz }}</option>@endforeach</select></label>
<label>Layout<select name="layout">@foreach(['grid','list'] as $layout)<option @selected(($s['layout'] ?? 'grid')===$layout)>{{ $layout }}</option>@endforeach</select></label>
<label>Font<select name="font">@foreach(['sans-serif','serif','monospace'] as $font)<option @selected(($s['font'] ?? 'sans-serif')===$font)>{{ $font }}</option>@endforeach</select></label>
@foreach(['background'=>'#f6f7f9','surface'=>'#ffffff','text_color'=>'#172033','accent'=>($property ? '#0f766e' : '#4338ca')] as $key=>$default)<label>{{ ucfirst(str_replace('_',' ',$key)) }}<input type="color" name="{{ $key }}" value="{{ $s[$key] ?? $default }}"></label>@endforeach
<label>Contact phone<input name="phone" value="{{ $s['phone'] ?? '' }}"></label><label>Contact email<input type="email" name="email" value="{{ $s['email'] ?? '' }}"></label></div>
<label><input type="checkbox" name="inquiries_enabled" value="1" @checked($s['inquiries_enabled'] ?? true)> Accept inquiries</label><button>Save settings</button></form></details>
<section><h2>Categories</h2>
<form method="post" action="{{ route('user.links.catalog.save',[$link,'starter']) }}">@csrf<button class="ghost">Add suggested categories</button><small>Adds categories only; no invented listings or prices are published.</small></form>
@foreach($catalog->categories as $category)
<form method="post" action="{{ route('user.links.catalog.save',[$link,'category']) }}">@csrf<input type="hidden" name="id" value="{{ $category->id }}"><div class="fields"><label>Name<input name="name" value="{{ $category->name }}" required maxlength="150"></label><label>Order<input type="number" name="sort_order" min="0" value="{{ $category->sort_order }}" required></label></div><button>Save category</button></form>
<form method="post" action="{{ route('user.links.catalog.save',[$link,'delete-category']) }}">@csrf<input type="hidden" name="id" value="{{ $category->id }}"><button class="danger">Delete category</button><small>Listings move to General.</small></form>@endforeach
<form method="post" action="{{ route('user.links.catalog.save',[$link,'category']) }}">@csrf<label>New category<input name="name" required maxlength="150"></label><input type="hidden" name="sort_order" value="{{ $catalog->categories->count() }}"><button>Add category</button></form></section>
<h2>{{ $property ? 'Properties' : 'Courses' }}</h2>
@foreach($catalog->entries as $entry)<details id="edit-entry-{{ $entry->id }}"><summary>{{ $entry->title }} · {{ $entry->is_active ? 'Published' : 'Draft' }}</summary>@include('user.links.catalog.entry-form',['entry'=>$entry])<form method="post" action="{{ route('user.links.catalog.save',[$link,'delete-entry']) }}">@csrf<input type="hidden" name="id" value="{{ $entry->id }}"><button class="danger">Delete {{ $property ? 'property' : 'course' }}</button><small>Previously received inquiries are retained.</small></form></details>@endforeach
<details open><summary>Add {{ $property ? 'property' : 'course' }}</summary>@include('user.links.catalog.entry-form',['entry'=>null])</details>
</div>
@endsection
