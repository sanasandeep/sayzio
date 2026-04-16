@extends('user.layouts.app')
@section('title', 'Review Verification')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('user.verification.admin') }}" class="text-xs font-medium transition-colors hover:text-violet-400" style="color: var(--text-muted);">
            <i class="fas fa-arrow-left mr-1"></i>Back to Requests
        </a>
        <h1 class="text-2xl font-bold mt-2" style="color: var(--text-primary);">Review Verification Request</h1>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            <div class="card-premium p-6">
                <div class="flex items-center gap-4 mb-5">
                    @if($verificationRequest->logo_path)
                    <img src="{{ asset('storage/' . $verificationRequest->logo_path) }}" alt="" class="w-16 h-16 rounded-xl object-cover" style="border: 1px solid var(--border-glass);">
                    @else
                    <div class="w-16 h-16 rounded-xl flex items-center justify-center" style="background: rgba(124,58,237,0.1);"><i class="fas fa-building text-violet-400 text-xl"></i></div>
                    @endif
                    <div>
                        <h2 class="text-lg font-bold" style="color: var(--text-primary);">{{ $verificationRequest->display_name }}</h2>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $verificationRequest->category === 'artist_creator' ? 'bg-violet-500/10 text-violet-400' : 'bg-violet-500/10 text-violet-400' }}">
                                {{ $verificationRequest->category === 'artist_creator' ? 'Artist / Creator' : 'Business / Product' }}
                            </span>
                            @if($verificationRequest->status === 'pending')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-400">Pending</span>
                            @elseif($verificationRequest->status === 'approved')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400">Approved</span>
                            @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-500/10 text-red-400">Rejected</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-semibold uppercase tracking-wider" style="color: var(--text-dimmed);">{{ $verificationRequest->category === 'artist_creator' ? 'Artist Name' : 'Business Name' }}</label>
                            <p class="text-sm font-medium mt-0.5" style="color: var(--text-primary);">{{ $verificationRequest->business_name }}</p>
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold uppercase tracking-wider" style="color: var(--text-dimmed);">Display Name</label>
                            <p class="text-sm font-medium mt-0.5" style="color: var(--text-primary);">{{ $verificationRequest->display_name }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-semibold uppercase tracking-wider" style="color: var(--text-dimmed);">Requested By</label>
                            <p class="text-sm font-medium mt-0.5" style="color: var(--text-primary);">{{ $verificationRequest->user->name ?? 'N/A' }}</p>
                            <p class="text-[11px]" style="color: var(--text-muted);">{{ $verificationRequest->user->email }}</p>
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold uppercase tracking-wider" style="color: var(--text-dimmed);">Biolink Page</label>
                            <p class="text-sm font-medium mt-0.5" style="color: var(--text-primary);">{{ $verificationRequest->link->title ?? $verificationRequest->link->alias }}</p>
                            <a href="/{{ $verificationRequest->link->alias }}" target="_blank" class="text-[11px] text-violet-400 hover:underline">/{{ $verificationRequest->link->alias }} <i class="fas fa-external-link-alt text-[9px]"></i></a>
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] font-semibold uppercase tracking-wider" style="color: var(--text-dimmed);">Purpose</label>
                        <p class="text-sm mt-1 leading-relaxed" style="color: var(--text-secondary);">{{ $verificationRequest->purpose }}</p>
                    </div>
                </div>
            </div>

            @if($verificationRequest->proof_files && count($verificationRequest->proof_files) > 0)
            <div class="card-premium p-6">
                <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);"><i class="fas fa-file-alt text-amber-400 mr-2"></i>Proof Documents ({{ count($verificationRequest->proof_files) }})</h3>
                <div class="grid grid-cols-2 gap-3">
                    @foreach($verificationRequest->proof_files as $file)
                    <a href="{{ asset('storage/' . $file) }}" target="_blank" class="flex items-center gap-3 p-3 rounded-xl transition-all hover:bg-white/[0.03]" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                        @if(str_ends_with($file, '.pdf'))
                        <i class="fas fa-file-pdf text-red-400"></i>
                        @else
                        <i class="fas fa-file-image text-violet-400"></i>
                        @endif
                        <span class="text-xs font-medium truncate" style="color: var(--text-primary);">{{ basename($file) }}</span>
                        <i class="fas fa-external-link-alt text-[9px] ml-auto" style="color: var(--text-dimmed);"></i>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <div class="space-y-5">
            <div class="card-premium p-5">
                <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Status</h3>
                <p class="text-xs mb-4" style="color: var(--text-dimmed);">Submitted {{ $verificationRequest->created_at->format('M d, Y') }}</p>

                @if($verificationRequest->isPending())
                <form action="{{ route('user.verification.admin.approve', $verificationRequest) }}" method="POST" class="mb-3">
                    @csrf
                    <textarea name="admin_notes" rows="3" class="theme-input w-full text-xs mb-3" placeholder="Admin notes (optional)..."></textarea>
                    <button type="submit" class="w-full px-4 py-2.5 rounded-xl text-sm font-medium text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, #10b981, #34d399);">
                        <i class="fas fa-check mr-1.5"></i>Approve
                    </button>
                </form>
                <form action="{{ route('user.verification.admin.reject', $verificationRequest) }}" method="POST">
                    @csrf
                    <textarea name="admin_notes" rows="3" class="theme-input w-full text-xs mb-3" placeholder="Rejection reason (required)..." required></textarea>
                    <button type="submit" class="w-full px-4 py-2.5 rounded-xl text-sm font-medium text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, #ef4444, #f87171);">
                        <i class="fas fa-times mr-1.5"></i>Reject
                    </button>
                </form>
                @else
                <div class="p-3 rounded-xl text-xs" style="background: rgba(0,0,0,0.15); color: var(--text-muted);">
                    <p class="font-semibold mb-1">Decision: {{ ucfirst($verificationRequest->status) }}</p>
                    @if($verificationRequest->reviewed_at)
                    <p class="text-[10px] mb-1" style="color: var(--text-dimmed);">Reviewed {{ $verificationRequest->reviewed_at->format('M d, Y H:i') }}</p>
                    @endif
                    @if($verificationRequest->admin_notes)
                    <p class="mt-2">{{ $verificationRequest->admin_notes }}</p>
                    @endif
                </div>
                @endif
            </div>

            <div class="card-premium p-5">
                <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Quick Info</h3>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span style="color: var(--text-dimmed);">User ID</span>
                        <span style="color: var(--text-primary);">#{{ $verificationRequest->user_id }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-dimmed);">Link ID</span>
                        <span style="color: var(--text-primary);">#{{ $verificationRequest->link_id }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-dimmed);">Page Verified</span>
                        <span style="color: var(--text-primary);">{{ $verificationRequest->link->is_verified ? 'Yes' : 'No' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
