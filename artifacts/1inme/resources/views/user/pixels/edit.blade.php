@extends('user.layouts.app')
@section('title', 'Edit Pixel')

@section('content')
<div class="max-w-xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.pixels.index') }}" class="text-white/30 hover:text-white/50"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-2xl font-bold text-white">Edit Pixel</h1>
    </div>

    <form method="POST" action="{{ route('user.pixels.update', $pixel) }}">
        @csrf @method('PUT')
        <div class="glass rounded-2xl p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Name</label>
                <input type="text" name="name" value="{{ old('name', $pixel->name) }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40" required>
                @error('name') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Type</label>
                <select name="type" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40" required>
                    @foreach(['facebook', 'google_analytics', 'google_tag_manager', 'linkedin', 'twitter', 'pinterest', 'tiktok', 'snapchat', 'quora', 'custom'] as $t)
                        <option value="{{ $t }}" {{ $pixel->type === $t ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-white/60 mb-1">Pixel ID</label>
                <input type="text" name="pixel_id" value="{{ old('pixel_id', $pixel->pixel_id) }}" class="w-full border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500/40" required>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 mt-4">
            <a href="{{ route('user.pixels.index') }}" class="px-4 py-2.5 text-sm text-white/60 hover:bg-white/10 rounded-xl">Cancel</a>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium">Save Changes</button>
        </div>
    </form>
</div>
@endsection
