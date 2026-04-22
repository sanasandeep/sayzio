@extends('user.layouts.app')
@section('title', 'Direct Messages')

@section('content')
<div class="max-w-5xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Direct Messages',
        'subtitle' => 'Conversations from biolink viewers',
        'icon'     => 'fa-comments',
        'chips'    => [
            ['icon' => 'fa-envelope text-violet-400', 'text' => number_format($unreadTotal) . ' unread'],
        ],
    ])

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex items-center gap-2 mb-4">
        <a href="{{ route('user.inbox.dms.index', ['tab' => 'active']) }}"
           class="px-4 py-2 rounded-xl text-sm {{ $tab === 'active' ? 'bg-indigo-500/20 text-indigo-200 border border-indigo-400/40' : 'bg-white/5 border border-white/10' }}">
            Active
        </a>
        <a href="{{ route('user.inbox.dms.index', ['tab' => 'blocked']) }}"
           class="px-4 py-2 rounded-xl text-sm {{ $tab === 'blocked' ? 'bg-rose-500/20 text-rose-200 border border-rose-400/40' : 'bg-white/5 border border-white/10' }}">
            Blocked
        </a>
        @if(\Illuminate\Support\Facades\Route::has('user.inbox.ai-companions.index'))
            <a href="{{ route('user.inbox.ai-companions.index') }}"
               class="px-4 py-2 rounded-xl text-sm bg-white/5 border border-white/10 hover:bg-white/10">
                AI Companions
            </a>
        @endif
        <div class="ml-auto">
            <a href="{{ route('user.inbox.index') }}" class="text-xs text-white/60 hover:text-white">
                <i class="fas fa-arrow-left mr-1"></i> Form / subscriber inbox
            </a>
        </div>
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/[0.02] divide-y divide-white/5 overflow-hidden">
        @forelse($conversations as $c)
            <a href="{{ route('user.inbox.dms.thread', $c->id) }}"
               class="flex items-start gap-3 px-4 py-3 hover:bg-white/[0.04] transition">
                <div class="w-10 h-10 rounded-full overflow-hidden flex-shrink-0 bg-white/5 flex items-center justify-center">
                    @if($c->viewer && $c->viewer->profile_picture)
                        <img src="{{ $c->viewer->profile_picture }}" alt="" class="w-full h-full object-cover">
                    @else
                        <i class="fas fa-user text-white/40"></i>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-semibold truncate">{{ $c->viewer->name ?? 'Unknown viewer' }}</p>
                        @if($c->owner_unread_count > 0)
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-500/20 text-indigo-200">{{ $c->owner_unread_count }} new</span>
                        @endif
                        @if(!$c->owner_replied)
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-200">Awaiting your reply</span>
                        @endif
                    </div>
                    <p class="text-xs text-white/50 truncate">
                        @if($c->last_sender === 'owner')<span class="text-white/40">You: </span>@endif
                        {{ $c->last_message_preview ?? '—' }}
                    </p>
                    <p class="text-[11px] text-white/30 mt-0.5">
                        <i class="fas fa-link mr-1"></i>{{ $c->link->alias ?? '—' }}
                        <span class="mx-1">·</span>
                        {{ optional($c->last_message_at)->diffForHumans() ?? '' }}
                    </p>
                </div>
            </a>
        @empty
            <div class="px-6 py-12 text-center text-sm text-white/50">
                <i class="fas fa-comments text-3xl mb-3 block opacity-40"></i>
                No {{ $tab === 'blocked' ? 'blocked' : 'direct message' }} conversations yet.
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $conversations->withQueryString()->links() }}
    </div>
</div>
@endsection
