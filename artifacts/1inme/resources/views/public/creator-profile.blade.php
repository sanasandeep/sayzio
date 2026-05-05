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
                <div class="flex items-center gap-2 mb-1 flex-wrap justify-end">
                    @if($isOwner)
                        <a href="{{ route('user.creator-profile.edit') }}" class="px-3.5 py-2 rounded-lg bg-slate-900 text-white text-xs font-semibold hover:bg-slate-700">
                            <i class="fas fa-pen mr-1"></i> Edit profile
                        </a>
                        <a href="{{ route('user.monetization.earnings') }}" class="px-3.5 py-2 rounded-lg bg-violet-50 border border-violet-200 text-violet-700 text-xs font-semibold hover:bg-violet-100">
                            <i class="fas fa-gem mr-1"></i> Monetization
                        </a>
                    @else
                        @include('public.partials.follow-button', ['creator' => $creator, 'viewer' => $viewer, 'isFollowing' => $isFollowing])

                        {{-- ── Monetization CTAs (Task #1209) ──────────────── --}}
                        @if($tiers->count())
                            @if($viewerSubscription && $viewerSubscription->isCurrent())
                                <a href="{{ route('creator-profile.subscription.manage', ['handle' => $creator->handle]) }}"
                                   class="px-3.5 py-2 rounded-lg bg-violet-50 border border-violet-200 text-violet-700 text-xs font-semibold hover:bg-violet-100">
                                    <i class="fas fa-circle-check mr-1"></i> Subscribed
                                </a>
                            @else
                                <a href="{{ route('creator-profile.subscribe.show', ['handle' => $creator->handle]) }}"
                                   class="px-3.5 py-2 rounded-lg text-xs font-semibold text-white shadow-sm bg-gradient-to-r from-violet-600 to-fuchsia-600 hover:from-violet-700 hover:to-fuchsia-700">
                                    <i class="fas fa-gem mr-1"></i> Subscribe
                                </a>
                            @endif
                        @endif
                        @if($creator->canAcceptTips ?? true)
                            <button type="button"
                                    data-cp-open-tip
                                    data-cp-tip-creator="{{ $creator->id }}"
                                    data-cp-tip-handle="{{ $creator->handle }}"
                                    class="px-3.5 py-2 rounded-lg bg-white border border-slate-200 text-rose-600 text-xs font-semibold hover:border-rose-400">
                                <i class="fas fa-heart mr-1"></i> Tip
                            </button>

                            {{-- Task #1211 — Block / report kebab. Visible only to
                                 logged-in viewers (otherwise both endpoints would
                                 require auth and bounce them through OTP). --}}
                            @auth
                            <div class="relative" x-data="{open:false, reportOpen:false}">
                                <button type="button" @click="open=!open"
                                        class="px-2.5 py-2 rounded-lg bg-white border border-slate-200 text-slate-500 text-xs hover:border-slate-400" title="More">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div x-show="open" @click.outside="open=false" x-transition x-cloak
                                     class="absolute right-0 mt-1 w-48 bg-white border border-slate-200 rounded-lg shadow-lg z-30 overflow-hidden">
                                    <form method="POST" action="{{ route('users.block.toggle', ['creator' => $creator->id]) }}">
                                        @csrf
                                        <button class="w-full text-left px-3 py-2 text-xs hover:bg-slate-50 text-slate-700">
                                            <i class="fas fa-ban mr-1.5 text-slate-500"></i> Block creator
                                        </button>
                                    </form>
                                    <button type="button" @click="reportOpen=true; open=false"
                                            class="w-full text-left px-3 py-2 text-xs hover:bg-slate-50 text-rose-600 border-t border-slate-100">
                                        <i class="fas fa-flag mr-1.5"></i> Report creator
                                    </button>
                                    <a href="{{ route('legal.dmca.show', ['handle' => $creator->handle]) }}"
                                       class="block px-3 py-2 text-xs hover:bg-slate-50 text-slate-700 border-t border-slate-100">
                                        <i class="fas fa-gavel mr-1.5 text-slate-500"></i> DMCA takedown
                                    </a>
                                </div>

                                {{-- Report modal — submits the enum reason + optional
                                     free-text comment that UserReportController validates. --}}
                                <div x-show="reportOpen" x-transition x-cloak
                                     class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9999] flex items-center justify-center"
                                     style="display:none;">
                                    <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-[95%] p-5" @click.outside="reportOpen=false">
                                        <div class="flex items-start justify-between mb-3">
                                            <h3 class="text-base font-bold text-slate-900">Report {{ $creator->name }}</h3>
                                            <button type="button" @click="reportOpen=false" class="text-slate-400 hover:text-slate-700">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        <form method="POST" action="{{ route('users.report', ['creator' => $creator->id]) }}" class="space-y-3">
                                            @csrf
                                            <div>
                                                <label class="text-xs font-semibold text-slate-700">Reason</label>
                                                <select name="reason" required class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 text-sm">
                                                    @foreach(\App\Modules\Common\Models\UserReport::REASONS as $key => $label)
                                                        <option value="{{ $key }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="text-xs font-semibold text-slate-700">Add a note (optional)</label>
                                                <textarea name="comment" rows="3" maxlength="1000"
                                                          placeholder="More context for our moderators…"
                                                          class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-200 text-sm"></textarea>
                                            </div>
                                            <button type="submit" class="w-full py-2 rounded-lg text-sm font-bold text-white bg-rose-600 hover:bg-rose-700">
                                                Send report
                                            </button>
                                            <p class="text-[11px] text-slate-500 text-center">Reports are private and reviewed by our moderators.</p>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endauth
                        @endif
                        @if($creator->isSectionVisible('contact'))
                            <a href="mailto:{{ $creator->email }}" class="px-3.5 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 text-xs font-semibold hover:border-violet-400 hover:text-violet-600">
                                <i class="fas fa-envelope mr-1"></i> Contact
                            </a>
                        @endif
                        {{-- Paid DMs (Task #1210): Message button --}}
                        @if(($creator->dm_access_mode ?? 'open') !== 'closed')
                            <button type="button"
                                    data-cp-open-dm
                                    data-cp-dm-handle="{{ $creator->handle ?: $creator->id }}"
                                    class="px-3.5 py-2 rounded-lg text-xs font-semibold text-white shadow-sm bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700">
                                <i class="fas fa-paper-plane mr-1"></i> Message
                            </button>
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
                            'access'        => $accessByPost[$post->id] ?? ['can' => true, 'reason' => 'free'],
                        ])
                    @endforeach
                </div>
                <div class="mt-4">{{ $posts->links() }}</div>
            @endif
        </section>
    @endif

    {{-- Task #1211 — "More creators like {{ $creator->name }}". Surfaces only
         when there are matches; uses the cached helper on CreatorsController
         so we don't run discovery queries on every profile view. --}}
    @if(!empty($relatedCreators) && count($relatedCreators) > 0)
        <section class="cp-card mt-4 px-5 py-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-slate-900">More creators like {{ $creator->name }}</h3>
                <a href="{{ route('creators.index') }}" class="text-xs font-semibold text-violet-600 hover:underline">Browse all →</a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach($relatedCreators as $rc)
                    <a href="{{ url('/@' . $rc->handle) }}" class="flex items-center gap-2 p-2 rounded-lg border border-slate-200 hover:border-violet-400 hover:bg-violet-50 transition">
                        @if($rc->avatar)
                            <img src="{{ $rc->avatar }}" alt="" class="w-9 h-9 rounded-full object-cover bg-slate-100 shrink-0">
                        @else
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                {{ $rc->getInitials() }}
                            </div>
                        @endif
                        <div class="min-w-0">
                            <div class="text-xs font-semibold text-slate-900 truncate">{{ $rc->name }}</div>
                            <div class="text-[11px] text-slate-500 truncate">@ {{ $rc->handle }}</div>
                        </div>
                    </a>
                @endforeach
            </div>
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

    // ── Tip modal (Task #1209) ───────────────────────────
    const tipModal = document.getElementById('cp-tip-modal');
    const tipForm  = document.getElementById('cp-tip-form');
    const tipPostInput = document.getElementById('cp-tip-post-id');
    const tipCloseBtn  = document.getElementById('cp-tip-close');
    document.querySelectorAll('[data-cp-open-tip]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!tipModal) return;
            const handle = btn.dataset.cpTipHandle;
            const postId = btn.dataset.cpTipPost || '';
            tipForm.action = postId
                ? `/@${handle}/p/${postId}/tip`
                : `/@${handle}/tip`;
            tipPostInput.value = postId;
            tipModal.classList.remove('hidden');
        });
    });
    if (tipCloseBtn) tipCloseBtn.addEventListener('click', () => tipModal.classList.add('hidden'));
    if (tipModal) tipModal.addEventListener('click', (e) => {
        if (e.target === tipModal) tipModal.classList.add('hidden');
    });
    document.querySelectorAll('[data-cp-tip-amount]').forEach(b => {
        b.addEventListener('click', () => {
            document.querySelector('input[name=amount]').value = b.dataset.cpTipAmount;
        });
    });

    // Auto-trigger viewer-OTP modal when controller flashed
    // viewer_login_required (e.g. user tried to subscribe while signed
    // out). Reuses the existing global modal already on the page.
    @if(session('viewer_login_required'))
        window.dispatchEvent(new CustomEvent('open-viewer-login', { detail: { creatorId: {{ (int) $creator->id }} } }));
    @endif
})();
</script>

