@extends('user.layouts.app')
@section('title', 'Add Tracker')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.pixels.index') }}" class="text-white/30 hover:text-white/50"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-2xl font-bold text-white">Add Tracker</h1>
    </div>

    <form method="POST" action="{{ route('user.pixels.store') }}">
        @csrf
        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Facebook Main Tracker" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40" required>
                @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Type <span class="text-red-500">*</span></label>
                <select name="type" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40" required>
                    <option value="">Select type</option>
                    <option value="facebook" {{ old('type') === 'facebook' ? 'selected' : '' }}>Facebook</option>
                    <option value="google_analytics" {{ old('type') === 'google_analytics' ? 'selected' : '' }}>Google Analytics</option>
                    <option value="google_tag_manager" {{ old('type') === 'google_tag_manager' ? 'selected' : '' }}>Google Tag Manager</option>
                    <option value="linkedin" {{ old('type') === 'linkedin' ? 'selected' : '' }}>LinkedIn</option>
                    <option value="twitter" {{ old('type') === 'twitter' ? 'selected' : '' }}>Twitter / X</option>
                    <option value="pinterest" {{ old('type') === 'pinterest' ? 'selected' : '' }}>Pinterest</option>
                    <option value="tiktok" {{ old('type') === 'tiktok' ? 'selected' : '' }}>TikTok</option>
                    <option value="snapchat" {{ old('type') === 'snapchat' ? 'selected' : '' }}>Snapchat</option>
                    <option value="quora" {{ old('type') === 'quora' ? 'selected' : '' }}>Quora</option>
                    <option value="custom" {{ old('type') === 'custom' ? 'selected' : '' }}>Custom</option>
                </select>
                @error('type') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Tracker ID <span class="text-red-500">*</span></label>
                <input type="text" name="pixel_id" value="{{ old('pixel_id') }}" placeholder="e.g. 123456789" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-violet-500/40 focus:border-violet-500/40" required>
                @error('pixel_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-4">
            <a href="{{ route('user.pixels.index') }}" class="px-4 py-2.5 text-sm text-white/60 hover:bg-white/10 rounded-xl">Cancel</a>
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium">Add Tracker</button>
        </div>
    </form>
</div>
@endsection
