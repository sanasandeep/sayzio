@extends('user.layouts.app')
@section('title', 'Verification Requests')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Verification Requests</h1>
            <p class="text-sm mt-1" style="color: var(--text-muted);">Review and manage verification requests</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('user.verification.admin', ['status' => 'pending']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ request('status') === 'pending' ? 'text-white' : '' }}" style="{{ request('status') === 'pending' ? 'background: #f59e0b;' : 'background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-glass);' }}">
                Pending
            </a>
            <a href="{{ route('user.verification.admin', ['status' => 'approved']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ request('status') === 'approved' ? 'text-white' : '' }}" style="{{ request('status') === 'approved' ? 'background: #10b981;' : 'background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-glass);' }}">
                Approved
            </a>
            <a href="{{ route('user.verification.admin', ['status' => 'rejected']) }}" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ request('status') === 'rejected' ? 'text-white' : '' }}" style="{{ request('status') === 'rejected' ? 'background: #ef4444;' : 'background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-glass);' }}">
                Rejected
            </a>
            <a href="{{ route('user.verification.admin') }}" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ !request('status') ? 'text-white' : '' }}" style="{{ !request('status') ? 'background: #1b84ff;' : 'background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-glass);' }}">
                All
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-4 p-4 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); color: #34d399; border: 1px solid rgba(16,185,129,0.2);">
        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    </div>
    @endif

    @if($requests->count() > 0)
    <div class="space-y-3">
        @foreach($requests as $req)
        <a href="{{ route('user.verification.admin.review', $req) }}" class="card-premium p-5 block transition-all hover:-translate-y-0.5 hover:shadow-lg">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-4">
                    @if($req->logo_path)
                    <img src="{{ asset('storage/' . $req->logo_path) }}" alt="" class="w-12 h-12 rounded-xl object-cover" style="border: 1px solid var(--border-glass);">
                    @else
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background: rgba(27,132,255,0.1);"><i class="fas fa-building text-blue-400"></i></div>
                    @endif
                    <div>
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-sm font-bold" style="color: var(--text-primary);">{{ $req->display_name }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $req->category === 'artist_creator' ? 'bg-blue-500/10 text-blue-400' : 'bg-blue-500/10 text-blue-400' }}">
                                {{ $req->category === 'artist_creator' ? 'Artist / Creator' : 'Business / Product' }}
                            </span>
                        </div>
                        <p class="text-[11px]" style="color: var(--text-dimmed);">
                            by {{ $req->user->name ?? $req->user->email }} &middot; {{ $req->business_name }} &middot; /{{ $req->link->alias ?? '?' }}
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
        <div class="w-16 h-16 rounded-2xl mx-auto mb-4 flex items-center justify-center" style="background: rgba(27,132,255,0.1);">
            <i class="fas fa-inbox text-blue-400 text-2xl"></i>
        </div>
        <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">No verification requests</h3>
        <p class="text-xs" style="color: var(--text-dimmed);">{{ request('status') ? 'No ' . request('status') . ' requests found.' : 'No requests submitted yet.' }}</p>
    </div>
    @endif
</div>
@endsection
