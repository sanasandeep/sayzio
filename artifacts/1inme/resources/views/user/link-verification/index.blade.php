{{--
    Link Verification — the user-facing list of per-page verification requests.

    Lives at resources/views/user/link-verification/ rather than
    resources/views/user/verification/ on purpose. Profile Verification
    (ProfileVerificationController, Task #5439) renders
    `user.verification.index` with compact('user', 'requests', 'tickTypes');
    this feature's VerificationController rendered the SAME view name with
    compact('requests', 'biolinks'). Whichever file existed answered to both,
    so this page died with "Undefined variable $user" while Profile
    Verification worked. Separate names, separate pages, no collision.

    Data contract (VerificationController::index):
      $requests  VerificationRequest rows for this user, newest first,
                 each with ->link eager-loaded.
      $biolinks  the user's Link rows in Link::BIOLINK_FAMILY.
--}}
@extends('user.layouts.settings')
@section('title', 'Link Verification')
@section('settings-content')
<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Link Verification</h1>
        <p class="text-sm mt-1" style="color: var(--text-muted);">
            Get a verified name and logo on one of your Link in Bio pages. Our team reviews every request.
        </p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg text-emerald-400 text-sm" style="border:1px solid rgba(16,185,129,0.2); background: rgba(16,185,129,0.06);">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg text-red-400 text-sm" style="border:1px solid rgba(239,68,68,0.2); background: rgba(239,68,68,0.06);">{{ session('error') }}</div>
    @endif

    @php
        // A page can only carry one pending request at a time (enforced in
        // VerificationController::store), so the request rows are the source
        // of truth for which pages are already spoken for.
        $pendingLinkIds  = $requests->where('status', 'pending')->pluck('link_id')->all();
        $approvedLinkIds = $requests->where('status', 'approved')->pluck('link_id')->all();
        $statusMeta = [
            'pending'  => ['label' => 'In review', 'icon' => 'fa-clock',        'color' => '#f59e0b', 'tint' => 'rgba(245,158,11,0.10)'],
            'approved' => ['label' => 'Verified',  'icon' => 'fa-circle-check', 'color' => '#10b981', 'tint' => 'rgba(16,185,129,0.10)'],
            'rejected' => ['label' => 'Declined',  'icon' => 'fa-circle-xmark', 'color' => '#ef4444', 'tint' => 'rgba(239,68,68,0.10)'],
        ];
    @endphp

    {{-- Your pages: one row per biolink, with whatever verification state it
         currently carries and the one action that makes sense for it. --}}
    <div class="rounded-2xl border p-5 mb-6" style="background: var(--bg-card); border-color: var(--border-soft);">
        <h2 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Your Link in Bio pages</h2>
        <p class="text-xs mb-4" style="color: var(--text-muted);">Verification applies to one page at a time, not to your whole account.</p>

        @if($biolinks->isEmpty())
            <div class="text-sm rounded-lg p-3" style="background: var(--bg-subtle); color: var(--text-muted); border:1px solid var(--border-soft);">
                <i class="fas fa-circle-info mr-1.5"></i>
                You don't have a Link in Bio page yet. Create one first, then come back to request verification for it.
            </div>
        @else
            <div class="flex flex-col gap-2">
                @foreach($biolinks as $link)
                    @php
                        $isApproved = $link->is_verified || in_array($link->id, $approvedLinkIds, true);
                        $isPending  = ! $isApproved && in_array($link->id, $pendingLinkIds, true);
                    @endphp
                    <div class="flex flex-wrap items-center gap-3 rounded-xl p-3" style="background: var(--bg-subtle); border:1px solid var(--border-soft);">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <span class="text-sm font-semibold truncate" style="color: var(--text-primary);">{{ $link->title ?: $link->alias }}</span>
                                @if($isApproved)
                                    <i class="fas fa-circle-check text-xs" style="color:#10b981;" title="Verified"></i>
                                @endif
                            </div>
                            <p class="text-xs mt-0.5 truncate" style="color: var(--text-faint);">/{{ $link->alias }}</p>
                        </div>

                        @if($isApproved)
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full" style="color:#10b981; background: rgba(16,185,129,0.10);">Verified</span>
                        @elseif($isPending)
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full" style="color:#f59e0b; background: rgba(245,158,11,0.10);">In review</span>
                        @else
                            <a href="{{ route('user.verification.request', ['link_id' => $link->id]) }}"
                               class="text-xs font-semibold px-3 py-1.5 rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition">
                                Request verification
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Request history. Rejected requests keep their admin note so the user
         knows what to fix before applying again. --}}
    <div class="rounded-2xl border p-5" style="background: var(--bg-card); border-color: var(--border-soft);">
        <h2 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Your requests</h2>
        <p class="text-xs mb-4" style="color: var(--text-muted);">Every request you've submitted, newest first.</p>

        @if($requests->isEmpty())
            <div class="text-sm rounded-lg p-3" style="background: var(--bg-subtle); color: var(--text-muted); border:1px solid var(--border-soft);">
                <i class="fas fa-circle-info mr-1.5"></i>
                Nothing submitted yet. Pick a page above to send your first request.
            </div>
        @else
            <div class="flex flex-col gap-3">
                @foreach($requests as $req)
                    @php $meta = $statusMeta[$req->status] ?? $statusMeta['pending']; @endphp
                    <div class="rounded-xl p-4" style="background: var(--bg-subtle); border:1px solid var(--border-soft);">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold truncate" style="color: var(--text-primary);">{{ $req->display_name }}</p>
                                <p class="text-xs mt-0.5" style="color: var(--text-muted);">
                                    {{ $req->link?->title ?: ($req->link?->alias ? '/' . $req->link->alias : 'Page removed') }}
                                    <span style="color: var(--text-faint);">&middot;</span>
                                    {{ $req->category === 'artist_creator' ? 'Artist or creator' : 'Business or product' }}
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full whitespace-nowrap"
                                  style="color: {{ $meta['color'] }}; background: {{ $meta['tint'] }};">
                                <i class="fas {{ $meta['icon'] }} text-[10px]"></i> {{ $meta['label'] }}
                            </span>
                        </div>

                        <p class="text-xs mt-2" style="color: var(--text-faint);">
                            Submitted {{ $req->created_at?->format('M j, Y') }}
                            @if($req->reviewed_at)
                                <span style="color: var(--text-faint);">&middot;</span> Reviewed {{ $req->reviewed_at->format('M j, Y') }}
                            @endif
                        </p>

                        @if($req->status === 'rejected' && $req->admin_notes)
                            <div class="mt-3 rounded-lg p-3 text-xs" style="background: rgba(239,68,68,0.06); border:1px solid rgba(239,68,68,0.2); color: var(--text-primary);">
                                <span class="font-semibold">Why it was declined:</span> {{ $req->admin_notes }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
