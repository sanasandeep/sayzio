<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Creators - {{ config('app.name') }}</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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

    <form method="GET" class="flex flex-wrap gap-2 mb-6">
        <input type="text" name="q" value="{{ $q }}" placeholder="Search by name, handle or bio..."
               class="flex-1 min-w-[240px] px-4 py-2 rounded-lg border border-slate-200 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-violet-500"/>
        <select name="sort" class="px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm">
            <option value="trending" {{ $sort === 'trending' ? 'selected' : '' }}>Trending (7d)</option>
            <option value="most_followed" {{ $sort === 'most_followed' ? 'selected' : '' }}>Most followed</option>
            <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Newest</option>
        </select>
        <button class="px-5 py-2 rounded-lg bg-slate-900 text-white text-sm font-semibold">Search</button>
    </form>

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
                    $href = $bio ? url('/' . $bio->alias) : '#';
                    $isFollowing = $viewerNow ? \App\Modules\User\Models\Follow::where('follower_id', $viewerNow->id)->where('creator_id', $creator->id)->exists() : false;
                @endphp
                <div class="bg-white rounded-2xl border border-slate-200 p-5 hover:shadow-lg transition-all">
                    <div class="flex items-center gap-3">
                        @if($creator->avatar)
                            <img src="{{ $creator->avatar }}" class="w-14 h-14 rounded-full object-cover" alt=""/>
                        @else
                            <div class="w-14 h-14 rounded-full bg-gradient-to-br from-violet-500 to-fuchsia-500 text-white flex items-center justify-center font-bold text-lg">
                                {{ $creator->getInitials() }}
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <a href="{{ $href }}" class="block font-bold text-slate-900 truncate hover:text-violet-700">{{ $creator->name }}</a>
                            @if($creator->handle)
                                <p class="text-xs text-slate-500 truncate">&#64;{{ $creator->handle }}</p>
                            @endif
                        </div>
                    </div>
                    @if($creator->bio)
                        <p class="text-sm text-slate-600 mt-3 line-clamp-3">{{ $creator->bio }}</p>
                    @endif
                    @php($buzz = $buzzSnippets[$creator->id] ?? null)
                    @if($buzz)
                        <div class="mt-3 inline-flex items-center gap-1.5 px-2 py-1 rounded-full bg-violet-50 text-violet-700 text-[11px] font-semibold max-w-full">
                            <i class="fas {{ $buzz['icon'] }} text-[10px]"></i>
                            <span class="truncate">{{ $buzz['text'] }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-slate-100"
                         x-data="{ following: {{ $isFollowing ? 'true' : 'false' }}, busy:false }">
                        <span class="text-xs text-slate-500">
                            <i class="fas fa-user-group mr-1"></i>{{ number_format($creator->followers_count ?? 0) }} followers
                        </span>
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
@endphp
@include('common.partials.viewer-login-modal', compact('modalCreatorId','modalAccent','modalBgPanel','viewerInitial'))
</body>
</html>
