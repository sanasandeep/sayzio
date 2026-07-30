@extends('admin.layouts.app')
@section('title', 'Gallery Images')
@section('page-title', 'Gallery Images')

@section('content')
<div class="max-w-7xl space-y-6"
     x-data="{
        preview: null,
        openPreview(asset) { this.preview = asset; document.documentElement.classList.add('overflow-hidden'); },
        closePreview() { this.preview = null; document.documentElement.classList.remove('overflow-hidden'); },
        previewStem() { return this.preview ? this.preview.name.replace(/\.[^.]+$/, '') : ''; }
     }"
     @keydown.escape.window="if (preview) closePreview()">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white/90 ak-strong">Curated gallery images</h2>
                <p class="text-xs text-white/50 mt-1 max-w-2xl ak-muted">
                    Platform-owned images shown in the user pickers (Link in Bio backgrounds, grid images,
                    hand-drawn stickers, avatars). Files live on the S3 bucket under
                    <code class="text-white/60 ak-muted">assets/&lt;folder&gt;/</code>; changes here refresh the
                    pickers immediately, no waiting for the cache to expire.
                </p>
            </div>
        </div>
    </div>

    @if(!$storageOk)
        <div class="rounded-xl px-4 py-3 bg-amber-500/10 border border-amber-500/30 text-amber-200 text-sm ak-amber">
            <i class="fas fa-triangle-exclamation mr-1"></i>
            Storage is not reachable right now (S3 not configured or unavailable). Listings may be empty and
            uploads will fail until storage is configured under Admin → Integrations.
        </div>
    @endif

    @if(session('success'))
        <div class="rounded-xl px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm ak-green">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl px-4 py-3 bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm ak-red">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-xl px-4 py-3 bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm ak-red">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Folder tabs --}}
    <div class="flex items-center gap-1.5 flex-wrap">
        @foreach($folders as $folder)
            <a href="{{ route('admin.platform-gallery.index', ['folder' => $folder]) }}"
               class="text-[12px] font-semibold px-3 py-1.5 rounded-full {{ $current === $folder ? 'bg-blue-600/30 text-blue-200 border border-blue-500/40 ak-blue' : 'bg-white/5 text-white/70 border border-white/10 ak-strong' }}">
                {{ \App\Modules\User\Support\PlatformAssetCatalog::folderLabel($folder) }}
                <span class="opacity-60">{{ $counts[$folder] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    {{-- Upload --}}
    <form method="POST" action="{{ route('admin.platform-gallery.upload', $current) }}" enctype="multipart/form-data"
          class="glass rounded-2xl border border-white/10 p-4 flex items-center gap-3 flex-wrap">
        @csrf
        <div class="flex-1 min-w-[240px]">
            <input type="file" name="files[]" multiple required
                   accept=".jpg,.jpeg,.png,.webp,.gif,.svg"
                   class="block w-full text-sm text-white/70 file:mr-3 file:px-4 file:py-2 file:rounded-xl file:border-0 file:bg-white/10 file:text-white/90 file:text-sm file:font-medium hover:file:bg-white/15 ak-strong">
            <p class="text-[11px] text-white/40 mt-1 ak-note">JPG, PNG, WebP, GIF or SVG · up to 10&nbsp;MB each · up to 20 files at once. Duplicate names get a numeric suffix.</p>
        </div>
        <button type="submit"
                class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white inline-flex items-center gap-2">
            <i class="fas fa-cloud-arrow-up text-xs"></i>
            Upload to {{ \App\Modules\User\Support\PlatformAssetCatalog::folderLabel($current) }}
        </button>
    </form>

    {{-- Asset grid --}}
    @if(empty($assets))
        <div class="glass rounded-2xl border border-white/10 p-8 text-center text-white/60 text-sm ak-muted">
            No images in this folder yet. Upload some above.
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
            @foreach($assets as $asset)
                <div class="glass rounded-2xl border border-white/10 p-3 flex flex-col gap-2"
                     x-data="{ renaming: false }">
                    <div class="rounded-xl overflow-hidden bg-black/30 border border-white/5 relative cursor-zoom-in group"
                         style="aspect-ratio: 1;"
                         role="button" tabindex="0" title="Click to preview full size"
                         @click="openPreview(@js($asset))"
                         @keydown.enter.prevent="openPreview(@js($asset))">
                        <img src="{{ $asset['url'] }}" alt="{{ $asset['label'] }}" loading="lazy"
                             class="absolute inset-0 w-full h-full object-cover">
                        @if(!empty($asset['svg_url']))
                            <div class="absolute top-1.5 left-1.5 text-[10px] px-1.5 py-0.5 rounded-md bg-black/50 text-white/80 backdrop-blur ak-strong">PNG + SVG</div>
                        @endif
                        <div class="absolute inset-0 flex items-center justify-center bg-black/0 group-hover:bg-black/30 transition-colors">
                            <i class="fas fa-magnifying-glass-plus text-white/0 group-hover:text-white/80 transition-colors"></i>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-white/90 truncate ak-strong" title="{{ $asset['label'] }}">{{ $asset['label'] }}</p>
                        <p class="text-[10px] text-white/40 font-mono truncate ak-note" title="{{ $asset['name'] }}">{{ $asset['name'] }}</p>
                    </div>

                    <form method="POST" action="{{ route('admin.platform-gallery.rename', $current) }}"
                          x-show="renaming" x-cloak class="flex items-center gap-1.5">
                        @csrf
                        <input type="hidden" name="key" value="{{ $asset['key'] }}">
                        <input type="text" name="new_name" value="{{ pathinfo($asset['name'], PATHINFO_FILENAME) }}" required
                               class="flex-1 min-w-0 bg-black/30 border border-white/15 rounded-lg px-2 py-1.5 text-xs text-white ak-strong">
                        <button type="submit" title="Save name"
                                class="text-[11px] font-semibold px-2 py-1.5 rounded-md bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-200 ak-green">
                            <i class="fas fa-check text-[10px]"></i>
                        </button>
                    </form>

                    <div class="flex items-center gap-1.5 mt-auto" x-show="!renaming">
                        <button type="button" @click="renaming = true"
                                class="flex-1 text-center text-[11px] font-semibold px-2 py-1.5 rounded-md bg-blue-600/20 hover:bg-blue-600/30 text-blue-200 ak-blue">
                            <i class="fas fa-pen text-[10px] mr-1"></i> Rename
                        </button>
                        <form method="POST" action="{{ route('admin.platform-gallery.destroy', $current) }}"
                              onsubmit="return confirm('Delete &quot;{{ addslashes($asset['name']) }}&quot; from the {{ \App\Modules\User\Support\PlatformAssetCatalog::folderLabel($current) }} gallery? This cannot be undone.');"
                              class="inline">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="key" value="{{ $asset['key'] }}">
                            <button type="submit" title="Delete"
                                    class="text-[11px] font-semibold px-2 py-1.5 rounded-md bg-rose-500/20 hover:bg-rose-500/30 text-rose-200 ak-red">
                                <i class="fas fa-trash text-[10px]"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Full-size preview lightbox --}}
    <template x-if="preview">
        <div class="fixed inset-0 z-[90] flex items-center justify-center p-4 sm:p-8"
             role="dialog" aria-modal="true" aria-label="Image preview">
            <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="closePreview()"></div>
            <div class="relative glass rounded-2xl border border-white/15 bg-black/60 max-w-3xl w-full max-h-full overflow-y-auto p-4 sm:p-6 space-y-4">
                <button type="button" @click="closePreview()" title="Close preview"
                        class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white/80 flex items-center justify-center">
                    <i class="fas fa-xmark text-sm"></i>
                </button>

                <div class="rounded-xl overflow-hidden bg-black/40 border border-white/10 flex items-center justify-center"
                     style="background-image: linear-gradient(45deg, rgba(255,255,255,0.05) 25%, transparent 25%, transparent 75%, rgba(255,255,255,0.05) 75%), linear-gradient(45deg, rgba(255,255,255,0.05) 25%, transparent 25%, transparent 75%, rgba(255,255,255,0.05) 75%); background-size: 20px 20px; background-position: 0 0, 10px 10px;">
                    <img :src="preview.url" :alt="preview.label" class="max-w-full max-h-[60vh] object-contain">
                </div>

                <div class="space-y-1 pr-8">
                    <p class="text-base font-semibold text-white/90 break-words ak-strong" x-text="preview.label"></p>
                    <p class="text-xs text-white/50 font-mono break-all ak-muted" x-text="preview.name"></p>
                    <p class="text-[11px] text-white/40 font-mono break-all ak-note">
                        <span class="text-white/50 font-sans ak-muted">S3 key:</span>
                        <span x-text="preview.key"></span>
                    </p>
                    <template x-if="preview.svg_url">
                        <p class="text-xs">
                            <a :href="preview.svg_url" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 text-blue-300 hover:text-blue-200 font-semibold ak-blue">
                                <i class="fas fa-bezier-curve text-[10px]"></i>
                                Open SVG variant
                                <i class="fas fa-arrow-up-right-from-square text-[9px] opacity-70"></i>
                            </a>
                        </p>
                    </template>
                </div>

                <div class="border-t border-white/10 pt-4 flex items-end gap-3 flex-wrap">
                    <form method="POST" action="{{ route('admin.platform-gallery.rename', $current) }}"
                          class="flex items-center gap-1.5 flex-1 min-w-[220px]">
                        @csrf
                        <input type="hidden" name="key" :value="preview.key">
                        <input type="text" name="new_name" :value="previewStem()" required
                               class="flex-1 min-w-0 bg-black/30 border border-white/15 rounded-lg px-2.5 py-2 text-sm text-white ak-strong">
                        <button type="submit"
                                class="text-xs font-semibold px-3 py-2 rounded-lg bg-blue-600/20 hover:bg-blue-600/30 text-blue-200 whitespace-nowrap ak-blue">
                            <i class="fas fa-pen text-[10px] mr-1"></i> Rename
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.platform-gallery.destroy', $current) }}"
                          @submit="if (!confirm('Delete \x22' + preview.name + '\x22 from the {{ addslashes(\App\Modules\User\Support\PlatformAssetCatalog::folderLabel($current)) }} gallery? This cannot be undone.')) $event.preventDefault()">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="key" :value="preview.key">
                        <button type="submit"
                                class="text-xs font-semibold px-3 py-2 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 text-rose-200 whitespace-nowrap ak-red">
                            <i class="fas fa-trash text-[10px] mr-1"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
