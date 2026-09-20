@extends('admin.layouts.app')
@section('title', $origin === 'new' ? 'New background look' : 'Edit background look')
@section('page-title', $origin === 'new' ? 'New background look' : $label)

@php
    $isNew  = $origin === 'new';
    $action = $isNew
        ? route('admin.bg-catalog.store', $kind)
        : route('admin.bg-catalog.update', [$kind, $entryKey]);

    $input = 'w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white ak-strong';
    $mono  = $input.' font-mono text-[12px] leading-relaxed';
@endphp

@section('content')
<div class="max-w-3xl space-y-6">
    @if($errors->any())
        <div class="glass rounded-2xl border border-rose-500/30 p-4 text-sm text-rose-200 ak-red space-y-1">
            @foreach($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if(!$isNew)
        <div class="glass rounded-2xl border border-white/10 p-4 text-xs text-white/60 ak-muted">
            @if($origin === 'shipped')
                This look ships with the code. Saving creates an override; you can put the original back at any time
                from the library, and the shipped version is never deleted.
            @elseif($origin === 'override')
                This is an edited version of a look that ships with the code. Restoring it from the library brings the
                shipped version back.
            @else
                This look was created here.
            @endif
        </div>
    @endif

    <form method="POST" action="{{ $action }}" class="glass rounded-2xl border border-white/10 p-6 space-y-5">
        @csrf
        @unless($isNew) @method('PUT') @endunless

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Name</label>
                <input type="text" name="label" value="{{ old('label', $label) }}" required class="{{ $input }}">
                <p class="text-[11px] text-white/40 mt-1 ak-note">What creators see under the swatch.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Key</label>
                @if($isNew)
                    <input type="text" name="entry_key" value="{{ old('entry_key') }}" required
                           placeholder="mesh_twilight" class="{{ $input }} font-mono">
                    <p class="text-[11px] text-white/40 mt-1 ak-note">Lowercase letters, digits, - and _. This is saved on every page that picks the look, so it never changes afterwards.</p>
                @else
                    <input type="text" value="{{ $entryKey }}" disabled class="{{ $input }} font-mono opacity-60">
                    <p class="text-[11px] text-white/40 mt-1 ak-note">Fixed: pages already store this.</p>
                @endif
            </div>
        </div>

        {{-- ===== Per-kind fields ===== --}}

        @if($kind === 'preset')
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Group</label>
                <select name="group" class="{{ $input }}">
                    @foreach(\App\Modules\User\Support\BgPresetCatalog::GROUPS as $g => $gLabel)
                        <option value="{{ $g }}" @selected(old('group', $form['group']) === $g)>{{ $gLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">CSS</label>
                <textarea name="css" rows="5" class="{{ $mono }}">{{ old('css', $form['css']) }}</textarea>
                <p class="text-[11px] text-white/40 mt-1 ak-note">Declarations only, e.g.
                    <code>background-color: #111827;background-image: radial-gradient(…)</code>.
                    No <code>&lt;</code>, <code>{</code> or <code>}</code>.</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Paper colour <span class="opacity-50">(torn group only)</span></label>
                    <input type="text" name="paper" value="{{ old('paper', $form['paper']) }}" placeholder="#cfe0e6" class="{{ $input }} font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Backdrop CSS <span class="opacity-50">(torn group only)</span></label>
                    <input type="text" name="backdrop" value="{{ old('backdrop', $form['backdrop']) }}" class="{{ $input }} font-mono">
                </div>
            </div>

        @elseif($kind === 'gradient')
            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Mood</label>
                    <select name="category" class="{{ $input }}">
                        @foreach(\App\Modules\User\Support\GradientCatalog::CATEGORIES as $c => $cLabel)
                            <option value="{{ $c }}" @selected(old('category', $form['category']) === $c)>{{ $cLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Type</label>
                    <select name="type" class="{{ $input }}">
                        @foreach(['linear' => 'Linear', 'radial' => 'Radial', 'conic' => 'Conic'] as $t => $tLabel)
                            <option value="{{ $t }}" @selected(old('type', $form['type']) === $t)>{{ $tLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Angle</label>
                    <input type="number" name="angle" min="0" max="360" value="{{ old('angle', $form['angle']) }}" class="{{ $input }}">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Stops</label>
                <textarea name="stops" rows="5" class="{{ $mono }}" placeholder="#ff6b6b 0&#10;#feca57 50&#10;#48dbfb 100">{{ old('stops', $form['stops']) }}</textarea>
                <p class="text-[11px] text-white/40 mt-1 ak-note">One per line: a hex colour, a space, then its position 0–100. At least two.</p>
            </div>

        @elseif($kind === 'mesh')
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Base colour</label>
                <input type="text" name="base" value="{{ old('base', $form['base']) }}" class="{{ $input }} font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Blobs</label>
                <textarea name="blobs" rows="5" class="{{ $mono }}" placeholder="#22d3ee 15 20 55&#10;#a78bfa 80 15 50">{{ old('blobs', $form['blobs']) }}</textarea>
                <p class="text-[11px] text-white/40 mt-1 ak-note">One per line: colour, x%, y%, spread%. Each blob is a soft radial glow over the base.</p>
            </div>

        @elseif($kind === 'pattern')
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">CSS</label>
                <textarea name="css" rows="5" class="{{ $mono }}">{{ old('css', $form['css']) }}</textarea>
                <p class="text-[11px] text-white/40 mt-1 ak-note">Declarations only. No <code>&lt;</code>, <code>{</code> or <code>}</code>.</p>
            </div>
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Representative colours</label>
                <input type="text" name="colors" value="{{ old('colors', $form['colors']) }}" placeholder="#111827, #374151" class="{{ $input }} font-mono">
                <p class="text-[11px] text-white/40 mt-1 ak-note">The mobile app cannot render CSS, so it draws these instead.</p>
            </div>

        @elseif($kind === 'tiles')
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Representative colours</label>
                <input type="text" name="colors" value="{{ old('colors', $form['colors']) }}" placeholder="#1e293b, #3b82f6, #0f172a" class="{{ $input }} font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Tile gradients</label>
                <textarea name="tiles" rows="7" class="{{ $mono }}" placeholder="linear-gradient(135deg, #1e293b, #0f172a)">{{ old('tiles', $form['tiles']) }}</textarea>
                <p class="text-[11px] text-white/40 mt-1 ak-note">One CSS value per line. The grid is 24 tiles and cycles through this list, so six reads as a palette rather than a repeat.</p>
            </div>

        @elseif($kind === 'torn')
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Tear shape</label>
                    <select name="style" class="{{ $input }}">
                        @foreach($styles as $s => $sLabel)
                            <option value="{{ $s }}" @selected(old('style', $form['style']) === $s)>{{ $sLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Paper colour</label>
                    <input type="text" name="paper" value="{{ old('paper', $form['paper']) }}" class="{{ $input }} font-mono">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Backdrop colours</label>
                <input type="text" name="backdrop" value="{{ old('backdrop', $form['backdrop']) }}" placeholder="#8aa6b4, #46626f" class="{{ $input }} font-mono">
                <p class="text-[11px] text-white/40 mt-1 ak-note">Exactly two: the gradient visible beyond the tear.</p>
            </div>

        @elseif($kind === 'torn_style')
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Sheets</label>
                <textarea name="sheets" rows="8" class="{{ $mono }}" placeholder="polygon(0% 0%, 72% 0%, …, 0% 100%) | 1.0">{{ old('sheets', $form['sheets']) }}</textarea>
                <p class="text-[11px] text-white/40 mt-1 ak-note">
                    One sheet per line: a <code>polygon(…)</code> clip path, then <code>|</code> and a shade from just above 0 to 1.
                    A shade below 1 darkens that sheet so stacked ones read as separate pieces of paper.
                </p>
            </div>
        @endif

        <div class="grid sm:grid-cols-2 gap-4 pt-2 border-t border-white/10">
            <div>
                <label class="block text-xs font-semibold text-white/70 mb-1.5 ak-strong">Sort order</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $sortOrder) }}" class="{{ $input }}">
            </div>
            <label class="flex items-center gap-2 text-sm text-white/80 ak-strong self-end pb-2">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $isActive))
                       class="rounded border-white/20 bg-black/30">
                Show in the picker
            </label>
        </div>

        <div class="flex items-center gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white">
                {{ $isNew ? 'Create' : 'Save' }}
            </button>
            <a href="{{ route('admin.bg-catalog.index', $kind) }}"
               class="px-4 py-2 rounded-xl text-sm font-medium bg-white/10 hover:bg-white/15 text-white/90 ak-strong">
                Back to the library
            </a>
        </div>
    </form>
</div>
@endsection
