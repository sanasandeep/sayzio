@extends('user.layouts.app')
@section('title', 'AI Minds')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8 space-y-6">
    @if(session('status'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>@endif

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-white">AI Minds</h1>
            <p class="text-sm text-white/50 mt-1">Labelled knowledge bases your AI personas and coach can draw on. Add text, FAQs, documents, links, or live Sayzio data.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-white/40">{{ $usedMinds }} / {{ $caps['max_minds_per_user'] == -1 ? '∞' : $caps['max_minds_per_user'] }} minds</span>
            @if($caps['max_minds_per_user'] == -1 || $usedMinds < $caps['max_minds_per_user'])
                <a href="{{ route('user.minds.create') }}" class="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-medium">
                    <i class="fas fa-plus mr-1"></i> New mind
                </a>
            @else
                <button disabled class="px-4 py-2 rounded-xl bg-white/5 text-white/30 text-sm cursor-not-allowed">Limit reached</button>
            @endif
        </div>
    </div>

    @if($platform->isNotEmpty())
        <div>
            <h2 class="text-xs uppercase tracking-wider text-white/40 mb-2">Platform mind</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($platform as $m)
                    <a href="{{ route('user.minds.edit', $m) }}" class="rounded-2xl border border-white/10 bg-gradient-to-br from-cyan-900/20 to-violet-900/10 p-5 hover:border-white/20 transition">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-white font-semibold">{{ $m->name }} <span class="ml-1 text-[10px] uppercase tracking-wider text-cyan-300/80">Default</span></p>
                                <p class="text-xs text-white/50 mt-1">{{ $m->description ?: 'Sayzio product knowledge available to every persona.' }}</p>
                            </div>
                            <i class="fas fa-network-wired text-cyan-400/60"></i>
                        </div>
                        <div class="mt-3 flex items-center gap-3 text-xs text-white/40">
                            <span><i class="fas fa-layer-group mr-1"></i>{{ $m->sources_count }} sources</span>
                            <span><i class="fas fa-database mr-1"></i>{{ $m->chunks_count }} chunks</span>
                            @if($m->is_disabled)<span class="text-red-300">Disabled</span>@endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div>
        <h2 class="text-xs uppercase tracking-wider text-white/40 mb-2">Your minds</h2>
        @if($mine->isEmpty())
            <div class="rounded-2xl border border-dashed border-white/10 p-8 text-center text-white/40 text-sm">
                You don't have any minds yet. <a class="text-cyan-300 hover:underline" href="{{ route('user.minds.create') }}">Create your first one</a>.
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($mine as $m)
                    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 flex flex-col gap-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-white font-semibold">{{ $m->name }}</p>
                                <p class="text-xs text-white/50 mt-1">{{ $m->description ?: 'No description.' }}</p>
                            </div>
                            <i class="fas fa-brain text-violet-300/60"></i>
                        </div>
                        <div class="flex items-center gap-3 text-xs text-white/40">
                            <span><i class="fas fa-layer-group mr-1"></i>{{ $m->sources_count }} sources</span>
                            <span><i class="fas fa-database mr-1"></i>{{ $m->chunks_count }} chunks</span>
                            @if($m->is_disabled)<span class="text-red-300">Disabled — {{ $m->disabled_reason }}</span>@endif
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <a href="{{ route('user.minds.edit', $m) }}" class="px-3 py-1.5 text-xs rounded-lg bg-white/10 hover:bg-white/15 text-white">Open</a>
                            <form method="POST" action="{{ route('user.minds.refresh', $m) }}">@csrf
                                <button class="px-3 py-1.5 text-xs rounded-lg bg-white/5 hover:bg-white/10 text-white/70" title="Re-ingest every source"><i class="fas fa-rotate"></i> Refresh all</button>
                            </form>
                            <form method="POST" action="{{ route('user.minds.destroy', $m) }}" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this mind?', message: 'All of its sources will be removed too.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">
                                @csrf @method('DELETE')
                                <button class="px-3 py-1.5 text-xs rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-300"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
