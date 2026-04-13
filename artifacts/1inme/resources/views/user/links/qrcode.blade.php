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
        <a href="{{ route('user.links.show', $link) }}" class="text-gray-400 hover:text-gray-600"><i class="fas fa-arrow-left"></i></a>
        <h1 class="text-2xl font-bold text-gray-900">QR Code</h1>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Customize</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Link</label>
                    <div class="text-sm text-primary-600 bg-primary-50 px-3 py-2 rounded-lg font-mono">{{ $link->getShortUrl() }}</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Size (px)</label>
                    <input type="range" x-model="size" min="100" max="1000" step="50" class="w-full">
                    <div class="text-xs text-gray-500 mt-1" x-text="size + 'px'"></div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Foreground</label>
                        <div class="flex items-center gap-2">
                            <input type="color" x-model="fgColor" class="w-10 h-10 rounded border cursor-pointer">
                            <input type="text" x-model="fgColor" class="flex-1 border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-mono">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Background</label>
                        <div class="flex items-center gap-2">
                            <input type="color" x-model="bgColor" class="w-10 h-10 rounded border cursor-pointer">
                            <input type="text" x-model="bgColor" class="flex-1 border border-gray-300 rounded-lg px-2 py-1.5 text-sm font-mono">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Error Correction</label>
                    <select x-model="errorCorrection" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                        <option value="L">Low (7%)</option>
                        <option value="M">Medium (15%)</option>
                        <option value="Q">Quartile (25%)</option>
                        <option value="H">High (30%)</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Higher correction = more resilient but denser QR code</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Logo Overlay</label>
                    <input type="file" name="logo" accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200" form="downloadForm">
                    <p class="text-xs text-gray-400 mt-1">Optional logo centered on the QR code (max 2MB)</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Download Format</label>
                    <select x-model="format" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500">
                        <option value="png">PNG (Raster)</option>
                        <option value="svg">SVG (Vector)</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Preview</h2>

            <div class="flex items-center justify-center bg-gray-50 rounded-lg p-6 mb-4" style="min-height: 320px;">
                <img :src="previewUrl" alt="QR Code Preview" class="max-w-full" :style="'max-height: ' + Math.min(size, 400) + 'px'">
            </div>

            <form id="downloadForm" method="POST" action="{{ route('user.links.qrcode.download', $link) }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="size" :value="size">
                <input type="hidden" name="format" :value="format">
                <input type="hidden" name="fg_color" :value="fgColor">
                <input type="hidden" name="bg_color" :value="bgColor">
                <input type="hidden" name="error_correction" :value="errorCorrection">

                <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white px-6 py-2.5 rounded-lg text-sm font-medium flex items-center justify-center gap-2">
                    <i class="fas fa-download"></i>
                    <span x-text="'Download ' + format.toUpperCase()"></span>
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
