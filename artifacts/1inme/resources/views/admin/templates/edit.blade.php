@extends('admin.layouts.app')
@section('title', 'Edit Template')
@section('page-title', 'Edit ' . ucfirst($kind) . ' Template, ' . $tpl->name)

@section('content')
<div class="max-w-7xl">
    <a href="{{ route('admin.templates.index', ['tab' => $kind]) }}" class="text-xs text-white/40 hover:text-white mb-4 inline-block ak-note"><i class="fas fa-arrow-left mr-1"></i>Back to templates</a>

    @if($kind === 'page')
        {{-- Visual design editor: loads the template in the real biolink editor
             (blocks, backgrounds, live preview) via a temporary draft page,
             embedded right here on the edit page. --}}
        <div class="mb-5 rounded-2xl overflow-hidden" x-data="{ open: false }"
             style="background: rgba(61,107,255,0.08); border: 1px solid rgba(61,107,255,0.3);">
            <div class="px-4 py-3.5 flex flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-semibold text-white ak-strong">
                        <i class="fas fa-wand-magic-sparkles mr-1.5 ak-blue" style="color:#90acff;"></i>Design editor
                    </div>
                    <div class="text-[11px] text-white/50 ak-note mt-0.5">
                        Edit this template's background and blocks visually with a live preview, the same editor users get. Use "Save to template" inside the editor to publish the design to users.
                    </div>
                </div>
                <a href="{{ route('admin.templates.design.session', ['id' => $tpl->id]) }}"
                   class="px-3 py-2 rounded-xl text-xs font-semibold text-white/70 ak-muted transition-all"
                   style="border: 1px solid rgba(61,107,255,0.35);">
                    <i class="fas fa-up-right-from-square mr-1.5"></i>Full screen
                </a>
                <button type="button" @click="open = !open" class="px-4 py-2 rounded-xl text-xs font-bold text-white transition-all"
                        style="background: linear-gradient(135deg, #3d6bff, #2f54d6);">
                    <i class="fas fa-pen-ruler mr-1.5"></i><span x-text="open ? 'Close editor' : 'Edit design here'"></span>
                </button>
            </div>
            <template x-if="open">
                <div style="border-top: 1px solid rgba(61,107,255,0.3);">
                    <iframe src="{{ route('admin.templates.design.session', ['id' => $tpl->id]) }}"
                            title="Template design editor"
                            class="w-full block bg-transparent"
                            style="height: min(78vh, 900px); border: 0;"></iframe>
                </div>
            </template>
        </div>
    @endif

    {{-- Two-column layout: the edit form on the left, a live rendered preview
         of the template (via the existing admin preview route) on the right.
         The preview reflects the SAVED design — basic-info fields don't change
         the rendered page — and stays sticky while scrolling the form. On
         narrow screens it collapses behind a toggle below the form. --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">
        <div class="lg:col-span-7 min-w-0">
            @include('admin.templates._form', ['tpl' => $tpl, 'categories' => $categories, 'plans' => $plans, 'kind' => $kind])
        </div>

        <div class="lg:col-span-5 min-w-0 lg:sticky lg:top-4" x-data="{ openPreview: window.matchMedia('(min-width: 1024px)').matches }">
            <div class="glass rounded-2xl border border-white/10 p-4">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-white ak-strong">
                            <i class="fas fa-eye mr-1.5 text-blue-300 ak-blue"></i>Live preview
                        </h3>
                        <p class="text-[11px] text-white/40 ak-note mt-0.5">Rendered from the saved design. Save a new snapshot or use the design editor, then reload to see changes.</p>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="button" @click="$refs.tplPreviewFrame.src = $refs.tplPreviewFrame.src"
                                class="device-switcher-btn-lite px-2.5 py-1.5 rounded-lg text-[11px] font-medium text-white/60 hover:text-white border border-white/10 bg-white/5 ak-muted"
                                title="Reload preview">
                            <i class="fas fa-rotate"></i>
                        </button>
                        <a href="{{ route('admin.templates.preview', ['kind' => $kind, 'id' => $tpl->id]) }}" target="_blank" rel="noopener"
                           class="px-2.5 py-1.5 rounded-lg text-[11px] font-medium text-white/60 hover:text-white border border-white/10 bg-white/5 ak-muted"
                           title="Open preview in a new tab">
                            <i class="fas fa-up-right-from-square"></i>
                        </a>
                        <button type="button" @click="openPreview = !openPreview"
                                class="lg:hidden px-2.5 py-1.5 rounded-lg text-[11px] font-medium text-white/60 hover:text-white border border-white/10 bg-white/5 ak-muted">
                            <span x-text="openPreview ? 'Hide' : 'Show'"></span>
                        </button>
                    </div>
                </div>

                {{-- Scaled iframe: rendered at a fixed 420px-wide viewport, then
                     transform-scaled to fit the panel. The ResizeObserver keeps the
                     scale correct across panel resizes and the mobile show toggle
                     (which mounts the panel at clientWidth 0). --}}
                <div x-show="openPreview" x-cloak>
                    <div class="relative rounded-xl overflow-hidden border border-white/10 bg-white"
                         style="height: min(72vh, 760px);"
                         x-data="{ s: 1, h: 760, calc() { var w = $el.clientWidth; if (w > 0) { this.s = w / 420; this.h = Math.ceil($el.clientHeight / this.s); } } }"
                         x-init="calc(); new ResizeObserver(() => calc()).observe($el)">
                        <iframe x-ref="tplPreviewFrame" id="tplPreviewFrame"
                                src="{{ route('admin.templates.preview', ['kind' => $kind, 'id' => $tpl->id]) }}"
                                title="{{ $tpl->name }} live preview"
                                loading="lazy"
                                class="absolute top-0 left-0 border-0 bg-white"
                                :style="'width: 420px; height: ' + h + 'px; transform: scale(' + s + '); transform-origin: top left;'"></iframe>
                    </div>
                    <p class="text-[10px] text-white/30 mt-2 text-center ak-note">Scaled to fit &middot; scroll inside the preview to see the full page</p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Auto-refresh the live preview after a design-session save. The inline
     design editor (and the full-screen editor tab) stamp
     localStorage['sayzio:tpl-design-saved:{kind}:{id}'] and postMessage the
     parent when "Save to template" is submitted; here we reload the preview
     iframe on that message, on a storage event from another tab, or when
     this tab regains focus after a newer save stamp. The manual reload
     button stays as a fallback. --}}
<script>
(function () {
    var KEY = 'sayzio:tpl-design-saved:{{ $kind }}:{{ (int) $tpl->id }}';
    var lastLoad = Date.now();
    function reload() {
        var f = document.getElementById('tplPreviewFrame');
        if (f) { lastLoad = Date.now(); f.src = f.src; }
    }
    function maybeReload() {
        var stamp = parseInt(localStorage.getItem(KEY) || '0', 10);
        if (stamp && stamp > lastLoad) reload();
    }
    window.addEventListener('message', function (e) {
        if (e.origin !== window.location.origin) return;
        var d = e.data || {};
        if (d && d.type === 'sayzio:template-design-saved'
            && String(d.kind || 'page') === @js($kind)
            && parseInt(d.templateId, 10) === {{ (int) $tpl->id }}) {
            reload();
        }
    });
    window.addEventListener('storage', function (e) { if (e.key === KEY) maybeReload(); });
    window.addEventListener('focus', maybeReload);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) maybeReload(); });
})();
</script>
@endsection
