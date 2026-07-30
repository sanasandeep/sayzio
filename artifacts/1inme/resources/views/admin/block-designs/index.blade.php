@extends('admin.layouts.app')
@section('title', 'Block Designs')
@section('page-title', 'Block Designs')

@section('content')
<div class="max-w-6xl">

    @if(session('success'))
        <div class="mb-4 p-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 text-sm ak-green">
            <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 p-3 rounded-xl border border-red-500/30 bg-red-500/10 text-red-200 text-sm ak-red">
            <i class="fas fa-exclamation-circle mr-1"></i> {{ $errors->first() }}
        </div>
    @endif

    {{-- Header --}}
    <div class="glass rounded-2xl border border-white/10 p-6 mb-5">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h2 class="text-lg font-semibold text-white/90 ak-strong">Block Designs</h2>
                <p class="text-sm text-white/50 mt-1 max-w-2xl ak-muted">
                    Manage the Designs gallery variants and global Block Theme presets every user sees in the
                    biolink editor. Built-in designs are code-defined &mdash; they can be hidden but never deleted,
                    so pages already using them keep rendering. Custom designs appear instantly, no deploy needed.
                </p>
            </div>
            <div class="flex-shrink-0 px-3 py-1.5 rounded-xl text-sm font-semibold"
                 style="background: rgba(14,165,233,0.12); border: 1px solid rgba(14,165,233,0.3); color: var(--accent-light);">
                Catalog v{{ $catalogVersion }}
            </div>
        </div>
    </div>

    {{-- ============ Designs gallery variants ============ --}}
    <div class="glass rounded-2xl border border-white/10 p-5 mb-5">
        <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
            <div>
                <h3 class="text-sm font-bold text-white/90 ak-strong">Designs gallery variants</h3>
                <p class="text-xs text-white/40 mt-0.5 ak-muted">Per-block looks shown in the editor's "Designs" tab.</p>
            </div>
            <div class="flex items-center gap-2">
                <form method="GET" action="{{ route('admin.block-designs.index') }}">
                    <select name="type" onchange="this.form.submit()"
                            class="text-sm rounded-xl px-3 py-2 border border-white/10 bg-white/5 text-white/80">
                        @foreach($typeOptions as $t)
                            <option value="{{ $t }}" @selected($t === $previewType)>{{ $t }}</option>
                        @endforeach
                    </select>
                </form>
                <a href="{{ route('admin.block-designs.variants.create') }}"
                   class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-semibold text-white"
                   style="background: var(--accent, #2563eb);">
                    <i class="fas fa-plus"></i> New variant
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            @foreach($variants as $v)
                @php
                    $isCustom = str_starts_with($v['key'], \App\Modules\User\Support\AdminBlockDesigns::KEY_PREFIX);
                    $isHidden = in_array($v['key'], $hiddenKeys, true);
                    $custom = $customByKey[$v['key']] ?? null;
                    $pv = $v['preview'] ?? [];
                @endphp
                <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl border"
                     style="border-color: var(--border-glass); background: var(--bg-glass); {{ $isHidden ? 'opacity:.55;' : '' }}">
                    {{-- swatch --}}
                    <div class="flex-shrink-0 w-16 h-9 flex items-center justify-center rounded-lg text-[10px] font-semibold"
                         style="background: {{ $pv['bg'] ?? '#1a1a2e' }};
                                color: {{ $pv['text'] ?? '#fff' }};
                                border-radius: {{ min((int)($pv['radius'] ?? 10), 18) }}px;
                                border: 1px {{ !empty($pv['dashed']) ? 'dashed' : 'solid' }} {{ $pv['border'] ?? 'rgba(255,255,255,0.15)' }};
                                {{ !empty($pv['shadow']) ? 'box-shadow:' . $pv['shadow'] . ';' : '' }}">
                        Aa
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold truncate" style="color: var(--text-primary);">
                            {{ $v['name'] }}
                            @if($isCustom)
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-md" style="background: rgba(14,165,233,0.15); color: var(--accent-light);">custom</span>
                            @else
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-md bg-white/5 text-white/40 ak-muted">built-in</span>
                            @endif
                            @if($isHidden)
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-md" style="background: rgba(239,68,68,0.15); color: #fca5a5;">hidden</span>
                            @endif
                            @if($isCustom && empty($custom['enabled']))
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-md" style="background: rgba(245,158,11,0.15); color: #fcd34d;">disabled</span>
                            @endif
                        </div>
                        <div class="text-[11px] text-white/35 truncate ak-muted">{{ $v['key'] }} @if(!empty($v['tags'])) &middot; {{ implode(', ', $v['tags']) }} @endif</div>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        @if($isCustom)
                            <form method="POST" action="{{ route('admin.block-designs.variants.move', $v['key']) }}">@csrf
                                <input type="hidden" name="direction" value="up">
                                <button class="w-7 h-7 rounded-lg text-white/50 hover:bg-white/10" title="Move up"><i class="fas fa-arrow-up text-xs"></i></button>
                            </form>
                            <form method="POST" action="{{ route('admin.block-designs.variants.move', $v['key']) }}">@csrf
                                <input type="hidden" name="direction" value="down">
                                <button class="w-7 h-7 rounded-lg text-white/50 hover:bg-white/10" title="Move down"><i class="fas fa-arrow-down text-xs"></i></button>
                            </form>
                            <a href="{{ route('admin.block-designs.variants.edit', $v['key']) }}"
                               class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-white/50 hover:bg-white/10" title="Edit"><i class="fas fa-pen text-xs"></i></a>
                        @endif
                        <form method="POST" action="{{ route('admin.block-designs.variants.duplicate', $v['key']) }}">@csrf
                            <button class="w-7 h-7 rounded-lg text-white/50 hover:bg-white/10" title="Duplicate as a custom variant"><i class="fas fa-clone text-xs"></i></button>
                        </form>
                        <form method="POST" action="{{ route('admin.block-designs.variants.toggle', $v['key']) }}">@csrf
                            <input type="hidden" name="hidden" value="{{ $isHidden ? 0 : 1 }}">
                            <button class="w-7 h-7 rounded-lg text-white/50 hover:bg-white/10" title="{{ $isHidden ? 'Show in gallery' : 'Hide from gallery' }}">
                                <i class="fas {{ $isHidden ? 'fa-eye' : 'fa-eye-slash' }} text-xs"></i>
                            </button>
                        </form>
                        @if($isCustom)
                            <form method="POST" action="{{ route('admin.block-designs.variants.delete', $v['key']) }}"
                                  onsubmit="return confirm('Delete this custom variant? Blocks using it keep their current styling.');">
                                @csrf @method('DELETE')
                                <button class="w-7 h-7 rounded-lg text-red-300/70 hover:bg-red-500/10" title="Delete"><i class="fas fa-trash text-xs"></i></button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ============ Global Block Theme presets ============ --}}
    <div class="glass rounded-2xl border border-white/10 p-5">
        <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
            <div>
                <h3 class="text-sm font-bold text-white/90 ak-strong">Block Theme presets</h3>
                <p class="text-xs text-white/40 mt-0.5 ak-muted">Page-wide presets shown in the editor's "Block Theme" settings tab.</p>
            </div>
            <a href="{{ route('admin.block-designs.templates.create') }}"
               class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-semibold text-white"
               style="background: var(--accent, #2563eb);">
                <i class="fas fa-plus"></i> New preset
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
            @foreach($templates as $key => $tpl)
                @php
                    $isCustom = str_starts_with($key, \App\Modules\User\Support\AdminBlockDesigns::KEY_PREFIX);
                    $isHidden = in_array($key, $hiddenTemplateKeys, true);
                    $custom = $customTemplates[$key] ?? null;
                @endphp
                <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl border"
                     style="border-color: var(--border-glass); background: var(--bg-glass); {{ $isHidden ? 'opacity:.55;' : '' }}">
                    <div class="flex-shrink-0 w-10 h-9 flex items-center justify-center rounded-lg"
                         style="background: {{ $tpl['preview_bg'] ?? '#1a1a2e' }}; color: {{ $tpl['preview_text'] ?? '#fff' }};">
                        <i class="fas {{ $tpl['icon'] ?? 'fa-swatchbook' }} text-xs"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold truncate" style="color: var(--text-primary);">
                            {{ $tpl['label'] ?? $key }}
                            @if($isCustom)
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-md" style="background: rgba(14,165,233,0.15); color: var(--accent-light);">custom</span>
                            @else
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-md bg-white/5 text-white/40 ak-muted">built-in</span>
                            @endif
                            @if($isHidden)
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-md" style="background: rgba(239,68,68,0.15); color: #fca5a5;">hidden</span>
                            @endif
                            @if($isCustom && empty($custom['enabled']))
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded-md" style="background: rgba(245,158,11,0.15); color: #fcd34d;">disabled</span>
                            @endif
                        </div>
                        <div class="text-[11px] text-white/35 truncate ak-muted">{{ $key }}</div>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        @if($isCustom)
                            <a href="{{ route('admin.block-designs.templates.edit', $key) }}"
                               class="w-7 h-7 inline-flex items-center justify-center rounded-lg text-white/50 hover:bg-white/10" title="Edit"><i class="fas fa-pen text-xs"></i></a>
                        @endif
                        <form method="POST" action="{{ route('admin.block-designs.templates.duplicate', $key) }}">@csrf
                            <button class="w-7 h-7 rounded-lg text-white/50 hover:bg-white/10" title="Duplicate as a custom preset"><i class="fas fa-clone text-xs"></i></button>
                        </form>
                        <form method="POST" action="{{ route('admin.block-designs.templates.toggle', $key) }}">@csrf
                            <input type="hidden" name="hidden" value="{{ $isHidden ? 0 : 1 }}">
                            <button class="w-7 h-7 rounded-lg text-white/50 hover:bg-white/10" title="{{ $isHidden ? 'Show in picker' : 'Hide from picker' }}">
                                <i class="fas {{ $isHidden ? 'fa-eye' : 'fa-eye-slash' }} text-xs"></i>
                            </button>
                        </form>
                        @if($isCustom)
                            <form method="POST" action="{{ route('admin.block-designs.templates.delete', $key) }}"
                                  onsubmit="return confirm('Delete this custom preset? Pages that applied it keep their current styling.');">
                                @csrf @method('DELETE')
                                <button class="w-7 h-7 rounded-lg text-red-300/70 hover:bg-red-500/10" title="Delete"><i class="fas fa-trash text-xs"></i></button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
