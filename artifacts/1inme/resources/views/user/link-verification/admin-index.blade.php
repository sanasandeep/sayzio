{{--
    Link Verification — admin queue.

    Sibling of link-verification/index.blade.php; the header there explains why
    this feature's views moved out of resources/views/user/verification/, which
    Profile Verification (Task #5439) now owns outright. This queue reviews
    VerificationRequest rows (per Link in Bio page); the profile-level queue at
    user.profile-verification.admin.index is a different feature reviewing
    different rows.

    Data contract (VerificationController::adminIndex):
      $requests  LengthAwarePaginator of VerificationRequest, ->user and ->link
                 eager-loaded, newest first, optionally filtered by ?status=.
--}}
@extends('user.layouts.app')
@section('title', 'Link Verification Queue')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Link Verification</h1>
            <p class="text-sm mt-1" style="color: var(--text-muted);">Review verification requests for individual Link in Bio pages</p>
        </div>
        <a href="{{ route('user.profile-verification.admin.index') }}" class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all" style="background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-glass);">
            <i class="fas fa-shield-check mr-1"></i>Profile Verification queue
        </a>
    </div>

    @if(session('success'))
    <div class="mb-4 p-4 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); color: #34d399; border: 1px solid rgba(16,185,129,0.2);">
        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-4 p-4 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2);">
        <i class="fas fa-circle-exclamation mr-2"></i>{{ session('error') }}
    </div>
    @endif

    {{-- Status filter. adminIndex() reads ?status= and applies it directly, so
         these links are the whole filter surface. --}}
    <div class="flex items-center gap-2 mb-5 flex-wrap">
        @foreach(['pending' => '#f59e0b', 'approved' => '#10b981', 'rejected' => '#ef4444'] as $st => $stColor)
        <a href="{{ route('user.verification.admin', ['status' => $st]) }}"
           class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ request('status') === $st ? 'text-white' : '' }}"
           style="{{ request('status') === $st ? 'background: '.$stColor.';' : 'background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-glass);' }}">
            {{ ucfirst($st) }}
        </a>
        @endforeach
        <a href="{{ route('user.verification.admin') }}"
           class="px-3 py-1.5 rounded-lg text-xs font-medium transition-all {{ !request('status') ? 'text-white' : '' }}"
           style="{{ !request('status') ? 'background: #64748b;' : 'background: var(--bg-glass); color: var(--text-muted); border: 1px solid var(--border-glass);' }}">
            All
        </a>
    </div>

    @if($requests->count() > 0)
    <div class="grid grid-cols-1 gap-3">
        @foreach($requests as $req)
        @php
            $stColor = ['pending' => '#f59e0b', 'approved' => '#10b981', 'rejected' => '#ef4444'][$req->status] ?? '#64748b';
        @endphp
        <a href="{{ route('user.verification.admin.review', $req) }}" class="card-premium p-5 block transition-all hover:-translate-y-0.5 hover:shadow-lg">
            <div class="flex items-start gap-4 flex-wrap">
                @if($req->logo_path)
                <img src="{{ \App\Support\PublicStorageUrl::resolve($req->logo_path) }}" alt=""
                     class="w-12 h-12 rounded-xl object-cover flex-shrink-0" style="border: 1px solid var(--border-glass);">
                @else
                <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                    <i class="fas fa-image text-sm" style="color: var(--text-faint);"></i>
                </div>
                @endif

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold truncate" style="color: var(--text-primary);">{{ $req->display_name }}</p>
                    <p class="text-xs mt-0.5 truncate" style="color: var(--text-muted);">
                        {{ $req->business_name }}
                        <span style="color: var(--text-faint);">&middot;</span>
                        {{ $req->category === 'artist_creator' ? 'Artist or creator' : 'Business or product' }}
                    </p>
                    <p class="text-xs mt-1 truncate" style="color: var(--text-faint);">
                        <i class="fas fa-link text-[10px] mr-1"></i>{{ $req->link?->alias ? '/' . $req->link->alias : 'Page removed' }}
                        <span class="mx-1">&middot;</span>
                        <i class="fas fa-user text-[10px] mr-1"></i>{{ $req->user?->handle ? '@' . $req->user->handle : ($req->user?->email ?? 'Unknown user') }}
                    </p>
                </div>

                <div class="text-right flex-shrink-0">
                    <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold"
                          style="background: {{ $stColor }}1a; color: {{ $stColor }};">{{ ucfirst($req->status) }}</span>
                    <p class="text-[11px] mt-1.5" style="color: var(--text-faint);">{{ $req->created_at?->format('M j, Y') }}</p>
                </div>
            </div>
        </a>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $requests->withQueryString()->links() }}
    </div>
    @else
    <div class="card-premium p-10 text-center">
        <i class="fas fa-inbox text-2xl mb-3" style="color: var(--text-faint);"></i>
        <p class="text-sm font-medium" style="color: var(--text-primary);">Nothing in this queue</p>
        <p class="text-xs mt-1" style="color: var(--text-muted);">
            @if(request('status'))
                No {{ request('status') }} link verification requests.
            @else
                No link verification requests have been submitted yet.
            @endif
        </p>
    </div>
    @endif
</div>
@endsection
