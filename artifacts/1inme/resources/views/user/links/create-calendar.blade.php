@extends('user.layouts.app')
@section('title', 'Create Calendar')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-white/30 hover:text-white/50" title="Choose a different type"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white">Create Calendar</h1>
            <p class="text-xs text-white/40 mt-0.5">Step 2 of 2 &middot; <a href="{{ route('user.links.create') . (!empty($prefillAlias ?? '') ? '?alias=' . urlencode($prefillAlias) : '') }}" class="text-blue-400 hover:underline">change type</a></p>
        </div>
    </div>

    <form method="POST" action="{{ route('user.links.store') }}">
        @csrf
        <input type="hidden" name="type" value="calendar">

        <div class="glass rounded-2xl p-6 space-y-4">
            <p class="text-sm text-white/50">
                A shareable, followable calendar. Publish events with times, locations and hashtags, people can <strong class="text-white/70">follow</strong> your calendar, get reminders, and subscribe in Google Calendar or Apple Calendar via an ICS feed. You'll add events next in the editor.
            </p>

            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Calendar Title</label>
                <input type="text" name="title" value="{{ old('title') }}" placeholder="e.g. Community Events" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">
                @error('title') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Description <span class="text-white/30">(optional)</span></label>
                <textarea name="calendar_description" rows="2" placeholder="What is this calendar about?" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">{{ old('calendar_description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    @include('user.links.partials.alias-field')
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Folder</label>
                    <select name="project_id" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">
                        <option value="">No folder</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') == $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Default timezone</label>
                    <select name="calendar_timezone" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">
                        @foreach($timezones as $tz)
                            <option value="{{ $tz }}" @selected(old('calendar_timezone', $defaultTimezone) === $tz)>{{ $tz }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Accent color</label>
                    <input type="color" name="calendar_accent_color" value="{{ old('calendar_accent_color', '#3d6bff') }}" class="w-full h-[42px] border border-white/10 rounded-xl px-2 py-1 bg-transparent cursor-pointer">
                </div>
            </div>

            @if(($domains ?? collect())->count() > 0)
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Domain</label>
                <select name="domain_id" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">
                    @foreach($domains as $domain)
                        <option value="{{ $domain->id }}" @selected(old('domain_id', $defaultDomainId ?? null) == $domain->id)>{{ $domain->host }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <a href="{{ route('user.links.index') }}" class="px-5 py-2.5 rounded-xl text-sm text-white/60 hover:text-white">Cancel</a>
            <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white">Create Calendar</button>
        </div>
    </form>
</div>
@endsection
