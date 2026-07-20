@extends('user.layouts.app')
@section('title', 'Profile Verification Queue')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Profile Verification</h1>
            <p class="text-sm mt-1" style="color: var(--text-muted);">Review and manage creator profile verification requests</p>
        </div>
        <a href="{{ route('user.profile-verification.admin.tick-types') }}" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all" style="background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-glass);">
            <i class="fas fa-tags mr-1"></i>Manage Tick Types
        </a>
    </div>

    @if(session('success'))
    <div class="mb-4 p-4 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); color: #34d399; border: 1px solid rgba(16,185,129,0.2);">
        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    </div>
    @endif

    {{-- Queue tabs --}}
    <div class="flex items-center gap-2 mb-5 flex-wrap">
        <a href="{{ route('user.profile-verification.admin.index', ['queue' => 'new']) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ $queue === 'new' ? 'text-white' : '' }}"
           style="{{ $queue === 'new' ? 'background: #3d6bff;' : 'background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-glass);' }}">
            New Requests
            @if($pendingNewCount > 0)
            <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold" style="background: rgba(245,158,11,0.2); color: #f59e0b;">{{ $pendingNewCount }}</span>
            @endif
        </a>
        <a href="{{ route('user.profile-verification.admin.index', ['queue' => 'reverification']) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ $queue === 'reverification' ? 'text-white' : '' }}"
           style="{{ $queue === 'reverification' ? 'background: #3d6bff;' : 'background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-glass);' }}">
            Re-verifications
            @if($pendingReVerCount > 0)
            <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold" style="background: rgba(245,158,11,0.2); color: #f59e0b;">{{ $pendingReVerCount }}</span>
            @endif
        </a>
        <div class="ml-auto flex gap-2">
            @foreach(['pending' => '#f59e0b', 'approved' => '#10b981', 'rejected' => '#ef4444'] as $st => $stColor)
            <a href="{{ route('user.profile-verification.admin.index', ['queue' => $queue, 'status' => $st]) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ request('status') === $st ? 'text-white' : '' }}"
               style="{{ request('status') === $st ? 'background: '.$stColor.';' : 'background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-glass);' }}">
                {{ ucfirst($st) }}
            </a>
            @endforeach
            <a href="{{ route('user.profile-verification.admin.index', ['queue' => $queue]) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ !request('status') ? 'text-white' : '' }}"
               style="{{ !request('status') ? 'background: #64748b;' : 'background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-glass);' }}">
                All
            </a>
        </div>
    </div>

    @if($requests->count() > 0)
    <div class="space-y-3">
        @foreach($requests as $req)
        <a href="{{ route('user.profile-verification.admin.review', $req) }}" class="card-premium p-5 block transition-all hover:-translate-y-0.5 hover:shadow-lg">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-4">
                    @if($req->logo_path)
                    <img src="{{ \App\Support\PublicStorageUrl::resolve($req->logo_path) }}" alt="" class="w-12 h-12 rounded-xl object-cover" style="border: 1px solid var(--border-glass);">
                    @elseif($req->user?->avatar)
                    <img src="{{ \App\Support\PublicStorageUrl::resolve($req->user->avatar) }}" alt="" class="w-12 h-12 rounded-xl object-cover" style="border: 1px solid var(--border-glass);">
                    @else
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background: rgba(61,107,255,0.1);"><i class="fas fa-user text-blue-400"></i></div>
                    @endif
                    <div>
                        <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                            <span class="text-sm font-bold" style="color: var(--text-primary);">{{ $req->official_name }}</span>
                            @if($req->tickType)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold" style="background: {{ $req->tickType->color }}20; color: {{ $req->tickType->color }};">
                                <i class="fas {{ $req->tickType->icon }} mr-0.5 text-[9px]"></i>{{ $req->tickType->name }}
                            </span>
                            @endif
                            @if($req->kind === 'reverification')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-500/10 text-blue-400">Re-verify</span>
                            @endif
                        </div>
                        <p class="text-[11px]" style="color: var(--text-dimmed);">
                            by {{ $req->user?->name ?? $req->user?->email ?? 'Unknown' }} · @{{ $req->user?->handle }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    @if($req->status === 'pending')
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400"><i class="fas fa-clock mr-1"></i>Pending</span>
                    @elseif($req->status === 'approved')
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400"><i class="fas fa-check mr-1"></i>Approved</span>
                    @else
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-red-500/10 text-red-400"><i class="fas fa-times mr-1"></i>Rejected</span>
                    @endif
                    <span class="text-[10px]" style="color: var(--text-dimmed);">{{ $req->created_at->diffForHumans() }}</span>
                    <i class="fas fa-chevron-right text-xs" style="color: var(--text-dimmed);"></i>
                </div>
            </div>
        </a>
        @endforeach
    </div>
    <div class="mt-6">{{ $requests->withQueryString()->links() }}</div>
    @else
    <div class="card-premium p-10 text-center">
        <div class="w-16 h-16 rounded-2xl mx-auto mb-4 flex items-center justify-center" style="background: rgba(61,107,255,0.1);">
            <i class="fas fa-inbox text-blue-400 text-2xl"></i>
        </div>
        <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">No verification requests</h3>
        <p class="text-xs" style="color: var(--text-dimmed);">{{ request('status') ? 'No ' . request('status') . ' requests in this queue.' : 'No requests submitted yet.' }}</p>
    </div>
    @endif
</div>
@endsection
