@extends('user.layouts.app')
@section('title', 'Splash · ' . ($link->title ?: $link->alias))

@section('content')
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Splash Page',
        'subtitle' => $link->title ?: $link->alias,
        'icon'     => 'fa-rocket',
        'favicon'  => $link->favicon,
        'back'     => route('user.links.show', $link),
        'chips'    => [
            ['icon' => 'fa-circle ' . ($link->hasSplashEnabled() ? 'text-emerald-400' : 'text-gray-400'),
             'text' => $link->hasSplashEnabled() ? 'Enabled' : 'Disabled'],
        ],
    ])

    <form method="POST" action="{{ route('user.links.splash.update', $link) }}" class="space-y-6"
          x-data="{ enabled: {{ $link->splash_enabled ? 'true' : 'false' }}, picked: {{ (int) $link->splash_page_id }} }">
        @csrf

        <div class="card-premium p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-bold mb-1" style="color: var(--text-primary);">Show a splash before this link</h3>
                    <p class="text-xs" style="color: var(--text-muted);">Display a transition page (announcements, branding, ads, disclaimers) before sending visitors to the destination.</p>
                </div>
                <label class="inline-flex items-center cursor-pointer flex-shrink-0">
                    <input type="hidden" name="splash_enabled" value="0">
                    <input type="checkbox" name="splash_enabled" value="1" x-model="enabled" class="sr-only peer">
                    <span class="relative w-11 h-6 rounded-full transition" :style="enabled ? 'background: var(--accent);' : 'background: var(--bg-glass-hover);'">
                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform" :class="enabled ? 'translate-x-5' : ''"></span>
                    </span>
                </label>
            </div>
        </div>

        <div class="card-premium p-6" x-show="enabled" x-cloak>
            <h3 class="text-base font-bold mb-1" style="color: var(--text-primary);">Choose splash page</h3>
            <p class="text-xs mb-4" style="color: var(--text-muted);">Pick from your reusable splash pages, or create a new one.</p>

            @if($splashPages->isEmpty())
                <div class="rounded-lg p-5 text-center" style="background: var(--bg-glass-hover); border: 1px dashed var(--border-glass);">
                    <p class="text-sm mb-3" style="color: var(--text-muted);">You don't have any splash pages yet.</p>
                    <a href="{{ route('user.splash-pages.create') }}"
                       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold" style="background: var(--accent); color: #fff;">
                        <i class="fas fa-plus"></i> Create your first
                    </a>
                </div>
            @else
                <div class="space-y-2">
                    @foreach($splashPages as $sp)
                        <label class="flex items-center gap-3 p-3 rounded-lg cursor-pointer transition"
                               :style="picked === {{ $sp->id }} ? 'background: var(--c-primary-soft); border: 1px solid var(--accent);' : 'background: var(--bg-glass-hover); border: 1px solid transparent;'">
                            <input type="radio" name="splash_page_id" value="{{ $sp->id }}" x-model.number="picked" class="w-4 h-4">
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-sm truncate" style="color: var(--text-primary);">{{ $sp->name }}</div>
                                @if($sp->title && $sp->title !== $sp->name)
                                    <div class="text-xs truncate" style="color: var(--text-muted);">{{ $sp->title }}</div>
                                @endif
                            </div>
                            <a href="{{ route('user.splash-pages.edit', $sp) }}" class="text-xs px-2 py-1 rounded" style="background: var(--bg-glass); color: var(--text-secondary);" @click.stop>
                                <i class="fas fa-pen"></i>
                            </a>
                            <a href="{{ route('user.splash-pages.preview', $sp) }}" target="_blank" class="text-xs px-2 py-1 rounded" style="background: var(--bg-glass); color: var(--text-secondary);" @click.stop>
                                <i class="fas fa-eye"></i>
                            </a>
                        </label>
                    @endforeach
                </div>

                <div class="mt-4 pt-4 border-t" style="border-color: var(--border-subtle);">
                    <a href="{{ route('user.splash-pages.create') }}" class="text-xs font-semibold inline-flex items-center gap-1.5" style="color: var(--accent);">
                        <i class="fas fa-plus"></i> Create new splash page
                    </a>
                </div>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold" style="background: var(--accent); color: #fff;">
                <i class="fas fa-save"></i> Save
            </button>
            <a href="{{ route('user.links.show', $link) }}" class="text-sm" style="color: var(--text-muted);">Cancel</a>
        </div>
    </form>
</div>
@endsection
