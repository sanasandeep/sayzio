@extends('admin.layouts.app')
@section('title', $template ? 'Edit Theme Preset' : 'New Theme Preset')
@section('page-title', $template ? 'Edit Theme Preset' : 'New Theme Preset')

@section('content')
@php
    $styleJson = old('style_json', $template
        ? json_encode($template['style'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        : "{\n    \"bg_color\": \"#1a1a2e\",\n    \"text_color\": \"#ffffff\",\n    \"border_radius\": 14\n}");
@endphp
<div class="max-w-3xl">

    @if($errors->any())
        <div class="mb-4 p-3 rounded-xl border border-red-500/30 bg-red-500/10 text-red-200 text-sm ak-red">
            <i class="fas fa-exclamation-circle mr-1"></i> {{ $errors->first() }}
        </div>
    @endif

    <a href="{{ route('admin.block-designs.index') }}" class="inline-flex items-center gap-2 text-sm text-white/50 hover:text-white/80 mb-4 ak-muted">
        <i class="fas fa-arrow-left"></i> Back to Block Designs
    </a>

    <form method="POST" action="{{ route('admin.block-designs.templates.save') }}" class="glass rounded-2xl border border-white/10 p-6 space-y-5">
        @csrf
        @if($templateKey)
            <input type="hidden" name="key" value="{{ $templateKey }}">
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest mb-1.5" style="color: var(--text-faint);">Label</label>
                <input type="text" name="label" value="{{ old('label', $template['label'] ?? '') }}" required maxlength="40"
                       class="w-full text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/90">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest mb-1.5" style="color: var(--text-faint);">Font Awesome icon</label>
                <input type="text" name="icon" value="{{ old('icon', $template['icon'] ?? 'fa-swatchbook') }}" maxlength="40"
                       placeholder="fa-swatchbook"
                       class="w-full text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/90">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-widest mb-1.5" style="color: var(--text-faint);">Style</label>
            <p class="text-[11px] text-white/35 mb-2 ak-muted">
                Applied page-wide to every block when a user picks this preset. Same allowlist as the editor's
                Style tab &mdash; unknown or invalid values are dropped on save.
            </p>
            @include('admin.block-designs._style-editor', [
                'styleJson'      => $styleJson,
                'sampleLabel'    => 'Sample block',
                'showLinkLayout' => true,
            ])
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-white/80">
            <input type="checkbox" name="enabled" value="1" @checked((bool) old('enabled', $template['enabled'] ?? true))>
            Enabled (visible in users' Block Theme picker)
        </label>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold text-white" style="background: var(--accent, #2563eb);">
                {{ $template ? 'Save changes' : 'Create preset' }}
            </button>
            <a href="{{ route('admin.block-designs.index') }}" class="text-sm text-white/50 hover:text-white/80 ak-muted">Cancel</a>
        </div>
    </form>
</div>

<script>
(function () {
    var area = document.querySelector('textarea[name="style_json"]');
    var box = document.getElementById('bd-preview');
    window.__bdPreview = function () {
        var s;
        try { s = JSON.parse(area.value); } catch (e) { return; }
        if (!s || typeof s !== 'object') return;
        box.style.background = s.bg_color || '#1a1a2e';
        box.style.color = s.text_color || '#fff';
        box.style.borderRadius = (s.border_radius != null ? s.border_radius : 14) + 'px';
        if (s.border_color && s.border_style && s.border_style !== 'none') {
            box.style.border = (s.border_width || 1) + 'px ' + s.border_style + ' ' + s.border_color;
        } else {
            box.style.border = 'none';
        }
        if (s.shadow_type && s.shadow_type !== 'none' && s.shadow_color) {
            box.style.boxShadow = (s.shadow_x || 0) + 'px ' + (s.shadow_y || 4) + 'px ' + (s.shadow_blur != null ? s.shadow_blur : 12) + 'px ' + s.shadow_color;
        } else {
            box.style.boxShadow = 'none';
        }
        box.style.fontFamily = s.font_family || '';
        if (s.bg_opacity != null) box.style.opacity = Math.max(0.15, Math.min(1, s.bg_opacity / 100));
    };
    window.__bdPreview();
})();
</script>
@endsection