{{-- ── Paid DMs widget (Task #1210) ────────────────────────── --}}
@if(!$isOwner && ($creator->dm_access_mode ?? 'open') !== 'closed')
<div id="cp-dm-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9998] hidden items-end sm:items-center justify-center" style="display: none;">
    <style>#cp-dm-modal:not(.hidden){display:flex !important;}</style>
    <div x-data="cpDm({ handle: @js($creator->handle ?: $creator->id), creatorName: @js($creator->name) })"
         x-init="open()"
         class="bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl w-full sm:max-w-md sm:w-[95%] sm:h-[85vh] h-[92vh] flex flex-col">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
            <div>
                <h3 class="text-sm font-bold text-slate-900">DM {{ $creator->name }}</h3>
                <p class="text-[11px] text-slate-500" x-text="statusLabel()"></p>
            </div>
            <button id="cp-dm-close" class="text-slate-400 hover:text-slate-700 p-1"><i class="fas fa-times"></i></button>
        </div>

        <div x-show="state.reason === 'login_required'" class="flex-1 flex items-center justify-center p-6 text-center">
            <div>
                <p class="text-sm text-slate-700 mb-3">Sign in to send a direct message.</p>
                <a href="#" @click.prevent="window.dispatchEvent(new CustomEvent('open-viewer-login', { detail: { creatorId: {{ (int) $creator->id }} } }))"
                   class="inline-block px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-semibold">Sign in</a>
            </div>
        </div>

        <div x-show="state.reason !== 'login_required'" class="flex-1 overflow-y-auto p-4 space-y-2 bg-slate-50" id="cp-dm-scroll">
            <template x-for="m in state.messages" :key="m.id">
                <div :class="m.side === 'viewer' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="m.side === 'viewer'
                        ? 'bg-violet-600 text-white rounded-2xl rounded-br-sm px-3 py-2 max-w-[80%]'
                        : (m.kind === 'system'
                            ? 'bg-amber-50 border border-amber-200 text-amber-700 italic rounded-2xl px-3 py-2 max-w-[80%] text-xs'
                            : 'bg-white border border-slate-200 text-slate-800 rounded-2xl rounded-bl-sm px-3 py-2 max-w-[80%]')">
                        <p class="text-sm whitespace-pre-wrap" x-text="m.body"></p>
                        <template x-for="a in m.attachments" :key="a.id">
                            <div class="mt-2">
                                <template x-if="a.is_locked">
                                    <button type="button" @click="unlock(a)"
                                            class="block relative rounded-lg overflow-hidden border border-violet-300 bg-violet-50 text-left">
                                        <img :src="a.thumb_url" class="w-44 h-44 object-cover blur-md" alt="">
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/40">
                                            <span class="text-white text-xs font-bold">
                                                <i class="fas fa-lock mr-1"></i>
                                                Unlock $<span x-text="(a.lock_price_cents/100).toFixed(2)"></span>
                                            </span>
                                        </div>
                                    </button>
                                </template>
                                <template x-if="!a.is_locked">
                                    <a :href="a.url" target="_blank">
                                        <img :src="a.thumb_url || a.url" class="w-44 max-h-60 object-cover rounded-lg" alt="">
                                    </a>
                                </template>
                            </div>
                        </template>
                        <p class="text-[10px] mt-1 opacity-70" x-text="formatTime(m.sent_at)"></p>
                    </div>
                </div>
            </template>
            <div x-show="state.messages.length === 0 && !state.loading" class="text-center text-xs text-slate-400 py-10">
                No messages yet — say hi 👋
            </div>
        </div>

        <div class="border-t border-slate-100 p-3 bg-white" x-show="state.reason !== 'login_required'">
            <template x-if="state.reason === 'closed'">
                <p class="text-xs text-slate-500">DMs are turned off for this creator.</p>
            </template>
            <template x-if="state.reason === 'subs_required'">
                <a :href="`/@${state.handle}/subscribe`" class="block text-center px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-semibold">
                    Subscribe to message
                    <span x-show="state.policy.min_tier_name" x-text="`· ${state.policy.min_tier_name}`"></span>
                </a>
            </template>
            <template x-if="state.reason === 'paid_required'">
                <button type="button" @click="payToMessage()"
                        class="w-full px-4 py-2 rounded-lg bg-gradient-to-r from-rose-500 to-pink-600 text-white text-sm font-semibold">
                    <i class="fas fa-lock mr-1"></i>
                    Pay $<span x-text="((state.policy.price_cents||0)/100).toFixed(2)"></span> to start chatting
                </button>
            </template>
            <template x-if="state.reason === 'account_blocked' || state.reason === 'thread_blocked'">
                <p class="text-xs text-rose-500">You can't message this creator.</p>
            </template>
            <template x-if="state.reason === 'throttled'">
                <p class="text-xs text-amber-600">Wait for {{ $creator->name }} to reply before sending more messages.</p>
            </template>
            <template x-if="state.reason === 'ok'">
                <form @submit.prevent="send()" class="flex items-end gap-2">
                    <textarea x-model="draft" rows="1" maxlength="5000" placeholder="Write a message…"
                              class="flex-1 px-3 py-2 rounded-lg border border-slate-200 focus:border-violet-400 focus:outline-none text-sm resize-none"
                              @keydown.enter.prevent.exact="send()"></textarea>
                    <button type="button" @click="openTip()" title="Tip"
                            class="px-3 py-2 rounded-lg border border-rose-200 text-rose-500 hover:bg-rose-50">
                        <i class="fas fa-heart"></i>
                    </button>
                    <button type="submit" :disabled="state.sending || !draft.trim()"
                            class="px-3 py-2 rounded-lg bg-violet-600 text-white text-sm font-semibold disabled:opacity-50">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </template>
        </div>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('[data-cp-open-dm]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const m = document.getElementById('cp-dm-modal');
            if (m) m.classList.remove('hidden');
        });
    });
    const close = () => document.getElementById('cp-dm-modal')?.classList.add('hidden');
    document.getElementById('cp-dm-close')?.addEventListener('click', close);
    document.getElementById('cp-dm-modal')?.addEventListener('click', (e) => {
        if (e.target.id === 'cp-dm-modal') close();
    });
})();

