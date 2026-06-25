@php
    /** @var \App\Modules\User\Models\BiolinkBlock $block */
    /** @var array $s */
    /** @var string $fontColor */
    $items   = is_array($s['items'] ?? null) ? $s['items'] : [];
    $layout  = $s['layout'] ?? ($s['_registry']['layout'] ?? 'compact');
    $accent  = $s['accent_color'] ?? '#3d6bff';
    $title   = trim($s['title'] ?? '');

    $fmtSize = static function ($bytes) {
        if (! is_numeric($bytes) || $bytes <= 0) return '';
        $units = ['B','KB','MB','GB'];
        $i = 0; $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) { $n /= 1024; $i++; }
        return number_format($n, $n < 10 ? 1 : 0) . ' ' . $units[$i];
    };

    $iconFor = static function ($ext) {
        $ext = strtolower((string) $ext);
        return match (true) {
            in_array($ext, ['pdf']) => 'fa-file-pdf',
            in_array($ext, ['doc','docx','rtf','txt','md']) => 'fa-file-lines',
            in_array($ext, ['xls','xlsx','csv','numbers']) => 'fa-file-excel',
            in_array($ext, ['ppt','pptx','keynote','key']) => 'fa-file-powerpoint',
            in_array($ext, ['zip','rar','7z','tar','gz']) => 'fa-file-zipper',
            in_array($ext, ['mp3','wav','m4a','flac','ogg','aac']) => 'fa-file-audio',
            in_array($ext, ['mp4','mov','m4v','avi','webm','mkv']) => 'fa-file-video',
            in_array($ext, ['png','jpg','jpeg','gif','webp','svg']) => 'fa-file-image',
            default => 'fa-file',
        };
    };
@endphp

