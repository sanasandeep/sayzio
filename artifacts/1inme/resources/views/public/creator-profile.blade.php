<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $creator->name }} (&#64;{{ $creator->handle }}) - {{ config('app.name') }}</title>
<meta name="description" content="{{ Str::limit($creator->tagline ?: $creator->bio ?: ($creator->name . ' on 1INME'), 180) }}">
<meta property="og:title" content="{{ $creator->name }} (&#64;{{ $creator->handle }})">
<meta property="og:description" content="{{ Str::limit($creator->tagline ?: $creator->bio ?: ('Follow ' . $creator->name . ' on 1INME'), 180) }}">
<meta property="og:type" content="profile">
@if($creator->cover_image)
    <meta property="og:image" content="{{ $creator->cover_image }}">
@elseif($creator->avatar)
    <meta property="og:image" content="{{ $creator->avatar }}">
@endif
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
    [x-cloak]{display:none!important}
    .cp-card{background:#fff;border:1px solid rgba(15,23,42,0.06);border-radius:1rem;}
</style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">
@include('common.partials.viewer-login-modal')

<div class="max-w-3xl mx-auto px-3 sm:px-4 pb-24">

    {{-- ── Hero ─────────────────────────────────────────────── --}}
    <header class="cp-card overflow-hidden mt-4">
        <div class="h-40 sm:h-56 bg-gradient-to-br from-violet-500 via-fuchsia-500 to-indigo-500 relative">
            @if($creator->cover_image)
                <img src="{{ $creator->cover_image }}" alt="" class="absolute inset-0 w-full h-full object-cover">
            @endif
        </div>
        <div class="px-5 sm:px-7 pb-6 -mt-12">
            <div class="flex items-end justify-between gap-3 flex-wrap">
                <div class="flex items-end gap-4">
                    @if($creator->avatar)
                        <img src="{{ $creator->avatar }}" alt="" class="w-24 h-24 rounded-2xl object-cover border-4 border-white shadow-md bg-white">
                    @else
                        <div class="w-24 h-24 rounded-2xl border-4 border-white shadow-md bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white flex items-center justify-center font-extrabold text-2xl">
                            {{ $creator->getInitials() }}
                        </div>
                    @endif
                </div>
                <div class="flex items-center gap-2 mb-1">
                    @if($isOwner)
                        <a href="{{ route('user.creator-profile.edit') }}" class="px-3.5 py-2 rounded-lg bg-slate-900 text-white text-xs font-semibold hover:bg-slate-700">
                            <i class="fas fa-pen mr-1"></i> Edit profile
                        </a>
                    @else
                        @include('public.partials.follow-button', ['creator' => $creator, 'viewer' => $viewer, 'isFollowing' => $isFollowing])
                        @if($creator->isSectionVisible('contact'))
                            <a href="mailto:{{ $creator->email }}" class="px-3.5 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 text-xs font-semibold hover:border-violet-400 hover:text-violet-600">
                                <i class="fas fa-envelope mr-1"></i> Contact
                            </a>
                        @endif
                    @endif
                </div>
            </div>

            <div class="mt-4">
                <h1 class="text-2xl sm:text-3xl font-extrabold flex items-center gap-2 flex-wrap">
                    {{ $creator->name }}
                    @if(method_exists($creator, 'isVerified') && $creator->isVerified())
                        <span class="text-violet-600" title="Verified"><i class="fas fa-circle-check"></i></span>
                    @endif
                </h1>
                <p class="text-slate-500 text-sm mt-0.5">@<span class="font-medium">{{ $creator->handle }}</span>
                    @if($creator->location)
                        <span class="ml-2 text-slate-400"><i class="fas fa-location-dot mr-1"></i>{{ $creator->location }}</span>
                    @endif
                </p>
                @if($creator->tagline)
                    <p class="mt-2 text-slate-700 text-base">{{ $creator->tagline }}</p>
                @endif
                @if(is_array($creator->niche_tags) && count($creator->niche_tags))
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach($creator->niche_tags as $tag)
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-violet-50 text-violet-700 font-medium">#{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </header>

    {{-- ── Stats strip ─────────────────────────────────────── --}}
    @if(($sectionsVisible['stats'] ?? true))
        <div class="cp-card mt-3 px-5 py-4 grid grid-cols-3 text-center divide-x divide-slate-100">
            <div>
                <div class="text-xl font-extrabold">{{ number_format($creator->posts_count ?? 0) }}</div>
                <div class="text-[11px] uppercase tracking-wider text-slate-500 mt-0.5">Posts</div>
            </div>
            <div>
                <div class="text-xl font-extrabold">{{ number_format($creator->followers_count ?? 0) }}</div>
                <div class="text-[11px] uppercase tracking-wider text-slate-500 mt-0.5">Followers</div>
            </div>
            <div>
                <div class="text-xl font-extrabold">{{ $creator->created_at?->format('M Y') ?: '—' }}</div>
                <div class="text-[11px] uppercase tracking-wider text-slate-500 mt-0.5">Joined</div>
            </div>
        </div>
    @endif

    {{-- ── About ───────────────────────────────────────────── --}}
    @if(($sectionsVisible['about'] ?? true) && !empty($creator->bio))
        <section class="cp-card mt-3 p-5">
            <h2 class="text-xs uppercase tracking-wider text-slate-500 font-semibold mb-2">About</h2>
            <p class="text-sm text-slate-700 whitespace-pre-line leading-relaxed">{{ $creator->bio }}</p>
        </section>
    @endif

    {{-- ── Socials ─────────────────────────────────────────── --}}
    @if(($sectionsVisible['socials'] ?? true) && is_array($creator->socials) && count($creator->socials))
        <section class="cp-card mt-3 p-5">
            <h2 class="text-xs uppercase tracking-wider text-slate-500 font-semibold mb-3">Find me on</h2>
            <div class="flex flex-wrap gap-2">
                @php
                    $platforms = \App\Modules\User\Controllers\CreatorProfileController::SOCIAL_PLATFORMS;
                @endphp
                @foreach($creator->socials as $key => $value)
                    @php
                        $p = $platforms[$key] ?? null;
                        if (!$p) continue;
                        $href = $value;
                        if ($key === 'twitter')   $href = preg_match('#^https?://#', $value) ? $value : 'https://twitter.com/'   . ltrim($value, '@');
                        if ($key === 'instagram') $href = preg_match('#^https?://#', $value) ? $value : 'https://instagram.com/' . ltrim($value, '@');
                        if ($key === 'tiktok')    $href = preg_match('#^https?://#', $value) ? $value : 'https://tiktok.com/@'   . ltrim($value, '@');
                        if ($key === 'youtube')   $href = preg_match('#^https?://#', $value) ? $value : 'https://youtube.com/@'  . ltrim($value, '@');
                        if ($key === 'github')    $href = preg_match('#^https?://#', $value) ? $value : 'https://github.com/'    . ltrim($value, '@');
                        if ($key === 'twitch')    $href = preg_match('#^https?://#', $value) ? $value : 'https://twitch.tv/'     . ltrim($value, '@');
                        if ($key === 'email')     $href = preg_match('#^mailto:#', $value)   ? $value : 'mailto:' . $value;
                    @endphp
                    <a href="{{ $href }}" target="_blank" rel="noopener nofollow"
                       class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-50 hover:bg-violet-50 hover:text-violet-700 text-slate-700 text-xs font-semibold border border-slate-200">
                        <i class="{{ $p['icon'] }}"></i> {{ $p['label'] }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ── Biolink callout ────────────────────────────────── --}}
    @if(($sectionsVisible['biolink'] ?? true) && $primaryBiolink)
        <section class="cp-card mt-3 p-5 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs uppercase tracking-wider text-slate-500 font-semibold">My links</p>
                <p class="text-sm text-slate-700 mt-1 truncate">All my projects, services, and current focus.</p>
            </div>
            <a href="{{ url('/' . $primaryBiolink->alias) }}" class="shrink-0 px-4 py-2 rounded-lg bg-violet-600 text-white text-xs font-semibold hover:bg-violet-700">
                Open biolink <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </section>
    @endif

    {{-- ── Posts feed ─────────────────────────────────────── --}}
    @if(($sectionsVisible['posts'] ?? true))
        <section class="mt-4">
            <h2 class="text-xs uppercase tracking-wider text-slate-500 font-semibold px-1 mb-2">
                Latest posts
            </h2>
            @if($posts->count() === 0)
                <div class="cp-card p-8 text-center">
                    <i class="fas fa-feather text-2xl text-slate-300 mb-2"></i>
                    <p class="text-slate-500 text-sm">{{ $isOwner ? 'You haven\'t shared anything yet.' : 'No posts yet — check back soon.' }}</p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($posts as $post)
                        @include('public.partials.creator-post-card', [
                            'post'          => $post,
                            'creator'       => $creator,
                            'totals'        => $reactionTotals[$post->id] ?? [],
                            'myReaction'    => $myReactions[$post->id] ?? null,
                            'comments'      => $commentsByPost[$post->id] ?? [],
                            'reactionDefs'  => $reactionDefs,
                            'viewer'        => $viewer,
                        ])
                    @endforeach
                </div>
                <div class="mt-4">{{ $posts->links() }}</div>
            @endif
        </section>
    @endif
</div>

<script>
(() => {
    // ── Branded reactions ─────────────────────────────────
    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
    document.querySelectorAll('[data-cp-reactions]').forEach(group => {
        const endpoint = group.dataset.cpEndpoint;
        group.querySelectorAll('[data-cp-reaction]').forEach(btn => {
            btn.addEventListener('click', async () => {
                try {
                    const res = await fetch(endpoint, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'},
                        body: JSON.stringify({reaction: btn.dataset.cpReaction}),
                    });
                    if (res.status === 401) { window.dispatchEvent(new CustomEvent('open-viewer-login')); return; }
                    const json = await res.json();
                    if (!json.success) return;
                    const totals = json.totals || {};
                    group.querySelectorAll('[data-cp-reaction]').forEach(b => {
                        const key = b.dataset.cpReaction;
                        const c = parseInt(totals[key] || 0, 10);
                        b.querySelector('[data-cp-count]').textContent = c > 0 ? c : '';
                        const isMine = json.reaction === key;
                        b.classList.toggle('bg-[color:var(--accent)]', isMine);
                        b.classList.toggle('text-white', isMine);
                        b.classList.toggle('border-[color:var(--accent)]', isMine);
                        b.classList.toggle('bg-white', !isMine);
                        b.classList.toggle('text-slate-700', !isMine);
                        b.classList.toggle('border-slate-200', !isMine);
                    });
                } catch (e) { /* swallow */ }
            });
        });
    });

    // ── Comments ──────────────────────────────────────────
    document.querySelectorAll('[data-cp-comment-form]').forEach(form => {
        form.addEventListener('submit', async e => {
            e.preventDefault();
            const endpoint = form.dataset.cpEndpoint;
            const fd = new FormData(form);
            const body = (fd.get('body') || '').toString().trim();
            const parentId = fd.get('parent_id') || null;
            if (!body) return;
            const res = await fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'},
                body: JSON.stringify({body, parent_id: parentId}),
            });
            if (res.status === 401) { window.dispatchEvent(new CustomEvent('open-viewer-login')); return; }
            const json = await res.json();
            if (!json.success) {
                alert(json.message || 'Could not post comment');
                return;
            }
            // Append the new comment to the right thread.
            const list = form.closest('[data-cp-comments]')?.querySelector(parentId ? `[data-cp-replies="${parentId}"]` : '[data-cp-toplevel]');
            if (list) {
                const c = json.comment;
                const node = document.createElement('div');
                node.className = 'flex items-start gap-2 py-2';
                node.innerHTML = `
                    <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center text-[11px] font-semibold text-slate-600">${(c.author.name||'?').charAt(0)}</div>
                    <div class="flex-1 text-xs text-slate-700">
                        <span class="font-semibold text-slate-900">${c.author.name||'Someone'}</span>
                        <span class="text-slate-400"> · just now</span>
                        <p class="mt-0.5">${c.body.replace(/[<>&]/g, x => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[x]))}</p>
                    </div>`;
                list.appendChild(node);
            }
            form.reset();
        });
    });

    // ── Toggle reply form / comments expanded ────────────
    document.querySelectorAll('[data-cp-toggle-comments]').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.querySelector(btn.dataset.cpToggleComments);
            if (target) target.classList.toggle('hidden');
        });
    });
})();
</script>
</body>
</html>
