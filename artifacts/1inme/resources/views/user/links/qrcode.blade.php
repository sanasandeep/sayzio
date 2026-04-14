@extends('user.layouts.app')
@section('title', 'QR Code - ' . ($link->title ?: $link->alias))

@section('content')
<div class="max-w-3xl mx-auto" x-data="{
    size: 300,
    fgColor: '#000000',
    bgColor: '#FFFFFF',
    errorCorrection: 'M',
    format: 'png',
    get previewUrl() {
        return '{{ route('user.links.qrcode.preview', $link) }}?size=' + this.size + '&fg_color=' + encodeURIComponent(this.fgColor) + '&bg_color=' + encodeURIComponent(this.bgColor) + '&error_correction=' + this.errorCorrection;
    }
}">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.show', $link) }}" class="text-white/30 hover:text-white/50"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-2xl font-bold text-white">QR Code</h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="glass rounded-2xl p-6">
            <h2 class="text-lg font-semibold text-white mb-4">Customize</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Link</label>
                    <div class="text-sm text-purple-400 bg-purple-500/10 px-3 py-2 rounded-xl font-mono">{{ $link->getShortUrl() }}</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Size (px)</label>
                    <input type="range" x-model="size" min="100" max="1000" step="50" class="w-full">
                    <div class="text-xs text-white/40 mt-1" x-text="size + 'px'"></div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-white/60 mb-1">Foreground</label>
                        <div class="flex items-center gap-2">
                            <input type="color" x-model="fgColor" class="w-10 h-10 rounded border cursor-pointer">
                            <input type="text" x-model="fgColor" class="flex-1 border border-white/10 rounded-xl px-2 py-1.5 text-sm font-mono">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/60 mb-1">Background</label>
                        <div class="flex items-center gap-2">
                            <input type="color" x-model="bgColor" class="w-10 h-10 rounded border cursor-pointer">
                            <input type="text" x-model="bgColor" class="flex-1 border border-white/10 rounded-xl px-2 py-1.5 text-sm font-mono">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Error Correction</label>
                    <select x-model="errorCorrection" class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500/40">
                        <option value="L">Low (7%)</option>
                        <option value="M">Medium (15%)</option>
                        <option value="Q">Quartile (25%)</option>
                        <option value="H">High (30%)</option>
                    </select>
                    <p class="text-xs text-white/30 mt-1">Higher correction = more resilient but denser QR code</p>
                </div>

                <div x-show="format === 'png'">
                    <label class="block text-sm font-medium text-white/60 mb-1">Logo Overlay</label>
                    <input type="file" name="logo" accept="image/*" class="w-full text-sm text-white/40 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:bg-white/10 file:text-white/60 hover:file:bg-gray-200" form="downloadForm">
                    <p class="text-xs text-white/30 mt-1">Optional logo centered on the QR code (PNG only, max 2MB)</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-white/60 mb-1">Download Format</label>
                    <select x-model="format" class="w-full border border-white/10 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500/40">
                        <option value="png">PNG (Raster)</option>
                        <option value="svg">SVG (Vector)</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="glass rounded-2xl p-6">
            <h2 class="text-lg font-semibold text-white mb-4">Preview</h2>

            <div class="flex items-center justify-center bg-white/5 rounded-xl p-6 mb-4" style="min-height: 320px;">
                <img :src="previewUrl" alt="QR Code Preview" class="max-w-full" :style="'max-height: ' + Math.min(size, 400) + 'px'">
            </div>

            <form id="downloadForm" method="POST" action="{{ route('user.links.qrcode.download', $link) }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="size" :value="size">
                <input type="hidden" name="format" :value="format">
                <input type="hidden" name="fg_color" :value="fgColor">
                <input type="hidden" name="bg_color" :value="bgColor">
                <input type="hidden" name="error_correction" :value="errorCorrection">

                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium flex items-center justify-center gap-2">
                    <i class="fas fa-download"></i>
                    <span x-text="'Download ' + format.toUpperCase()"></span>
                </button>
            </form>

            <div class="mt-4 border-t pt-4">
                <label class="block text-sm font-medium text-white/60 mb-2">Embed Code</label>
                @php $publicQrUrl = route('qr.public.link', $link->alias); @endphp
                <div class="relative">
                    <textarea readonly class="w-full border border-white/10 rounded-xl px-3 py-2 text-xs font-mono bg-white/5 resize-none" rows="3" x-ref="embedCode" :value="'<img src=&quot;{{ $publicQrUrl }}?size=' + size + '&fg_color=' + encodeURIComponent(fgColor) + '&bg_color=' + encodeURIComponent(bgColor) + '&error_correction=' + errorCorrection + '&quot; alt=&quot;QR Code&quot; width=&quot;' + size + '&quot; height=&quot;' + size + '&quot;>'"></textarea>
                    <button @click="navigator.clipboard.writeText($refs.embedCode.value); $el.textContent = 'Copied!'; setTimeout(() => $el.textContent = 'Copy', 2000)" type="button" class="absolute top-2 right-2 text-xs bg-white border border-white/10 rounded px-2 py-1 text-white/50 hover:bg-white/5">Copy</button>
                </div>
                <p class="text-xs text-white/30 mt-1">Paste this HTML to embed the QR code on any webpage</p>
            </div>
        </div>
    </div>
</div>
@endsection
