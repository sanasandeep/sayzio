@extends('admin.layouts.app')
@section('title', 'Custom CSS & JavaScript')
@section('page-title', 'Custom CSS & JavaScript')
@section('content')
<div class="max-w-6xl mx-auto space-y-6">
<div class="card-premium p-6"><span class="text-xs uppercase tracking-wider" style="color:var(--text-muted)">Site customization</span><h1 class="text-2xl font-bold mt-2">Custom CSS & JavaScript</h1><p class="mt-2 text-sm" style="color:var(--text-muted)">Choose where your code runs. Each area has its own switch and head/body placements. Changes apply after saving and reloading the destination page.</p></div>
@if($errors->any())<div role="alert" class="card-premium p-4"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('admin.site-custom-code.update') }}" class="space-y-5">@csrf @method('PUT')
@foreach(\App\Modules\Common\Support\SiteCustomCode::AREAS as $area => $label)
<section class="card-premium p-6 space-y-5">
<div class="flex items-start justify-between gap-4"><div><h2 class="text-lg font-semibold">{{ $label }}</h2><p class="text-sm mt-1" style="color:var(--text-muted)">{{ ['marketing'=>'Homepage, features, pricing, blog and other marketing pages.','dashboard'=>'Signed-in user pages, including settings and editors.'][$area] }}</p></div><label class="flex items-center gap-2 text-sm shrink-0"><input type="hidden" name="code[{{ $area }}][enabled]" value="0"><input type="checkbox" name="code[{{ $area }}][enabled]" value="1" @checked(old('code.'.$area.'.enabled', $settings[$area]['enabled']))> Enabled</label></div>
<div class="grid md:grid-cols-2 gap-5">
@foreach(['css'=>['Custom CSS','CSS only, without style tags. Added at the end of the head.'], 'head_js'=>['JavaScript in head','JavaScript only, without script tags. Runs before body parsing; use DOMContentLoaded for page elements.'], 'body_js'=>['JavaScript at body end','JavaScript only, without script tags. Added before the closing body tag.']] as $field => [$title, $hint])
<div><label for="{{ $area }}-{{ $field }}" class="block text-sm font-semibold mb-2">{{ $title }}</label><textarea id="{{ $area }}-{{ $field }}" name="code[{{ $area }}][{{ $field }}]" rows="7" maxlength="100000" spellcheck="false" class="theme-input w-full font-mono text-xs">{{ old('code.'.$area.'.'.$field, $settings[$area][$field]) }}</textarea><p class="text-xs mt-2" style="color:var(--text-muted)">{{ $hint }}</p></div>
@endforeach
</div>
<details><summary class="cursor-pointer text-sm font-semibold">HTML snippets and additional placements</summary><div class="grid gap-4 mt-4">
@foreach(['head_html'=>'Before closing head', 'body_start_html'=>'Immediately after opening body', 'body_end_html'=>'Before closing body'] as $field=>$title)<div><label for="{{ $area }}-{{ $field }}" class="block text-sm mb-2">{{ $title }}</label><textarea id="{{ $area }}-{{ $field }}" name="code[{{ $area }}][{{ $field }}]" rows="5" maxlength="100000" spellcheck="false" class="theme-input w-full font-mono text-xs">{{ old('code.'.$area.'.'.$field, $settings[$area][$field]) }}</textarea></div>@endforeach
<p class="text-xs" style="color:var(--text-muted)">Use complete HTML snippets here, including script or style tags when required by your provider. Code runs as supplied. Analytics and marketing snippets should respect visitor consent. Custom code does not run in this admin panel, publicly shared user pages, login pages, API responses or downloaded files.</p>
</div></details></section>
@endforeach
<div class="flex items-center gap-4"><button type="submit" class="px-6 py-3 rounded-xl font-semibold" style="background:linear-gradient(110deg,#4655ee,#7845dd);color:#fff">Save custom code</button><p class="text-sm" style="color:var(--text-muted)">Switch an area off to pause its code without deleting it.</p></div>
</form></div>
@endsection
