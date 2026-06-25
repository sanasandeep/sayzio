@extends('user.layouts.app')
@section('title', 'AI Companions')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
    @if(session('status'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="flex items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white">AI Companions</h1>
            <p class="text-sm text-white/50 mt-1">Drop one of your Personas into a Link in Bio chatbot, an external website embed, or your inbox as an auto-reply bot.</p>
            <p class="text-[11px] text-white/40 mt-1">{{ $used }} of {{ $caps['max_companions_per_user'] == -1 ? '∞' : $caps['max_companions_per_user'] }} used</p>
        </div>
        @if($caps['max_companions_per_user'] == -1 || $used < $caps['max_companions_per_user'])
            <a href="{{ route('user.ai-companions.create') }}" class="px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-500 text-white text-sm">
                <i class="fas fa-plus"></i> New Companion
            </a>
        @endif
    </div>

    @if($companions->isEmpty())
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-10 text-center">
            <i class="fas fa-comments text-4xl text-violet-400/70"></i>
            <p class="mt-3 text-white font-semibold">No Companions yet.</p>
            <p class="text-sm text-white/50 mt-1">Pick an AI Persona, choose a placement, and ship a chatbot in minutes.</p>
            <a href="{{ route('user.ai-companions.create') }}" class="inline-block mt-4 px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-500 text-white text-sm">
                <i class="fas fa-plus"></i> Create your first Companion
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($companions as $c)
                @php
                    $placementLabel = $placements[$c->placement] ?? $c->placement;
                    $icon = match($c->placement) {
                        'biolink' => 'fa-link',
                        'embed'   => 'fa-code',
                        'inbox'   => 'fa-inbox',
                        default   => 'fa-comments',
                    };
                @endphp
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 space-y-3 {{ $c->is_disabled ? 'opacity-60' : '' }}">
                    <div class="flex items-start gap-3">
                        <div class="w-12 h-12 rounded-xl bg-violet-500/10 border border-violet-500/20 flex items-center justify-center text-violet-300">
                            <i class="fas {{ $icon }}"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('user.ai-companions.edit', $c) }}" class="text-white font-semibold truncate hover:text-violet-300">{{ $c->name }}</a>
                                @if($c->is_disabled)
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-red-500/10 text-red-300 border border-red-500/20">Disabled</span>
                                @endif
                            </div>
                            <p class="text-xs text-white/50 truncate">
                                {{ $placementLabel }} &middot; Persona: <span class="text-white/70">{{ optional($c->persona)->name ?? '—' }}</span>
                            </p>
                            <p class="text-[10px] text-white/40 mt-1">
                                {{ $c->conversations_count }} conversation{{ $c->conversations_count === 1 ? '' : 's' }} &middot;
                                last used {{ $c->last_used_at?->diffForHumans() ?? 'never' }}
                            </p>
                            @if($c->is_disabled && $c->disabled_reason)
                                <p class="text-[10px] text-red-300 mt-1">Reason: {{ $c->disabled_reason }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-white/40">
                        <span class="font-mono truncate">{{ $c->public_id }}</span>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('user.ai-companions.conversations', $c) }}" class="px-2 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-white"><i class="fas fa-comment"></i> Logs</a>
                            <a href="{{ route('user.ai-companions.edit', $c) }}" class="px-2 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-white"><i class="fas fa-edit"></i> Edit</a>
                            <form method="POST" action="{{ route('user.ai-companions.destroy', $c) }}" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this Companion?', message: 'All of its conversations will be removed too.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">@csrf @method('DELETE')
                                <button class="px-2 py-1 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-300"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
