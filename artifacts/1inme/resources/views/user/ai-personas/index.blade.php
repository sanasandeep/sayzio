@extends('user.layouts.app')
@section('title', 'AI Agents')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
    @if(session('status'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="flex items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white">AI Agents</h1>
            <p class="text-sm text-white/50 mt-1">Configurable agents that combine a system prompt, tone, and the AI Minds you choose. Test them here, then wire them into widgets.</p>
            <p class="text-[11px] text-white/40 mt-1">{{ $used }} of {{ $caps['max_personas_per_user'] == -1 ? '∞' : $caps['max_personas_per_user'] }} used &middot; up to {{ $caps['max_minds_per_persona'] }} AI Minds per AI agent</p>
        </div>
        @if($caps['max_personas_per_user'] == -1 || $used < $caps['max_personas_per_user'])
            <a href="{{ route('user.ai-personas.create') }}" class="btn-primary text-sm">
                <i class="fas fa-plus"></i> New AI Agent
            </a>
        @endif
    </div>

    @if($personas->isEmpty())
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-10 text-center">
            <i class="fas fa-user-astronaut text-4xl text-blue-400/70"></i>
            <p class="mt-3 text-white font-semibold">No AI agents yet.</p>
            <p class="text-sm text-white/50 mt-1">Create one from a starter template, then attach the AI Minds it should know.</p>
            <a href="{{ route('user.ai-personas.create') }}" class="btn-primary inline-flex mt-4 text-sm">
                <i class="fas fa-plus"></i> Create your first AI Agent
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($personas as $p)
                <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 space-y-3 {{ $p->is_disabled ? 'opacity-60' : '' }}">
                    <div class="flex items-start gap-3">
                        @if($p->avatar_url)
                            <img src="{{ $p->avatar_url }}" alt="" class="w-12 h-12 rounded-xl object-cover border border-white/10">
                        @else
                            <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-300">
                                <i class="fas fa-user-astronaut"></i>
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('user.ai-personas.edit', $p) }}" class="text-white font-semibold truncate hover:text-blue-300">{{ $p->name }}</a>
                                @if($p->is_disabled)
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-red-500/10 text-red-300 border border-red-500/20">Disabled</span>
                                @endif
                            </div>
                            <p class="text-xs text-white/50 truncate">{{ $p->description ?: 'No description' }}</p>
                            <p class="text-[10px] text-white/40 mt-1">
                                {{ $p->model }} &middot; T={{ number_format($p->temperature(), 2) }} &middot;
                                {{ $p->minds_count }} AI Mind{{ $p->minds_count === 1 ? '' : 's' }}
                                @if($p->use_default_mind) + default @endif
                            </p>
                            @if($p->is_disabled && $p->disabled_reason)
                                <p class="text-[10px] text-red-300 mt-1">Reason: {{ $p->disabled_reason }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-[11px] text-white/40">
                        <span>v{{ optional($p->activeVersion)->revision ?? '—' }} &middot; {{ $p->updated_at?->diffForHumans() }}</span>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('user.ai-personas.edit', $p) }}" class="px-2 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-white"><i class="fas fa-edit"></i> Edit</a>
                            <form method="POST" action="{{ route('user.ai-personas.duplicate', $p) }}" class="inline">@csrf
                                <button class="px-2 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-white"><i class="fas fa-copy"></i> Duplicate</button>
                            </form>
                            <form method="POST" action="{{ route('user.ai-personas.destroy', $p) }}" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this AI agent?', message: 'All of its versions will be removed too.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">@csrf @method('DELETE')
                                <button class="px-2 py-1 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-300"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if(isset($shared) && $shared->isNotEmpty())
    <div class="space-y-2">
        <h2 class="text-xs uppercase tracking-wider text-white/40">Shared with you</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @foreach($shared as $p)
                <a href="{{ route('user.ai-personas.edit', $p) }}" class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 flex items-start gap-3 hover:border-white/20 transition {{ $p->is_disabled ? 'opacity-60' : '' }}">
                    @if($p->avatar_url)
                        <img src="{{ $p->avatar_url }}" alt="" class="w-12 h-12 rounded-xl object-cover border border-white/10">
                    @else
                        <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-300">
                            <i class="fas fa-user-astronaut"></i>
                        </div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-white font-semibold truncate">{{ $p->name }}</span>
                            <span class="text-[10px] uppercase tracking-wider {{ $p->share_access === \App\Modules\User\Models\AiResourceShare::ACCESS_EDIT ? 'text-emerald-300/80' : 'text-sky-300/80' }}">
                                {{ $p->share_access === \App\Modules\User\Models\AiResourceShare::ACCESS_EDIT ? 'Can edit' : 'View only' }}
                            </span>
                        </div>
                        <p class="text-xs text-white/50 truncate">{{ $p->description ?: 'No description' }}</p>
                        <p class="text-[10px] text-white/40 mt-1">
                            {{ $p->model }} &middot; {{ $p->minds_count }} AI Mind{{ $p->minds_count === 1 ? '' : 's' }}
                            @if($p->use_default_mind) + default @endif
                        </p>
                    </div>
                    <i class="fas fa-user-group text-sky-300/60"></i>
                </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
