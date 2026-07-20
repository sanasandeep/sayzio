@extends('user.layouts.app')
@section('title', 'Review Verification Request')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('user.profile-verification.admin.index') }}" class="text-xs font-medium transition-colors hover:text-blue-400" style="color: var(--text-muted);">
            <i class="fas fa-arrow-left mr-1"></i>Back to Queue
        </a>
        <h1 class="text-2xl font-bold mt-2" style="color: var(--text-primary);">
            Review {{ $req->isReverification() ? 'Re-verification' : 'Verification' }} Request
        </h1>
    </div>

    @if(session('success'))
    <div class="mb-4 p-4 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); color: #34d399; border: 1px solid rgba(16,185,129,0.2);">
        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    </div>
    @endif

    @php
        $resolveFile = function ($p) {
            if (!$p) return null;
            if (str_starts_with($p, 'http://') || str_starts_with($p, 'https://') || str_starts_with($p, '/')) return $p;
            return asset('storage/' . $p);
        };
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- Left: details --}}
        <div class="lg:col-span-2 space-y-5">
            {{-- Header card --}}
            <div class="card-premium p-6">
                <div class="flex items-center gap-4 mb-5">
                    @if($req->logo_path)
                    <img src="{{ $resolveFile($req->logo_path) }}" alt="" class="w-16 h-16 rounded-xl object-cover" style="border: 1px solid var(--border-glass);">
                    @elseif($req->user?->avatar)
                    <img src="{{ \App\Support\PublicStorageUrl::resolve($req->user->avatar) }}" alt="" class="w-16 h-16 rounded-xl object-cover" style="border: 1px solid var(--border-glass);">
                    @else
                    <div class="w-16 h-16 rounded-xl flex items-center justify-center" style="background: rgba(61,107,255,0.1);"><i class="fas fa-user text-blue-400 text-xl"></i></div>
                    @endif
                    <div>
                        <h2 class="text-lg font-bold" style="color: var(--text-primary);">{{ $req->official_name }}</h2>
                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                            @if($req->tickType)
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold" style="background: {{ $req->tickType->color }}20; color: {{ $req->tickType->color }};">
                                <i class="fas {{ $req->tickType->icon }} mr-0.5"></i>{{ $req->tickType->name }}
                            </span>
                            @endif
                            @if($req->isReverification())
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-500/10 text-blue-400">Re-verification</span>
                            @endif
                            @if($req->status === 'pending')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-500/10 text-amber-400">Pending</span>
                            @elseif($req->status === 'approved')
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400">Approved</span>
                            @else
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-500/10 text-red-400">Rejected</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                    <div>
                        <label class="text-[10px] font-semibold uppercase tracking-wider" style="color: var(--text-dimmed);">Requested By</label>
                        <p class="font-medium mt-0.5" style="color: var(--text-primary);">{{ $req->user?->name ?? 'N/A' }}</p>
                        <p class="text-[11px]" style="color: var(--text-muted);">{{ $req->user?->email }} · @{{ $req->user?->handle }}</p>
                    </div>
                    <div>
                        <label class="text-[10px] font-semibold uppercase tracking-wider" style="color: var(--text-dimmed);">Current Account Status</label>
                        @php $st = $req->user?->profile_verification_status ?? 'unverified'; @endphp
                        <p class="font-medium mt-0.5" style="color: var(--text-primary);">{{ ucwords(str_replace('_', ' ', $st)) }}</p>
                        @if($req->user?->verificationTickType)
                        <p class="text-[11px] mt-0.5" style="color: var(--text-muted);">{!! $req->user->verificationTickType->tickHtml('text-xs') !!} {{ $req->user->verificationTickType->name }}</p>
                        @endif
                    </div>
                </div>

                @if($req->isReverification())
                <div class="p-4 rounded-xl mb-4" style="background: rgba(156,106,254,0.07); border: 1px solid rgba(156,106,254,0.2);">
                    <p class="text-xs font-bold mb-2" style="color: #9c6afe;"><i class="fas fa-sync mr-1"></i>Name / Avatar Change</p>
                    <div class="grid grid-cols-2 gap-3 text-xs">
                        @if($req->prev_verified_name)
                        <div>
                            <span style="color: var(--text-dimmed);">Previous name</span>
                            <p class="font-medium mt-0.5" style="color: var(--text-primary);">{{ $req->prev_verified_name }}</p>
                        </div>
                        @endif
                        @if($req->new_name)
                        <div>
                            <span style="color: var(--text-dimmed);">New name</span>
                            <p class="font-medium mt-0.5 text-amber-400">{{ $req->new_name }}</p>
                        </div>
                        @endif
                        @if($req->new_avatar)
                        <div class="col-span-2">
                            <span style="color: var(--text-dimmed);">New avatar</span>
                            <img src="{{ $resolveFile($req->new_avatar) }}" alt="New avatar" class="w-12 h-12 rounded-xl object-cover mt-1" style="border: 1px solid var(--border-glass);">
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                <div>
                    <label class="text-[10px] font-semibold uppercase tracking-wider" style="color: var(--text-dimmed);">Purpose / Statement</label>
                    <p class="text-sm mt-1 leading-relaxed" style="color: var(--text-secondary);">{{ $req->purpose }}</p>
                </div>
            </div>

            {{-- Proof documents --}}
            @if($req->proof_files && count($req->proof_files) > 0)
            <div class="card-premium p-6">
                <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);"><i class="fas fa-file-alt text-amber-400 mr-2"></i>Proof Documents ({{ count($req->proof_files) }})</h3>
                <div class="grid grid-cols-2 gap-3">
                    @foreach($req->proof_files as $file)
                    <a href="{{ $resolveFile($file) }}" target="_blank" class="flex items-center gap-3 p-3 rounded-xl transition-all hover:bg-white/[0.03]" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                        @if(str_ends_with(strtolower($file), '.pdf'))
                        <i class="fas fa-file-pdf text-red-400"></i>
                        @else
                        <i class="fas fa-file-image text-blue-400"></i>
                        @endif
                        <span class="text-xs font-medium truncate" style="color: var(--text-primary);">{{ basename($file) }}</span>
                        <i class="fas fa-external-link-alt text-[9px] ml-auto" style="color: var(--text-dimmed);"></i>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Right: review actions --}}
        <div class="space-y-5">
            <div class="card-premium p-5">
                <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Review Decision</h3>
                <p class="text-xs mb-4" style="color: var(--text-dimmed);">Submitted {{ $req->created_at->format('M d, Y') }}</p>

                @if($req->isPending())
                <form action="{{ route('user.profile-verification.admin.approve', $req) }}" method="POST" class="mb-3">
                    @csrf
                    {{-- Allow admin to override the tick type --}}
                    <label class="block text-[10px] font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-dimmed);">Assign Tick Type</label>
                    <select name="tick_type_id" class="theme-input w-full text-xs mb-3">
                        <option value="">Keep requested type ({{ $req->tickType?->name ?? 'none' }})</option>
                        @foreach(\App\Modules\User\Models\VerificationTickType::orderBy('sort_order')->get() as $tt)
                        <option value="{{ $tt->id }}" {{ $req->tick_type_id == $tt->id ? 'selected' : '' }}>{{ $tt->name }}</option>
                        @endforeach
                    </select>
                    <textarea name="admin_notes" rows="3" class="theme-input w-full text-xs mb-3" placeholder="Admin notes (optional)..."></textarea>
                    <button type="submit" class="w-full px-4 py-2.5 rounded-xl text-sm font-medium text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, #10b981, #34d399);">
                        <i class="fas fa-check mr-1.5"></i>Approve
                    </button>
                </form>
                <form action="{{ route('user.profile-verification.admin.reject', $req) }}" method="POST">
                    @csrf
                    <textarea name="admin_notes" rows="3" class="theme-input w-full text-xs mb-3" placeholder="Rejection reason (required)..." required></textarea>
                    <button type="submit" class="w-full px-4 py-2.5 rounded-xl text-sm font-medium text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, #ef4444, #f87171);">
                        <i class="fas fa-times mr-1.5"></i>Reject
                    </button>
                </form>
                @else
                <div class="p-3 rounded-xl text-xs" style="background: rgba(0,0,0,0.15); color: var(--text-muted);">
                    <p class="font-semibold mb-1">Decision: {{ ucfirst($req->status) }}</p>
                    @if($req->reviewed_at)
                    <p class="text-[10px] mb-1" style="color: var(--text-dimmed);">Reviewed {{ $req->reviewed_at->format('M d, Y H:i') }}</p>
                    @endif
                    @if($req->reviewer)
                    <p class="text-[10px] mb-1" style="color: var(--text-dimmed);">by {{ $req->reviewer->name }}</p>
                    @endif
                    @if($req->admin_notes)
                    <p class="mt-2 leading-relaxed">{{ $req->admin_notes }}</p>
                    @endif
                </div>
                @endif
            </div>

            <div class="card-premium p-5">
                <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Quick Info</h3>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span style="color: var(--text-dimmed);">User ID</span>
                        <span style="color: var(--text-primary);">#{{ $req->user_id }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-dimmed);">Request ID</span>
                        <span style="color: var(--text-primary);">#{{ $req->id }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-dimmed);">Kind</span>
                        <span style="color: var(--text-primary);">{{ ucfirst($req->kind) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span style="color: var(--text-dimmed);">Requested tick</span>
                        <span style="color: var(--text-primary);">{{ $req->tickType?->name ?? '—' }}</span>
                    </div>
                    @if($req->user?->handle)
                    <div class="flex justify-between">
                        <span style="color: var(--text-dimmed);">Profile</span>
                        <a href="/@{{ $req->user->handle }}" target="_blank" class="text-blue-400 hover:underline">/@{{ $req->user->handle }}</a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
