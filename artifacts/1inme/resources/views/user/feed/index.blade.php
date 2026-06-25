@php
    $useDashboardLayout = (bool) auth()->check();
    $unreadNotifs = \App\Modules\User\Models\UserNotification::where('user_id', $me->id)->whereNull('read_at')->count();
@endphp
@if($useDashboardLayout)
    @extends('user.layouts.app')
    @section('title', 'My Feed')
    @section('content')
@else
    <!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}"><title>My Feed - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script defer src="{{ asset('js/vendor/alpine-collapse.min.js') }}"></script>
    <script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
    <style>:root{--text-primary:#0f172a;--text-muted:#475569;--text-faint:#94a3b8;--bg-card:#fff;--border-soft:#e2e8f0;}</style>
    </head><body class="bg-slate-50">
@endif
<div class="max-w-3xl mx-auto px-4 py-8" x-data="feedScroll('{{ $events->nextPageUrl() }}')" x-init="init()">
    <div class="flex items-center justify-between mb-6 gap-2 flex-wrap">
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">My Feed</h1>
            <span class="text-xs" style="color: var(--text-faint);">Hi {{ $me->name }}</span>
        </div>
        <div class="flex items-center gap-2">
            <form action="{{ route('feed.notifications.read') }}" method="POST">@csrf
                <button class="px-3 py-1.5 rounded-lg text-xs font-semibold btn-ghost">
                    Mark all read @if($unreadNotifs)<span class="ml-1 inline-block px-1.5 rounded-full bg-rose-500 text-white">{{ $unreadNotifs }}</span>@endif
                </button>
            </form>
            <a href="{{ route('creators.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-violet-600 text-white">
                <i class="fas fa-compass mr-1"></i> Discover creators
            </a>
        </div>
    </div>

    @if(!empty($pinnedPosts) && $pinnedPosts->count() > 0)
        <div class="mb-4 space-y-3">
            <h2 class="text-xs font-bold uppercase tracking-wider flex items-center gap-2" style="color: var(--text-muted);">
                <i class="fas fa-thumbtack text-amber-500"></i> Pinned by creators you follow
            </h2>
            @foreach($pinnedPosts as $pp)
                <div class="rounded-2xl border-2 border-amber-300 p-4" style="background: var(--bg-card);">
                    <div class="flex items-start gap-3">
                        @if($pp->user && $pp->user->avatar)
                            <img src="{{ $pp->user->avatar }}" class="w-10 h-10 rounded-full object-cover" alt=""/>
                        @else
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-amber-500 to-orange-500 text-white flex items-center justify-center font-bold">
                                {{ strtoupper(substr(optional($pp->user)->name ?? '?', 0, 1)) }}
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 text-sm">
                                <span class="font-semibold" style="color: var(--text-primary);">{{ optional($pp->user)->name ?? 'Creator' }}</span>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-amber-100 text-amber-800"><i class="fas fa-thumbtack text-[9px]"></i> Pinned</span>
                                <span class="text-xs" style="color: var(--text-faint);">{{ $pp->published_at?->diffForHumans() }}</span>
                            </div>
                            @if($pp->title)<h3 class="font-bold mt-1" style="color: var(--text-primary);">{{ $pp->title }}</h3>@endif
                            <p class="text-sm mt-1 whitespace-pre-line" style="color: var(--text-muted);">{{ \Illuminate\Support\Str::limit($pp->body, 280) }}</p>
                            @if($pp->image)<img src="{{ $pp->image }}" class="mt-3 rounded-lg max-h-72"/>@endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if($events->count() === 0)
        <div class="text-center py-16 rounded-2xl border" style="background: var(--bg-card); border-color: var(--border-soft);">
            <i class="fas fa-stream text-4xl mb-3" style="color: var(--text-faint);"></i>
            <p class="font-semibold mb-2" style="color: var(--text-primary);">Your feed is empty.</p>
            <p class="text-sm mb-4" style="color: var(--text-muted);">Follow creators to see their posts and updates here.</p>
            <a href="{{ route('creators.index') }}" class="inline-block px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-semibold">Find creators</a>
        </div>
    @else
        <div class="space-y-3" id="feedList">
            @foreach($events as $event)
                @php $d = $event->data ?? []; @endphp
                <div class="rounded-2xl border p-4 feed-item" style="background: var(--bg-card); border-color: var(--border-soft);">
                    <div class="flex items-start gap-3">
                        @if(!empty($d['creator_avatar']))
                            <img src="{{ $d['creator_avatar'] }}" class="w-10 h-10 rounded-full object-cover" alt=""/>
                        @else
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white flex items-center justify-center font-bold">
                                {{ strtoupper(substr($d['creator_name'] ?? '?', 0, 1)) }}
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 text-sm">
                                <span class="font-semibold" style="color: var(--text-primary);">{{ $d['creator_name'] ?? $event->user->name ?? 'Creator' }}</span>
                                <span class="text-xs" style="color: var(--text-faint);">{{ $event->occurred_at->diffForHumans() }}</span>
                            </div>
                            @if($event->type === 'post')
                                @if(!empty($d['title']))<h3 class="font-bold mt-1" style="color: var(--text-primary);">{{ $d['title'] }}</h3>@endif
                                <p class="text-sm mt-1" style="color: var(--text-muted);">{{ $d['body_excerpt'] ?? '' }}</p>
                            @elseif($event->type === 'profile_update')
                                <p class="text-sm mt-1" style="color: var(--text-muted);">Updated their profile.</p>
                            @elseif($event->type === 'link_published')
                                <p class="text-sm mt-1" style="color: var(--text-muted);">
                                    Published a new link: <span class="font-semibold">{{ $d['title'] ?? $d['alias'] ?? '' }}</span>
                                </p>
                                @if(!empty($d['alias']))<a href="{{ url('/' . $d['alias']) }}" target="_blank" class="text-xs text-violet-600 font-semibold">Open link →</a>@endif
                            @elseif($event->type === 'block_added')
                                <p class="text-sm mt-1" style="color: var(--text-muted);">Added a new block to their Link in Bio: <span class="font-semibold">{{ $d['block_label'] ?? $d['block_type'] ?? '' }}</span></p>
                            @else
                                <p class="text-sm mt-1" style="color: var(--text-muted);">{{ $d['message'] ?? 'New activity' }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div x-show="loading" class="text-center py-6 text-sm" style="color: var(--text-faint);">
            <i class="fas fa-spinner fa-spin mr-2"></i>Loading more...
        </div>
        <div x-show="!nextUrl && !loading" class="text-center py-6 text-xs" style="color: var(--text-faint);">— End of feed —</div>
        <div id="feedSentinel" class="h-2"></div>
    @endif
</div>

<script>
function feedScroll(initialNext) {
    return {
        nextUrl: initialNext || null,
        loading: false,
        init() {
            if (!this.nextUrl) return;
            const sentinel = document.getElementById('feedSentinel');
            if (!sentinel) return;
            const obs = new IntersectionObserver(entries => {
                if (entries[0].isIntersecting && this.nextUrl && !this.loading) this.loadMore();
            }, { rootMargin: '300px' });
            obs.observe(sentinel);
        },
        async loadMore() {
            this.loading = true;
            try {
                const r = await fetch(this.nextUrl, {headers: {'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
                const d = await r.json();
                const list = document.getElementById('feedList');
                (d.items || []).forEach(ev => {
                    const data = ev.data || {};
                    const div = document.createElement('div');
                    div.className = 'rounded-2xl border p-4 feed-item';
                    div.style.cssText = 'background: var(--bg-card); border-color: var(--border-soft);';
                    const avatar = data.creator_avatar
                        ? `<img src="${data.creator_avatar}" class="w-10 h-10 rounded-full object-cover" alt=""/>`
                        : `<div class="w-10 h-10 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white flex items-center justify-center font-bold">${(data.creator_name||'?').charAt(0).toUpperCase()}</div>`;
                    let body = '';
                    if (ev.type === 'post') body = `${data.title ? `<h3 class="font-bold mt-1">${escapeHtml(data.title)}</h3>` : ''}<p class="text-sm mt-1" style="color: var(--text-muted);">${escapeHtml(data.body_excerpt||'')}</p>`;
                    else if (ev.type === 'link_published') body = `<p class="text-sm mt-1" style="color: var(--text-muted);">Published a new link: <span class="font-semibold">${escapeHtml(data.title||data.alias||'')}</span></p>`;
                    else if (ev.type === 'profile_update') body = `<p class="text-sm mt-1" style="color: var(--text-muted);">Updated their profile.</p>`;
                    else body = `<p class="text-sm mt-1" style="color: var(--text-muted);">${escapeHtml(data.message||'New activity')}</p>`;
                    div.innerHTML = `<div class="flex items-start gap-3">${avatar}<div class="flex-1 min-w-0"><div class="flex items-center gap-2 text-sm"><span class="font-semibold" style="color: var(--text-primary);">${escapeHtml(data.creator_name||'Creator')}</span><span class="text-xs" style="color: var(--text-faint);">${timeAgo(ev.occurred_at)}</span></div>${body}</div></div>`;
                    list.appendChild(div);
                });
                this.nextUrl = d.next_page_url;
            } catch(e) {}
            this.loading = false;
        }
    }
}
function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); }
function timeAgo(iso){ const d=new Date(iso); const s=Math.floor((Date.now()-d.getTime())/1000); if(s<60)return s+'s ago'; if(s<3600)return Math.floor(s/60)+'m ago'; if(s<86400)return Math.floor(s/3600)+'h ago'; return Math.floor(s/86400)+'d ago'; }
</script>
@if($useDashboardLayout)
    @endsection
@else
    </body></html>
@endif