<div class="mb-4 glass-block rounded-xl p-4"
     x-data="{
         preview: null,
         open(url, name, ext) {
             ext = (ext || '').toString().toLowerCase();
             const isPdf   = ext === 'pdf' || /\.pdf(\?|$)/i.test(url);
             const isImage = ['png','jpg','jpeg','gif','webp','svg','bmp'].includes(ext) || /\.(png|jpe?g|gif|webp|svg|bmp)(\?|$)/i.test(url);
             if (isPdf || isImage) { this.preview = { url, name, kind: isPdf ? 'pdf' : 'image' }; }
             else { window.open(url, '_blank', 'noopener'); }
         },
         close() { this.preview = null; }
     }"
     @keydown.escape.window="close()">
    @if($title !== '')
        <p class="text-sm font-semibold mb-3" style="color: {{ $fontColor }};">{{ $title }}</p>
    @endif

    @if(empty($items))
        <p class="text-xs opacity-50 text-center py-4" style="color: {{ $fontColor }};">No files yet</p>
    @elseif($layout === 'pdf_strip')
        <div class="flex gap-3 overflow-x-auto -mx-1 px-1 pb-2">
            @foreach($items as $it)
                @php
                    $name = $it['name'] ?? 'Untitled';
                    $url  = $it['url'] ?? '#';
                    $ext  = $it['ext'] ?? pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
                @endphp
                <a href="{{ $url }}" target="_blank" rel="noopener" @click.prevent="open(@js($url), @js($name), @js($ext))"
                   class="flex-shrink-0 w-32 rounded-xl overflow-hidden border transition hover:-translate-y-1"
                   style="border-color: {{ $accent }}33; background: {{ $accent }}11;">
                    <div class="aspect-[3/4] flex items-center justify-center" style="background: {{ $accent }}1a;">
                        <i class="fas {{ $iconFor($ext) }} text-3xl" style="color: {{ $accent }};"></i>
                    </div>
                    <div class="p-2">
                        <div class="text-xs font-medium truncate" style="color: {{ $fontColor }};">{{ $name }}</div>
                        @if($s = $fmtSize($it['size'] ?? null))
                            <div class="text-[10px] opacity-50" style="color: {{ $fontColor }};">{{ $s }}</div>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    @elseif($layout === 'grid')
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            @foreach($items as $it)
                @php
                    $name = $it['name'] ?? 'Untitled';
                    $url  = $it['url'] ?? '#';
                    $ext  = $it['ext'] ?? pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
                    $sz   = $fmtSize($it['size'] ?? null);
                @endphp
                <a href="{{ $url }}" target="_blank" rel="noopener" @click.prevent="open(@js($url), @js($name), @js($ext))"
                   class="flex flex-col items-center text-center p-3 rounded-xl border transition hover:-translate-y-0.5"
                   style="border-color: {{ $fontColor }}1a; background: {{ $fontColor }}08;">
                    <i class="fas {{ $iconFor($ext) }} text-2xl mb-2" style="color: {{ $accent }};"></i>
                    <div class="text-xs font-medium truncate w-full" style="color: {{ $fontColor }};">{{ $name }}</div>
                    @if($sz)<div class="text-[10px] opacity-50 mt-0.5" style="color: {{ $fontColor }};">{{ $sz }}</div>@endif
                </a>
            @endforeach
        </div>
    @elseif($layout === 'cards')
        <div class="space-y-2">
            @foreach($items as $it)
                @php
                    $name = $it['name'] ?? 'Untitled';
                    $url  = $it['url'] ?? '#';
                    $desc = $it['description'] ?? '';
                    $ext  = $it['ext'] ?? pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
                    $sz   = $fmtSize($it['size'] ?? null);
                @endphp
                <a href="{{ $url }}" target="_blank" rel="noopener" @click.prevent="open(@js($url), @js($name), @js($ext))"
                   class="flex items-center gap-3 p-3 rounded-xl border transition hover:-translate-y-0.5"
                   style="border-color: {{ $fontColor }}1a; background: {{ $fontColor }}08;">
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0"
                         style="background: {{ $accent }}22;">
                        <i class="fas {{ $iconFor($ext) }} text-xl" style="color: {{ $accent }};"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold truncate" style="color: {{ $fontColor }};">{{ $name }}</div>
                        @if($desc)<div class="text-xs opacity-60 truncate" style="color: {{ $fontColor }};">{{ $desc }}</div>@endif
                        @if($sz)<div class="text-[10px] opacity-50 mt-0.5" style="color: {{ $fontColor }};">{{ strtoupper((string)$ext) }}{{ $ext ? ' · ' : '' }}{{ $sz }}</div>@endif
                    </div>
                    <i class="fas fa-download opacity-50" style="color: {{ $accent }};"></i>
                </a>
            @endforeach
        </div>
    @else
        <div class="space-y-1">
            @foreach($items as $it)
                @php
                    $name = $it['name'] ?? 'Untitled';
                    $url  = $it['url'] ?? '#';
                    $ext  = $it['ext'] ?? pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
                    $sz   = $fmtSize($it['size'] ?? null);
                @endphp
                <a href="{{ $url }}" target="_blank" rel="noopener" @click.prevent="open(@js($url), @js($name), @js($ext))"
                   class="flex items-center gap-3 px-2 py-2 rounded-lg transition"
                   style="color: {{ $fontColor }};"
                   onmouseover="this.style.background='{{ $fontColor }}10'"
                   onmouseout="this.style.background=''">
                    <i class="fas {{ $iconFor($ext) }} w-5 text-center" style="color: {{ $accent }};"></i>
                    <span class="flex-1 text-sm truncate">{{ $name }}</span>
                    @if($sz)<span class="text-[10px] opacity-50 whitespace-nowrap">{{ $sz }}</span>@endif
                    <i class="fas fa-download text-xs opacity-40"></i>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Click-to-preview modal (PDF + image). Other file types fall back to a new-tab download. --}}
    <template x-if="preview">
        <div class="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
             @click.self="close()">
            <div class="relative w-full max-w-4xl h-[85vh] rounded-2xl overflow-hidden bg-neutral-900 shadow-2xl flex flex-col">
                <div class="flex items-center justify-between px-4 py-2 border-b border-white/10 text-white">
                    <p class="text-sm font-medium truncate" x-text="preview.name"></p>
                    <div class="flex items-center gap-2">
                        <a :href="preview.url" target="_blank" rel="noopener"
                           class="text-xs px-2.5 py-1.5 rounded-md bg-white/10 hover:bg-white/20 transition">
                            <i class="fas fa-arrow-up-right-from-square mr-1"></i>Open
                        </a>
                        <button type="button" @click="close()"
                                class="text-xs px-2.5 py-1.5 rounded-md bg-white/10 hover:bg-white/20 transition">
                            <i class="fas fa-xmark"></i>
                        </button>
                    </div>
                </div>
                <div class="flex-1 bg-black overflow-auto flex items-center justify-center">
                    <template x-if="preview.kind === 'pdf'">
                        <iframe :src="preview.url + '#view=FitH'" class="w-full h-full" frameborder="0"></iframe>
                    </template>
                    <template x-if="preview.kind === 'image'">
                        <img :src="preview.url" :alt="preview.name" class="max-w-full max-h-full object-contain">
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
