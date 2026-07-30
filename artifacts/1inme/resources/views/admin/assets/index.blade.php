@extends('admin.layouts.app')
@section('title', 'Asset Vault')

@push('styles')
<style>
    /* On touch devices (no hover), keep asset hover actions always visible */
    @media (hover: none) {
        .asset-actions { opacity: 1 !important; }
    }
    /* Toast popup for "URL copied" feedback */
    .vault-toast {
        position: fixed; left: 50%; bottom: 24px;
        transform: translateX(-50%);
        background: #111827; color: #fff;
        padding: 10px 18px; border-radius: 999px;
        font-size: 12px; font-weight: 600;
        box-shadow: 0 10px 30px rgba(0,0,0,.35);
        z-index: 80; display: flex; align-items: center; gap: 8px;
        border: 1px solid rgba(61,107,255,.5);
    }
    .vault-toast i { color: #90acff; }
</style>
@endpush

@section('content')
<div x-data="adminAssetVault()" x-init="init()" class="space-y-5 relative"
     @dragenter.prevent.stop="dragDepth++; dragOver = true"
     @dragover.prevent.stop="dragOver = true"
     @dragleave.prevent.stop="dragDepth = Math.max(0, dragDepth - 1); if (dragDepth === 0) dragOver = false"
     @drop.prevent.stop="dragOver = false; dragDepth = 0; handleDrop($event)">

    {{-- Dropzone overlay --}}
    <div x-show="dragOver" x-cloak
         class="fixed inset-0 z-40 flex items-center justify-center pointer-events-none"
         style="background: rgba(61,107,255,0.10); backdrop-filter: blur(2px);">
        <div class="rounded-2xl px-10 py-8 text-center"
             style="background: var(--bg-card); border: 2px dashed #3d6bff; box-shadow: 0 20px 60px rgba(0,0,0,.35);">
            <i class="fas fa-cloud-arrow-up text-5xl text-blue-400 mb-3 ak-blue"></i>
            <p class="text-base font-bold" style="color: var(--text-primary);">Drop files to upload</p>
            <p class="text-xs mt-1" style="color: var(--text-faint);"
               x-text="folder ? 'Files will be added to “' + ((folders.find(f => f.slug === folder) || { name: folder }).name) + '”' : 'Files will be added to Unfiled'"></p>
        </div>
    </div>


    {{-- Header --}}
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight" style="color: var(--text-primary);">Asset Vault</h1>
            <p class="text-sm mt-1" style="color: var(--text-faint);">
                Centralised storage for admin uploads, organised into folders, backed by local disk or S3.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2.5 py-1.5 rounded-lg"
                  :class="storage.is_s3 ? 'text-emerald-300 ak-green' : 'text-blue-300 ak-blue'"
                  :style="(storage.is_s3 ? 'background: rgba(16,185,129,0.10); border: 1px solid rgba(16,185,129,0.25);' : 'background: rgba(61,107,255,0.10); border: 1px solid rgba(61,107,255,0.25);')">
                <i :class="storage.is_s3 ? 'fab fa-aws' : 'fas fa-server'"></i>
                <span x-text="storage.is_s3 ? 'AWS S3' : 'Local Disk'"></span>
                <span class="opacity-60">·</span>
                <span x-text="storage.disk"></span>
            </span>
            <button @click="newFolderModal = true"
                    class="px-3 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 transition-all"
                    style="background: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                <i class="fas fa-folder-plus"></i> New Folder
            </button>
            <button @click="importModal = true"
                    class="px-3 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 transition-all"
                    style="background: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-primary);">
                <i class="fas fa-file-zipper"></i> Import Zip
            </button>
            <button @click="$refs.fileInput.click()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 transition-all shadow-sm">
                <i class="fas fa-cloud-upload-alt"></i> Upload
            </button>
            <input type="file" x-ref="fileInput" @change="handleFiles($event)" multiple class="hidden">
            <input type="file" x-ref="zipInput" accept=".zip,application/zip" @change="zipFile = $event.target.files[0] || null" class="hidden">
        </div>
    </div>

    {{-- Stat tiles --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="rounded-xl p-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <p class="text-[10px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Total assets</p>
            <p class="text-2xl font-bold" style="color: var(--text-primary);" x-text="storage.file_count"></p>
        </div>
        <div class="rounded-xl p-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <p class="text-[10px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Storage used</p>
            <p class="text-2xl font-bold" style="color: var(--text-primary);" x-text="storage.total_human"></p>
        </div>
        <div class="rounded-xl p-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <p class="text-[10px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Folders</p>
            <p class="text-2xl font-bold" style="color: var(--text-primary);" x-text="folders.filter(f => !f.system).length"></p>
        </div>
        <div class="rounded-xl p-4" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <p class="text-[10px] font-bold uppercase tracking-wider mb-1" style="color: var(--text-faint);">Driver</p>
            <p class="text-2xl font-bold capitalize" style="color: var(--text-primary);" x-text="storage.driver"></p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-5">

        {{-- Folder sidebar --}}
        <aside class="rounded-xl overflow-hidden" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
            <div class="px-4 py-3 flex items-center justify-between" style="border-bottom: 1px solid var(--border-subtle);">
                <span class="text-xs font-bold uppercase tracking-wider" style="color: var(--text-faint);">Folders</span>
                <button @click="newFolderModal = true" class="text-xs text-blue-400 hover:text-blue-300 ak-blue" title="New folder">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <div class="p-2 max-h-[480px] overflow-y-auto">
                <button @click="folder = ''; load(1)"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-all"
                        :class="folder === '' ? 'bg-blue-500/15 text-blue-200 ak-blue' : 'hover:bg-white/5'"
                        :style="folder !== '' ? 'color: var(--text-secondary);' : ''">
                    <span class="flex items-center gap-2"><i class="fas fa-layer-group text-xs"></i> All assets</span>
                    <span class="text-[10px] opacity-70" x-text="storage.file_count"></span>
                </button>

                <template x-for="f in folders" :key="f.slug">
                    <div class="group flex items-center">
                        <button @click="folder = f.slug; load(1)"
                                class="flex-1 flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-all min-w-0"
                                :class="folder === f.slug ? 'bg-blue-500/15 text-blue-200 ak-blue' : 'hover:bg-white/5'"
                                :style="folder !== f.slug ? 'color: var(--text-secondary);' : ''">
                            <span class="flex items-center gap-2 min-w-0">
                                <i class="fas text-xs" :class="f.system ? 'fa-inbox' : 'fa-folder'"></i>
                                <span class="truncate" x-text="f.name"></span>
                            </span>
                            <span class="text-[10px] opacity-70 flex-shrink-0 ml-2" x-text="f.count"></span>
                        </button>
                        <template x-if="!f.system">
                            <button @click="deleteFolder(f)" title="Delete folder"
                                    class="opacity-0 group-hover:opacity-100 text-xs text-red-400 hover:text-red-300 px-2 transition-opacity ak-red">
                                <i class="fas fa-trash"></i>
                            </button>
                        </template>
                    </div>
                </template>
            </div>
        </aside>

        {{-- Main panel --}}
        <div class="space-y-4">
            {{-- Toolbar --}}
            <div class="rounded-xl p-3 flex flex-col md:flex-row items-stretch md:items-center gap-3"
                 style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                <div class="relative flex-1">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color: var(--text-faint);"></i>
                    <input type="text" x-model="search" @input.debounce.300ms="load(1)" placeholder="Search by name, label, or description"
                           class="w-full pl-9 pr-3 py-2 text-sm rounded-lg"
                           style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                </div>
                <div class="flex items-center gap-2 overflow-x-auto">
                    <template x-for="t in types" :key="t.k">
                        <button @click="type = t.k; load(1)"
                                class="text-xs px-3 py-1.5 rounded-lg font-medium whitespace-nowrap transition-all"
                                :class="type === t.k ? 'text-white bg-blue-600' : ''"
                                :style="type === t.k ? '' : 'background: var(--bg-glass); color: var(--text-faint); border: 1px solid var(--border-subtle);'">
                            <i class="mr-1" :class="t.icon"></i>
                            <span x-text="t.label"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Breadcrumb --}}
            <div class="flex items-center gap-2 text-xs" style="color: var(--text-faint);">
                <i class="fas fa-folder-open"></i>
                <button @click="folder = ''; load(1)" class="hover:text-blue-400">Asset Vault</button>
                <template x-if="folder">
                    <span class="flex items-center gap-2">
                        <i class="fas fa-chevron-right text-[8px]"></i>
                        <span class="font-semibold" style="color: var(--text-secondary);"
                              x-text="(folders.find(f => f.slug === folder) || { name: folder }).name"></span>
                    </span>
                </template>
            </div>

            {{-- Zip import progress / summary panel --}}
            <div x-show="importPanel" x-cloak class="rounded-xl p-4 space-y-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                <template x-if="activeImport">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <div class="animate-spin w-4 h-4 border-2 border-blue-400 border-t-transparent rounded-full flex-shrink-0"></div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold" style="color: var(--text-primary);"
                                   x-text="activeImport.status === 'downloading' ? 'Downloading archive…' : (activeImport.status === 'pending' ? 'Waiting for a worker…' : 'Extracting archive…')"></p>
                                <p class="text-xs truncate" style="color: var(--text-faint);" x-text="activeImport.source || ''"></p>
                            </div>
                            <span class="ml-auto text-xs font-semibold flex-shrink-0" style="color: var(--text-secondary);"
                                  x-text="activeImport.total_entries > 0 ? (activeImport.processed_entries + ' / ' + activeImport.total_entries + ' entries') : ''"></span>
                        </div>
                        <div class="h-2 rounded-full overflow-hidden" style="background: var(--bg-glass-input);">
                            <div class="h-full rounded-full bg-blue-500 transition-all duration-500"
                                 :style="'width:' + (activeImport.total_entries > 0 ? Math.round(activeImport.processed_entries / activeImport.total_entries * 100) : 3) + '%'"></div>
                        </div>
                        <div class="flex items-center justify-between mt-1.5 gap-2">
                            <p class="text-[11px]" style="color: var(--text-faint);"
                               x-text="activeImport.imported_count + ' imported · ' + activeImport.overwritten_count + ' overwritten · ' + activeImport.skipped_count + ' skipped'"></p>
                            <button @click="cancelImport()" :disabled="cancellingImport"
                                    class="text-[11px] font-semibold text-red-400 hover:text-red-300 ak-red flex-shrink-0 disabled:opacity-50">
                                <span x-show="!cancellingImport">Cancel import</span>
                                <span x-show="cancellingImport" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i> Cancelling…</span>
                            </button>
                        </div>
                    </div>
                </template>
                <template x-if="!activeImport && lastImport">
                    <div>
                        <div class="flex items-start gap-3">
                            <i class="mt-0.5" :class="lastImport.status === 'completed' ? 'fas fa-check-circle text-emerald-400 ak-green' : (isCancelled(lastImport) ? 'fas fa-ban text-slate-400 ak-muted' : 'fas fa-triangle-exclamation text-red-400 ak-red')"></i>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold" style="color: var(--text-primary);"
                                   x-text="lastImport.status === 'completed' ? 'Zip import finished' : (isCancelled(lastImport) ? 'Import cancelled' : 'Zip import failed')"></p>
                                <p class="text-xs truncate" style="color: var(--text-faint);" x-text="lastImport.source || ''"></p>
                                <template x-if="lastImport.status === 'completed'">
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);"
                                       x-text="lastImport.imported_count + ' imported · ' + lastImport.overwritten_count + ' overwritten · ' + lastImport.skipped_count + ' skipped of ' + lastImport.total_entries + ' entries'"></p>
                                </template>
                                <template x-if="isCancelled(lastImport)">
                                    <p class="text-xs mt-1" style="color: var(--text-secondary);"
                                       x-text="'This import was stopped by an admin. ' + ((lastImport.imported_count + lastImport.overwritten_count) === 1 ? '1 file' : (lastImport.imported_count + lastImport.overwritten_count) + ' files') + ' already imported ' + ((lastImport.imported_count + lastImport.overwritten_count) === 1 ? 'was' : 'were') + ' kept (' + lastImport.imported_count + ' new · ' + lastImport.overwritten_count + ' overwritten · ' + lastImport.skipped_count + ' skipped).'"></p>
                                </template>
                                <template x-if="lastImport.error && !isCancelled(lastImport)">
                                    <p class="text-xs mt-1 text-red-400 ak-red" x-text="lastImport.error"></p>
                                </template>
                                <template x-if="lastImport.status === 'failed' && lastImport.source_type === 'url'">
                                    <div class="mt-2">
                                        <button @click="retryImport(lastImport)" :disabled="retrySubmitting"
                                                class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-blue-500/15 text-blue-400 hover:bg-blue-500/25 ak-blue disabled:opacity-50">
                                            <i class="fas fa-rotate-right mr-1"></i>
                                            <span x-text="retrySubmitting ? 'Retrying…' : 'Retry import'"></span>
                                        </button>
                                        <p class="text-[11px] mt-1" style="color: var(--text-faint);">Already-imported files are skipped or overwritten per the original mode, so the retry picks up where it stopped.</p>
                                    </div>
                                </template>
                                <template x-if="lastImport.status === 'failed' && lastImport.source_type === 'upload'">
                                    <p class="text-[11px] mt-2" style="color: var(--text-faint);">The uploaded zip file was removed after the run, so this import can't be retried automatically: please re-upload the archive to run it again.</p>
                                </template>
                                <template x-if="(lastImport.skipped || []).length">
                                    <div class="mt-2">
                                        <button @click="showSkipped = !showSkipped" class="text-[11px] font-semibold text-blue-400 hover:text-blue-300 ak-blue">
                                            <span x-text="(showSkipped ? 'Hide' : 'Show') + ' skipped entries (' + lastImport.skipped.length + (lastImport.skipped_count > lastImport.skipped.length ? ' of ' + lastImport.skipped_count : '') + ')'"></span>
                                        </button>
                                        <div x-show="showSkipped" x-cloak class="mt-1.5 max-h-40 overflow-y-auto rounded-lg p-2 space-y-1"
                                             style="background: var(--bg-glass-input); border: 1px solid var(--border-subtle);">
                                            <template x-for="(s, i) in lastImport.skipped" :key="i">
                                                <p class="text-[11px] flex gap-2" style="color: var(--text-faint);">
                                                    <span class="truncate flex-1" x-text="s.path"></span>
                                                    <span class="flex-shrink-0" style="color: var(--text-secondary);" x-text="s.reason"></span>
                                                </p>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <button @click="importPanel = false" class="text-xs flex-shrink-0" style="color: var(--text-faint);" title="Dismiss">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <div x-show="uploading" x-cloak class="rounded-xl p-3" style="background: var(--bg-card); border: 1px solid var(--border-subtle);">
                <div class="flex items-center gap-3 mb-2">
                    <div class="animate-spin w-4 h-4 border-2 border-blue-400 border-t-transparent rounded-full"></div>
                    <span class="text-sm font-medium" style="color: var(--text-secondary);"
                          x-text="'Uploading ' + uploadDone + ' / ' + uploadTotal"></span>
                </div>
            </div>

            <div x-show="loading" x-cloak class="text-center py-10 text-sm" style="color: var(--text-faint);">
                <i class="fas fa-spinner fa-spin mr-2"></i> Loading…
            </div>

            <div x-show="!loading && assets.length === 0" x-cloak
                 @click="$refs.fileInput.click()"
                 class="text-center py-16 rounded-xl cursor-pointer transition-all hover:border-blue-500/50"
                 style="background: var(--bg-card); border: 2px dashed var(--border-glass);">
                <i class="fas fa-cloud-arrow-up text-4xl mb-3 text-blue-400 ak-blue"></i>
                <p class="text-sm font-semibold" style="color: var(--text-secondary);">Drag &amp; drop files here</p>
                <p class="text-xs mt-1" style="color: var(--text-faint);">or click anywhere in this box to browse</p>
            </div>

            {{-- Bulk selection bar --}}
            <div x-show="selected.length > 0" x-cloak
                 class="rounded-xl p-3 flex flex-wrap items-center gap-3"
                 style="background: rgba(61,107,255,0.10); border: 1px solid rgba(61,107,255,0.35);">
                <span class="text-sm font-semibold" style="color: var(--text-primary);"
                      x-text="selected.length + ' selected'"></span>
                <button @click="selectAllOnPage()" class="text-xs font-semibold text-blue-400 hover:text-blue-300 ak-blue">Select page</button>
                <button @click="selected = []" class="text-xs font-semibold" style="color: var(--text-faint);">Clear</button>
                <div class="ml-auto flex items-center gap-2">
                    <button @click="openBulkEdit()"
                            class="px-3 py-1.5 text-xs rounded-lg font-semibold bg-blue-600 hover:bg-blue-700 text-white">
                        <i class="fas fa-pen mr-1"></i> Bulk edit
                    </button>
                </div>
            </div>

            <div x-show="!loading && assets.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                <template x-for="a in assets" :key="a.id">
                    <div class="group relative rounded-xl overflow-hidden transition-all hover:-translate-y-0.5"
                         :style="'background: var(--bg-card); border: 1px solid ' + (selected.includes(a.id) ? '#3d6bff' : 'var(--border-subtle)') + ';'">
                        <div class="aspect-square flex items-center justify-center cursor-pointer"
                             style="background: var(--bg-glass-input);"
                             @click="selected.length > 0 && toggleSelect(a)">
                            <template x-if="a.type === 'image'">
                                <img :src="a.url" :alt="a.original_name" class="w-full h-full object-cover" loading="lazy">
                            </template>
                            <template x-if="a.type === 'video'"><i class="fas fa-film text-3xl text-blue-400 ak-blue"></i></template>
                            <template x-if="a.type === 'audio'"><i class="fas fa-music text-3xl text-pink-400"></i></template>
                            <template x-if="a.type === 'document'"><i class="fas fa-file-lines text-3xl text-cyan-400 ak-blue"></i></template>
                            <template x-if="a.type === 'archive'"><i class="fas fa-file-zipper text-3xl text-amber-400 ak-amber"></i></template>
                            <template x-if="a.type === 'other'"><i class="fas fa-file text-3xl text-slate-400 ak-muted"></i></template>
                        </div>
                        <div class="p-2.5">
                            <p class="text-xs font-semibold truncate" :title="a.original_name" style="color: var(--text-primary);" x-text="a.label || a.original_name"></p>
                            <p class="text-[10px] mt-0.5 flex items-center gap-1.5" style="color: var(--text-faint);">
                                <span x-text="a.size_human"></span>
                                <template x-if="a.dimensions"><span class="flex items-center gap-1.5"><span>·</span><span x-text="a.dimensions"></span></span></template>
                                <template x-if="!a.dimensions"><span class="flex items-center gap-1.5"><span>·</span><span x-text="a.type"></span></span></template>
                                <template x-if="a.folder"><span class="ml-auto truncate" x-text="a.folder"></span></template>
                            </p>
                        </div>
                        {{-- Selection checkbox --}}
                        <button @click.stop="toggleSelect(a)" :title="selected.includes(a.id) ? 'Deselect' : 'Select'"
                                class="absolute top-1.5 left-1.5 w-6 h-6 rounded-md flex items-center justify-center text-[11px] transition-opacity"
                                :class="selected.includes(a.id) ? 'opacity-100' : 'opacity-0 group-hover:opacity-100 asset-actions'"
                                :style="selected.includes(a.id) ? 'background: #3d6bff; color: #fff;' : 'background: rgba(0,0,0,0.55); color: #fff; border: 1px solid rgba(255,255,255,0.4);'">
                            <i class="fas fa-check" x-show="selected.includes(a.id)"></i>
                        </button>
                        <div class="asset-actions absolute top-1.5 right-1.5 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button @click="openMove(a)" title="Move to folder"
                                    class="w-7 h-7 rounded-md flex items-center justify-center text-xs"
                                    style="background: rgba(0,0,0,0.55); color: #fff;">
                                <i class="fas fa-folder-tree"></i>
                            </button>
                            <button @click="copyUrl(a)" title="Copy URL"
                                    class="w-7 h-7 rounded-md flex items-center justify-center text-xs"
                                    style="background: rgba(0,0,0,0.55); color: #fff;">
                                <i class="fas fa-link"></i>
                            </button>
                            <a :href="a.url" target="_blank" title="Open"
                               class="w-7 h-7 rounded-md flex items-center justify-center text-xs"
                               style="background: rgba(0,0,0,0.55); color: #fff;">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                            <button @click="remove(a)" title="Delete"
                                    class="w-7 h-7 rounded-md flex items-center justify-center text-xs"
                                    style="background: rgba(220,38,38,0.85); color: #fff;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <div x-show="!loading && pagination.last_page > 1" class="flex items-center justify-center gap-2 pt-2">
                <button @click="load(pagination.current_page - 1)" :disabled="pagination.current_page <= 1"
                        class="px-3 py-1.5 text-xs rounded-lg disabled:opacity-40"
                        style="background: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-secondary);">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="text-xs" style="color: var(--text-faint);"
                      x-text="'Page ' + pagination.current_page + ' of ' + pagination.last_page + ' (' + pagination.total + ' files)'"></span>
                <button @click="load(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page"
                        class="px-3 py-1.5 text-xs rounded-lg disabled:opacity-40"
                        style="background: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-secondary);">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- New folder modal --}}
    <div x-show="newFolderModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.55);">
        <div @click.outside="newFolderModal = false" class="w-full max-w-sm rounded-xl p-5"
             style="background: var(--bg-card); border: 1px solid var(--border-strong);">
            <h3 class="text-base font-bold mb-3" style="color: var(--text-primary);">New folder</h3>
            <input x-model="newFolderName" @keydown.enter="createFolder()" type="text" placeholder="e.g. Brand Logos"
                   class="w-full px-3 py-2 text-sm rounded-lg mb-4"
                   style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
            <div class="flex justify-end gap-2">
                <button @click="newFolderModal = false; newFolderName = ''"
                        class="px-3 py-2 text-sm rounded-lg"
                        style="background: var(--bg-glass); border: 1px solid var(--border-subtle); color: var(--text-secondary);">Cancel</button>
                <button @click="createFolder()" class="px-3 py-2 text-sm rounded-lg bg-blue-600 hover:bg-blue-700 text-white">Create</button>
            </div>
        </div>
    </div>

    {{-- Import zip modal --}}
    <div x-show="importModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.55);">
        <div @click.outside="importModal = false" class="w-full max-w-md rounded-xl p-5"
             style="background: var(--bg-card); border: 1px solid var(--border-strong);">
            <h3 class="text-base font-bold mb-1" style="color: var(--text-primary);">Import zip archive</h3>
            <p class="text-xs mb-4" style="color: var(--text-faint);">
                Extract images from a zip into the vault. Folders inside the archive become vault folders.
                For very large archives (over the upload limit), use the URL / S3 option.
            </p>

            <div class="flex gap-2 mb-4">
                <button @click="importTab = 'upload'"
                        class="flex-1 text-xs px-3 py-2 rounded-lg font-semibold transition-all"
                        :class="importTab === 'upload' ? 'bg-blue-600 text-white' : ''"
                        :style="importTab === 'upload' ? '' : 'background: var(--bg-glass); color: var(--text-faint); border: 1px solid var(--border-subtle);'">
                    <i class="fas fa-upload mr-1"></i> Upload zip
                </button>
                <button @click="importTab = 'url'"
                        class="flex-1 text-xs px-3 py-2 rounded-lg font-semibold transition-all"
                        :class="importTab === 'url' ? 'bg-blue-600 text-white' : ''"
                        :style="importTab === 'url' ? '' : 'background: var(--bg-glass); color: var(--text-faint); border: 1px solid var(--border-subtle);'">
                    <i class="fas fa-link mr-1"></i> From URL / S3
                </button>
            </div>

            <template x-if="importTab === 'upload'">
                <div @click="$refs.zipInput.click()"
                     class="rounded-xl p-5 text-center cursor-pointer mb-4 transition-all hover:border-blue-500/50"
                     style="background: var(--bg-glass-input); border: 2px dashed var(--border-glass);">
                    <template x-if="!zipFile">
                        <div>
                            <i class="fas fa-file-zipper text-2xl text-blue-400 mb-2 ak-blue"></i>
                            <p class="text-xs font-semibold" style="color: var(--text-secondary);">Click to choose a .zip file</p>
                        </div>
                    </template>
                    <template x-if="zipFile">
                        <p class="text-xs font-semibold truncate" style="color: var(--text-primary);"
                           x-text="zipFile.name + ' (' + (zipFile.size / 1048576).toFixed(1) + ' MB)'"></p>
                    </template>
                </div>
            </template>
            <template x-if="importTab === 'url'">
                <div class="mb-4">
                    <input x-model="importUrl" type="text"
                           placeholder="https://example.com/assets.zip or s3://bucket/path.zip"
                           class="w-full px-3 py-2 text-sm rounded-lg"
                           style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                    <p class="text-[11px] mt-1.5" style="color: var(--text-faint);">
                        The archive is downloaded on the server, so this handles multi-GB files (up to 4 GB).
                    </p>
                </div>
            </template>

            <label class="flex items-center gap-2 mb-4 text-xs cursor-pointer" style="color: var(--text-secondary);">
                <input type="checkbox" x-model="importOverwrite" class="rounded">
                Overwrite files already imported from the same archive paths (default: skip duplicates)
            </label>

            <div class="flex justify-end gap-2">
                <button @click="importModal = false"
                        class="px-3 py-2 text-sm rounded-lg"
                        style="background: var(--bg-glass); border: 1px solid var(--border-subtle); color: var(--text-secondary);">Cancel</button>
                <button @click="startImport()" :disabled="importSubmitting"
                        class="px-3 py-2 text-sm rounded-lg bg-blue-600 hover:bg-blue-700 text-white disabled:opacity-50">
                    <span x-show="!importSubmitting">Start import</span>
                    <span x-show="importSubmitting" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i> Starting…</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Copy toast --}}
    <div x-show="toast" x-transition x-cloak class="vault-toast">
        <i class="fas fa-check-circle"></i>
        <span x-text="toast"></span>
    </div>

    {{-- Bulk edit modal --}}
    <div x-show="bulkModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.55);">
        <div @click.outside="bulkModal = false" class="w-full max-w-md rounded-xl p-5"
             style="background: var(--bg-card); border: 1px solid var(--border-strong);">
            <h3 class="text-base font-bold mb-1" style="color: var(--text-primary);">Bulk edit</h3>
            <p class="text-xs mb-4" style="color: var(--text-faint);"
               x-text="'Applies to ' + selected.length + ' selected asset(s). Only ticked fields are changed.'"></p>

            <label class="flex items-center gap-2 text-xs font-semibold mb-1.5 cursor-pointer" style="color: var(--text-secondary);">
                <input type="checkbox" x-model="bulk.applyLabel" class="rounded"> Label
            </label>
            <input x-model="bulk.label" :disabled="!bulk.applyLabel" type="text" placeholder="e.g. Hero background"
                   class="w-full px-3 py-2 text-sm rounded-lg mb-3 disabled:opacity-40"
                   style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">

            <label class="flex items-center gap-2 text-xs font-semibold mb-1.5 cursor-pointer" style="color: var(--text-secondary);">
                <input type="checkbox" x-model="bulk.applyDescription" class="rounded"> Description
            </label>
            <textarea x-model="bulk.description" :disabled="!bulk.applyDescription" rows="2" placeholder="Shown in search results"
                      class="w-full px-3 py-2 text-sm rounded-lg mb-3 disabled:opacity-40"
                      style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);"></textarea>

            <label class="flex items-center gap-2 text-xs font-semibold mb-1.5 cursor-pointer" style="color: var(--text-secondary);">
                <input type="checkbox" x-model="bulk.applyFolder" class="rounded"> Move to folder
            </label>
            <select x-model="bulk.folder" :disabled="!bulk.applyFolder"
                    class="w-full px-3 py-2 text-sm rounded-lg mb-4 disabled:opacity-40"
                    style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                <option value="">Unfiled</option>
                <template x-for="f in folders.filter(f => !f.system)" :key="f.slug">
                    <option :value="f.slug" x-text="f.name"></option>
                </template>
            </select>

            <div class="flex justify-end gap-2">
                <button @click="bulkModal = false"
                        class="px-3 py-2 text-sm rounded-lg"
                        style="background: var(--bg-glass); border: 1px solid var(--border-subtle); color: var(--text-secondary);">Cancel</button>
                <button @click="commitBulkEdit()" :disabled="bulkSubmitting || (!bulk.applyLabel && !bulk.applyDescription && !bulk.applyFolder)"
                        class="px-3 py-2 text-sm rounded-lg bg-blue-600 hover:bg-blue-700 text-white disabled:opacity-50">
                    <span x-show="!bulkSubmitting">Apply</span>
                    <span x-show="bulkSubmitting" x-cloak><i class="fas fa-spinner fa-spin mr-1"></i> Applying…</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Move modal --}}
    <div x-show="moveModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.55);">
        <div @click.outside="moveModal = false" class="w-full max-w-sm rounded-xl p-5"
             style="background: var(--bg-card); border: 1px solid var(--border-strong);">
            <h3 class="text-base font-bold mb-3" style="color: var(--text-primary);">Move asset</h3>
            <p class="text-xs mb-3 truncate" style="color: var(--text-faint);" x-text="moveAsset?.original_name || ''"></p>
            <select x-model="moveTarget"
                    class="w-full px-3 py-2 text-sm rounded-lg mb-4"
                    style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-primary);">
                <option value="">Unfiled</option>
                <template x-for="f in folders.filter(f => !f.system)" :key="f.slug">
                    <option :value="f.slug" x-text="f.name"></option>
                </template>
            </select>
            <div class="flex justify-end gap-2">
                <button @click="moveModal = false"
                        class="px-3 py-2 text-sm rounded-lg"
                        style="background: var(--bg-glass); border: 1px solid var(--border-subtle); color: var(--text-secondary);">Cancel</button>
                <button @click="commitMove()" class="px-3 py-2 text-sm rounded-lg bg-blue-600 hover:bg-blue-700 text-white">Move</button>
            </div>
        </div>
    </div>

