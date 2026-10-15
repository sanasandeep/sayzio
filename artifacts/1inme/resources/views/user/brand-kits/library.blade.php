@extends('user.layouts.app')
@section('title','Brand Kits')
@section('content')
@include('user.brand-kits.styles')
<div class="bk-page" x-data="{search:'',archived:false}">
@include('user.partials.page-hero',['title'=>'Brand Kits','subtitle'=>'Create reusable brand identities. Assign each kit to the pages and links where it belongs.','emblemIcon'=>'fa-palette','back'=>route('user.dashboard'),'chips'=>[['text'=>($cap<0||$cap>=PHP_INT_MAX?'Unlimited':$cap).' kits allowed'],['text'=>number_format($balance).' coins']],'actions'=>[['label'=>$canCreate?'Create Brand Kit':'View plan options','url'=>$canCreate?route('user.brand-kits.create'):route('user.upgrade'),'icon'=>'fa-plus','class'=>'btn-primary']]])
@unless($canCreate)<p class="bk-feedback">Your saved-kit limit has been reached. Existing kits can still be edited and assigned. Manual saving uses no AI coins; your plan's saved-kit limit still applies.</p>@endunless
@if(session('status'))<p class="bk-feedback" role="status">{{ session('status') }}</p>@endif
<div class="bk-stat-grid">
@foreach([['Brand kits',$kits->whereNull('config.archived_at')->count(),'#123c85','#3b82f6','pages'],['Assigned pages / links / QR',$assignedLinks->count()+$assignedQrCount,'#512b89','#8b5cf6','links'],['Saved assets',$assetsCount,'#075953','#0d9488','folders']] as [$label,$value,$base,$glow,$kind])
<div class="ov-panel ov-count" style="--ov-tone:{{ $base }};--ov-color:{{ $glow }}"><div class="ov-count-top"><span class="ov-count-label">{{ $label }}</span></div>@include('user.partials.count-card-art',['kind'=>$kind,'artId'=>'brand-stat-'.$loop->index])<div class="ov-count-bottom"><strong>{{ $value }}</strong><p>In this workspace</p></div></div>
@endforeach
</div>
<div class="bk-actions mb-5"><input class="bk-input" style="max-width:420px" x-model="search" placeholder="Search Brand Kits…" aria-label="Search Brand Kits"><label class="bk-muted"><input type="checkbox" x-model="archived"> Show archived kits</label></div>
<div class="bk-grid">
@forelse($kits as $kit)
<article class="bk-panel bk-library-card" x-show="{{ !empty($kit->config['archived_at']) ? 'archived && ' : '' }}(!search || @js(mb_strtolower($kit->name)).includes(search.toLowerCase()))">
@php($coverColor=preg_match('/^#[0-9a-fA-F]{6}$/',$kit->palette()['primary']??'')?$kit->palette()['primary']:'#3454ef')
<div class="bk-cover" style="--brand-color:{{ $coverColor }}"><span class="bk-cover-caption">BRAND IDENTITY</span><strong>{{ $kit->name }}</strong><span>{{ \Illuminate\Support\Str::limit($kit->taglines()[0]??'Made to feel like you.',65) }}</span><div class="bk-cover-orbit" aria-hidden="true"></div></div>
<div class="bk-actions mb-4">@if($kit->logo())<img src="{{ $kit->logo() }}" alt="" style="width:48px;height:48px;object-fit:contain;border-radius:12px" loading="lazy">@else<i class="fas fa-palette text-2xl" style="color:{{ $kit->palette()['primary']??'#3454ef' }}" aria-hidden="true"></i>@endif<h2>{{ $kit->name }}</h2></div>
<p class="bk-muted mb-4">{{ \Illuminate\Support\Str::limit($kit->config['description']??$kit->config['bio']??'',100) }}</p>
<div class="bk-actions mb-3">@foreach(array_filter([$kit->palette()['primary']??null,$kit->palette()['secondary']??null,$kit->palette()['accent']??null]) as $color)<span class="bk-swatch" style="background:{{ $color }}" title="{{ $color }}"></span>@endforeach</div>
<p class="bk-type-sample">Aa <span>{{ $kit->fonts()['heading']??'Inter' }}</span></p><p class="bk-muted">{{ $kit->fonts()['heading']??'Inter' }} / {{ $kit->fonts()['body']??'Inter' }} · {{ $assignedLinks->where('settings.brand_identity.kit_id',$kit->id)->count() }} assigned</p>
<p class="bk-muted">{{ !empty($kit->config['archived_at'])?'Archived':($kit->is_default?'Default for new AI drafts':'Ready to use') }}</p>
<div class="bk-actions mt-4"><a class="btn-primary text-sm" href="{{ route('user.brand-kits.edit',$kit) }}">Edit & assign</a><details class="bk-card-menu"><summary class="btn-ghost text-sm">More actions</summary><div class="bk-menu-content"><form method="POST" action="{{ route('user.brand-kits.duplicate',$kit) }}">@csrf<button class="btn-ghost text-sm">Duplicate</button></form><form method="POST" action="{{ route('user.brand-kits.archive',$kit) }}">@csrf<button class="btn-ghost text-sm">{{ !empty($kit->config['archived_at'])?'Restore':'Archive' }}</button></form>@if(Route::has('user.brand-kits.export'))<a href="{{ route('user.brand-kits.export',[$kit,'pdf']) }}" class="btn-ghost text-sm">PDF guide</a><a href="{{ route('user.brand-kits.export',[$kit,'zip']) }}" class="btn-ghost text-sm">Export ZIP</a>@endif<form method="POST" action="{{ route('user.brand-kits.destroy',$kit) }}" onsubmit="return confirm('Permanently delete this Brand Kit? Assignments will be removed. Published styles and library files will be kept. This cannot be undone.');">@csrf @method('DELETE')<button class="btn-ghost text-sm" style="color:#dc2626">Delete permanently</button></form></div></details></div>
</article>
@empty<div class="bk-panel"><h2>Your first brand starts here</h2><p class="bk-muted mb-4">Add your own details for free, or ask AI to prepare a draft after reviewing the coin estimate.</p><a href="{{ route('user.brand-kits.create') }}" class="btn-primary">Create Brand Kit</a></div>@endforelse
</div>
</div>
@endsection
