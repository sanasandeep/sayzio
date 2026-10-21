@extends('user.layouts.app')
@section('title', $property ? 'Real Estate' : 'Education & Training')
@section('content')
@include('user.links.partials.editor-header', ['activeMainTab'=>'catalog'])
@include('user.links.catalog.editor-css')
@include('user.links.partials.business-editor-layout')
<div x-data="menuEditorPanes(@js(in_array(request()->query('pane'), ['items','design','ordering'], true) ? request()->query('pane') : 'items'))">
@include('user.links.partials.business-editor-panes', ['businessItems'=>($property ? 'Properties' : 'Courses'),'businessWorkflow'=>'Contact & inquiries'])
<div class="business-editor-grid"><div class="business-editor-content"><div class="catalog-editor">
@if(session('success'))<section role="status">{{ session('success') }}</section>@endif
@if($errors->any())<section role="alert"><strong>Please correct these fields:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></section>@endif

<details open x-show="pane === 'design' || pane === 'ordering'" x-cloak><summary x-text="pane === 'ordering' ? 'Contact and inquiry settings' : 'Brand and appearance'">Brand, appearance and contact details</summary>
@php($s=$catalog->settings ?? [])
<form method="post" action="{{ route('user.links.catalog.save',[$link,'settings']) }}">@csrf<input type="hidden" name="editor_pane" x-model="pane">
<div x-show="pane === 'design'"><label>Business / institute name<input name="business_name" maxlength="150" value="{{ $s['business_name'] ?? '' }}"></label><label>Introduction<textarea name="intro" maxlength="1000">{{ $s['intro'] ?? '' }}</textarea></label><label>Hero photo URL<input type="url" name="hero_url" value="{{ $s['hero_url'] ?? '' }}"></label>
</div><div class="fields"><label x-show="pane === 'ordering'">Currency (three-letter code)<input name="currency" value="{{ $s['currency'] ?? 'INR' }}" maxlength="3" pattern="[A-Z]{3}" required></label>
<label x-show="pane === 'ordering'">Timezone<select name="timezone">@foreach(timezone_identifiers_list() as $tz)<option @selected(\App\Support\PlatformTimezone::resolve($s['timezone'] ?? null)===$tz)>{{ $tz }}</option>@endforeach</select></label>
<label x-show="pane === 'design'">Layout<select name="layout">@foreach(['grid','list'] as $layout)<option @selected(($s['layout'] ?? 'grid')===$layout)>{{ $layout }}</option>@endforeach</select></label>
<label x-show="pane === 'design'">Font<select name="font">@foreach(['sans-serif','serif','monospace'] as $font)<option @selected(($s['font'] ?? 'sans-serif')===$font)>{{ $font }}</option>@endforeach</select></label>
@foreach(['background'=>'#f6f7f9','surface'=>'#ffffff','text_color'=>'#172033','accent'=>($property ? '#0f766e' : '#4338ca')] as $key=>$default)<label x-show="pane === 'design'">{{ ucfirst(str_replace('_',' ',$key)) }}<input type="color" name="{{ $key }}" value="{{ $s[$key] ?? $default }}"></label>@endforeach
<label x-show="pane === 'ordering'">Contact phone<input name="phone" value="{{ $s['phone'] ?? '' }}"></label><label x-show="pane === 'ordering'">Contact email<input type="email" name="email" value="{{ $s['email'] ?? '' }}"></label></div>
<p x-show="pane === 'ordering'"><a class="action" href="{{ route('user.links.catalog.inquiries',$link) }}">Open inquiries</a></p><label x-show="pane === 'ordering'"><input type="checkbox" name="inquiries_enabled" value="1" @checked($s['inquiries_enabled'] ?? true)> Accept inquiries</label><button>Save settings</button></form></details>
<div x-show="pane === 'items'" x-cloak>
@include('user.links.partials.business-starter-picker')
<section><h2>Categories</h2>

@foreach($catalog->categories as $category)<details><summary><i class="fas fa-folder" aria-hidden="true"></i> {{ $category->name }}</summary>
<form method="post" action="{{ route('user.links.catalog.save',[$link,'category']) }}">@csrf<input type="hidden" name="editor_pane" x-model="pane"><input type="hidden" name="id" value="{{ $category->id }}"><div class="fields"><label>Name<input name="name" value="{{ $category->name }}" required maxlength="150"></label><label>Order<input type="number" name="sort_order" min="0" value="{{ $category->sort_order }}" required></label></div><button>Save category</button></form>
<form method="post" action="{{ route('user.links.catalog.save',[$link,'delete-category']) }}">@csrf<input type="hidden" name="editor_pane" x-model="pane"><input type="hidden" name="id" value="{{ $category->id }}"><button class="danger">Delete category</button><small>Listings move to General.</small></form></details>@endforeach
<form method="post" action="{{ route('user.links.catalog.save',[$link,'category']) }}">@csrf<input type="hidden" name="editor_pane" x-model="pane"><label>New category<input name="name" required maxlength="150"></label><input type="hidden" name="sort_order" value="{{ $catalog->categories->count() }}"><button>Add category</button></form></section>
<h2>{{ $property ? 'Properties' : 'Courses' }}</h2>
@if($catalog->entries->isEmpty())<section style="text-align:center;padding:32px"><i class="fas fa-layer-group" aria-hidden="true" style="color:var(--accent,#3d6bff);font-size:26px;margin-bottom:12px"></i><h3>Ready for your first listing?</h3><p>Choose a starter above or add your own details below. Drafts stay private until published.</p></section>@endif
@foreach($catalog->entries as $entry)<details id="edit-entry-{{ $entry->id }}"><summary>{{ $entry->title }} · {{ $entry->is_active ? 'Published' : 'Draft' }}</summary><form method="post" action="{{ route('user.links.catalog.save',[$link,'publish']) }}">@csrf<input type="hidden" name="editor_pane" x-model="pane"><input type="hidden" name="id" value="{{ $entry->id }}"><input type="hidden" name="is_active" value="{{ $entry->is_active ? 0 : 1 }}"><button type="submit">{{ $entry->is_active ? 'Unpublish' : 'Publish' }} {{ $property ? 'property' : 'course' }}</button></form>@include('user.links.catalog.entry-form',['entry'=>$entry])<form method="post" action="{{ route('user.links.catalog.save',[$link,'delete-entry']) }}">@csrf<input type="hidden" name="editor_pane" x-model="pane"><input type="hidden" name="id" value="{{ $entry->id }}"><button class="danger">Delete {{ $property ? 'property' : 'course' }}</button><small>Previously received inquiries are retained.</small></form></details>@endforeach
<details open><summary>Add {{ $property ? 'property' : 'course' }}</summary>@include('user.links.catalog.entry-form',['entry'=>null])</details>
</div>
</div>
</div><aside class="business-editor-preview">@include('user.links.partials.device-preview', ['link'=>$link])</aside></div></div>
@endsection
