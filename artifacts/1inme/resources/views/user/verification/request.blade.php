@extends('user.layouts.settings')
@section('title', 'Apply for Verification')

@section('settings-content')
<div class="max-w-3xl" x-data="{ tickTypeId: '{{ old('tick_type_id', $tickTypes->where('slug','creator')->first()?->id) }}' }">
    <div class="mb-6">
        <a href="{{ route('user.profile-verification.index') }}" class="text-xs font-medium transition-colors hover:text-blue-400" style="color: var(--text-muted);">
            <i class="fas fa-arrow-left mr-1"></i>Back to Verification
        </a>
        <h2 class="text-lg font-bold mt-2" style="color: var(--text-primary);">Apply for Creator Profile Verification</h2>
        <p class="text-sm mt-1" style="color: var(--text-muted);">Your profile name and avatar will be locked once verified to protect your identity.</p>
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

    <form action="{{ route('user.profile-verification.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
        @csrf

        {{-- Tick type selector --}}
        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-4" style="color: var(--text-primary);"><i class="fas fa-tag mr-2" style="color: var(--color-primary-400, #5c83ff);"></i>Verification Type</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach($tickTypes as $type)
                <label class="cursor-pointer p-4 rounded-xl transition-all" :class="tickTypeId === '{{ $type->id }}' ? 'ring-2' : ''" :style="tickTypeId === '{{ $type->id }}' ? 'ring-color: {{ $type->color }}' : ''" style="background: var(--bg-glass); border: 1px solid var(--border-glass);" @click="tickTypeId = '{{ $type->id }}'">
                    <input type="radio" name="tick_type_id" value="{{ $type->id }}" x-model="tickTypeId" class="sr-only">
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
                    <input type="text" name="official_name" value="{{ old('official_name', $user->name) }}" required maxlength="200" class="theme-input w-full text-sm" placeholder="Enter your official name">
                    <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">This name will be locked on your profile once verified and must match your proof documents.</p>
                    @error('official_name')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Why should your account be verified? <span class="text-red-400">*</span></label>
                    <textarea name="purpose" required maxlength="3000" rows="4" class="theme-input w-full text-sm" placeholder="Explain your identity, your work, and why verification matters to you or your audience...">{{ old('purpose') }}</textarea>
                    @error('purpose')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        {{-- Avatar (profile photo) --}}
        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-2" style="color: var(--text-primary);"><i class="fas fa-image mr-2 text-emerald-400"></i>Verified Profile Photo (optional)</h3>
            <p class="text-xs mb-4" style="color: var(--text-muted);">Upload the photo that will be locked as your verified avatar. Leave blank to keep your current avatar.</p>
            @include('user.partials.dropzone-input', [
                'name'   => 'logo',
                'policy' => \App\Services\UploadPolicy::for('verification.logo', auth()->user()),
                'hint'   => 'Profile photo to lock — leave blank to keep current',
            ])
            @error('logo')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
        </div>

        {{-- Proof documents --}}
        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-2" style="color: var(--text-primary);"><i class="fas fa-file-alt mr-2 text-amber-400"></i>Proof Documents</h3>
            <p class="text-xs mb-4" style="color: var(--text-muted);">Upload any documents that confirm your identity or organization: government ID, business registration, verified social media screenshots, press articles, etc.</p>
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
            <strong>Important:</strong> Once approved, your display name and profile avatar will be locked. You can request a change later — this will temporarily mark your tick as "pending re-verification" while we review.
        </div>

        <div class="flex justify-end gap-3 pb-4">
            <a href="{{ route('user.profile-verification.index') }}" class="px-5 py-2.5 rounded-xl text-sm font-medium transition-all" style="background: var(--bg-glass); border: 1px solid var(--border-glass); color: var(--text-secondary);">Cancel</a>
            <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-semibold text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, var(--color-primary-500, #3d6bff), var(--color-primary-400, #5c83ff));">
                <i class="fas fa-paper-plane mr-1.5"></i>Submit Verification Request
            </button>
        </div>
    </form>
</div>
@endsection
