@extends('user.layouts.settings')
@section('title', 'Verification & Badges')

@section('settings-content')
@php
    $statusMeta = [
        'unverified'             => ['bg' => 'rgba(100,116,139,0.1)', 'text' => '#94a3b8', 'label' => 'Not verified',                'icon' => 'fa-circle'],
        'pending'                => ['bg' => 'rgba(245,158,11,0.1)',  'text' => '#f59e0b', 'label' => 'Pending review',              'icon' => 'fa-clock'],
        'verified'               => ['bg' => 'rgba(16,185,129,0.1)',  'text' => '#10b981', 'label' => 'Verified',                    'icon' => 'fa-check-circle'],
        'pending_reverification' => ['bg' => 'rgba(245,158,11,0.1)',  'text' => '#f59e0b', 'label' => 'Pending re-verification',     'icon' => 'fa-sync'],
    ];
    $sm = $statusMeta[$user->profile_verification_status] ?? $statusMeta['unverified'];
@endphp

<div class="max-w-3xl">
    <div class="mb-6">
        <h2 class="text-lg font-bold" style="color: var(--text-primary);">Creator Profile Verification</h2>
        <p class="text-sm mt-1" style="color: var(--text-muted);">
            Get a colored verification tick on your creator profile, dialer, and all public pages.
        </p>
    </div>

    @if(session('success'))
    <div class="mb-4 p-4 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); color: #34d399; border: 1px solid rgba(16,185,129,0.2);">
        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    </div>
    @endif
    @if(session('info'))
    <div class="mb-4 p-4 rounded-xl text-sm font-medium" style="background: var(--c-primary-soft); color: var(--c-primary); border: 1px solid var(--c-primary-soft);">
        <i class="fas fa-info-circle mr-2"></i>{{ session('info') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-4 p-4 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2);">
        <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
    </div>
    @endif

    {{-- Current verification status card --}}
    <div class="card-premium p-6 mb-5">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                @if($user->profile_verified_avatar)
                <img src="{{ \App\Support\PublicStorageUrl::resolve($user->profile_verified_avatar) }}" alt="" class="w-14 h-14 rounded-2xl object-cover" style="border: 1px solid var(--border-glass);">
                @elseif($user->avatar)
                <img src="{{ \App\Support\PublicStorageUrl::resolve($user->avatar) }}" alt="" class="w-14 h-14 rounded-2xl object-cover" style="border: 1px solid var(--border-glass);">
                @else
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center" style="background: var(--c-primary-soft);"><i class="fas fa-user text-xl" style="color: var(--c-primary);"></i></div>
                @endif
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-base" style="color: var(--text-primary);">{{ $user->profile_verified_name ?: $user->name }}</span>
                        @if($user->isVerified() && $user->verificationTickType)
                        {!! $user->verificationTickType->tickHtml('text-base') !!}
                        @if($user->isPendingReverification())
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold" style="background: rgba(245,158,11,0.1); color: #f59e0b;">re-verification pending</span>
                        @endif
                        @endif
                    </div>
                    <p class="text-xs mt-0.5" style="color: var(--text-muted);">{{ '@' . $user->handle }}</p>
                </div>
            </div>
            <span class="px-3 py-1.5 rounded-full text-xs font-semibold" style="background: {{ $sm['bg'] }}; color: {{ $sm['text'] }};">
                <i class="fas {{ $sm['icon'] }} mr-1 text-[10px]"></i>{{ $sm['label'] }}
            </span>
        </div>

        @if($user->isVerified() && $user->verificationTickType)
        <div class="mt-4 pt-4 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs" style="border-top: 1px solid var(--border-glass);">
            <div>
                <span style="color: var(--text-dimmed);">Tick type</span>
                <p class="font-semibold mt-0.5" style="color: var(--text-primary);">
                    {!! $user->verificationTickType->tickHtml('text-xs') !!}
                    <span class="ml-1">{{ $user->verificationTickType->name }}</span>
                </p>
            </div>
            <div>
                <span style="color: var(--text-dimmed);">Verified name</span>
                <p class="font-semibold mt-0.5 flex items-center gap-1" style="color: var(--text-primary);">
                    {{ $user->profile_verified_name }}
                    <i class="fas fa-lock text-[9px]" style="color: var(--text-dimmed);" title="Locked: name and avatar are frozen. Request a change via re-verification."></i>
                </p>
            </div>
            @if($user->profile_verified_at)
            <div>
                <span style="color: var(--text-dimmed);">Verified since</span>
                <p class="font-semibold mt-0.5" style="color: var(--text-primary);">{{ $user->profile_verified_at->format('M d, Y') }}</p>
            </div>
            @endif
        </div>
        @endif
    </div>

    {{-- Tick type catalog (only for non-verified users) --}}
    @if(!$user->isVerified())
    <div class="card-premium p-6 mb-5">
        <h3 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Verification Tick Types</h3>
        <p class="text-xs mb-4" style="color: var(--text-muted);">Choose the tick type that best describes you when you apply.</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            @foreach($tickTypes as $type)
            <div class="p-3 rounded-xl text-center" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                <i class="fas {{ $type->icon }} text-xl" style="color: {{ $type->color }};"></i>
                <p class="text-xs font-semibold mt-1.5" style="color: var(--text-primary);">{{ $type->name }}</p>
            </div>
            @endforeach
            <div class="p-3 rounded-xl text-center opacity-50" style="background: var(--bg-glass); border: 1px solid var(--border-glass);" title="Assigned by admins only">
                <i class="fas fa-check-circle text-xl" style="color: #1d9bf0;"></i>
                <p class="text-xs font-semibold mt-1.5" style="color: var(--text-primary);">Official</p>
                <p class="text-[10px]" style="color: var(--text-dimmed);">Admin-assigned</p>
            </div>
        </div>
    </div>
    @endif

    {{-- CTA --}}
    @if($user->profile_verification_status === 'unverified')
    <div class="mb-5">
        <a href="{{ route('user.profile-verification.request') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, var(--color-primary-500, #3d6bff), var(--color-primary-400, #5c83ff));">
            <i class="fas fa-shield-alt"></i>Apply for Verification
        </a>
    </div>
    @elseif($user->profile_verification_status === 'pending')
    <div class="p-4 rounded-xl mb-5 text-sm" style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.2); color: #f59e0b;">
        <i class="fas fa-clock mr-2"></i>Your verification request is under review. We'll notify you when it's processed.
    </div>
    @elseif($user->profile_verification_status === 'pending_reverification')
    <div class="p-4 rounded-xl mb-5 text-sm" style="background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.2); color: #f59e0b;">
        <i class="fas fa-sync mr-2"></i>Your name/avatar change is under review. Your tick remains visible in the meantime.
    </div>
    @endif

    {{-- Follow-up message / attachments to the review team (pending only) --}}
    @php $pendingReq = $requests->firstWhere('status', 'pending'); @endphp
    @if($pendingReq)
    <div class="card-premium p-6 mb-5" x-data="{ openUpdate: false }">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h3 class="text-sm font-bold" style="color: var(--text-primary);"><i class="fas fa-comment-dots mr-2" style="color: var(--accent);"></i>Send more info to the review team</h3>
                <p class="text-xs mt-1" style="color: var(--text-muted);">Add a message or extra documents to your pending request; the reviewer sees them alongside your original application.</p>
            </div>
            <button type="button" @click="openUpdate = !openUpdate" class="px-4 py-2 rounded-xl text-xs font-semibold transition-all" style="background: var(--bg-glass); border: 1px solid var(--border-glass); color: var(--text-secondary);">
                <i class="fas mr-1" :class="openUpdate ? 'fa-chevron-up' : 'fa-plus'"></i><span x-text="openUpdate ? 'Close' : 'Add a message / attachment'">Add a message / attachment</span>
            </button>
        </div>
        <form x-show="openUpdate" x-cloak action="{{ route('user.profile-verification.updates.store') }}" method="POST" enctype="multipart/form-data" class="mt-4 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Message to reviewer</label>
                <textarea name="message" maxlength="2000" rows="3" class="theme-input w-full text-sm" placeholder="Anything else the reviewer should know...">{{ old('message') }}</textarea>
                @error('message')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Attachments (optional)</label>
                @include('user.partials.dropzone-input', [
                    'name'        => 'attachments',
                    'policy'      => \App\Services\UploadPolicy::for('verification.proof', auth()->user()),
                    'hint'        => 'Extra proof documents: ID, articles, screenshots...',
                    'previewKind' => 'file',
                ])
                @error('attachments.*')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, var(--color-primary-500, #3d6bff), var(--color-primary-400, #5c83ff));">
                    <i class="fas fa-paper-plane mr-1.5"></i>Send Update
                </button>
            </div>
        </form>

        @if(count($pendingReq->updates ?? []) > 0)
        <div class="mt-4 pt-4 space-y-3" style="border-top: 1px solid var(--border-glass);">
            <p class="text-[10px] font-semibold uppercase tracking-wider" style="color: var(--text-dimmed);">Updates you've sent</p>
            @foreach($pendingReq->updates as $upd)
            <div class="p-3 rounded-xl text-xs" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                @if(!empty($upd['message']))
                <p style="color: var(--text-secondary);">{{ $upd['message'] }}</p>
                @endif
                @if(!empty($upd['files']))
                <div class="flex flex-wrap gap-2 {{ !empty($upd['message']) ? 'mt-2' : '' }}">
                    @foreach($upd['files'] as $f)
                    <span class="px-2 py-1 rounded-lg text-[10px]" style="background: var(--c-primary-soft); color: var(--text-muted);"><i class="fas fa-paperclip mr-1"></i>{{ basename($f) }}</span>
                    @endforeach
                </div>
                @endif
                @if(!empty($upd['created_at']))
                <p class="text-[10px] mt-1.5" style="color: var(--text-dimmed);">{{ \Illuminate\Support\Carbon::parse($upd['created_at'])->diffForHumans() }}</p>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    {{-- Request history --}}
    @if($requests->count() > 0)
    <div class="card-premium p-6">
        <h3 class="text-sm font-bold mb-4" style="color: var(--text-primary);">Request History</h3>
        <div class="space-y-3">
            @foreach($requests as $req)
            <div class="p-4 rounded-xl" style="background: var(--bg-glass); border: 1px solid var(--border-glass);">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        @if($req->tickType)
                        <i class="fas {{ $req->tickType->icon }} text-lg" style="color: {{ $req->tickType->color }};"></i>
                        @else
                        <i class="fas fa-shield-alt text-lg" style="color: var(--text-dimmed);"></i>
                        @endif
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--text-primary);">{{ $req->official_name }}</p>
                            <p class="text-[11px]" style="color: var(--text-dimmed);">
                                {{ $req->kind === 'reverification' ? 'Re-verification request' : 'New verification request' }}
                                &middot; {{ $req->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                    <div>
                        @if($req->status === 'pending')
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400"><i class="fas fa-clock mr-1 text-[10px]"></i>Pending</span>
                        @elseif($req->status === 'approved')
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400"><i class="fas fa-check mr-1 text-[10px]"></i>Approved</span>
                        @else
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-red-500/10 text-red-400"><i class="fas fa-times mr-1 text-[10px]"></i>Rejected</span>
                        @endif
                    </div>
                </div>
                @if($req->admin_notes && in_array($req->status, ['rejected', 'approved'], true))
                <p class="text-xs mt-2 pt-2" style="color: var(--text-muted); border-top: 1px solid var(--border-glass);">
                    <i class="fas fa-comment-alt mr-1" style="color: var(--text-dimmed);"></i>{{ $req->admin_notes }}
                </p>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
