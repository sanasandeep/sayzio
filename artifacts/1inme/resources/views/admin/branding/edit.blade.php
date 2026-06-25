@extends('admin.layouts.app')
@section('title', 'Branding')
@section('page-title', 'Branding')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    @if(session('success'))
        <div class="rounded-xl px-4 py-3 text-sm" style="background: rgba(34,197,94,0.08); border: 1px solid rgba(34,197,94,0.20); color: #86efac;">
            {{ session('success') }}
        </div>
    @endif

    <div class="glass rounded-2xl p-6">
        <h2 class="text-lg font-semibold text-white mb-1">Brand logos &amp; icon</h2>
        <p class="text-sm text-white/50 mb-6">
            Upload three image variants. They appear automatically across the auth pages, sidebars, and shared screens.
            Square icon under 1&nbsp;MB, wordmarks up to 4&nbsp;MB. PNG, JPG, WebP{{ ' and SVG' }} accepted.
        </p>

        <form method="POST" action="{{ route('admin.branding.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            {{-- Light mode logo --}}
            <div class="grid grid-cols-1 md:grid-cols-[260px,1fr] gap-4 items-center">
                <div class="rounded-xl p-4 flex items-center justify-center" style="background:#ffffff; border:1px solid var(--border-glass); min-height:120px;">
                    <img src="{{ $logos['logo_light'] }}" alt="Light mode logo preview" class="max-h-20 w-auto">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white mb-1">Wordmark — light mode</label>
                    <p class="text-xs text-white/50 mb-2">Shown on light backgrounds. Use the dark/colored version of your logo.</p>
                    <input type="file" name="logo_light" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                           class="block w-full text-xs text-white/70 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-600 file:text-white hover:file:bg-blue-700 file:cursor-pointer">
                    @error('logo_light')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Dark mode logo --}}
            <div class="grid grid-cols-1 md:grid-cols-[260px,1fr] gap-4 items-center">
                <div class="rounded-xl p-4 flex items-center justify-center" style="background:#0a0a0f; border:1px solid var(--border-glass); min-height:120px;">
                    <img src="{{ $logos['logo_dark'] }}" alt="Dark mode logo preview" class="max-h-20 w-auto">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white mb-1">Wordmark — dark mode</label>
                    <p class="text-xs text-white/50 mb-2">Shown on dark backgrounds. Use the white/light version of your logo.</p>
                    <input type="file" name="logo_dark" accept="image/png,image/jpeg,image/webp,image/svg+xml"
                           class="block w-full text-xs text-white/70 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-600 file:text-white hover:file:bg-blue-700 file:cursor-pointer">
                    @error('logo_dark')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Square icon --}}
            <div class="grid grid-cols-1 md:grid-cols-[260px,1fr] gap-4 items-center">
                <div class="rounded-xl p-4 flex items-center justify-center" style="background:rgba(255,255,255,0.04); border:1px solid var(--border-glass); min-height:120px;">
                    <img src="{{ $logos['icon'] }}" alt="App icon preview" class="h-20 w-20 rounded-xl object-cover">
                </div>
                <div>
                    <label class="block text-sm font-medium text-white mb-1">Square icon / favicon</label>
                    <p class="text-xs text-white/50 mb-2">Used as the favicon, app icon, and small badges.</p>
                    <input type="file" name="icon" accept="image/png,image/jpeg,image/webp,image/x-icon"
                           class="block w-full text-xs text-white/70 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-600 file:text-white hover:file:bg-blue-700 file:cursor-pointer">
                    @error('icon')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-white/10">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition-all hover:shadow-lg hover:shadow-blue-500/20">
                    Save branding
                </button>
            </div>
        </form>
    </div>

    <div class="glass rounded-2xl p-6">
        <h3 class="text-sm font-semibold text-white mb-1">Reset to defaults</h3>
        <p class="text-xs text-white/50 mb-3">Restores the bundled Sayzio logos.</p>
        <form method="POST" action="{{ route('admin.branding.reset') }}">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-xl text-xs font-medium" style="background: rgba(255,255,255,0.05); border: 1px solid var(--border-glass); color: var(--text-muted);">
                <i class="fas fa-rotate-left mr-1"></i> Reset to defaults
            </button>
        </form>
    </div>
</div>
@endsection
