@extends('user.layouts.app')
@section('title', 'New AI Mind')

@section('content')
@include('user.minds.partials.styles')
<div class="minds-page w-full space-y-6">
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>@endif

    @include('user.partials.page-hero', ['title' => 'New AI Mind', 'subtitle' => 'Name your knowledge base, then add sources for your AI agents.', 'icon' => 'fa-brain', 'back' => route('user.minds.index'), 'actions' => [], 'chips' => []])

    <form method="POST" action="{{ route('user.minds.store') }}" class="rounded-2xl border border-white/10 bg-white/[0.03] p-6 space-y-4">
        @csrf
        <div>
            <label class="text-xs uppercase tracking-wider text-white/40">Name</label>
            <input name="name" required maxlength="120" value="{{ old('name') }}"
                class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm" placeholder="e.g. My Coaching Library">
            @error('name')<p class="text-xs text-red-300 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="text-xs uppercase tracking-wider text-white/40">Description (optional)</label>
            <textarea name="description" maxlength="2000" rows="3"
                class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm" placeholder="Short note for yourself.">{{ old('description') }}</textarea>
            @error('description')<p class="text-xs text-red-300 mt-1">{{ $message }}</p>@enderror
        </div>
        @include('user.ai._partials.cost-estimate', ['cost' => [
            'feature' => 'minds',
            'mode'    => 'fixed',
            'prefix'  => '≈',
            'note'    => 'per ~1,000 words of source you add',
        ]])
        <div class="flex items-center justify-end gap-2 pt-2">
            <a href="{{ route('user.minds.index') }}" class="px-4 py-2 rounded-xl bg-white/5 text-white/70 text-sm">Cancel</a>
            <button class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium">Create AI Mind</button>
        </div>
    </form>
</div>
@endsection
