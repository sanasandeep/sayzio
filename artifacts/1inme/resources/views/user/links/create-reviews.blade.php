@extends('user.layouts.app')
@section('title', 'Create Reviews Page')

@section('content')
@include('user.links.partials.create-premium-style')
<div class="create-premium">
    @include('user.partials.create-step-header', [
        'title' => 'Create Reviews Page',
        'icon' => 'fa-star',
    ])


    <form method="POST" action="{{ route('user.links.store') }}">
        @csrf
        <input type="hidden" name="type" value="reviews">
        <div class="glass rounded-2xl p-6 space-y-4">
            <p class="text-sm text-white/50">
                A public, Google-style reviews page. Visitors can leave star ratings, written reviews, photos, audio and video, no account required. You moderate, reply, and can sync reviews from Google and Trustpilot.
            </p>

            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Page Title</label>
                <input type="text" name="title" value="{{ old('title') }}" placeholder="e.g. Reviews for Acme Studio" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">
                @error('title') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
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

            @if(($domains ?? collect())->count() > 0)
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Domain</label>
                <select name="domain_id" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40">
                    @foreach($domains as $domain)
                        <option value="{{ $domain->id }}" @selected(old('domain_id', $defaultDomainId ?? null) == $domain->id)>{{ $domain->domain }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <a href="{{ route('user.links.index') }}" class="px-5 py-2.5 rounded-xl text-sm text-white/60 hover:text-white">Cancel</a>
            <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white">Create &amp; Configure</button>
        </div>
    </form>
</div>
@endsection
