@extends('user.layouts.app')
@section('title', 'Inbox snippets')

@section('content')
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Reply snippets',
        'subtitle' => 'Saved shortcuts you can drop into any inbox reply',
        'icon' => 'fa-bolt',
        'actions' => [
            ['label' => 'Back to inbox', 'url' => route('user.inbox.unified.index'), 'icon' => 'fa-arrow-left', 'class' => 'btn-ghost'],
        ],
    ])

    @if(session('success'))<div class="mb-4 px-4 py-3 rounded-xl text-sm" style="background: rgba(16,185,129,0.1); color: #10b981;">{{ session('success') }}</div>@endif

    <form method="POST" action="{{ route('user.inbox.unified.snippets.store') }}" class="card-premium p-4 mb-6 space-y-3">@csrf
        <div class="grid sm:grid-cols-2 gap-3">
            <input name="shortcut" required placeholder="shortcut (e.g. intro)" maxlength="64" class="px-3 py-2 rounded-lg text-sm" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
            <input name="label" required placeholder="Label (e.g. Booking intro)" maxlength="200" class="px-3 py-2 rounded-lg text-sm" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
        </div>
        <textarea name="body" rows="4" required maxlength="5000" placeholder="The text inserted when you pick this snippet…" class="w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);"></textarea>
        <div class="flex justify-end">
            <button class="px-4 py-2 rounded-lg text-xs font-bold text-white" style="background: linear-gradient(135deg,#5c83ff,#2342c7);"><i class="fas fa-plus mr-1"></i>Save snippet</button>
        </div>
    </form>

    @if($snippets->isEmpty())
        <div class="card-premium p-8 text-center text-sm" style="color: var(--text-muted);">No snippets yet.</div>
    @else
        <div class="card-premium overflow-hidden divide-y" style="border-color: var(--border-glass);">
            @foreach($snippets as $sn)
                <div class="flex items-start gap-4 p-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] font-mono px-2 py-0.5 rounded" style="background: rgba(92,131,255,0.15); color: #bccfff;">{{ $sn->shortcut }}</span>
                            <span class="text-sm font-semibold" style="color: var(--text-primary);">{{ $sn->label }}</span>
                        </div>
                        <div class="text-xs mt-2 whitespace-pre-wrap" style="color: var(--text-muted);">{{ $sn->body }}</div>
                    </div>
                    <form method="POST" action="{{ route('user.inbox.unified.snippets.destroy', $sn->id) }}" onsubmit="return confirm('Delete snippet?')">@csrf @method('DELETE')
                        <button class="px-2 py-1 rounded-lg text-xs" style="background: rgba(239,68,68,0.1); color: #f87171;"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $snippets->links() }}</div>
    @endif
</div>
@endsection
