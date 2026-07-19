@extends('admin.layouts.app')
@section('title', 'Android APK Releases')
@section('page-title', 'Android APK Releases')

@section('content')
<div class="space-y-6 max-w-5xl">

    <p class="text-sm text-white/50">
        Manage the Android APK hosted on the Sayzio domain. Upload a local file or pull from an EAS artifact URL.
        Only one release can be <strong class="text-white/70">live</strong> at a time — that is the file served to the public from <code class="text-xs bg-white/5 px-1.5 py-0.5 rounded">/android/download</code>.
    </p>

    @if(session('success'))
        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs flex items-center gap-2">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs flex items-center gap-2">
            <i class="fas fa-triangle-exclamation"></i> {{ session('error') }}
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- Storage info banner                                          --}}
    {{-- ============================================================ --}}
    <div class="glass rounded-2xl border border-white/10 p-4 flex flex-wrap items-center gap-4 text-xs text-white/50">
        <span><i class="fas fa-hard-drive mr-1 text-sky-400/70"></i> Storage disk: <strong class="text-white/70">{{ $diskName }}</strong></span>
        <span class="w-px h-4 bg-white/10"></span>
        <span><i class="fas fa-cloud mr-1 text-sky-400/70"></i> Driver: <strong class="text-white/70">{{ $isS3 ? 'S3' : 'local' }}</strong></span>
        @if($live)
            <span class="w-px h-4 bg-white/10"></span>
            <span>
                <i class="fas fa-circle-dot mr-1 text-emerald-400"></i> Live:
                <strong class="text-emerald-300">v{{ $live->version_name }}</strong>
                &mdash; {{ $live->size_human }}
            </span>
            <a href="{{ route('android.download') }}" target="_blank"
               class="ml-auto flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs bg-white/5 hover:bg-white/10 border border-white/10 text-white/70 transition-colors">
                <i class="fas fa-external-link-alt"></i> View public page
            </a>
        @else
            <span class="ml-auto text-amber-300/70 flex items-center gap-1.5">
                <i class="fas fa-triangle-exclamation"></i> No live release set — public page shows "not available".
            </span>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- Upload / Fetch forms                                         --}}
    {{-- ============================================================ --}}
    <div x-data="{ tab: 'upload' }" class="glass rounded-2xl border border-white/10 overflow-hidden">
        {{-- Tab bar --}}
        <div class="flex border-b border-white/10">
            <button @click="tab = 'upload'" :class="tab === 'upload' ? 'text-white border-b-2 border-indigo-400 bg-white/5' : 'text-white/40 hover:text-white/70'"
                    class="px-5 py-3 text-sm font-medium transition-colors flex items-center gap-2">
                <i class="fas fa-upload"></i> Upload APK
            </button>
            <button @click="tab = 'fetch'" :class="tab === 'fetch' ? 'text-white border-b-2 border-indigo-400 bg-white/5' : 'text-white/40 hover:text-white/70'"
                    class="px-5 py-3 text-sm font-medium transition-colors flex items-center gap-2">
                <i class="fas fa-link"></i> Fetch from URL
            </button>
        </div>

        {{-- Upload form --}}
        <div x-show="tab === 'upload'" class="p-6">
            @if($errors->has('apk_file') || $errors->has('version_name'))
                <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-xs">
                    <ul class="list-disc pl-4 space-y-0.5">
                        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ route('admin.android-apk.upload') }}"
                  enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-white/60 mb-1">APK file <span class="text-red-400">*</span></label>
                        <input type="file" name="apk_file" accept=".apk,application/vnd.android.package-archive"
                               class="w-full text-sm text-white/70 file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:bg-white/10 file:text-white/70 file:border-0 file:text-xs file:cursor-pointer bg-white/5 border border-white/10 rounded-xl px-3 py-1.5"
                               required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-white/60 mb-1">Version name <span class="text-red-400">*</span></label>
                        <input type="text" name="version_name" value="{{ old('version_name') }}" placeholder="e.g. 1.2.3"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder:text-white/25 focus:outline-none focus:border-indigo-400/50"
                               required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-white/60 mb-1">Build number</label>
                        <input type="text" name="build_number" value="{{ old('build_number') }}" placeholder="e.g. 42"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder:text-white/25 focus:outline-none focus:border-indigo-400/50">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-white/60 mb-1">Notes (optional)</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" placeholder="e.g. Beta build, internal only"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder:text-white/25 focus:outline-none focus:border-indigo-400/50">
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-white/60 cursor-pointer select-none">
                    <input type="checkbox" name="set_live" value="1" class="rounded border-white/20 bg-white/5 text-indigo-400">
                    Set as live immediately after upload
                </label>
                <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-medium transition-colors">
                    <i class="fas fa-upload mr-1.5"></i> Upload APK
                </button>
            </form>
        </div>

        {{-- Fetch from URL form --}}
        <div x-show="tab === 'fetch'" class="p-6">
            <p class="text-xs text-white/40 mb-4">
                Paste an EAS artifact URL or any direct APK download link. The server fetches and stores the file — no temp file left on disk.
                <strong class="text-white/50">Max 300 MB.</strong>
            </p>
            <form method="POST" action="{{ route('admin.android-apk.fetch') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-white/60 mb-1">Source URL <span class="text-red-400">*</span></label>
                    <input type="url" name="source_url" value="{{ old('source_url') }}" placeholder="https://expo.dev/artifacts/eas/…"
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder:text-white/25 focus:outline-none focus:border-indigo-400/50"
                           required>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-white/60 mb-1">Version name <span class="text-red-400">*</span></label>
                        <input type="text" name="version_name" value="{{ old('version_name') }}" placeholder="e.g. 1.2.3"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder:text-white/25 focus:outline-none focus:border-indigo-400/50"
                               required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-white/60 mb-1">Build number</label>
                        <input type="text" name="build_number" value="{{ old('build_number') }}" placeholder="e.g. 42"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder:text-white/25 focus:outline-none focus:border-indigo-400/50">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-white/60 mb-1">EAS Build ID</label>
                        <input type="text" name="eas_build_id" value="{{ old('eas_build_id') }}" placeholder="(optional)"
                               class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder:text-white/25 focus:outline-none focus:border-indigo-400/50">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-white/60 mb-1">Notes (optional)</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" placeholder="e.g. From EAS build #42"
                           class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-sm text-white placeholder:text-white/25 focus:outline-none focus:border-indigo-400/50">
                </div>
                <label class="flex items-center gap-2 text-sm text-white/60 cursor-pointer select-none">
                    <input type="checkbox" name="set_live" value="1" class="rounded border-white/20 bg-white/5 text-indigo-400">
                    Set as live immediately after fetch
                </label>
                <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-500 hover:bg-indigo-600 text-white text-sm font-medium transition-colors">
                    <i class="fas fa-download mr-1.5"></i> Fetch &amp; Store APK
                </button>
            </form>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- Releases history                                             --}}
    {{-- ============================================================ --}}
    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-white/10 flex items-center justify-between">
            <h3 class="font-semibold text-white text-sm flex items-center gap-2">
                <i class="fas fa-history text-indigo-400/70"></i> Release history
            </h3>
            <span class="text-xs text-white/40">{{ $releases->total() }} {{ Str::plural('release', $releases->total()) }}</span>
        </div>

        @if($releases->isEmpty())
            <div class="p-8 text-center text-white/40 text-sm">
                <i class="fas fa-box-open text-3xl mb-3 block opacity-50"></i>
                No APK releases yet. Upload or fetch one above.
            </div>
        @else
            <div class="divide-y divide-white/5">
                @foreach($releases as $release)
                    <div class="flex flex-wrap items-center gap-3 px-5 py-4 hover:bg-white/[0.02] transition-colors">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-white text-sm">v{{ $release->version_name }}</span>
                                @if($release->build_number)
                                    <span class="text-xs text-white/30">#{{ $release->build_number }}</span>
                                @endif
                                @if($release->is_live)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/15 border border-emerald-500/25 text-emerald-300">
                                        LIVE
                                    </span>
                                @endif
                            </div>
                            <div class="mt-0.5 flex flex-wrap gap-3 text-xs text-white/35">
                                <span><i class="fas fa-weight-hanging mr-1"></i>{{ $release->size_human }}</span>
                                <span><i class="fas fa-clock mr-1"></i>{{ $release->created_at->diffForHumans() }}</span>
                                @if($release->eas_build_id)
                                    <span title="EAS Build ID"><i class="fas fa-hammer mr-1"></i>{{ $release->eas_build_id }}</span>
                                @endif
                                @if($release->notes)
                                    <span class="italic">{{ Str::limit($release->notes, 80) }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            @if(!$release->is_live)
                                <form method="POST" action="{{ route('admin.android-apk.set-live', $release) }}"
                                      onsubmit="return confirm('Set v{{ $release->version_name }} as the live APK?')">
                                    @csrf @method('PUT')
                                    <button type="submit"
                                            class="px-3 py-1.5 rounded-lg text-xs bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/20 text-emerald-300 transition-colors">
                                        <i class="fas fa-circle-dot mr-1"></i> Set Live
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.android-apk.destroy', $release) }}"
                                      onsubmit="return confirm('Delete this APK release? The file will be removed from storage.')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="px-3 py-1.5 rounded-lg text-xs bg-red-500/10 hover:bg-red-500/20 border border-red-500/15 text-red-400 transition-colors">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            @else
                                <span class="px-3 py-1.5 text-xs text-white/20 italic">Currently serving</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if($releases->hasPages())
                <div class="px-5 py-3 border-t border-white/10">
                    {{ $releases->links() }}
                </div>
            @endif
        @endif
    </div>

</div>
@endsection
