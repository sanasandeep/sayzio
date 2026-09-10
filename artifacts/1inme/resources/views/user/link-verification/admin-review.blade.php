{{--
    Link Verification — admin review of a single request.

    See link-verification/index.blade.php for why this feature's views are not
    under resources/views/user/verification/.

    Data contract (VerificationController::adminReview):
      $verificationRequest  one VerificationRequest with ->user and ->link
                            eager-loaded.

    Approving writes through to the page itself (is_verified, verified_name,
    verified_logo, plus the verified_heading and verified_avatar blocks), so
    the reviewer is confirming what the page will actually show. Rejecting
    requires a note, which the user reads back on their own page.
--}}
@extends('user.layouts.app')
@section('title', 'Review Link Verification Request')

@section('content')
@php
    $req = $verificationRequest;
    $stColor = ['pending' => '#f59e0b', 'approved' => '#10b981', 'rejected' => '#ef4444'][$req->status] ?? '#64748b';
@endphp
<div class="max-w-3xl mx-auto">
    <a href="{{ route('user.verification.admin') }}" class="text-xs font-semibold inline-flex items-center gap-1.5 mb-4" style="color: var(--text-muted);">
        <i class="fas fa-arrow-left text-[10px]"></i> Back to the queue
    </a>

    <div class="flex items-start justify-between mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">{{ $req->display_name }}</h1>
            <p class="text-sm mt-1" style="color: var(--text-muted);">
                Requested {{ $req->created_at?->format('M j, Y') }}
                @if($req->reviewed_at)
                    <span style="color: var(--text-faint);">&middot;</span> Reviewed {{ $req->reviewed_at->format('M j, Y') }}
                @endif
            </p>
        </div>
        <span class="inline-block px-3 py-1 rounded-full text-xs font-bold"
              style="background: {{ $stColor }}1a; color: {{ $stColor }};">{{ ucfirst($req->status) }}</span>
    </div>

    @if(session('error'))
    <div class="mb-4 p-4 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2);">
        <i class="fas fa-circle-exclamation mr-2"></i>{{ session('error') }}
    </div>
    @endif
    @if($errors->any())
    <div class="mb-4 p-4 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2);">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="card-premium p-5 mb-4">
        <h2 class="text-xs font-bold uppercase tracking-wider mb-4" style="color: var(--text-muted);">What was submitted</h2>

        <div class="flex items-start gap-4 mb-5 flex-wrap">
            @if($req->logo_path)
            <img src="{{ \App\Support\PublicStorageUrl::resolve($req->logo_path) }}" alt="Submitted logo"
                 class="w-16 h-16 rounded-2xl object-cover flex-shrink-0" style="border: 1px solid var(--border-glass);">
            @else
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center flex-shrink-0" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                <i class="fas fa-image" style="color: var(--text-faint);"></i>
            </div>
            @endif
            <div class="min-w-0">
                <p class="text-sm font-bold" style="color: var(--text-primary);">{{ $req->display_name }}</p>
                <p class="text-xs mt-0.5" style="color: var(--text-muted);">This is the name and avatar the page will carry once approved.</p>
            </div>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Legal or business name</dt>
                <dd class="text-sm" style="color: var(--text-primary);">{{ $req->business_name }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Category</dt>
                <dd class="text-sm" style="color: var(--text-primary);">{{ $req->category === 'artist_creator' ? 'Artist or creator' : 'Business or product' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Page</dt>
                <dd class="text-sm" style="color: var(--text-primary);">
                    @if($req->link?->alias)
                        <a href="{{ url('/' . $req->link->alias) }}" target="_blank" rel="noopener" class="hover:underline">
                            /{{ $req->link->alias }} <i class="fas fa-arrow-up-right-from-square text-[10px] ml-0.5"></i>
                        </a>
                    @else
                        <span style="color: var(--text-faint);">Page removed</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-[11px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Account</dt>
                <dd class="text-sm" style="color: var(--text-primary);">{{ $req->user?->handle ? '@' . $req->user->handle : ($req->user?->email ?? 'Unknown user') }}</dd>
            </div>
        </dl>

        <div class="mt-4">
            <dt class="text-[11px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Purpose</dt>
            <dd class="text-sm whitespace-pre-line" style="color: var(--text-primary);">{{ $req->purpose }}</dd>
        </div>

        @if(!empty($req->proof_files))
        <div class="mt-4">
            <dt class="text-[11px] font-bold uppercase tracking-wider mb-2" style="color: var(--text-faint);">Supporting documents</dt>
            <dd class="flex flex-wrap gap-2">
                @foreach($req->proof_files as $i => $path)
                <a href="{{ \App\Support\PublicStorageUrl::resolve($path) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium"
                   style="background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-glass);">
                    <i class="fas fa-paperclip text-[10px]"></i> Document {{ $i + 1 }}
                </a>
                @endforeach
            </dd>
        </div>
        @endif
    </div>

    @if($req->status === 'pending')
    <div class="card-premium p-5">
        <h2 class="text-xs font-bold uppercase tracking-wider mb-4" style="color: var(--text-muted);">Decision</h2>

        <form method="POST" action="{{ route('user.verification.admin.approve', $req) }}" class="mb-5">
            @csrf
            <label for="lv-approve-notes" class="block text-[11px] font-bold uppercase tracking-wider mb-1.5" style="color: var(--text-faint);">Note (optional)</label>
            <textarea id="lv-approve-notes" name="admin_notes" rows="2" maxlength="2000"
                      class="w-full px-3 py-2.5 rounded-lg text-sm mb-3"
                      style="background: var(--bg-glass); border: 1px solid var(--border-glass); color: var(--text-primary);">{{ old('admin_notes') }}</textarea>
            <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-bold text-white transition" style="background: #10b981;">
                <i class="fas fa-circle-check mr-1.5"></i>Approve and verify the page
            </button>
        </form>

        <div class="pt-5" style="border-top: 1px solid var(--border-glass);">
            <form method="POST" action="{{ route('user.verification.admin.reject', $req) }}">
                @csrf
                <label for="lv-reject-notes" class="block text-[11px] font-bold uppercase tracking-wider mb-1.5" style="color: var(--text-faint);">Reason for declining (required)</label>
                <textarea id="lv-reject-notes" name="admin_notes" rows="2" maxlength="2000" required
                          placeholder="The requester reads this, so say what would make a new request succeed."
                          class="w-full px-3 py-2.5 rounded-lg text-sm mb-3"
                          style="background: var(--bg-glass); border: 1px solid var(--border-glass); color: var(--text-primary);"></textarea>
                <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-bold text-white transition" style="background: #ef4444;">
                    <i class="fas fa-circle-xmark mr-1.5"></i>Decline
                </button>
            </form>
        </div>
    </div>
    @elseif($req->admin_notes)
    <div class="card-premium p-5">
        <h2 class="text-xs font-bold uppercase tracking-wider mb-2" style="color: var(--text-muted);">Reviewer note</h2>
        <p class="text-sm whitespace-pre-line" style="color: var(--text-primary);">{{ $req->admin_notes }}</p>
    </div>
    @endif
</div>
@endsection
