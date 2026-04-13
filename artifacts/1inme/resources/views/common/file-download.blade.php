<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @if($link->seo_title)
        <title>{{ $link->seo_title }}</title>
        <meta property="og:title" content="{{ $link->seo_title }}">
    @else
        <title>{{ $link->title ?: 'Download File' }} - 1INME</title>
    @endif
    @if($link->seo_description)
        <meta name="description" content="{{ $link->seo_description }}">
    @endif
    @if($link->favicon)
        <link rel="icon" type="image/png" href="{{ $link->favicon }}">
    @endif
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-lg">
        <div class="bg-white rounded-2xl shadow-lg p-8 text-center">
            @php
                $ext = strtolower(pathinfo($fileLink->original_name, PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                $isPdf = $ext === 'pdf';
                $previewUrl = route('redirect.file.raw', ['alias' => $link->alias, 'mode' => 'preview']);
                $downloadUrl = route('redirect.file.raw', $link->alias);
            @endphp

            <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
                @if($isImage)
                    <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                @elseif($isPdf)
                    <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                @else
                    <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                @endif
            </div>

            <h1 class="text-xl font-bold text-gray-900 mb-1">{{ $link->title ?: $fileLink->original_name }}</h1>

            <div class="flex items-center justify-center gap-3 text-sm text-gray-500 mb-6">
                <span>{{ strtoupper($ext) }}</span>
                <span>&middot;</span>
                <span>{{ $fileLink->human_file_size }}</span>
            </div>

            @if($isImage)
            <div class="mb-6 rounded-lg overflow-hidden border border-gray-200">
                <img src="{{ $previewUrl }}" alt="{{ $fileLink->original_name }}" class="w-full max-h-64 object-contain bg-gray-100">
            </div>
            @elseif($isPdf)
            <div class="mb-6 rounded-lg overflow-hidden border border-gray-200" style="height: 400px;">
                <iframe src="{{ $previewUrl }}" class="w-full h-full" title="PDF Preview"></iframe>
            </div>
            @endif

            <a href="{{ $downloadUrl }}" class="inline-flex items-center justify-center gap-2 w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Download File
            </a>

            <p class="text-xs text-gray-400 mt-4">Shared via <strong>1INME</strong></p>
        </div>
    </div>

    @include('common.partials.pixel-scripts')
</body>
</html>
