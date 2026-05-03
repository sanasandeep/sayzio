@extends('user.layouts.app')
@section('title', 'AR Business Card - ' . ($link->title ?: $link->alias))
@section('breadcrumb_parent', 'Links')
@section('breadcrumb_parent_url', route('user.links.index'))

@section('content')
@php
    $arUrl = route('ar.card.view', $link->alias);
    $kitUrl = route('ar.card.kit', $link->alias);
@endphp

<div class="w-full max-w-7xl mx-auto">
    @include('user.links.partials.editor-header', ['link' => $link, 'activeMainTab' => 'settings'])

    @if(session('success'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        <div class="lg:col-span-7">
            <form method="POST" action="{{ route('user.links.settings.ar.update', $link) }}" class="space-y-6">
                @csrf

                <div class="card-premium p-6">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgba(124,58,237,0.12);">
                            <i class="fas fa-vr-cardboard text-[12px]" style="color:#a78bfa;"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold" style="color: var(--text-primary);">AR Business Card</h3>
                            <p class="text-[11px]" style="color: var(--text-dimmed);">Scan a QR or NFC tag to open this biolink as a tappable card in AR.</p>
                        </div>
                    </div>

                    <label class="flex items-center gap-3 mt-4 cursor-pointer">
                        <input type="hidden" name="enabled" value="0">
                        <input type="checkbox" name="enabled" value="1" {{ $link->ar_enabled ? 'checked' : '' }} class="w-4 h-4">
                        <span class="text-sm" style="color: var(--text-primary);">Enable AR mode for this biolink</span>
                    </label>
                </div>

                <div class="card-premium p-6">
                    <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Card details</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <label class="block">
                            <span class="text-[11px] font-semibold" style="color: var(--text-dimmed);">Display name</span>
                            <input type="text" name="display_name" maxlength="80" value="{{ old('display_name', $cfg['display_name']) }}"
                                   class="mt-1 w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                        </label>
                        <label class="block">
                            <span class="text-[11px] font-semibold" style="color: var(--text-dimmed);">Accent color</span>
                            <input type="color" name="accent_color" value="{{ old('accent_color', $cfg['accent_color']) }}"
                                   class="mt-1 w-full h-10 rounded-lg" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                        </label>
                        <label class="block md:col-span-2">
                            <span class="text-[11px] font-semibold" style="color: var(--text-dimmed);">Headline</span>
                            <input type="text" name="headline" maxlength="120" value="{{ old('headline', $cfg['headline']) }}" placeholder="e.g. Photographer · Bali"
                                   class="mt-1 w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                        </label>
                        <label class="block md:col-span-2">
                            <span class="text-[11px] font-semibold" style="color: var(--text-dimmed);">Subtitle (optional)</span>
                            <input type="text" name="subtitle" maxlength="120" value="{{ old('subtitle', $cfg['subtitle']) }}" placeholder="e.g. Available for shoots in May"
                                   class="mt-1 w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                        </label>
                        <label class="block md:col-span-2">
                            <span class="text-[11px] font-semibold" style="color: var(--text-dimmed);">Avatar URL (optional)</span>
                            <input type="url" name="avatar_url" maxlength="500" value="{{ old('avatar_url', $cfg['avatar_url']) }}"
                                   class="mt-1 w-full px-3 py-2 rounded-lg text-sm" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                        </label>
                    </div>
                </div>

                <div class="card-premium p-6">
                    <h3 class="text-sm font-bold mb-2" style="color: var(--text-primary);">Tappable blocks <span class="text-[10px] font-normal" style="color: var(--text-dimmed);">(pick up to 6)</span></h3>
                    @if($blocks->isEmpty())
                        <p class="text-[12px]" style="color: var(--text-dimmed);">Add some blocks to your biolink first, then come back to choose which ones float in AR.</p>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mt-3">
                            @foreach($blocks as $b)
                            @php
                                $bs = $b->settings ?? [];
                                $label = $bs['title'] ?? $bs['text'] ?? $bs['label'] ?? ucfirst(str_replace('_',' ', $b->type));
                                $checked = in_array($b->id, $cfg['block_ids'] ?? [], true);
                            @endphp
                            <label class="flex items-center gap-2 px-3 py-2 rounded-lg cursor-pointer" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                                <input type="checkbox" name="block_ids[]" value="{{ $b->id }}" {{ $checked ? 'checked' : '' }} class="w-4 h-4">
                                <span class="text-[12px]" style="color: var(--text-primary);">{{ \Illuminate\Support\Str::limit($label, 40) }}</span>
                                <span class="ml-auto text-[10px]" style="color: var(--text-dimmed);">{{ $b->type }}</span>
                            </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                <button type="submit" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-white" style="background: linear-gradient(135deg,#a78bfa,#67e8f9);">
                    Save AR settings
                </button>
            </form>
        </div>

        <div class="lg:col-span-5">
            <div class="card-premium p-6">
                <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Preview & kit</h3>
                @if($link->ar_enabled)
                    <img src="{{ route('ar.card.texture', $link->alias) }}?_t={{ time() }}"
                         alt="AR card preview"
                         class="w-full rounded-xl mb-4"
                         style="border: 1px solid var(--border-glass);">
                    <a href="{{ $arUrl }}" target="_blank" class="block w-full text-center px-4 py-2.5 rounded-lg text-sm font-semibold text-white mb-2" style="background: linear-gradient(135deg,#a78bfa,#67e8f9);">
                        <i class="fas fa-external-link-alt mr-1.5"></i> Open AR card
                    </a>
                    <a href="{{ $kitUrl }}" target="_blank" class="block w-full text-center px-4 py-2.5 rounded-lg text-sm font-semibold mb-2" style="background: var(--bg-glass-input); border:1px solid var(--border-glass); color: var(--text-primary);">
                        <i class="fas fa-qrcode mr-1.5"></i> Open printable kit
                    </a>
                    <a href="{{ route('ar.card.kit.pdf', $link->alias) }}" class="block w-full text-center px-4 py-2.5 rounded-lg text-sm font-semibold" style="background: var(--bg-glass-input); border:1px solid var(--border-glass); color: var(--text-primary);">
                        <i class="fas fa-file-pdf mr-1.5"></i> Download kit PDF
                    </a>
                @else
                    <p class="text-[12px]" style="color: var(--text-dimmed);">Enable AR mode and save to generate a preview, QR + NFC kit.</p>
                @endif
            </div>

            <div class="card-premium p-6 mt-4">
                <h3 class="text-sm font-bold mb-2" style="color: var(--text-primary);">How it works</h3>
                <ul class="text-[11.5px] space-y-1.5 list-disc pl-5" style="color: var(--text-dimmed);">
                    <li>Print the QR or write the NFC URL to a tag.</li>
                    <li>iOS opens via Quick Look (USDZ); Android via Scene Viewer (GLB); other browsers fall back to WebXR or a 3D preview.</li>
                    <li>Tappable blocks open via the standard click pipeline — clicks count as <em>source = ar</em> in analytics.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
