@extends('user.layouts.app')
@section('title', 'Verification')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Verification</h1>
            <p class="text-sm mt-1" style="color: var(--text-muted);">Get a verified badge on your Link in Bio pages</p>
        </div>
        <a href="{{ route('user.verification.request') }}" class="px-4 py-2 rounded-xl text-sm font-medium text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, #7c3aed, #8b5cf6);">
            <i class="fas fa-plus mr-1.5"></i>Request Verification
        </a>
    </div>

    @if(session('success'))
    <div class="mb-4 p-4 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); color: #34d399; border: 1px solid rgba(16,185,129,0.2);">
        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-4 p-4 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2);">
        <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
    </div>
    @endif

    <div class="card-premium p-6 mb-6">
        <div class="flex items-start gap-4 mb-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0" style="background: rgba(59,130,246,0.1);">
                <i class="fas fa-shield-alt text-violet-400 text-lg"></i>
            </div>
            <div>
                <h3 class="font-bold text-sm mb-1" style="color: var(--text-primary);">Why get verified?</h3>
                <p class="text-xs leading-relaxed" style="color: var(--text-muted);">A verified badge confirms your identity and adds a blue checkmark to your Link in Bio page. Verified pages get special blocks (heading & avatar with blue tick) and the page name becomes locked to prevent impersonation.</p>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="p-3 rounded-xl" style="background: rgba(59,130,246,0.05); border: 1px solid rgba(59,130,246,0.1);">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas fa-check-circle text-violet-400 text-xs"></i>
                    <span class="text-xs font-semibold" style="color: var(--text-primary);">Build Trust</span>
                </div>
                <p class="text-[10px]" style="color: var(--text-dimmed);">Show visitors your page is authentic</p>
            </div>
            <div class="p-3 rounded-xl" style="background: rgba(124,58,237,0.05); border: 1px solid rgba(124,58,237,0.1);">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas fa-lock text-violet-400 text-xs"></i>
                    <span class="text-xs font-semibold" style="color: var(--text-primary);">Protected Identity</span>
                </div>
                <p class="text-[10px]" style="color: var(--text-dimmed);">Page name locked to prevent impersonation</p>
            </div>
            <div class="p-3 rounded-xl" style="background: rgba(16,185,129,0.05); border: 1px solid rgba(16,185,129,0.1);">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas fa-star text-emerald-400 text-xs"></i>
                    <span class="text-xs font-semibold" style="color: var(--text-primary);">Premium Blocks</span>
                </div>
                <p class="text-[10px]" style="color: var(--text-dimmed);">Get verified heading & avatar blocks</p>
            </div>
        </div>
    </div>

    @if($requests->count() > 0)
    <div class="space-y-3">
        @foreach($requests as $req)
        <div class="card-premium p-5">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-4">
                    @if($req->logo_path)
                    <img src="{{ asset('storage/' . $req->logo_path) }}" alt="" class="w-12 h-12 rounded-xl object-cover" style="border: 1px solid var(--border-glass);">
                    @else
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background: rgba(124,58,237,0.1);"><i class="fas fa-building text-violet-400"></i></div>
                    @endif
                    <div>
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-sm font-bold" style="color: var(--text-primary);">{{ $req->display_name }}</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $req->category === 'artist_creator' ? 'bg-violet-500/10 text-violet-400' : 'bg-violet-500/10 text-violet-400' }}">
                                {{ $req->category === 'artist_creator' ? 'Artist / Creator' : 'Business / Product' }}
                            </span>
                        </div>
                        <p class="text-[11px]" style="color: var(--text-dimmed);">
                            {{ $req->business_name }} &middot; Link in Bio: {{ $req->link->title ?? $req->link->alias }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if($req->status === 'pending')
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400"><i class="fas fa-clock mr-1"></i>Pending Review</span>
                    @elseif($req->status === 'approved')
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400"><i class="fas fa-check-circle mr-1"></i>Approved</span>
                    @else
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-red-500/10 text-red-400"><i class="fas fa-times-circle mr-1"></i>Rejected</span>
                    @endif
                    <span class="text-[10px]" style="color: var(--text-dimmed);">{{ $req->created_at->diffForHumans() }}</span>
                </div>
            </div>
            @if($req->admin_notes)
            <div class="mt-3 p-3 rounded-lg text-xs" style="background: rgba(0,0,0,0.15); color: var(--text-muted);">
                <span class="font-semibold">Admin notes:</span> {{ $req->admin_notes }}
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @else
    <div class="card-premium p-10 text-center">
        <div class="w-16 h-16 rounded-2xl mx-auto mb-4 flex items-center justify-center" style="background: rgba(124,58,237,0.1);">
            <i class="fas fa-check-circle text-violet-400 text-2xl"></i>
        </div>
        <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">No verification requests yet</h3>
        <p class="text-xs mb-4" style="color: var(--text-dimmed);">Submit a verification request to get the blue badge on your Link in Bio page.</p>
        <a href="{{ route('user.verification.request') }}" class="inline-flex items-center px-4 py-2 rounded-xl text-sm font-medium text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, #7c3aed, #8b5cf6);">
            <i class="fas fa-plus mr-1.5"></i>Request Verification
        </a>
    </div>
    @endif
</div>
@endsection
