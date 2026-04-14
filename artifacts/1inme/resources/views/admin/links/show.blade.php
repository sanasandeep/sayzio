@extends('admin.layouts.app')
@section('title', 'Link: ' . ($link->title ?: $link->alias))

@section('content')
<div class="flex items-center gap-4 mb-6">
    <a href="{{ route('admin.links.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
    <h1 class="text-2xl font-bold text-gray-900">{{ $link->title ?: $link->alias }}</h1>
    <span class="px-2 py-0.5 rounded text-xs {{ $link->is_active ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
        {{ $link->is_active ? 'Active' : 'Inactive' }}
    </span>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="text-3xl font-bold text-gray-900">{{ number_format($link->total_clicks) }}</div>
        <div class="text-sm text-gray-500">Total Clicks</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="text-3xl font-bold text-purple-600">{{ number_format($link->unique_clicks) }}</div>
        <div class="text-sm text-gray-500">Unique Clicks</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <div class="text-sm text-gray-500 mb-1">Owner</div>
        <div class="text-lg font-semibold text-gray-900">{{ $link->user->name }}</div>
        <div class="text-xs text-gray-500">{{ $link->user->email }}</div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Link Details</h2>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">Type</dt><dd class="font-medium">{{ ucfirst($link->type) }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Short URL</dt><dd class="font-mono text-purple-600">{{ $link->getShortUrl() }}</dd></div>
            @if($link->long_url)
            <div><dt class="text-gray-500">Destination</dt><dd class="text-purple-600 break-all mt-1">{{ $link->long_url }}</dd></div>
            @endif
            @if($link->project)
            <div class="flex justify-between"><dt class="text-gray-500">Project</dt><dd>{{ $link->project->name }}</dd></div>
            @endif
            <div class="flex justify-between"><dt class="text-gray-500">Created</dt><dd>{{ $link->created_at->format('M d, Y H:i') }}</dd></div>
            @if($link->expires_at)
            <div class="flex justify-between"><dt class="text-gray-500">Expires</dt><dd>{{ $link->expires_at->format('M d, Y H:i') }}</dd></div>
            @endif
            <div class="flex justify-between"><dt class="text-gray-500">Password Protected</dt><dd>{{ $link->is_password_protected ? 'Yes' : 'No' }}</dd></div>
        </dl>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">SEO & UTM</h2>
        <dl class="space-y-3 text-sm">
            @if($link->seo_title)<div class="flex justify-between"><dt class="text-gray-500">SEO Title</dt><dd>{{ $link->seo_title }}</dd></div>@endif
            @if($link->seo_description)<div><dt class="text-gray-500">SEO Description</dt><dd class="mt-1">{{ $link->seo_description }}</dd></div>@endif
            @if($link->utm_source)<div class="flex justify-between"><dt class="text-gray-500">UTM Source</dt><dd>{{ $link->utm_source }}</dd></div>@endif
            @if($link->utm_medium)<div class="flex justify-between"><dt class="text-gray-500">UTM Medium</dt><dd>{{ $link->utm_medium }}</dd></div>@endif
            @if($link->utm_campaign)<div class="flex justify-between"><dt class="text-gray-500">UTM Campaign</dt><dd>{{ $link->utm_campaign }}</dd></div>@endif
            @if(!$link->seo_title && !$link->utm_source)
            <p class="text-gray-400">No SEO or UTM parameters configured.</p>
            @endif
        </dl>

        @if($link->pixels->count())
        <h3 class="text-sm font-semibold text-gray-700 mt-4 mb-2">Tracking Pixels ({{ $link->pixels->count() }})</h3>
        <div class="flex flex-wrap gap-1">
            @foreach($link->pixels as $pixel)
                <span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded text-xs">{{ $pixel->name }}</span>
            @endforeach
        </div>
        @endif
    </div>
</div>

@if($clicksOverTime->count())
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Clicks (Last 30 Days)</h2>
    <div class="grid grid-cols-7 gap-1">
        @foreach($clicksOverTime as $day)
        <div class="text-center">
            <div class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($day->date)->format('M d') }}</div>
            <div class="text-sm font-medium">{{ $day->count }}</div>
        </div>
        @endforeach
    </div>
</div>
@endif

<div class="flex gap-3">
    <form method="POST" action="{{ route('admin.links.toggle', $link) }}" class="inline">
        @csrf
        <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm font-medium">
            {{ $link->is_active ? 'Disable Link' : 'Enable Link' }}
        </button>
    </form>
    <form method="POST" action="{{ route('admin.links.destroy', $link) }}" class="inline" onsubmit="return confirm('Delete this link permanently?')">
        @csrf @method('DELETE')
        <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium">Delete Link</button>
    </form>
    <a href="{{ route('admin.links.index') }}" class="text-gray-600 hover:text-gray-800 px-4 py-2 text-sm">Back to list</a>
</div>
@endsection
