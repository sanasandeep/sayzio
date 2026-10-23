@extends('user.layouts.settings')
@section('title', 'Apply for Verification')

@section('settings-content')
@if($user->relationLoaded('activeCreatorProfile'))
<p class="text-sm mb-4" style="color:var(--text-muted)">Verification for workspace: {{ $user->activeCreatorProfile->workspace->name }}. Approval applies only to this creator profile.</p>
@endif

@php $verificationPreviewUrl = $user->handle ? route('creator-profile.show', ['handle'=>$user->handle,'cp_preview'=>1]) : null; @endphp
<div class="max-w-5xl" x-data="{
    tickTypeId: @js($selectedTickTypeId), officialName: @js(old('official_name', $user->name)), originalName: @js($user->name), tickMap: @js($tickMap), previewAvatar: null,
    refreshPreview() {
        const color = this.tickMap[this.tickTypeId]?.color;
        if (!color || !this.$refs.verificationFrame?.contentWindow) return;
        this.$refs.verificationFrame.contentWindow.postMessage({type:'cpLive',verificationPreview:true,verificationColor:color,name:this.officialName || this.originalName,avatarUrl:this.previewAvatar}, window.location.origin);
    }
}" x-effect="tickTypeId; officialName; previewAvatar; $nextTick(() => refreshPreview())"
@media-source-selected="if ($event.detail.name === 'logo' && !$event.detail.file) { previewAvatar = null; $refs.verificationFrame?.contentWindow.location.reload(); } if ($event.detail.name === 'logo' && $event.detail.file) { const reader = new FileReader(); reader.onload = e => { previewAvatar = e.target.result; }; reader.readAsDataURL($event.detail.file); }">
    <div class="mb-6">
        <a href="{{ route('user.profile-verification.index') }}" class="text-xs font-medium transition-colors hover:text-blue-400" style="color: var(--text-muted);">
            <i class="fas fa-arrow-left mr-1"></i>Back to Verification
        </a>
        <h2 class="text-lg font-bold mt-2" style="color: var(--text-primary);">Apply for Creator Profile Verification</h2>
        <p class="text-sm mt-1" style="color: var(--text-muted);">An admin reviews your evidence and chooses the verification color. Your profile shows a colored circled check beside your name and a thin matching strip above the cover. Changing your name or handle later requires a fresh application.</p>
    </div>

    @if($errors->any())
    <div class="mb-4 p-4 rounded-xl text-sm" style="background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2);">
        <ul class="list-disc pl-4 space-y-1">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
    {{-- Left: application form --}}
    <form action="{{ route('user.profile-verification.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5 lg:col-span-2 order-1">
        @csrf<input type="hidden" name="creator_profile_id" value="{{ $user->relationLoaded('activeCreatorProfile') ? $user->activeCreatorProfile->id : 0 }}">

        {{-- Tick type selector --}}
        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-4" style="color: var(--text-primary);"><i class="fas fa-tag mr-2" style="color: var(--accent);"></i>Verification Type</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach($tickTypes as $type)
                <label class="cursor-pointer p-4 rounded-xl transition-all" :style="{outline: tickTypeId === '{{ $type->id }}' ? '2px solid {{ $type->color }}' : 'none', outlineOffset: '2px'}" style="background: var(--bg-glass); border: 1px solid var(--border-glass);" @click="tickTypeId = '{{ $type->id }}'">
                    <input type="radio" name="tick_type_id" value="{{ $type->id }}" x-model="tickTypeId" class="w-4 h-4 mb-2" aria-label="{{ $type->name }}">
                    <div class="flex flex-col items-center gap-2 text-center">
                        <i class="fas {{ $type->icon }} text-2xl" style="color: {{ $type->color }};"></i>
                        <span class="text-xs font-bold" style="color: var(--text-primary);">{{ $type->name }}</span>
                    </div>
                </label>
                @endforeach
            </div>
            @error('tick_type_id')<p class="mt-2 text-xs text-red-400">{{ $message }}</p>@enderror
        </div>

        {{-- Details --}}
        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-4" style="color: var(--text-primary);"><i class="fas fa-info-circle mr-2 text-cyan-400"></i>Your Official Name</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Official / legal name <span class="text-red-400">*</span></label>
                    <input type="text" name="official_name" x-model="officialName" value="{{ old('official_name', $user->name) }}" required maxlength="200" class="theme-input w-full text-sm" placeholder="Enter your official name">
                    <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">Use the identity shown in your proof documents. Changing the profile name later removes verification until a fresh application is approved.</p>
                    @error('official_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Why should your account be verified? <span class="text-red-400">*</span></label>
                    <textarea name="purpose" required maxlength="3000" rows="4" class="theme-input w-full text-sm" placeholder="Explain your identity, your work, and why verification matters to you or your audience...">{{ old('purpose') }}</textarea>
                    @error('purpose')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Message to reviewer (optional)</label>
                    <textarea name="message" maxlength="2000" rows="3" class="theme-input w-full text-sm" placeholder="Anything else the review team should know: context, links to press, etc.">{{ old('message') }}</textarea>
                    @error('message')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        @include('user.verification.evidence-fields')

        {{-- Avatar (profile photo) --}}
        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-2" style="color: var(--text-primary);"><i class="fas fa-image mr-2 text-emerald-400"></i>Verified Profile Photo (optional)</h3>
            <p class="text-xs mb-4" style="color: var(--text-muted);">Upload the photo that will be locked as your verified avatar. Leave blank to keep your current avatar.</p>
            @include('user.partials.dropzone-input', [
                'name'   => 'logo',
                'policy' => \App\Services\UploadPolicy::for('verification.logo', auth()->user()),
                'hint'   => 'Profile photo to lock (leave blank to keep current)',
            ])
            @error('logo')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
        </div>

        {{-- Proof documents --}}
        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-2" style="color: var(--text-primary);"><i class="fas fa-file-alt mr-2 text-amber-400"></i>Proof Documents</h3>
            <p class="text-xs mb-4" style="color: var(--text-muted);">Attach up to five files that confirm your identity or organization: government ID, business registration, verified social media screenshots, press articles, etc.</p>
            @include('user.partials.dropzone-input', [
                'name'        => 'proof_files',
                'policy'      => \App\Services\UploadPolicy::for('verification.proof', auth()->user()),
                'hint'        => 'Drop multiple files here or click to browse',
                'previewKind' => 'file',
            ])
            @error('proof_files.*')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
        </div>

        <div class="p-4 rounded-xl text-xs" style="background: rgba(245,158,11,0.07); border: 1px solid rgba(245,158,11,0.2); color: #f59e0b;">
            <i class="fas fa-lock mr-2"></i>
            <strong>Important:</strong> Approval protects your profile photo. Changing the profile name or handle removes verification and requires a fresh application.
        </div>

        <div class="flex justify-end gap-3 pb-4">
            <a href="{{ route('user.profile-verification.index') }}" class="px-5 py-2.5 rounded-xl text-sm font-medium transition-all" style="background: var(--bg-glass); border: 1px solid var(--border-glass); color: var(--text-secondary);">Cancel</a>
            <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, var(--color-primary-500, #3d6bff), var(--color-primary-400, #5c83ff));">
                <i class="fas fa-paper-plane mr-1.5"></i>Submit Verification Request
            </button>
        </div>
    </form>

    {{-- Right: live tick preview + advantages (sticky on desktop, below form on mobile) --}}
    <div class="space-y-5 order-2 lg:sticky lg:top-6">
        <div class="card-premium p-6" data-testid="tick-preview">
            <h3 class="text-sm font-bold mb-4" style="color: var(--text-primary);"><i class="fas fa-eye mr-2" style="color: var(--accent);"></i>Live Preview</h3>
            @if($verificationPreviewUrl)
            <iframe x-ref="verificationFrame" src="{{ $verificationPreviewUrl }}" @load="refreshPreview()" title="Creator profile verification preview" class="w-full rounded-2xl border" style="height:640px;border-color:var(--border-glass);background:var(--bg-card)"></iframe>
            <p class="text-[10px] mt-3" style="color:var(--text-dimmed)">Your saved creator profile design with the name, photo and verification color from this application. This preview does not grant verification.</p>
            @else
            <p class="text-sm" style="color:var(--text-muted)">Set a handle in <a class="underline" href="{{ route('user.creator-profile.edit') }}">Creator Profile</a> to preview your actual page here.</p>
            @endif
        </div>

        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);"><i class="fas fa-star mr-2 text-amber-400"></i>Advantages of Verification</h3>
            <ul class="space-y-2.5 text-xs" style="color: var(--text-secondary);">
                <li class="flex gap-2.5"><i class="fas fa-check-circle mt-0.5 text-emerald-400"></i><span><strong>Trust badge everywhere:</strong> your colored tick shows on your profile, dialer, and all public pages.</span></li>
                <li class="flex gap-2.5"><i class="fas fa-user-shield mt-0.5 text-blue-400"></i><span><strong>Impersonation protection:</strong> your photo is protected and identity changes require a fresh verification review.</span></li>
                <li class="flex gap-2.5"><i class="fas fa-compass mt-0.5 text-cyan-400"></i><span><strong>Higher discovery credibility</strong> · verified creators stand out in search and discovery surfaces.</span></li>
                <li class="flex gap-2.5"><i class="fas fa-handshake mt-0.5 text-amber-400"></i><span><strong>Audience confidence:</strong> followers and subscribers know they're connecting with the real you.</span></li>
            </ul>
        </div>
    </div>
    </div>
</div>
@endsection
