@php($businessStarters = \App\Modules\User\Services\BusinessStarterCatalog::forType($link->type))
@if(count($businessStarters))
<div style="margin:12px 0 18px;padding:16px;border:1px solid var(--border-glass);border-radius:14px;background:var(--bg-card);color:var(--text-primary)">
<form method="post" action="{{ route('user.links.business-starter.store', $link) }}" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">@csrf
<label for="business-starter-{{ $link->id }}" style="font-weight:650"><i class="fas fa-layer-group" aria-hidden="true"></i> Start with a template</label>
<select id="business-starter-{{ $link->id }}" name="starter" required class="theme-input" style="width:auto;min-width:180px;appearance:auto;padding:10px 14px;border-radius:10px">
@foreach($businessStarters as $starter)<option value="{{ $starter['key'] }}">{{ $starter['label'] }}</option>@endforeach
</select>
<button type="submit" class="btn-primary" style="padding:10px 16px;border-radius:10px">Add starter content</button>
</form><p style="color:var(--text-muted);font-size:12px;margin-top:10px">Adds editable draft categories and sample items. Existing content is kept. Review details and prices before publishing.</p>
@if(session('success'))<p role="status" style="margin-top:8px">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
</div>
@endif