function cpDm(opts) {
    return {
        state: {
            handle: opts.handle,
            creatorName: opts.creatorName,
            messages: [],
            policy: { mode: 'open', price_cents: 0, currency: 'USD' },
            reason: 'ok',
            conversationId: null,
            loading: true,
            sending: false,
        },
        draft: '',
        async open() {
            await this.refresh();
        },
        async refresh() {
            this.state.loading = true;
            try {
                const r = await fetch(`/viewer/dm/profile/${this.state.handle}/thread`, { credentials: 'same-origin' });
                if (r.status === 401) { this.state.reason = 'login_required'; return; }
                const j = await r.json();
                if (!j.ok) { this.state.reason = j.reason || 'closed'; return; }
                this.state.messages = j.messages;
                this.state.policy = j.policy;
                this.state.reason = j.policy.reason;
                this.state.conversationId = j.conversation_id;
                this.$nextTick(() => {
                    const el = document.getElementById('cp-dm-scroll');
                    if (el) el.scrollTop = el.scrollHeight;
                });
            } catch (e) {
                this.state.reason = 'error';
            } finally {
                this.state.loading = false;
            }
        },
        async send() {
            if (!this.draft.trim() || this.state.sending) return;
            this.state.sending = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
                const r = await fetch(`/viewer/dm/profile/${this.state.handle}/send`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ body: this.draft }),
                });
                if (r.status === 402) {
                    const j = await r.json();
                    if (j.checkout_url) window.location.href = j.checkout_url;
                    return;
                }
                const j = await r.json();
                if (!j.ok) {
                    alert(j.reason === 'throttled' ? 'Wait for a reply before sending more.' : (j.reason || 'Could not send.'));
                    return;
                }
                this.state.messages.push(j.message);
                this.draft = '';
                this.$nextTick(() => {
                    const el = document.getElementById('cp-dm-scroll');
                    if (el) el.scrollTop = el.scrollHeight;
                });
            } finally {
                this.state.sending = false;
            }
        },
        async payToMessage() {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const r = await fetch(`/viewer/dm/profile/${this.state.handle}/send`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ body: '👋' }),
            });
            if (r.status === 402) {
                const j = await r.json();
                if (j.checkout_url) window.location.href = j.checkout_url;
            }
        },
        async unlock(att) {
            if (!confirm(`Unlock for $${(att.lock_price_cents/100).toFixed(2)}?`)) return;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const r = await fetch(`/viewer/dm/attachments/${att.id}/unlock`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ return_url: window.location.href }),
            });
            const j = await r.json();
            if (j.checkout_url) window.location.href = j.checkout_url;
        },
        openTip() {
            const btn = document.querySelector('[data-cp-open-tip]');
            if (btn) { document.getElementById('cp-dm-modal')?.classList.add('hidden'); btn.click(); }
        },
        statusLabel() {
            const m = this.state.policy?.mode || 'open';
            if (m === 'paid' && !this.state.policy.paid && !this.state.policy.subscribed) {
                return `Pay-to-message — $${((this.state.policy.price_cents||0)/100).toFixed(2)} to start`;
            }
            if (m === 'subs') return 'Subscribers only';
            return 'Direct message';
        },
        formatTime(iso) {
            if (!iso) return '';
            try { return new Date(iso).toLocaleString(undefined, { hour: '2-digit', minute: '2-digit' }); }
            catch { return ''; }
        },
    };
}
</script>
@endif

