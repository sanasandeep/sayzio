@extends('admin.layouts.app')
@section('title', 'Link: ' . ($link->title ?: $link->alias))

@section('content')
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('admin.links.index') }}" class="text-white/30 hover:text-white/50"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-white">{{ $link->title ?: $link->alias }}</h1>
    <span class="px-2 py-0.5 rounded text-xs {{ $link->is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' }}">
        {{ $link->is_active ? 'Active' : 'Inactive' }}
    </span>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="glass rounded-2xl p-5">
        <div class="text-3xl font-bold text-white">{{ number_format($link->total_clicks) }}</div>
        <div class="text-sm text-white/40">Total Clicks</div>
    </div>
    <div class="glass rounded-2xl p-5">
        <div class="text-3xl font-bold text-violet-400">{{ number_format($link->unique_clicks) }}</div>
        <div class="text-sm text-white/40">Unique Clicks</div>
    </div>
    <div class="glass rounded-2xl p-5">
        <div class="text-sm text-white/40 mb-1">Owner</div>
        <div class="text-lg font-semibold text-white">{{ $link->user->name }}</div>
        <div class="text-xs text-white/40">{{ $link->user->email }}</div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-4">Link Details</h2>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-white/40">Type</dt><dd class="font-medium">{{ \App\Modules\User\Models\Link::typeLabel($link->type) }}</dd></div>
            <div class="flex justify-between"><dt class="text-white/40">Short URL</dt><dd class="font-mono text-violet-400">{{ $link->getShortUrl() }}</dd></div>
            @if($link->long_url)
            <div><dt class="text-white/40">Destination</dt><dd class="text-violet-400 break-all mt-1">{{ $link->long_url }}</dd></div>
            @endif
            @if($link->project)
            <div class="flex justify-between"><dt class="text-white/40">Project</dt><dd>{{ $link->project->name }}</dd></div>
            @endif
            <div class="flex justify-between"><dt class="text-white/40">Created</dt><dd>{{ $link->created_at->format('M d, Y H:i') }}</dd></div>
            @if($link->expires_at)
            <div class="flex justify-between"><dt class="text-white/40">Expires</dt><dd>{{ $link->expires_at->format('M d, Y H:i') }}</dd></div>
            @endif
            <div class="flex justify-between"><dt class="text-white/40">Password Protected</dt><dd>{{ $link->is_password_protected ? 'Yes' : 'No' }}</dd></div>
        </dl>
    </div>

    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-4">SEO & UTM</h2>
        <dl class="space-y-3 text-sm">
            @if($link->seo_title)<div class="flex justify-between"><dt class="text-white/40">SEO Title</dt><dd>{{ $link->seo_title }}</dd></div>@endif
            @if($link->seo_description)<div><dt class="text-white/40">SEO Description</dt><dd class="mt-1">{{ $link->seo_description }}</dd></div>@endif
            @if($link->utm_source)<div class="flex justify-between"><dt class="text-white/40">UTM Source</dt><dd>{{ $link->utm_source }}</dd></div>@endif
            @if($link->utm_medium)<div class="flex justify-between"><dt class="text-white/40">UTM Medium</dt><dd>{{ $link->utm_medium }}</dd></div>@endif
            @if($link->utm_campaign)<div class="flex justify-between"><dt class="text-white/40">UTM Campaign</dt><dd>{{ $link->utm_campaign }}</dd></div>@endif
            @if(!$link->seo_title && !$link->utm_source)
            <p class="text-white/30">No SEO or UTM parameters configured.</p>
            @endif
        </dl>

        @if($link->pixels->count())
        <h3 class="text-sm font-semibold text-white/60 mt-4 mb-2">Tracking ({{ $link->pixels->count() }})</h3>
        <div class="flex flex-wrap gap-1">
            @foreach($link->pixels as $pixel)
                <span class="bg-white/10 text-white/60 px-2 py-0.5 rounded text-xs">{{ $pixel->name }}</span>
            @endforeach
        </div>
        @endif
    </div>
</div>

@if($clicksOverTime->count())
<div class="glass rounded-2xl p-6 mb-6">
    <h2 class="text-lg font-semibold text-white mb-4">Clicks (Last 30 Days)</h2>
    <div class="grid grid-cols-7 gap-1">
        @foreach($clicksOverTime as $day)
        <div class="text-center">
            <div class="text-xs text-white/40">{{ \Carbon\Carbon::parse($day->date)->format('M d') }}</div>
            <div class="text-sm font-medium">{{ $day->count }}</div>
        </div>
        @endforeach
    </div>
</div>
@endif

<div class="flex gap-3">
    <form method="POST" action="{{ route('admin.links.toggle', $link) }}" class="inline">
        @csrf
        <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-xl text-sm font-medium">
            {{ $link->is_active ? 'Disable Link' : 'Enable Link' }}
        </button>
    </form>
    <form method="POST" action="{{ route('admin.links.destroy', $link) }}" class="inline" onsubmit="return confirm('Delete this link permanently?')">
        @csrf @method('DELETE')
        <button type="submit" class="bg-red-500/100 hover:bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-medium">Delete Link</button>
    </form>
    <a href="{{ route('admin.links.index') }}" class="text-white/50 hover:text-white px-4 py-2 text-sm">Back to list</a>
</div>
@endsection
