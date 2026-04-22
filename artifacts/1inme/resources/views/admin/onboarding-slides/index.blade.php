@extends('admin.layouts.app')
@section('title', 'Onboarding Slides')
@section('page-title', 'Onboarding Slides')

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-white/40">Splash slider shown to new users in the mobile app. Reorder by editing the sort order; inactive slides are hidden from the app.</p>
    <a href="{{ route('admin.onboarding-slides.create') }}" class="px-4 py-2 bg-violet-600 text-white rounded-xl text-sm font-medium hover:bg-violet-700"><i class="fas fa-plus mr-2"></i>Add Slide</a>
</div>

@if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('success') }}</div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($slides as $slide)
    <div class="glass rounded-2xl border border-white/10 overflow-hidden {{ $slide->status !== 'active' ? 'opacity-60' : '' }}">
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
            <p class="text-xs text-white/50 line-clamp-2">{{ $slide->body ?? '—' }}</p>
            <div class="flex items-center justify-between text-[11px] text-white/40">
                <span class="font-mono">{{ $slide->slug }}</span>
                <span>order: <span class="text-white/70">{{ $slide->sort_order }}</span></span>
            </div>
            <div class="flex items-center justify-end gap-2 pt-3 border-t border-white/5">
                <a href="{{ route('admin.onboarding-slides.edit', $slide) }}" class="text-white/40 hover:text-violet-400" title="Edit"><i class="fas fa-edit"></i></a>
                <form action="{{ route('admin.onboarding-slides.destroy', $slide) }}" method="POST" class="inline" onsubmit="return confirm('Delete this slide?')">@csrf @method('DELETE')
                    <button class="text-white/40 hover:text-red-400" title="Delete"><i class="fas fa-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
    @empty
    <div class="col-span-full text-center text-white/40 py-12">
        No slides yet. <a href="{{ route('admin.onboarding-slides.create') }}" class="text-violet-400 hover:underline">Create your first one</a>.
    </div>
    @endforelse
</div>
@endsection
