{{--
    Link Verification — the request form.

    Companion to link-verification/index.blade.php; see that file's header for
    why this feature's views do not live under resources/views/user/verification/.

    Data contract (VerificationController::create):
      $biolinks  the user's Link rows in Link::BIOLINK_FAMILY.
      $linkId    the ?link_id= the user arrived with, or null.

    Posts to user.verification.store, whose validation is the authority on
    every field here: category is in:artist_creator,business_product;
    business_name / display_name max 200; purpose max 2000; logo and
    proof_files are sized and extension-checked by UploadPolicy
    ('verification.logo' 2MB image, 'verification.proof' 5MB multiple).
--}}
@extends('user.layouts.settings')
@section('title', 'Request Link Verification')
@section('settings-content')
<div>
    <div class="mb-6">
        <a href="{{ route('user.verification.index') }}" class="text-xs font-semibold inline-flex items-center gap-1.5 mb-3" style="color: var(--text-muted);">
            <i class="fas fa-arrow-left text-[10px]"></i> Back to Link Verification
        </a>
        <h1 class="text-2xl font-bold" style="color: var(--text-primary);">Request verification</h1>
        <p class="text-sm mt-1" style="color: var(--text-muted);">
            Tell us who's behind this page. Once approved, the name and logo below become the verified identity on it.
        </p>
    </div>

    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg text-red-400 text-sm" style="border:1px solid rgba(239,68,68,0.2); background: rgba(239,68,68,0.06);">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="mb-4 p-3 rounded-lg text-red-400 text-sm" style="border:1px solid rgba(239,68,68,0.2); background: rgba(239,68,68,0.06);">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($biolinks->isEmpty())
        <div class="rounded-2xl border p-5" style="background: var(--bg-card); border-color: var(--border-soft);">
            <div class="text-sm rounded-lg p-3" style="background: var(--bg-subtle); color: var(--text-muted); border:1px solid var(--border-soft);">
                <i class="fas fa-circle-info mr-1.5"></i>
                Verification applies to a Link in Bio page, and you don't have one yet. Create a page first, then come back.
            </div>
        </div>
    @else
    <form method="POST" action="{{ route('user.verification.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="rounded-2xl border p-5 mb-6" style="background: var(--bg-card); border-color: var(--border-soft);">
            <h2 class="text-sm font-bold mb-4" style="color: var(--text-primary);">What you're verifying</h2>

            <div class="mb-4">
                <label for="lv-link" class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Page</label>
                <select id="lv-link" name="link_id" required class="w-full px-3 py-2.5 rounded-lg text-sm" style="background: var(--bg-subtle); border:1px solid var(--border-soft); color: var(--text-primary);">
                    <option value="">Choose one of your pages</option>
                    @foreach($biolinks as $link)
                        <option value="{{ $link->id }}" {{ (string) old('link_id', $linkId) === (string) $link->id ? 'selected' : '' }}>
                            {{ $link->title ?: $link->slug }} (/{{ $link->slug }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <span class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Category</span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach([
                        'artist_creator'   => ['Artist or creator', 'fa-palette',   'A person, band, or creative project.'],
                        'business_product' => ['Business or product', 'fa-briefcase', 'A company, brand, shop, or service.'],
                    ] as $value => [$label, $icon, $hint])
                        <label class="flex items-start gap-2.5 rounded-xl p-3 cursor-pointer" style="background: var(--bg-subtle); border:1px solid var(--border-soft);">
                            <input type="radio" name="category" value="{{ $value }}" required class="mt-0.5"
                                   {{ old('category') === $value ? 'checked' : '' }}>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold" style="color: var(--text-primary);">
                                    <i class="fas {{ $icon }} text-[11px] mr-1"></i>{{ $label }}
                                </span>
                                <span class="block text-xs mt-0.5" style="color: var(--text-muted);">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="rounded-2xl border p-5 mb-6" style="background: var(--bg-card); border-color: var(--border-soft);">
            <h2 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Who you are</h2>
            <p class="text-xs mb-4" style="color: var(--text-muted);">The display name is what appears on the page once verified.</p>

            <div class="mb-4">
                <label for="lv-business" class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Legal or business name</label>
                <input id="lv-business" type="text" name="business_name" required maxlength="200" value="{{ old('business_name') }}"
                       placeholder="The name on your registration or ID"
                       class="w-full px-3 py-2.5 rounded-lg text-sm" style="background: var(--bg-subtle); border:1px solid var(--border-soft); color: var(--text-primary);">
            </div>

            <div>
                <label for="lv-display" class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Display name</label>
                <input id="lv-display" type="text" name="display_name" required maxlength="200" value="{{ old('display_name') }}"
                       placeholder="How you want to appear on the page"
                       class="w-full px-3 py-2.5 rounded-lg text-sm" style="background: var(--bg-subtle); border:1px solid var(--border-soft); color: var(--text-primary);">
            </div>
        </div>

        <div class="rounded-2xl border p-5 mb-6" style="background: var(--bg-card); border-color: var(--border-soft);">
            <h2 class="text-sm font-bold mb-1" style="color: var(--text-primary);">Why you qualify</h2>
            <p class="text-xs mb-4" style="color: var(--text-muted);">Reviewers read this first, so be specific.</p>

            <div class="mb-4">
                <label for="lv-purpose" class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Purpose of the page</label>
                <textarea id="lv-purpose" name="purpose" rows="4" required maxlength="2000"
                          placeholder="What this page is for, who it reaches, and anything that shows it's really you."
                          class="w-full px-3 py-2.5 rounded-lg text-sm" style="background: var(--bg-subtle); border:1px solid var(--border-soft); color: var(--text-primary);">{{ old('purpose') }}</textarea>
            </div>

            <div class="mb-4">
                <label for="lv-logo" class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Logo</label>
                <input id="lv-logo" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,.svg"
                       class="w-full text-sm" style="color: var(--text-muted);">
                <p class="text-xs mt-1.5" style="color: var(--text-faint);">JPG, PNG, WEBP or SVG, up to 2 MB. Shown as the verified avatar on the page.</p>
            </div>

            <div>
                <label for="lv-proof" class="block text-xs font-semibold uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Supporting documents</label>
                <input id="lv-proof" type="file" name="proof_files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp"
                       class="w-full text-sm" style="color: var(--text-muted);">
                <p class="text-xs mt-1.5" style="color: var(--text-faint);">PDF or images, up to 5 MB each. Registration papers, press coverage, an official profile linking here.</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 transition">Submit request</button>
            <a href="{{ route('user.verification.index') }}" class="text-sm font-semibold" style="color: var(--text-muted);">Cancel</a>
        </div>
    </form>
    @endif
</div>
@endsection
