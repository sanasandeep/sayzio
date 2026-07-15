@extends('admin.layouts.app')
@section('title', 'Privacy Request')
@section('content')
@php
    $statusStyles = [
        'pending_verification' => 'bg-amber-500/20 text-amber-300',
        'verified'             => 'bg-blue-500/20 text-blue-300',
        'approved'             => 'bg-sky-500/20 text-sky-300',
        'processing'           => 'bg-sky-500/20 text-sky-300',
        'completed'            => 'bg-emerald-500/20 text-emerald-300',
        'rejected'             => 'bg-gray-500/20 text-gray-400',
        'failed'               => 'bg-red-500/20 text-red-300',
        'blocked'              => 'bg-red-500/20 text-red-300',
    ];
    $statusLabel = fn ($s) => ucwords(str_replace('_', ' ', $s));
    $canDecide = $pr->status === \App\Modules\Common\Models\PrivacyRequest::STATUS_VERIFIED;
    $canReject = in_array($pr->status, [
        \App\Modules\Common\Models\PrivacyRequest::STATUS_PENDING_VERIFICATION,
        \App\Modules\Common\Models\PrivacyRequest::STATUS_VERIFIED,
    ], true);
@endphp
<div class="max-w-3xl mx-auto space-y-6">
    <a href="{{ route('admin.privacy-requests.index') }}" class="text-sm text-blue-300 hover:text-blue-200">
        <i class="fas fa-arrow-left mr-1"></i> Back to queue
    </a>

    @if(session('status'))
        <div class="rounded-xl px-4 py-3 text-sm bg-emerald-500/10 border border-emerald-500/30 text-emerald-300">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl px-4 py-3 text-sm bg-red-500/10 border border-red-500/30 text-red-300">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <div class="glass rounded-2xl p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <i class="fas {{ $pr->isDeletion() ? 'fa-user-slash text-red-300' : 'fa-download text-blue-300' }}"></i>
                    {{ $pr->typeLabel() }} request
                </h2>
                <p class="text-xs text-white/50 mt-1">Request #{{ $pr->id }}</p>
            </div>
            <span class="text-[11px] px-2.5 py-1 rounded-full {{ $statusStyles[$pr->status] ?? 'bg-white/10 text-white/60' }}">
                {{ $statusLabel($pr->status) }}
            </span>
        </div>

        <dl class="mt-5 grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div><dt class="text-white/40 text-xs uppercase tracking-wider">Email</dt><dd class="text-white/90 mt-0.5">{{ $pr->email }}</dd></div>
            <div><dt class="text-white/40 text-xs uppercase tracking-wider">Linked account</dt>
                <dd class="text-white/90 mt-0.5">
                    @if($pr->user_id)
                        <a href="{{ route('admin.users.show', $pr->user_id) }}" class="text-blue-300 hover:text-blue-200">#{{ $pr->user_id }}</a>
                    @else
                        <span class="text-amber-300">No matching account</span>
                    @endif
                </dd>
            </div>
            <div><dt class="text-white/40 text-xs uppercase tracking-wider">Submitted</dt><dd class="text-white/90 mt-0.5">{{ \App\Support\PlatformTimezone::format($pr->created_at, 'M j, Y g:i a', false) }}</dd></div>
            <div><dt class="text-white/40 text-xs uppercase tracking-wider">Verified</dt><dd class="text-white/90 mt-0.5">{{ \App\Support\PlatformTimezone::format($pr->verified_at, 'M j, Y g:i a', false) ?? '—' }}</dd></div>
            @if($pr->scheduled_at)
                <div><dt class="text-white/40 text-xs uppercase tracking-wider">Scheduled deletion</dt><dd class="text-white/90 mt-0.5">{{ \App\Support\PlatformTimezone::format($pr->scheduled_at, 'M j, Y g:i a') }}</dd></div>
            @endif
            @if($pr->completed_at)
                <div><dt class="text-white/40 text-xs uppercase tracking-wider">Completed</dt><dd class="text-white/90 mt-0.5">{{ \App\Support\PlatformTimezone::format($pr->completed_at, 'M j, Y g:i a', false) }}</dd></div>
            @endif
        </dl>

        @if($pr->reason)
            <div class="mt-4">
                <div class="text-white/40 text-xs uppercase tracking-wider mb-1">Reason given</div>
                <div class="text-sm text-white/80 bg-white/5 rounded-lg p-3 whitespace-pre-line">{{ $pr->reason }}</div>
            </div>
        @endif

        @if($pr->rejection_reason)
            <div class="mt-4 rounded-lg px-4 py-3 text-sm bg-red-500/10 border border-red-500/30 text-red-200">
                <span class="font-semibold">Rejection / block reason:</span> {{ $pr->rejection_reason }}
            </div>
        @endif
        @if($pr->failure_reason)
            <div class="mt-4 rounded-lg px-4 py-3 text-sm bg-red-500/10 border border-red-500/30 text-red-200">
                <span class="font-semibold">Failure:</span> {{ $pr->failure_reason }}
            </div>
        @endif
    </div>

    {{-- ---- Decision actions ---- --}}
    @if($canDecide || $canReject)
        <div class="glass rounded-2xl p-6 space-y-4">
            <h3 class="text-sm font-semibold text-white">Review decision</h3>

            @if($canDecide)
                <form method="POST" action="{{ route('admin.privacy-requests.approve', $pr->id) }}"
                      onsubmit="return confirm('{{ $pr->isDeletion() ? 'Approve this request? The account will be permanently deleted after the grace window.' : 'Approve this request? A data archive will be generated and emailed.' }}');">
                    @csrf
                    <button class="w-full py-2.5 rounded-lg text-sm font-bold text-white transition {{ $pr->isDeletion() ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700' }}">
                        <i class="fas fa-check mr-1.5"></i>
                        @if($pr->isDeletion())
                            Approve deletion (after {{ \App\Modules\Common\Models\PrivacyRequest::DELETION_GRACE_DAYS }}-day grace)
                        @else
                            Approve & generate export
                        @endif
                    </button>
                </form>
            @endif

            @if($canReject)
                <form method="POST" action="{{ route('admin.privacy-requests.reject', $pr->id) }}" class="space-y-2"
                      x-data="{ open: false }">
                    @csrf
                    <button type="button" @click="open = !open"
                            class="w-full py-2.5 rounded-lg text-sm font-bold text-white/80 bg-white/5 hover:bg-white/10 transition">
                        <i class="fas fa-xmark mr-1.5"></i> Reject request
                    </button>
                    <div x-show="open" x-cloak class="space-y-2">
                        <textarea name="rejection_reason" rows="2" required
                                  placeholder="Reason for rejection (sent to the requester)"
                                  class="w-full px-3 py-2.5 bg-white/5 border border-white/10 rounded-lg text-sm text-white"></textarea>
                        <button class="w-full py-2 rounded-lg text-sm font-bold text-white bg-red-600 hover:bg-red-700">Confirm rejection</button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    {{-- ---- Audit trail ---- --}}
    <div class="glass rounded-2xl p-6">
        <h3 class="text-sm font-semibold text-white mb-4">Audit trail</h3>
        @php $audit = is_array($pr->audit) ? $pr->audit : []; @endphp
        @if(empty($audit))
            <div class="text-white/40 text-sm">No audit entries yet.</div>
        @else
            <ol class="space-y-3">
                @foreach(array_reverse($audit) as $entry)
                    <li class="flex gap-3 text-sm">
                        <div class="w-2 h-2 rounded-full bg-blue-400 mt-1.5 shrink-0"></div>
                        <div>
                            <div class="text-white/90 font-medium">{{ ucwords(str_replace('_', ' ', $entry['event'] ?? 'event')) }}</div>
                            @if(!empty($entry['note']))<div class="text-white/60 text-xs mt-0.5">{{ $entry['note'] }}</div>@endif
                            <div class="text-white/40 text-[11px] mt-0.5">
                                @if(!empty($entry['actor'])){{ $entry['actor'] }} ·@endif
                                @if(!empty($entry['at'])){{ \App\Support\PlatformTimezone::format(\Illuminate\Support\Carbon::parse($entry['at']), 'M j, Y g:i a', false) }}@endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</div>
@endsection
