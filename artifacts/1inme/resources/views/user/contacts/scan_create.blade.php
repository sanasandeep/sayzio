@extends('user.layouts.app')

@section('title', 'Scan a card or brochure')

@section('content')
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Scan a card or brochure',
        'subtitle' => 'Snap a business card or upload a brochure PDF, our AI pulls the name, contact details, socials and tagline so you can save it as a contact or seed a Link in Bio page in one click.',
        'icon' => 'fa-camera-retro',
        'chips' => [
            ['icon' => 'fa-bolt text-pink-400', 'text' => 'Powered by AI credits'],
            ['icon' => 'fa-file-image text-cyan-400', 'text' => 'JPG / PNG / WebP / PDF'],
        ],
    ])

    @if(session('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
        <i class="fas fa-exclamation-circle mr-1.5"></i> {{ session('error') }}
    </div>
    @endif

    @if(!$engineOn)
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(245,158,11,0.10); border: 1px solid rgba(245,158,11,0.25); color: #f59e0b;">
        <i class="fas fa-triangle-exclamation mr-1.5"></i>
        AI scanning is currently disabled by your administrator. Please try again later.
    </div>
    @endif

    <form method="POST" action="{{ route('user.contacts.scan.store') }}" enctype="multipart/form-data"
          class="card-premium p-6 space-y-5">
        @csrf
        <input type="hidden" name="from" value="{{ $from }}">

        <div>
            <label class="block text-sm font-semibold mb-2" style="color: var(--text-primary);">
                Upload one or more cards or brochure pages
            </label>
            <input type="file" name="files[]" required multiple
                   accept="image/jpeg,image/png,image/webp,application/pdf"
                   class="block w-full text-sm rounded-xl px-3 py-2"
                   style="background: rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.10); color: var(--text-primary);">
            <p class="mt-2 text-xs" style="color: var(--text-muted);">
                Up to {{ $maxUploads }} files, {{ $maxMb }} MB each. Add the front and back of a card,
                or several brochure photos in one go. PDFs are processed up to {{ $maxPages }} pages.
            </p>
        </div>

        <div>
            <label for="instruction" class="block text-sm font-semibold mb-1" style="color: var(--text-primary);">
                What should I focus on? <span class="font-normal text-xs" style="color: var(--text-muted);">(optional)</span>
            </label>
            <textarea id="instruction" name="instruction" rows="2"
                      maxlength="{{ \App\Services\AI\CardBrochureExtractionService::MAX_INSTRUCTION_LENGTH }}"
                      placeholder="e.g. "just grab the logo and phone number" or "only extract brand colors""
                      class="block w-full text-sm rounded-xl px-3 py-2 resize-none"
                      style="background: rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.10); color: var(--text-primary);">{{ old('instruction') }}</textarea>
            <p class="mt-1 text-xs" style="color: var(--text-muted);">
                Tell the AI what to prioritise. Leave blank to extract everything on the card.
            </p>
        </div>

        <div class="rounded-xl p-4" style="background: rgba(61,107,255,0.08); border: 1px solid rgba(61,107,255,0.20);">
            <h4 class="text-xs font-bold uppercase tracking-wide mb-1" style="color:#90acff;">
                <i class="fas fa-sparkles mr-1"></i> What we'll extract
            </h4>
            <p class="text-xs leading-relaxed" style="color: var(--text-muted);">
                Name, role, company, tagline, phone numbers, emails, website, address, and social handles.
                You'll review and edit everything before saving.
            </p>
        </div>

        @include('user.ai._partials.cost-estimate', ['cost' => [
            'feature' => 'card_scan',
            'mode'    => 'fixed',
            'prefix'  => '≈',
            'note'    => 'per scan',
        ]])

        <div class="flex items-center justify-between pt-2 gap-3 flex-wrap">
            <a href="{{ route('user.contacts.index') }}" class="text-xs" style="color: var(--text-muted);">
                <i class="fas fa-arrow-left mr-1"></i> Back to contacts
            </a>
            <button type="submit" {{ $engineOn ? '' : 'disabled' }}
                    class="px-5 py-2.5 rounded-xl text-sm font-bold text-white transition"
                    style="background: linear-gradient(135deg,#3d6bff,#ec4899); {{ $engineOn ? '' : 'opacity:.5;cursor:not-allowed;' }}">
                <i class="fas fa-wand-magic-sparkles mr-1"></i> Scan with AI
            </button>
        </div>
    </form>
</div>
@endsection
