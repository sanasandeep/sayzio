@extends('admin.layouts.app')
@section('title', 'Gallery Images')
@section('page-title', 'Gallery Images')

@section('content')
<div class="max-w-7xl space-y-6">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white/90 ak-strong">Curated gallery images</h2>
                <p class="text-xs text-white/50 mt-1 max-w-2xl ak-muted">
                    Platform-owned images shown in the user pickers (Link in Bio backgrounds, grid images,
                    hand-drawn stickers, avatars). Files live on the S3 bucket under
                    <code class="text-white/60 ak-muted">assets/&lt;folder&gt;/</code>; changes here refresh the
                    pickers immediately — no waiting for the cache to expire.
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
                    <div class="rounded-xl overflow-hidden bg-black/30 border border-white/5 relative" style="aspect-ratio: 1;">
                        <img src="{{ $asset['url'] }}" alt="{{ $asset['label'] }}" loading="lazy"
                             class="absolute inset-0 w-full h-full object-cover">
                        @if(!empty($asset['svg_url']))
                            <div class="absolute top-1.5 left-1.5 text-[10px] px-1.5 py-0.5 rounded-md bg-black/50 text-white/80 backdrop-blur ak-strong">PNG + SVG</div>
                        @endif
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
</div>
@endsection
