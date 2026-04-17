@extends('user.layouts.app')
@section('title', $splashPage->name)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    @include('user.partials.page-hero', [
        'title'    => $splashPage->name,
        'subtitle' => $splashPage->title ?: 'Intro',
        'icon'     => 'fa-rocket',
        'back'     => route('user.splash-pages.index'),
        'actions'  => [
            ['url' => route('user.splash-pages.edit', $splashPage), 'label' => 'Edit', 'icon' => 'fa-pen', 'class' => 'btn-primary'],
            ['url' => route('user.splash-pages.preview', $splashPage), 'label' => 'Preview', 'icon' => 'fa-eye', 'class' => 'btn-ghost', 'target' => '_blank'],
        ],
    ])

    <div class="card-premium p-6">
        <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Used by {{ $splashPage->links->count() }} link(s)</h3>
        @if($splashPage->links->isEmpty())
            <p class="text-xs" style="color: var(--text-muted);">No links use this intro yet. Open any link's settings → Intro to attach it.</p>
        @else
            <div class="divide-y" style="border-color: var(--border-subtle);">
                @foreach($splashPage->links as $link)
                    <div class="flex items-center justify-between py-3" style="border-color: var(--border-subtle);">
                        <div>
                            <a href="{{ route('user.links.show', $link) }}" class="font-semibold text-sm hover:underline" style="color: var(--text-primary);">{{ $link->title ?: $link->alias }}</a>
                            <div class="text-xs" style="color: var(--text-muted);">/{{ $link->alias }}</div>
                        </div>
                        <span class="text-xs px-2 py-0.5 rounded" style="background: {{ $link->splash_enabled ? 'var(--c-success-soft)' : 'var(--bg-glass-hover)' }}; color: {{ $link->splash_enabled ? 'var(--c-success)' : 'var(--text-muted)' }};">
                            {{ $link->splash_enabled ? 'Active' : 'Disabled' }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
