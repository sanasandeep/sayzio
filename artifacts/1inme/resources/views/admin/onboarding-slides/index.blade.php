@extends('admin.layouts.app')
@section('title', 'Onboarding Slides')
@section('page-title', 'Onboarding Slides')

@push('styles')
<style>
html.light-mode .ons-desc { color: #64748b; }
html.light-mode .ons-success { background-color: rgba(16,185,129,.08); border-color: rgba(16,185,129,.25); color: #065f46; }
html.light-mode .ons-drift-banner { background-color: rgba(245,158,11,.08); border-color: rgba(245,158,11,.30); color: #78350f; }
html.light-mode .ons-drift-icon { color: #b45309; }
html.light-mode .ons-drift-body { color: #92400e; }
html.light-mode .ons-drift-slugs { color: #a16207; }
html.light-mode .ons-drift-accent { color: #78350f; }
html.light-mode .ons-card-wrap { border-color: rgba(0,0,0,.10); }
html.light-mode .ons-card-body-text { color: #475569; }
html.light-mode .ons-badge-customized { background-color: rgba(180,83,9,.10); color: #78350f; border-color: rgba(180,83,9,.25); }
html.light-mode .ons-badge-custom { background-color: rgba(0,0,0,.06); color: #475569; border-color: rgba(0,0,0,.12); }
html.light-mode .ons-badge-default { background-color: rgba(5,150,105,.10); color: #065f46; border-color: rgba(5,150,105,.25); }
html.light-mode .ons-card-meta { color: #64748b; }
html.light-mode .ons-card-order { color: #334155; }
html.light-mode .ons-card-footer { border-color: rgba(0,0,0,.08); }
html.light-mode .ons-card-action { color: #94a3b8; }
html.light-mode .ons-empty { color: #94a3b8; }
</style>
@endpush

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-white/40 ons-desc">Splash slider shown to new users in the mobile app. Reorder by editing the sort order; inactive slides are hidden from the app.</p>
    <a href="{{ route('admin.onboarding-slides.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700"><i class="fas fa-plus mr-2"></i>Add Slide</a>
</div>

@if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm ons-success">{{ session('success') }}</div>
@endif

@if($drifted->isNotEmpty())
    <div class="mb-4 p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-200 text-sm ons-drift-banner">
        <div class="flex items-start gap-3">
            <i class="fas fa-triangle-exclamation mt-0.5 text-amber-300 ons-drift-icon"></i>
            <div>
                <p class="font-medium">{{ $drifted->count() }} slide{{ $drifted->count() === 1 ? '' : 's' }} edited away from the shipped default wording.</p>
                <p class="text-amber-200/70 mt-1 ons-drift-body">The mobile app ships a bundled copy of these slides shown on a fresh install or offline. The edited slide{{ $drifted->count() === 1 ? '' : 's' }} below no longer match{{ $drifted->count() === 1 ? 'es' : '' }} that default, so a returning or offline user may see different wording than someone hitting the live copy. Look for the <span class="font-medium text-amber-100 ons-drift-accent">Customized</span> badge.</p>
                <p class="text-amber-200/60 mt-1 font-mono text-[11px] ons-drift-slugs">{{ $drifted->pluck('slug')->implode(', ') }}</p>
            </div>
        </div>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($slides as $slide)
    <div class="glass rounded-2xl border border-white/10 overflow-hidden ons-card-wrap {{ $slide->status !== 'active' ? 'opacity-60' : '' }}">
        {{-- Dark phone-style preview — intentionally stays dark in both modes --}}
        <div class="relative aspect-[3/4] bg-black/40">
            @if($slide->image_path)
                <img src="{{ $slide->imageUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover">
            @else
                <div class="absolute inset-0 flex items-center justify-center text-white/30 text-sm"><i class="fas fa-image mr-2"></i>No image</div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-black/40"></div>
            <div class="absolute top-3 left-3 right-3 flex items-center justify-between">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium backdrop-blur bg-white/15 text-white border border-white/20">{{ $slide->category }}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium backdrop-blur
                    {{ $slide->status === 'active' ? 'bg-emerald-500/20 text-emerald-200 border border-emerald-400/30' : 'bg-white/10 text-white/60 border border-white/20' }}">
                    {{ ucfirst($slide->status) }}
                </span>
            </div>
            <div class="absolute bottom-3 left-3 right-3">
                <div class="font-semibold text-white text-sm leading-snug line-clamp-3 drop-shadow">{{ $slide->title }}</div>
            </div>
        </div>

        <div class="p-4 space-y-3">
            <p class="text-xs text-white/50 line-clamp-2 ons-card-body-text">{{ $slide->body ?? '—' }}</p>
            @php($state = $slide->customizationState())
            <div>
                @if($state === 'customized')
                    <span title="Copy differs from the shipped default in: {{ implode(', ', $slide->driftedFields()) }}" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-500/20 text-amber-200 border border-amber-400/30 ons-badge-customized"><i class="fas fa-pen-nib text-[10px]"></i>Customized</span>
                @elseif($state === 'custom')
                    <span title="Admin-created slide — no shipped default to compare against" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-white/10 text-white/60 border border-white/20 ons-badge-custom"><i class="fas fa-plus text-[10px]"></i>Custom</span>
                @else
                    <span title="Copy matches the shipped default wording exactly" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-500/20 text-emerald-200 border border-emerald-400/30 ons-badge-default"><i class="fas fa-check text-[10px]"></i>Default</span>
                @endif
            </div>
            <div class="flex items-center justify-between text-[11px] text-white/40 ons-card-meta">
                <span class="font-mono">{{ $slide->slug }}</span>
                <span>order: <span class="text-white/70 ons-card-order">{{ $slide->sort_order }}</span></span>
            </div>
            <div class="flex items-center justify-end gap-2 pt-3 border-t border-white/5 ons-card-footer">
                <a href="{{ route('admin.onboarding-slides.edit', $slide) }}" class="text-white/40 hover:text-blue-400 ons-card-action" title="Edit"><i class="fas fa-edit"></i></a>
                <form action="{{ route('admin.onboarding-slides.destroy', $slide) }}" method="POST" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this slide?', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">@csrf @method('DELETE')
                    <button class="text-white/40 hover:text-red-400 ons-card-action" title="Delete"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="col-span-full text-center text-white/40 py-12 ons-empty">
        No slides yet. <a href="{{ route('admin.onboarding-slides.create') }}" class="text-blue-400 hover:underline">Create your first one</a>.
    </div>
    @endforelse
</div>
@endsection
