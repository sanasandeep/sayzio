@extends('user.layouts.app')
@section('title', 'Request Verification')

@section('content')
<div class="max-w-3xl mx-auto" x-data="{ category: 'artist_creator' }">
    <div class="mb-6">
        <a href="{{ route('user.verification.index') }}" class="text-xs font-medium transition-colors hover:text-violet-400" style="color: var(--text-muted);">
            <i class="fas fa-arrow-left mr-1"></i>Back to Verification
        </a>
        <h1 class="text-2xl font-bold mt-2" style="color: var(--text-primary);">Request Verification</h1>
        <p class="text-sm mt-1" style="color: var(--text-muted);">Submit proof of your identity to get verified</p>
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

    <form action="{{ route('user.verification.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
        @csrf

        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-4" style="color: var(--text-primary);"><i class="fas fa-link text-violet-400 mr-2"></i>Select Link in Bio Page</h3>
            <select name="link_id" required class="theme-input w-full text-sm">
                <option value="">Choose a Link in Bio page...</option>
                @foreach($biolinks as $bl)
                <option value="{{ $bl->id }}" {{ (old('link_id', $linkId) == $bl->id) ? 'selected' : '' }}>
                    {{ $bl->title ?: $bl->alias }} {{ $bl->is_verified ? '(Already Verified)' : '' }}
                </option>
                @endforeach
            </select>
        </div>

        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-4" style="color: var(--text-primary);"><i class="fas fa-tag text-violet-400 mr-2"></i>Category</h3>
            <div class="grid grid-cols-2 gap-3">
                <label class="cursor-pointer p-4 rounded-xl transition-all" :class="category === 'artist_creator' ? 'ring-2 ring-violet-500' : ''" style="background: var(--bg-glass); border: 1px solid var(--border-glass);" @click="category = 'artist_creator'">
                    <input type="radio" name="category" value="artist_creator" x-model="category" class="sr-only">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(124,58,237,0.1);"><i class="fas fa-palette text-violet-400"></i></div>
                        <div>
                            <span class="text-xs font-bold block" style="color: var(--text-primary);">Artist / Creator</span>
                            <span class="text-[10px]" style="color: var(--text-dimmed);">Musicians, artists, influencers, content creators</span>
                        </div>
                    </div>
                </label>
                <label class="cursor-pointer p-4 rounded-xl transition-all" :class="category === 'business_product' ? 'ring-2 ring-violet-500' : ''" style="background: var(--bg-glass); border: 1px solid var(--border-glass);" @click="category = 'business_product'">
                    <input type="radio" name="category" value="business_product" x-model="category" class="sr-only">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: rgba(59,130,246,0.1);"><i class="fas fa-building text-violet-400"></i></div>
                        <div>
                            <span class="text-xs font-bold block" style="color: var(--text-primary);">Business / Product</span>
                            <span class="text-[10px]" style="color: var(--text-dimmed);">Companies, brands, products, organizations</span>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-4" style="color: var(--text-primary);">
                <i class="fas fa-info-circle text-cyan-400 mr-2"></i>
                <span x-show="category === 'artist_creator'">Artist / Creator Details</span>
                <span x-show="category === 'business_product'">Business / Product Details</span>
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">
                        <span x-show="category === 'artist_creator'">Artist / Stage Name</span>
                        <span x-show="category === 'business_product'">Business / Company Name</span>
                    </label>
                    <input type="text" name="business_name" value="{{ old('business_name') }}" required maxlength="200" class="theme-input w-full text-sm" placeholder="Enter official name">
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Display Name (shown on page)</label>
                    <input type="text" name="display_name" value="{{ old('display_name') }}" required maxlength="200" class="theme-input w-full text-sm" placeholder="Name as it will appear on your verified page">
                    <p class="text-[10px] mt-1" style="color: var(--text-dimmed);">This name will be locked on your Link in Bio page once verified.</p>
                </div>
                <div>
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">
                        <span x-show="category === 'artist_creator'">Why should this page be verified?</span>
                        <span x-show="category === 'business_product'">Purpose of the Link in Bio page</span>
                    </label>
                    <textarea name="purpose" required maxlength="2000" rows="4" class="theme-input w-full text-sm" placeholder="Explain how this page is used and why it should be verified...">{{ old('purpose') }}</textarea>
                </div>
            </div>
        </div>

        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-4" style="color: var(--text-primary);"><i class="fas fa-image text-emerald-400 mr-2"></i>Logo / Profile Image</h3>
            <div class="flex items-center gap-4">
                <label class="cursor-pointer flex items-center gap-3 p-4 rounded-xl transition-all hover:bg-white/[0.03]" style="background: var(--bg-glass); border: 2px dashed var(--border-glass);">
                    <i class="fas fa-cloud-upload-alt text-violet-400"></i>
                    <div>
                        <span class="text-xs font-semibold block" style="color: var(--text-primary);">Upload Logo</span>
                        <span class="text-[10px]" style="color: var(--text-dimmed);">PNG, JPG up to 2MB</span>
                    </div>
                    <input type="file" name="logo" accept="image/*" class="hidden">
                </label>
            </div>
            <p class="text-[10px] mt-2" style="color: var(--text-dimmed);">This logo will be used for your verified avatar block and cannot be changed after verification.</p>
        </div>

        <div class="card-premium p-6">
            <h3 class="text-sm font-bold mb-4" style="color: var(--text-primary);"><i class="fas fa-file-alt text-amber-400 mr-2"></i>Proof Documents</h3>
            <p class="text-xs mb-3" style="color: var(--text-muted);">
                <span x-show="category === 'artist_creator'">Upload proof of your identity (government ID, verified social media screenshots, official website, press coverage, etc.)</span>
                <span x-show="category === 'business_product'">Upload proof of your business (business registration, trademark certificate, official documents, etc.)</span>
            </p>
            <label class="cursor-pointer block p-6 rounded-xl text-center transition-all hover:bg-white/[0.03]" style="background: var(--bg-glass); border: 2px dashed var(--border-glass);">
                <i class="fas fa-folder-open text-violet-400 text-2xl mb-2"></i>
                <p class="text-xs font-semibold mb-1" style="color: var(--text-primary);">Click to upload proof files</p>
                <p class="text-[10px]" style="color: var(--text-dimmed);">PDF, PNG, JPG up to 5MB each. Multiple files allowed.</p>
                <input type="file" name="proof_files[]" multiple accept=".pdf,.png,.jpg,.jpeg,.webp" class="hidden">
            </label>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('user.verification.index') }}" class="px-5 py-2.5 rounded-xl text-sm font-medium transition-all glass" style="color: var(--text-secondary);">Cancel</a>
            <button type="submit" class="px-6 py-2.5 rounded-xl text-sm font-medium text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, #7c3aed, #8b5cf6);">
                <i class="fas fa-paper-plane mr-1.5"></i>Submit Request
            </button>
        </div>
    </form>
</div>
@endsection
