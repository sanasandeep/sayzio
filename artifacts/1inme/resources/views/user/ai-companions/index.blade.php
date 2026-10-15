@extends('user.layouts.app')
@section('title', 'Chat Widgets')

@section('content')
<div class="w-full space-y-6">
    @if(session('status'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ session('error') }}</div>@endif

    @php
        $unlimited = in_array($caps['max_companions_per_user'], [-1, PHP_INT_MAX], true);
        $widgetActions = ($unlimited || $used < $caps['max_companions_per_user']) ? [['label'=>'New Chat Widget','url'=>route('user.ai-companions.create'),'icon'=>'fa-plus','class'=>'btn-primary']] : [];
    @endphp
    @include('user.partials.page-hero', ['title'=>'Chat Widgets','subtitle'=>'Bring your AI agents to Link in Bio, your website and inbox.','icon'=>'fa-comments','back'=>route('user.dashboard'),'actions'=>$widgetActions,'chips'=>[['text'=>$used.' widgets','icon'=>'fa-comments'],['text'=>$unlimited?'Unlimited plan':$caps['max_companions_per_user'].' widget limit','icon'=>'fa-layer-group']]])
    @if($companions->isEmpty())
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-10 text-center">
            <i class="fas fa-comments text-4xl text-blue-400/70"></i>
            <p class="mt-3 text-white font-semibold">No chat widgets yet.</p>
            <p class="text-sm text-white/50 mt-1">Pick an AI agent, choose a placement, and ship a chatbot in minutes.</p>
            <a href="{{ route('user.ai-companions.create') }}" class="inline-block mt-4 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm">
                <i class="fas fa-plus"></i> Create your first Chat Widget
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
                        <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-300">
                            <i class="fas {{ $icon }}"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('user.ai-companions.edit', $c) }}" class="text-white font-semibold truncate hover:text-blue-300">{{ $c->name }}</a>
                                @if($c->is_disabled)
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-red-500/10 text-red-300 border border-red-500/20">Disabled</span>
                                @endif
                            </div>
                            <p class="text-xs text-white/50 truncate">
                                {{ $placementLabel }} &middot; Agent: <span class="text-white/70">{{ optional($c->persona)->name ?? '—' }}</span>
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
                            <form method="POST" action="{{ route('user.ai-companions.destroy', $c) }}" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Delete this chat widget?', message: 'All of its conversations will be removed too.', confirmText: 'Delete', confirmIcon: 'fa-trash', iconClass: 'fa-trash'})">@csrf @method('DELETE')
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
