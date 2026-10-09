@extends('user.layouts.app')
@section('title', 'Contact Directory')
@section('content')
@include('user.links.partials.editor-header', ['activeMainTab' => 'directory'])
<style>
.directory-editor{max-width:1050px;margin:auto}.directory-editor section,.directory-editor details{border:1px solid var(--border-glass);border-radius:16px;padding:20px;margin:16px 0;background:var(--bg-card)}.directory-editor h2{font-size:20px;font-weight:700;margin-bottom:12px}.directory-editor label{display:block;margin:8px 0;font-size:14px}.directory-editor input,.directory-editor textarea,.directory-editor select{display:block;width:100%;padding:9px;border:1px solid var(--border-glass);border-radius:8px;background:var(--bg-glass-input);color:var(--text-primary)}.directory-editor input[type=checkbox]{display:inline;width:auto}.directory-editor button,.directory-editor .action{display:inline-block;background:#2563eb;color:white;padding:9px 16px;border-radius:8px;margin:8px 4px 0 0}.directory-editor .fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.directory-editor summary{cursor:pointer;font-weight:600}.directory-editor .danger{background:#9f1239}
</style>
<div class="directory-editor">
@if($errors->any())<section role="alert">{{ $errors->first() }}</section>@endif
<section><h2>Your contact directory</h2><p>Organize departments, publish contact details, and let visitors reach the right person.</p>
<a class="action" href="{{ route('user.links.blocks.editor', $link) }}">Add page blocks</a>
<a class="action" href="{{ $link->getShortUrl() }}" target="_blank" rel="noopener">View directory</a>
</section>
<section><h2>Starter departments</h2><p>Add draft departments, then fill in your own contact details.</p>
<form method="post" action="{{ route('user.links.directory.save', [$link, 'starter']) }}">@csrf
<select name="starter"><option value="hotel">Hotel & lodge</option><option value="office">Office & campus</option><option value="company">Company</option></select><button>Add starter</button></form></section>
<details><summary>Appearance and visitor instructions</summary>
@php($s = $directory->settings ?? [])
<form method="post" action="{{ route('user.links.directory.save', [$link, 'settings']) }}">@csrf
<label>Introduction<textarea name="intro">{{ $s['intro'] ?? '' }}</textarea></label>
<div class="fields">
<label>Timezone<select name="timezone">@foreach(timezone_identifiers_list() as $tz)<option @selected(($s['timezone'] ?? 'UTC') === $tz)>{{ $tz }}</option>@endforeach</select></label>
<label>Layout<select name="layout">@foreach(['list','grid','compact'] as $layout)<option @selected(($s['layout'] ?? 'list') === $layout)>{{ $layout }}</option>@endforeach</select></label>
<label>Font<select name="font">@foreach(['sans-serif','serif','monospace'] as $font)<option @selected(($s['font'] ?? 'sans-serif') === $font)>{{ $font }}</option>@endforeach</select></label>
@foreach(['background'=>'#f5f7fb','surface'=>'#ffffff','text_color'=>'#172033','accent'=>'#2563eb'] as $key => $default)<label>{{ ucfirst(str_replace('_',' ',$key)) }}<input type="color" name="{{ $key }}" value="{{ $s[$key] ?? $default }}"></label>@endforeach
</div>
<label>Visitor reference label (optional, e.g. Room number)<input name="reference_label" value="{{ $s['reference_label'] ?? '' }}"></label>
<label>Emergency instructions<textarea name="emergency_note">{{ $s['emergency_note'] ?? '' }}</textarea></label><button>Save settings</button></form></details>
<section><h2>Categories</h2>
@foreach($directory->categories as $category)
<form method="post" action="{{ route('user.links.directory.save', [$link, 'category']) }}">@csrf<input type="hidden" name="id" value="{{ $category->id }}"><div class="fields"><label>Name<input name="name" value="{{ $category->name }}" required></label><label>Order<input type="number" name="sort_order" value="{{ $category->sort_order }}" min="0"></label><label>Parent department<select name="parent_id"><option value="">Top level</option>@foreach($directory->categories->whereNull('parent_id') as $parent)@if($parent->id !== $category->id)<option value="{{ $parent->id }}" @selected($category->parent_id === $parent->id)>{{ $parent->name }}</option>@endif @endforeach</select></label></div><button>Save category</button></form>
<form method="post" action="{{ route('user.links.directory.save', [$link, 'delete-category']) }}">@csrf<input type="hidden" name="id" value="{{ $category->id }}"><button class="danger">Delete category</button><small>Contacts move to General.</small></form>
@endforeach
<form method="post" action="{{ route('user.links.directory.save', [$link, 'category']) }}">@csrf<label>New category<input name="name" required></label><input type="hidden" name="sort_order" value="{{ $directory->categories->count() }}"><label>Parent department<select name="parent_id"><option value="">Top level</option>@foreach($directory->categories->whereNull('parent_id') as $parent)<option value="{{ $parent->id }}">{{ $parent->name }}</option>@endforeach</select></label><button>Add category</button></form>
</section>
<h2>Contacts</h2>
@foreach($directory->contacts as $contact)<details id="edit-contact-{{ $contact->id }}"><summary>{{ $contact->name }} · {{ $contact->is_active ? 'Published' : 'Draft' }}</summary>@include('user.links.directory.contact-form', ['contact' => $contact])
<form method="post" action="{{ route('user.links.directory.save', [$link, 'delete-contact']) }}">@csrf<input type="hidden" name="id" value="{{ $contact->id }}"><button class="danger">Delete contact</button></form></details>@endforeach
<details><summary>Add contact</summary>@include('user.links.directory.contact-form', ['contact' => null])</details>
<section><h2>Import and export</h2><p>CSV columns: name,category,phone,email,role,location. Up to 500 rows. Existing name/category pairs are skipped. Imports are drafts.</p>
<a class="action" href="{{ route('user.links.directory.export', $link) }}">Export CSV</a>
<form method="post" enctype="multipart/form-data" action="{{ route('user.links.directory.save', [$link, 'import']) }}">@csrf<label>CSV file<input type="file" name="file" accept=".csv,text/csv" required></label><button>Import contacts</button></form></section>
</div>
@endsection
