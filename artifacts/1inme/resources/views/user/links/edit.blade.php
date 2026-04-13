@extends('user.layouts.app')
@section('title', 'Edit Link')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.show', $link) }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-2xl font-bold text-gray-900">Edit Link</h1>
    </div>

    <form method="POST" action="{{ route('user.links.update', $link) }}" x-data="{ passwordProtect: {{ $link->is_password_protected ? 'true' : 'false' }} }">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Link Details</h2>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Short URL</label>
                <div class="flex items-center gap-2 text-sm text-primary-600 bg-primary-50 px-3 py-2.5 rounded-lg">
                    <span>{{ $link->getShortUrl() }}</span>
                    <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded uppercase">{{ $link->type }}</span>
                </div>
            </div>

            @if($link->type === 'url')
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Destination URL</label>
                <input type="url" name="long_url" value="{{ old('long_url', $link->long_url) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                @error('long_url') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            @endif

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                <input type="text" name="title" value="{{ old('title', $link->title) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Project</label>
                    <select name="project_id" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500">
                        <option value="">No project</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ old('project_id', $link->project_id) == $project->id ? 'selected' : '' }}>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="is_active" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500">
                        <option value="1" {{ $link->is_active ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ !$link->is_active ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Protection & Expiry</h2>
            <div class="space-y-3">
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="is_password_protected" value="1" x-model="passwordProtect" class="rounded text-primary-600 focus:ring-primary-500">
                    <span class="text-sm text-gray-700">Password protect this link</span>
                </label>
                <div x-show="passwordProtect" class="ml-7">
                    <input type="password" name="password" placeholder="New password (leave empty to keep current)" class="w-full max-w-xs border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Expiration Date</label>
                    <input type="datetime-local" name="expires_at" value="{{ old('expires_at', $link->expires_at?->format('Y-m-d\TH:i')) }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">SEO Settings</h2>
            <div class="space-y-3">
                <input type="text" name="seo_title" value="{{ old('seo_title', $link->seo_title) }}" placeholder="SEO Title" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                <textarea name="seo_description" placeholder="SEO Description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">{{ old('seo_description', $link->seo_description) }}</textarea>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">UTM Parameters</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <input type="text" name="utm_source" value="{{ old('utm_source', $link->utm_source) }}" placeholder="UTM Source" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                <input type="text" name="utm_medium" value="{{ old('utm_medium', $link->utm_medium) }}" placeholder="UTM Medium" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                <input type="text" name="utm_campaign" value="{{ old('utm_campaign', $link->utm_campaign) }}" placeholder="UTM Campaign" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                <input type="text" name="utm_term" value="{{ old('utm_term', $link->utm_term) }}" placeholder="UTM Term" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                <input type="text" name="utm_content" value="{{ old('utm_content', $link->utm_content) }}" placeholder="UTM Content" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
            </div>
        </div>

        @if($pixels->count())
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Tracking Pixels</h2>
            <div class="space-y-2">
                @foreach($pixels as $pixel)
                <label class="flex items-center gap-3">
                    <input type="checkbox" name="pixel_ids[]" value="{{ $pixel->id }}" {{ $link->pixels->contains($pixel->id) ? 'checked' : '' }} class="rounded text-primary-600 focus:ring-primary-500">
                    <span class="text-sm text-gray-700">{{ $pixel->name }} ({{ ucfirst(str_replace('_', ' ', $pixel->type)) }})</span>
                </label>
                @endforeach
            </div>
        </div>
        @endif

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('user.links.show', $link) }}" class="px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</a>
            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium">Save Changes</button>
        </div>
    </form>
</div>
@endsection