{{-- ── Tip modal (Task #1209) ─────────────────────────────── --}}
@if(!$isOwner)
<div id="cp-tip-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[9998] hidden items-center justify-center" style="display: none;">
    <style>#cp-tip-modal:not(.hidden){display:flex !important;}</style>
    <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-[95%] p-6">
        <div class="flex items-start justify-between mb-3">
            <div>
                <h3 class="text-lg font-bold text-slate-900">Send a tip to {{ $creator->name }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">100% goes to the creator. 1INME takes 0%.</p>
            </div>
            <button id="cp-tip-close" class="text-slate-400 hover:text-slate-700"><i class="fas fa-times"></i></button>
        </div>
        <form id="cp-tip-form" method="POST" action="{{ route('creator-profile.tip', ['handle' => $creator->handle]) }}" class="space-y-3">
            @csrf
            <input type="hidden" name="post_id" id="cp-tip-post-id" value="">
            <div class="grid grid-cols-4 gap-1.5">
                @foreach([3, 5, 10, 20, 50, 100] as $amt)
                    <button type="button" data-cp-tip-amount="{{ $amt }}"
                            class="px-2 py-2 rounded-lg border border-slate-200 text-sm font-semibold text-slate-700 hover:border-rose-400 hover:bg-rose-50">
                        ${{ $amt }}
                    </button>
                @endforeach
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider text-slate-500">Amount ($)</label>
                <input type="number" name="amount" min="1" max="500" step="0.5" required value="5"
                       class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 focus:border-rose-400 focus:outline-none">
            </div>
            <div>
                <label class="text-xs uppercase tracking-wider text-slate-500">Message (optional)</label>
                <textarea name="note" rows="2" maxlength="280" placeholder="Say something nice…"
                          class="w-full mt-1 px-3 py-2 rounded-lg border border-slate-200 focus:border-rose-400 focus:outline-none text-sm"></textarea>
            </div>
            <button type="submit" class="w-full py-2.5 rounded-lg text-sm font-bold text-white bg-gradient-to-r from-rose-500 to-pink-600">
                <i class="fas fa-heart mr-1"></i> Send tip
            </button>
        </form>
    </div>
</div>
@endif

</body>
</html>