</div>

<script>
function adminAssetVault() {
    return {
        types: [
            { k: 'all',      label: 'All',       icon: 'fas fa-layer-group' },
            { k: 'image',    label: 'Images',    icon: 'fas fa-image' },
            { k: 'video',    label: 'Videos',    icon: 'fas fa-film' },
            { k: 'audio',    label: 'Audio',     icon: 'fas fa-music' },
            { k: 'document', label: 'Documents', icon: 'fas fa-file-lines' },
            { k: 'archive',  label: 'Archives',  icon: 'fas fa-file-zipper' },
            { k: 'other',    label: 'Other',     icon: 'fas fa-file' },
        ],
        type: @json($type ?? 'all'),
        search: @json($search ?? ''),
        folder: @json($folder ?? ''),
        folders: @json($folders ?? []),
        assets: [],
        pagination: { current_page: 1, last_page: 1, total: 0 },
        storage: @json($storage),
        loading: false,
        uploading: false,
        uploadTotal: 0,
        uploadDone: 0,

        newFolderModal: false,
        newFolderName: '',

        dragOver: false,
        dragDepth: 0,

        toast: '',
        toastTimer: null,

        moveModal: false,
        moveAsset: null,
        moveTarget: '',

        selected: [],
        bulkModal: false,
        bulkSubmitting: false,
        bulk: { applyLabel: false, label: '', applyDescription: false, description: '', applyFolder: false, folder: '' },

        importModal: false,
        importTab: 'upload',
        zipFile: null,
        importUrl: '',
        importOverwrite: false,
        importSubmitting: false,
        cancellingImport: false,
        retrySubmitting: false,
        importPanel: false,
        activeImport: null,
        lastImport: null,
        showSkipped: false,
        importTimer: null,

        init() { this.load(1); this.pollImports(true); },

        async startImport() {
            if (this.importSubmitting) return;
            const fd = new FormData();
            if (this.importTab === 'upload') {
                if (!this.zipFile) { alert('Choose a .zip file first.'); return; }
                fd.append('file', this.zipFile);
            } else {
                const url = (this.importUrl || '').trim();
                if (!url) { alert('Enter a URL or s3:// location.'); return; }
                fd.append('source_url', url);
            }
            fd.append('mode', this.importOverwrite ? 'overwrite' : 'skip');
            this.importSubmitting = true;
            try {
                const r = await fetch(`{{ route('admin.assets.import-zip') }}`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: fd,
                });
                const data = await r.json();
                if (!data.success) { alert(data.error || 'Could not start the import.'); return; }
                this.importModal = false;
                this.zipFile = null;
                this.importUrl = '';
                this.activeImport = data.import;
                this.importPanel = true;
                this.scheduleImportPoll(1500);
            } catch (_) {
                alert('Could not start the import.');
            } finally {
                this.importSubmitting = false;
            }
        },

        // A cancelled run is either a dedicated 'cancelled' status or a
        // 'failed' row whose error records an admin cancellation.
        isCancelled(i) {
            if (!i) return false;
            return i.status === 'cancelled'
                || (i.status === 'failed' && /cancelled by (an )?admin/i.test(i.error || ''));
        },

        async cancelImport() {
            if (!this.activeImport || this.cancellingImport) return;
            if (!confirm('Cancel this import? Files already extracted will stay in the vault.')) return;
            this.cancellingImport = true;
            try {
                const r = await fetch(`{{ route('admin.assets.imports') }}/${this.activeImport.id}/cancel`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                });
                const data = await r.json();
                if (!data.success) { alert(data.error || 'Could not cancel the import.'); return; }
                await this.pollImports();
            } catch (_) {
                alert('Could not cancel the import.');
            } finally {
                this.cancellingImport = false;
            }
        },

        async retryImport(imp) {
            if (this.retrySubmitting || !imp) return;
            this.retrySubmitting = true;
            try {
                const url = `{{ route('admin.assets.imports') }}/${imp.id}/retry`;
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                });
                const data = await r.json();
                if (!data.success) { alert(data.error || 'Could not retry the import.'); return; }
                this.activeImport = data.import;
                this.lastImport = null;
                this.importPanel = true;
                this.scheduleImportPoll(1500);
            } catch (_) {
                alert('Could not retry the import.');
            } finally {
                this.retrySubmitting = false;
            }
        },

        scheduleImportPoll(ms) {
            if (this.importTimer) clearTimeout(this.importTimer);
            this.importTimer = setTimeout(() => this.pollImports(), ms);
        },

        async pollImports(initial = false) {
            try {
                const r = await fetch(`{{ route('admin.assets.imports') }}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await r.json();
                if (data.success) {
                    const imports = data.imports || [];
                    const active = imports.find(i => ['pending', 'downloading', 'processing'].includes(i.status)) || null;
                    const wasActive = !!this.activeImport;
                    this.activeImport = active;
                    if (active) {
                        this.importPanel = true;
                        this.scheduleImportPoll(2500);
                    } else {
                        this.lastImport = imports[0] || null;
                        if (wasActive && this.lastImport) {
                            // Import just finished under our feet — refresh the grid.
                            this.importPanel = true;
                            await this.load(1);
                        } else if (initial) {
                            // Nothing running; leave any old summary hidden on page load.
                            this.importPanel = false;
                        }
                    }
                }
            } catch (_) {
                if (this.activeImport) this.scheduleImportPoll(5000);
            }
        },

        async load(page = 1) {
            this.loading = true;
            try {
                const params = new URLSearchParams({ page, type: this.type, q: this.search, folder: this.folder });
                const r = await fetch(`{{ route('admin.assets.index') }}?${params.toString()}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await r.json();
                if (data.success) {
                    this.assets = data.assets;
                    this.pagination = data.pagination;
                    this.storage = data.storage;
                    this.folders = data.folders;
                }
            } finally { this.loading = false; }
        },

        async handleDrop(e) {
            const dt = e.dataTransfer;
            if (!dt) return;
            const files = [];
            if (dt.items) {
                for (const item of dt.items) {
                    if (item.kind === 'file') {
                        const f = item.getAsFile();
                        if (f) files.push(f);
                    }
                }
            } else if (dt.files) {
                for (const f of dt.files) files.push(f);
            }
            if (files.length) await this.uploadFiles(files);
        },

        async handleFiles(e) {
            const files = Array.from(e.target.files || []);
            if (!files.length) return;
            await this.uploadFiles(files);
            e.target.value = '';
        },

        async uploadFiles(files) {
            this.uploading = true;
            this.uploadTotal = files.length;
            this.uploadDone = 0;
            for (const f of files) {
                const fd = new FormData();
                fd.append('file', f);
                if (this.folder && this.folder !== '__root__') fd.append('folder', this.folder);
                try {
                    const r = await fetch(`{{ route('admin.assets.upload') }}`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        body: fd,
                    });
                    const data = await r.json();
                    if (!data.success) alert(data.error || 'Upload failed');
                    else this.storage = data.storage;
                } catch (_) { alert('Upload failed'); }
                this.uploadDone++;
            }
            this.uploading = false;
            await this.load(1);
        },

        async createFolder() {
            const name = (this.newFolderName || '').trim();
            if (!name) return;
            const fd = new FormData();
            fd.append('name', name);
            const r = await fetch(`{{ route('admin.assets.folders.store') }}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: fd,
            });
            const data = await r.json();
            if (data.success) {
                this.folders = data.folders;
                this.folder = data.folder.slug;
                this.newFolderModal = false;
                this.newFolderName = '';
                await this.load(1);
            } else {
                alert(data.error || 'Could not create folder');
            }
        },

        async deleteFolder(f) {
            const hasFiles = f.count > 0;
            const msg = hasFiles
                ? `"${f.name}" contains ${f.count} file(s). Delete folder AND all files inside?`
                : `Delete empty folder "${f.name}"?`;
            if (!await window.themedConfirmAsync({
                title: hasFiles ? 'Delete folder and contents?' : 'Delete empty folder?',
                message: msg,
                confirmText: 'Delete',
                confirmIcon: 'fa-trash',
                iconClass: 'fa-folder-minus',
            })) return;
            const url = `/admin/assets/folders/${f.id}` + (hasFiles ? '?cascade=1' : '');
            const r = await fetch(url, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            });
            const data = await r.json();
            if (data.success) {
                this.folders = data.folders;
                this.storage = data.storage;
                if (this.folder === f.slug) this.folder = '';
                await this.load(1);
            } else {
                alert(data.error || 'Could not delete folder');
            }
        },

        toggleSelect(a) {
            if (this.selected.includes(a.id)) {
                this.selected = this.selected.filter(id => id !== a.id);
            } else {
                this.selected.push(a.id);
            }
        },

        selectAllOnPage() {
            const ids = new Set(this.selected);
            this.assets.forEach(a => ids.add(a.id));
            this.selected = [...ids];
        },

        openBulkEdit() {
            if (!this.selected.length) return;
            this.bulk = { applyLabel: false, label: '', applyDescription: false, description: '', applyFolder: false, folder: this.folder && this.folder !== '__root__' ? this.folder : '' };
            this.bulkModal = true;
        },

        async commitBulkEdit() {
            if (this.bulkSubmitting || !this.selected.length) return;
            this.bulkSubmitting = true;
            try {
                const r = await fetch(`{{ route('admin.assets.bulk-update') }}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        ids: this.selected,
                        apply_label: this.bulk.applyLabel ? 1 : 0,
                        label: this.bulk.label,
                        apply_description: this.bulk.applyDescription ? 1 : 0,
                        description: this.bulk.description,
                        apply_folder: this.bulk.applyFolder ? 1 : 0,
                        folder: this.bulk.folder,
                    }),
                });
                const data = await r.json();
                if (!data.success) {
                    alert(data.error || 'Bulk edit failed');
                    return;
                }
                this.bulkModal = false;
                if (data.folders) this.folders = data.folders;
                this.showToast('Updated ' + data.updated + ' asset(s)');
                this.selected = [];
                await this.load(this.pagination.current_page);
            } catch (_) {
                alert('Bulk edit failed');
            } finally {
                this.bulkSubmitting = false;
            }
        },

        openMove(a) {
            this.moveAsset = a;
            this.moveTarget = a.folder || '';
            this.moveModal = true;
        },

        async commitMove() {
            if (!this.moveAsset) return;
            const fd = new FormData();
            if (this.moveTarget) fd.append('folder', this.moveTarget);
            const r = await fetch(`/admin/assets/${this.moveAsset.id}/move`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: fd,
            });
            const data = await r.json();
            if (data.success) {
                this.moveModal = false;
                this.moveAsset = null;
                this.folders = data.folders;
                await this.load(this.pagination.current_page);
            } else {
                alert('Move failed');
            }
        },

        async remove(a) {
            if (!await window.themedConfirmAsync({
                title: 'Delete this asset?',
                message: `Delete "${a.original_name}"? This cannot be undone.`,
                confirmText: 'Delete',
                confirmIcon: 'fa-trash',
                iconClass: 'fa-trash',
            })) return;
            const r = await fetch(`/admin/assets/${a.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            });
            const data = await r.json();
            if (data.success) {
                this.assets = this.assets.filter(x => x.id !== a.id);
                this.storage = data.storage;
                this.folders = data.folders;
            }
        },

        async copyUrl(a) {
            const text = a.url;
            let ok = false;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                    ok = true;
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.focus(); ta.select();
                    ok = document.execCommand('copy');
                    document.body.removeChild(ta);
                }
            } catch (_) { ok = false; }
            this.showToast(ok ? 'URL copied to clipboard' : 'Copy failed, long-press to copy');
        },

        showToast(msg) {
            this.toast = msg;
            if (this.toastTimer) clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => { this.toast = ''; }, 2200);
        },
    }
}
</script>
@endsection
