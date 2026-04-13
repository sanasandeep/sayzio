@extends('user.layouts.app')
@section('title', 'Add Tracking Pixel')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.pixels.index') }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-2xl font-bold text-gray-900">Add Tracking Pixel</h1>
    </div>

    <form method="POST" action="{{ route('user.pixels.store') }}">
        @csrf
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Facebook Main Pixel" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                <select name="type" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500" required>
                    <option value="">Select type</option>
                    <option value="facebook" {{ old('type') === 'facebook' ? 'selected' : '' }}>Facebook Pixel</option>
                    <option value="google_analytics" {{ old('type') === 'google_analytics' ? 'selected' : '' }}>Google Analytics</option>
                    <option value="google_tag_manager" {{ old('type') === 'google_tag_manager' ? 'selected' : '' }}>Google Tag Manager</option>
                    <option value="linkedin" {{ old('type') === 'linkedin' ? 'selected' : '' }}>LinkedIn Insight</option>
                    <option value="twitter" {{ old('type') === 'twitter' ? 'selected' : '' }}>Twitter Pixel</option>
                    <option value="pinterest" {{ old('type') === 'pinterest' ? 'selected' : '' }}>Pinterest Tag</option>
                    <option value="tiktok" {{ old('type') === 'tiktok' ? 'selected' : '' }}>TikTok Pixel</option>
                    <option value="snapchat" {{ old('type') === 'snapchat' ? 'selected' : '' }}>Snapchat Pixel</option>
                    <option value="quora" {{ old('type') === 'quora' ? 'selected' : '' }}>Quora Pixel</option>
                    <option value="custom" {{ old('type') === 'custom' ? 'selected' : '' }}>Custom</option>
                </select>
                @error('type') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Pixel ID <span class="text-red-500">*</span></label>
                <input type="text" name="pixel_id" value="{{ old('pixel_id') }}" placeholder="e.g. 123456789" class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
                @error('pixel_id') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-4">
            <a href="{{ route('user.pixels.index') }}" class="px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</a>
            <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium">Add Pixel</button>
        </div>
    </form>
</div>
@endsection
