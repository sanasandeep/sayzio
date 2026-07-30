@extends('admin.layouts.app')
@section('title', $variant ? 'Edit Design Variant' : 'New Design Variant')
@section('page-title', $variant ? 'Edit Design Variant' : 'New Design Variant')

@section('content')
@php
    $old = fn ($key, $default = null) => old($key, $variant[$key] ?? $default);
    $styleJson = old('style_json', $variant
        ? json_encode($variant['style'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
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

    <form method="POST" action="{{ route('admin.block-designs.variants.save') }}" class="glass rounded-2xl border border-white/10 p-6 space-y-5">
        @csrf
        @if($variant)
            <input type="hidden" name="key" value="{{ $variant['key'] }}">
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest mb-1.5" style="color: var(--text-faint);">Name</label>
                <input type="text" name="name" value="{{ $old('name') }}" required maxlength="60"
                       class="w-full text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/90">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest mb-1.5" style="color: var(--text-faint);">Shape</label>
                <select name="shape" class="w-full text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/80">
                    <option value="">(none)</option>
                    @foreach($shapes as $sKey => $sLabel)
                        <option value="{{ $sKey }}" @selected($old('shape') === $sKey)>{{ $sLabel }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-widest mb-1.5" style="color: var(--text-faint);">Theme tags</label>
            <div class="flex flex-wrap gap-2">
                @php $selectedTags = (array) old('tags', $variant['tags'] ?? []); @endphp
                @foreach($tags as $tKey => $tLabel)
                    <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-white/10 bg-white/5 text-xs text-white/70 cursor-pointer">
                        <input type="checkbox" name="tags[]" value="{{ $tKey }}" @checked(in_array($tKey, $selectedTags, true))>
                        {{ $tLabel }}
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-widest mb-1.5" style="color: var(--text-faint);">Applies to block types</label>
            <p class="text-[11px] text-white/35 mb-2 ak-muted">Leave all unchecked to offer this design on every block type.</p>
            <div class="flex flex-wrap gap-2 max-h-44 overflow-y-auto p-1">
                @php $selectedTypes = (array) old('types', $variant['types'] ?? []); @endphp
                @foreach($typeOptions as $t)
                    <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-white/10 bg-white/5 text-xs text-white/70 cursor-pointer">
                        <input type="checkbox" name="types[]" value="{{ $t }}" @checked(in_array($t, $selectedTypes, true))>
                        {{ $t }}
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold uppercase tracking-widest mb-1.5" style="color: var(--text-faint);">Style</label>
            <p class="text-[11px] text-white/35 mb-2 ak-muted">
                Same properties as the editor's Style tab. Unknown or invalid values are dropped on save.
            </p>
            @include('admin.block-designs._style-editor', [
                'styleJson'      => $styleJson,
                'sampleLabel'    => 'Sample button',
                'showLinkLayout' => true,
            ])
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-white/80">
            <input type="checkbox" name="enabled" value="1" @checked((bool) old('enabled', $variant['enabled'] ?? true))>
            Enabled (visible in users' Designs gallery)
        </label>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold text-white" style="background: var(--accent, #2563eb);">
                {{ $variant ? 'Save changes' : 'Create variant' }}
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
