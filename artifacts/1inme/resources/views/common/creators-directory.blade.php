<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Creators - {{ config('app.name') }}</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script defer src="{{ asset('js/vendor/alpine.min.js') }}"></script>
<style>[x-cloak]{display:none!important}</style>
</head>
@php
    $viewerNow = \App\Modules\Common\Services\ViewerSession::user();
@endphp
<body class="bg-gradient-to-br from-slate-50 via-white to-violet-50 min-h-screen">
<div class="max-w-6xl mx-auto px-4 py-10">
    <div class="flex items-center justify-between flex-wrap gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900">Creators</h1>
            <p class="text-slate-600 text-sm mt-1">Discover people and follow the ones you love.</p>
        </div>
        <div class="flex gap-2 items-center">
            @if($viewerNow)
                <span class="text-xs text-slate-500">Hi {{ $viewerNow->name }}</span>
                <form action="{{ route('viewer.logout') }}" method="POST" onsubmit="event.preventDefault(); fetch(this.action,{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}}).then(()=>location.reload());">
                    @csrf <button class="text-xs text-slate-500 hover:text-rose-600">Sign out</button>
                </form>
            @else
                <button type="button" @click="$dispatch('open-viewer-login', {})" class="px-3 py-1.5 rounded-lg bg-slate-900 text-white text-xs font-semibold">Sign in</button>
            @endif
            @auth
                <a href="{{ route('feed.index') }}" class="px-4 py-2 rounded-lg bg-violet-600 text-white text-sm font-semibold hover:bg-violet-700">My Feed</a>
                <a href="{{ route('user.dashboard') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-semibold hover:bg-slate-200">Dashboard</a>
            @endauth
        </div>
    </div>

    <form method="GET" class="flex flex-wrap gap-2 mb-4">
        <input type="text" name="q" value="{{ $q }}" placeholder="Search name, handle, bio or #tag..."
               class="flex-1 min-w-[240px] px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"/>
        <select name="sort" class="px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm">
            <option value="trending"      {{ $sort === 'trending' ? 'selected' : '' }}>Trending (7d)</option>
            <option value="most_followed" {{ $sort === 'most_followed' ? 'selected' : '' }}>Most followed</option>
            <option value="most_active"   {{ $sort === 'most_active' ? 'selected' : '' }}>Most active</option>
            <option value="newest"        {{ $sort === 'newest' ? 'selected' : '' }}>Newest</option>
        </select>
        <select name="tier" class="px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm">
            <option value="">All creators</option>
            <option value="free" {{ ($tier ?? '') === 'free' ? 'selected' : '' }}>Free tier available</option>
            <option value="paid" {{ ($tier ?? '') === 'paid' ? 'selected' : '' }}>Paid tiers</option>
        </select>
        @if($ageGated)
            <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 bg-white text-xs text-slate-700">
                <input type="checkbox" name="show_adult" value="1" {{ ($showAdult ?? false) ? 'checked' : '' }} onchange="this.form.submit()"/>
                Show 18+
            </label>
        @endif
        @if($tag !== '')<input type="hidden" name="tag" value="{{ $tag }}"/>@endif
        <button class="px-5 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold">Search</button>
    </form>

    {{-- Niche tag pills (Task #1211). --}}
    @if(!empty($popularTags))
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <a href="{{ route('creators.index') }}" class="text-[11px] px-2.5 py-1 rounded-full {{ $tag === '' ? 'bg-violet-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-violet-400' }}">All</a>
            @foreach($popularTags as $tagName => $cnt)
                <a href="{{ route('creators.index', array_filter(['tag' => $tagName, 'sort' => $sort, 'tier' => $tier, 'q' => $q])) }}"
                   class="text-[11px] px-2.5 py-1 rounded-full {{ $tag === $tagName ? 'bg-violet-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-violet-400' }}">
                    #{{ $tagName }} <span class="opacity-60">{{ $cnt }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Trending carousel (Task #1211). --}}
    @if(!empty($trendingCarousel))
        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-sm font-bold text-slate-900 flex items-center gap-1.5"><i class="fas fa-fire text-orange-500"></i> Trending now</h2>
                <a href="{{ route('creators.index', ['sort' => 'trending']) }}" class="text-[11px] text-violet-700 hover:underline">See all</a>
            </div>
            <div class="flex gap-3 overflow-x-auto pb-2 -mx-2 px-2 snap-x">
                @foreach($trendingCarousel as $tc)
                    <a href="{{ url('/@' . $tc->handle) }}" class="snap-start flex-shrink-0 w-44 bg-white rounded-2xl border border-slate-200 hover:shadow-md transition-shadow p-3 text-center">
                        @if($tc->avatar)
                            <img src="{{ $tc->avatar }}" class="w-14 h-14 rounded-full mx-auto object-cover" alt=""/>
                        @else
                            <div class="w-14 h-14 rounded-full mx-auto bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white flex items-center justify-center font-bold">{{ $tc->getInitials() }}</div>
                        @endif
                        <div class="mt-2 text-[13px] font-bold truncate">{{ $tc->name }}</div>
                        <div class="text-[11px] text-slate-500 truncate">&#64;{{ $tc->handle }}</div>
                        @if(isset($tc->gained))
                            <div class="mt-1 text-[10px] text-emerald-600 font-semibold">+{{ number_format((int) $tc->gained) }} this week</div>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if($creators->count() === 0)
        <div class="text-center py-16 bg-white rounded-2xl border border-slate-200">
            <i class="fas fa-search text-3xl text-slate-300 mb-3"></i>
            <p class="text-slate-600">No creators match your search.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($creators as $creator)
                @php
                    $bio = $creator->primaryBiolink();
                    // Task #1207: cards now point to the new public Creator
                    // Profile at /@handle (when the creator has a handle and
                    // has published their profile). Falls back to the
                    // primary biolink for legacy accounts that haven't
                    // claimed a handle yet.
                    $hasProfile = !empty($creator->handle) && (bool) ($creator->profile_published ?? false);
                    $href = $hasProfile ? url('/@' . $creator->handle) : ($bio ? url('/' . $bio->alias) : '#');
                    $isFollowing = $viewerNow ? \App\Modules\User\Models\Follow::where('follower_id', $viewerNow->id)->where('creator_id', $creator->id)->exists() : false;
                @endphp
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden hover:shadow-lg transition-all">
                    @if(!empty($creator->cover_image))
                        <a href="{{ $href }}" class="block h-20 bg-cover bg-center" style="background-image:url('{{ $creator->cover_image }}');"></a>
                    @else
                        <a href="{{ $href }}" class="block h-20 bg-gradient-to-br from-violet-500 via-fuchsia-500 to-indigo-500"></a>
                    @endif
                    <div class="p-5">
                    <div class="flex items-center gap-3 -mt-9">
                        @if($creator->avatar)
                            <img src="{{ $creator->avatar }}" class="w-14 h-14 rounded-full object-cover border-4 border-white bg-white" alt=""/>
                        @else
                            <div class="w-14 h-14 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white flex items-center justify-center font-bold text-lg border-4 border-white">
                                {{ $creator->getInitials() }}
                            </div>
                        @endif
                        <div class="flex-1 min-w-0 mt-7">
                            <a href="{{ $href }}" class="block font-bold text-slate-900 truncate hover:text-violet-700">{{ $creator->name }}</a>
                            @if($creator->handle)
                                <p class="text-xs text-slate-500 truncate">&#64;{{ $creator->handle }}{{ $hasProfile ? '' : ' · biolink' }}</p>
                            @endif
                        </div>
                    </div>
                    @if($creator->bio)
                        <p class="text-sm text-slate-600 mt-3 line-clamp-3">{{ $creator->bio }}</p>
                    @endif
                    @php $buzz = $buzzSnippets[$creator->id] ?? null; @endphp
                    @if($buzz)
                        <div class="mt-3 relative inline-block max-w-full"
                             x-data="{
                                open: false,
                                tapped: false,
                                placement: 'top-left',
                                _onWin: null,
                                updatePlacement() {
                                    this.$nextTick(() => {
                                        const anchor = this.$refs.anchor;
                                        if (!anchor) return;
                                        const rect = anchor.getBoundingClientRect();
                                        const tip = this.$refs.tooltip;
                                        const tooltipW = tip ? tip.offsetWidth || 256 : 256;
                                        const tooltipH = tip ? tip.offsetHeight || 110 : 110;
                                        const margin = 8;
                                        const vw = window.innerWidth || document.documentElement.clientWidth;
                                        const vh = window.innerHeight || document.documentElement.clientHeight;
                                        const spaceAbove = rect.top;
                                        const spaceBelow = vh - rect.bottom;
                                        const vertical = (spaceAbove < tooltipH + margin && spaceBelow > spaceAbove) ? 'bottom' : 'top';
                                        const horizontal = (rect.left + tooltipW + margin > vw) ? 'right' : 'left';
                                        this.placement = vertical + '-' + horizontal;
                                    });
                                },
                                show() {
                                    this.open = true;
                                    this.updatePlacement();
                                    if (!this._onWin) {
                                        this._onWin = () => this.updatePlacement();
                                        window.addEventListener('resize', this._onWin, { passive: true });
                                        window.addEventListener('scroll', this._onWin, { passive: true, capture: true });
                                    }
                                },
                                hide() {
                                    this.open = false;
                                    if (this._onWin) {
                                        window.removeEventListener('resize', this._onWin);
                                        window.removeEventListener('scroll', this._onWin, { capture: true });
                                        this._onWin = null;
                                    }
                                }
                             }"
                             @mouseenter="show()"
                             @mouseleave="hide()">
                            <a href="{{ $href }}"
                               x-ref="anchor"
                               @focus="show()"
                               @blur="hide()"
                               @click="if (!tapped && window.matchMedia && window.matchMedia('(hover: none)').matches) { $event.preventDefault(); show(); tapped = true; setTimeout(() => { tapped = false; hide(); }, 4000); }"
                               class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-violet-50 text-violet-700 hover:bg-violet-100 text-[11px] font-semibold max-w-full transition-colors"
                               :aria-expanded="open ? 'true' : 'false'"
                               aria-describedby="buzz-preview-{{ $creator->id }}">
                                <i class="fas {{ $buzz['icon'] }} text-[10px]"></i>
                                <span class="truncate">{{ $buzz['text'] }}</span>
                            </a>
                            <div x-show="open"
                                 x-ref="tooltip"
                                 x-transition.opacity.duration.150ms
                                 x-cloak
                                 id="buzz-preview-{{ $creator->id }}"
                                 role="tooltip"
                                 :class="{
                                    'bottom-full mb-2': placement.startsWith('top'),
                                    'top-full mt-2': placement.startsWith('bottom'),
                                    'left-0': placement.endsWith('left'),
                                    'right-0': placement.endsWith('right')
                                 }"
                                 class="absolute z-30 w-64 p-3 rounded-xl bg-white shadow-xl border border-slate-200 text-left pointer-events-none">
                                <div class="flex items-start gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-violet-100 text-violet-700 flex items-center justify-center flex-shrink-0">
                                        <i class="fas {{ $buzz['icon'] }} text-xs"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[12px] font-semibold text-slate-900 leading-snug break-words">{{ $buzz['text'] }}</p>
                                        <p class="text-[10px] text-slate-500 mt-1 flex items-center gap-1">
                                            <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Live on {{ $creator->name }}'s page
                                        </p>
                                    </div>
                                </div>
                                <p class="text-[10px] text-violet-600 font-semibold mt-2">Tap to view profile →</p>
                            </div>
                        </div>
                    @endif
                    @php
                        $messageableLinkId = $messageableBiolinks[$creator->id] ?? null;
                        // Encode the chat-open payload with HEX-quote/apostrophe so it
                        // can be embedded safely inside an HTML attribute and parsed by
                        // Alpine without breaking on creators whose names contain quotes.
                        $chatPayload = $messageableLinkId
                            ? json_encode([
                                'biolinkId'       => (int) $messageableLinkId,
                                'creatorId'       => (int) $creator->id,
                                'creatorName'     => (string) $creator->name,
                                'creatorAvatar'   => (string) ($creator->avatar ?? ''),
                                'creatorInitials' => (string) $creator->getInitials(),
                            ], JSON_UNESCAPED_UNICODE)
                            : '{}';
                    @endphp
                    <div class="flex items-center justify-between gap-2 flex-wrap mt-4 pt-4 border-t border-slate-100"
                         x-data="{ following: {{ $isFollowing ? 'true' : 'false' }}, busy:false }">
                        <span class="text-xs text-slate-500">
                            <i class="fas fa-user-group mr-1"></i>{{ number_format($creator->followers_count ?? 0) }} followers
                        </span>
                        <div class="flex items-center gap-1.5 flex-wrap">
                            @if($messageableLinkId)
                                <button type="button"
                                        @click="$dispatch('open-creator-chat', {{ $chatPayload }})"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 text-slate-700 hover:bg-slate-200 inline-flex items-center gap-1">
                                    <i class="fas fa-comments"></i>
                                    <span>Message</span>
                                </button>
                            @endif
                            @if($viewerNow && (int)$viewerNow->id === (int)$creator->id)
                                <a href="{{ $href }}" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 text-slate-700">View my page</a>
                            @elseif(!($creator->allow_followers ?? true))
                                <a href="{{ $href }}" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 text-slate-500">View profile</a>
                            @elseif($viewerNow)
                                <button type="button" :disabled="busy"
                                        @click="busy=true; fetch('{{ route('viewer.follow.toggle', $creator->id) }}',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json'}}).then(r=>r.json()).then(d=>{following=!!d.following; busy=false;}).catch(()=>busy=false)"
                                        :class="following ? 'bg-slate-100 text-slate-700' : 'bg-violet-600 text-white'"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold">
                                    <span x-text="following ? 'Following' : 'Follow'"></span>
                                </button>
                            @else
                                <button type="button"
                                        @click="$dispatch('open-viewer-login', {creatorId: {{ (int)$creator->id }} })"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-violet-600 text-white hover:bg-violet-700">
                                    + Follow
                                </button>
                            @endif
                        </div>
                    </div>
                    </div>{{-- /p-5 wrapper added for cover-image header --}}
                </div>
            @endforeach
        </div>
        <div class="mt-8">{{ $creators->links() }}</div>
    @endif
</div>

@php
    $modalCreatorId = null;
    $modalAccent    = '#ffffff';
    $modalBgPanel   = '#0f172a';
    $viewerInitial  = $viewerNow ? ['id'=>$viewerNow->id,'name'=>$viewerNow->name,'email'=>$viewerNow->email,'avatar'=>$viewerNow->avatar] : null;
    $dmInitialLimit = (int) (\App\Modules\Common\Models\ViewerDmConversation::VIEWER_INITIAL_LIMIT);
@endphp
@include('common.partials.viewer-login-modal', compact('modalCreatorId','modalAccent','modalBgPanel','viewerInitial'))

{{-- Directory-level chat overlay. Mounts the shared DM widget against
     the creator's resolved default biolink. --}}
<div x-data="creatorChatOverlay({{ $viewerNow ? 'true' : 'false' }})"
     x-cloak
     @open-creator-chat.window="open($event.detail || {})"
     @viewer-message-ready.window="onViewerLoggedIn($event.detail || {})"
     x-show="visible"
     style="position: fixed; inset: 0; z-index: 9998;">
    <div class="absolute inset-0 bg-slate-900/70 backdrop-blur-sm" @click="close()"></div>
    <div class="absolute right-0 top-0 h-full w-full sm:max-w-md bg-white shadow-2xl overflow-y-auto flex flex-col"
         style="color:#fff; background: #0f172a;">
        <div class="flex items-center gap-3 p-4 border-b border-white/10">
            <template x-if="creator.avatar">
                <img :src="creator.avatar" class="w-10 h-10 rounded-full object-cover" alt=""/>
            </template>
            <template x-if="!creator.avatar">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white flex items-center justify-center font-bold text-sm"
                     x-text="creator.initials || '?'"></div>
            </template>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold truncate" x-text="creator.name || 'Creator'"></p>
                <p class="text-[11px] opacity-60">Direct message</p>
            </div>
            <button type="button" @click="close()" class="opacity-60 hover:opacity-100 text-2xl leading-none px-2">&times;</button>
        </div>
        <div class="flex-1 p-4">
            {{-- Mount the shared chat widget. The wrapping <template x-if> forces
                 a remount whenever the current biolink changes so the dmBlock()
                 Alpine state resets per creator. The dmBlock x-data lives here
                 (inheritDmContext=true on the partial) so we can wire its
                 linkId to the dynamic Alpine value. --}}
            <template x-if="visible && currentBiolinkId">
                <div :key="currentBiolinkId"
                     x-data="dmBlock({ linkId: currentBiolinkId, limit: {{ $dmInitialLimit }}, loggedIn: loggedIn, csrf: csrf })"
                     x-init="init()">
                    @include('common.partials.dm-chat-widget', [
                        'dmLinkId'         => 0,
                        'dmLimit'          => $dmInitialLimit,
                        'loggedIn'         => false,
                        'variant'          => 'overlay',
                        'inheritDmContext' => true,
                        'dmTitle'          => 'Direct message',
                        'dmDesc'           => 'Replies arrive in your inbox.',
                        'dmBtn'            => 'Send',
                    ])
                </div>
            </template>
        </div>
    </div>
</div>

{{-- Bring the shared dmBlock() Alpine component into scope on the
     directory page (no biolink-block partial is rendered here). --}}
@include('common.partials.dm-chat-widget-script')
<script>
function creatorChatOverlay(initialLoggedIn) {
    return {
        visible: false,
        loggedIn: !!initialLoggedIn,
        csrf: document.querySelector('meta[name="csrf-token"]')?.content || '',
        currentBiolinkId: null,
        creator: { name: '', avatar: '', initials: '' },
        open(detail) {
            const biolinkId = parseInt(detail.biolinkId, 10);
            if (!biolinkId) return;
            this.creator = {
                name:     detail.creatorName     || 'Creator',
                avatar:   detail.creatorAvatar   || '',
                initials: detail.creatorInitials || '?',
            };
            if (!this.loggedIn) {
                // Defer: ask the viewer to sign in first, remember the target.
                window.dispatchEvent(new CustomEvent('open-viewer-login', {
                    detail: {
                        action:      'message',
                        biolinkId:   biolinkId,
                        creatorId:   detail.creatorId,
                        creatorName: detail.creatorName,
                    },
                }));
                // Stash so we can re-open after login.
                this._pending = { ...detail, biolinkId };
                return;
            }
            // Force a remount of the inner widget so the dmBlock Alpine
            // component re-initialises against the new biolink id.
            this.currentBiolinkId = null;
            this.visible = true;
            setTimeout(() => { this.currentBiolinkId = biolinkId; }, 0);
        },
        onViewerLoggedIn(detail) {
            this.loggedIn = true;
            // If the user signed in via an explicit Message click, reopen.
            const target = this._pending || detail;
            const biolinkId = parseInt(target.biolinkId, 10);
            if (!biolinkId) return;
            this.creator = {
                name:     target.creatorName     || this.creator.name,
                avatar:   target.creatorAvatar   || this.creator.avatar,
                initials: target.creatorInitials || this.creator.initials,
            };
            this.currentBiolinkId = null;
            this.visible = true;
            setTimeout(() => { this.currentBiolinkId = biolinkId; }, 0);
            this._pending = null;
        },
        close() {
            this.visible = false;
            this.currentBiolinkId = null;
        },
    };
}
</script>
</body>
</html>
