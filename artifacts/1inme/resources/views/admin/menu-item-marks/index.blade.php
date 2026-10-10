@extends('admin.layouts.app')
@section('title', 'Menu Marks')
@section('page-title', 'Menu Marks')

@php
    $mkIcon = function (?string $key, ?string $color, int $times = 1) use ($icons) {
        if (! $key) {
            return '';
        }
        $shape = collect($icons)->firstWhere('key', $key);
        if (! $shape) {
            return '';
        }
        $out = '';
        for ($i = 0; $i < $times; $i++) {
            $out .= '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"'
                .' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:block"><path d="'
                .e($shape['path']).'"/>'
                .(! empty($shape['solid']) ? '<path d="'.e($shape['solid']).'" fill="currentColor" stroke="none"/>' : '')
                .'</svg>';
        }

        return '<span style="display:inline-flex;gap:1px;align-items:center;color:'.e($color ?: 'currentColor').'">'.$out.'</span>';
    };
@endphp

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6" x-data="{ editing: null }">
    <div class="mb-6">
        <h1 class="text-2xl font-bold mb-1" style="color: var(--text-primary);">Menu marks</h1>
        <p class="text-sm" style="color: var(--text-dimmed);">
            The labels a restaurant can put on a dish for diners to read: vegetarian, spicy,
            served hot, no garlic. This list is the whole vocabulary. Owners pick from it, they
            cannot invent their own, which is the only reason a green square means the same thing
            on every menu on the platform.
        </p>
    </div>

    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm ak-red">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Create --}}
    <div class="glass rounded-2xl border border-white/10 p-6 mb-6">
        <h3 class="text-lg font-semibold text-white mb-1 ak-strong">Add a mark</h3>
        <p class="text-xs text-white/40 mb-4 ak-note">
            Leave the icon empty and the mark draws as a small text chip instead. That is the right
            answer for anything with no clear picture at 14&nbsp;px, like "No garlic".
        </p>
        <form method="POST" action="{{ route('admin.menu-item-marks.store') }}" class="grid gap-3 md:grid-cols-6">
            @csrf
            <input type="text" name="key" required maxlength="32" placeholder="key, e.g. no-garlic" value="{{ old('key') }}"
                   class="md:col-span-2 px-3 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 outline-none ak-input">
            <input type="text" name="label" required maxlength="40" placeholder="Label diners read" value="{{ old('label') }}"
                   class="md:col-span-2 px-3 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 outline-none ak-input">
            <select name="group" class="px-3 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white outline-none ak-input">
                @foreach($groups as $key => $label)
                    <option value="{{ $key }}" @selected(old('group') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="icon" class="px-3 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white outline-none ak-input">
                <option value="">No icon (text chip)</option>
                @foreach($icons as $icon)
                    <option value="{{ $icon['key'] }}" @selected(old('icon') === $icon['key'])>{{ $icon['label'] }}</option>
                @endforeach
            </select>
            <input type="text" name="color" maxlength="7" placeholder="#0a8f3c" value="{{ old('color') }}"
                   class="md:col-span-2 px-3 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder:text-white/30 outline-none ak-input">
            <label class="md:col-span-2 flex items-center gap-2 text-sm text-white/70 px-1">
                <input type="checkbox" name="is_graded" value="1" @checked(old('is_graded'))>
                Graded (drawn 1, 2 or 3 times, like spice)
            </label>
            <button type="submit" class="md:col-span-2 px-6 py-2.5 bg-blue-600 text-white rounded-xl font-medium hover:bg-blue-700 transition">
                <i class="fas fa-plus mr-1"></i> Add mark
            </button>
        </form>
    </div>

    <div class="glass rounded-2xl border border-white/10 overflow-hidden">
        <table class="w-full">
            <thead>
                <tr class="border-b border-white/10 text-left text-xs uppercase tracking-wide text-white/40 ak-note">
                    <th class="px-5 py-3">On a menu</th>
                    <th class="px-5 py-3">Key</th>
                    <th class="px-5 py-3">Group</th>
                    <th class="px-5 py-3">Graded</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($marks as $mark)
                <tr class="border-b border-white/5 {{ $mark->is_active ? '' : 'opacity-50' }}">
                    <td class="px-5 py-4">
                        <span class="inline-flex items-center gap-2 text-sm" style="color: var(--text-primary);">
                            {!! $mkIcon($mark->icon, $mark->color, $mark->is_graded ? $mark->grades() : 1) !!}
                            @if(! $mark->icon)
                                <span class="text-[10.5px] font-semibold px-2 py-0.5 rounded-full bg-white/10">{{ $mark->label }}</span>
                            @else
                                <span>{{ $mark->label }}</span>
                            @endif
                        </span>
                        @unless($mark->is_active)
                            <div class="text-[11px] text-white/40 mt-1">Not offered to owners, not drawn on menus.</div>
                        @endunless
                    </td>
                    <td class="px-5 py-4 font-mono text-xs text-white/50">{{ $mark->key }}</td>
                    <td class="px-5 py-4 text-sm text-white/70">{{ $mark->groupLabel() }}</td>
                    <td class="px-5 py-4 text-sm text-white/70">
                        {{ $mark->is_graded ? 'Up to '.$mark->grades() : '—' }}
                    </td>
                    <td class="px-5 py-4 text-right whitespace-nowrap">
                        <button type="button" class="text-xs text-blue-300 hover:text-blue-200 mr-3"
                                @click="editing = (editing === {{ $mark->id }} ? null : {{ $mark->id }})">Edit</button>
                        <form method="POST" action="{{ route('admin.menu-item-marks.toggle', $mark) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-xs text-white/60 hover:text-white mr-3">
                                {{ $mark->is_active ? 'Switch off' : 'Switch on' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.menu-item-marks.destroy', $mark) }}" class="inline"
                              onsubmit="return confirm('Delete {{ $mark->label }}? Switching it off is almost always what you want instead: a dish that carries this mark keeps it, it just stops being drawn. Deleting orphans the key on every dish wearing it.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-rose-300 hover:text-rose-200">Delete</button>
                        </form>
                    </td>
                </tr>
                <tr x-show="editing === {{ $mark->id }}" x-cloak class="border-b border-white/5 bg-white/[0.03]">
                    <td colspan="5" class="px-5 py-4">
                        <form method="POST" action="{{ route('admin.menu-item-marks.update', $mark) }}" class="grid gap-3 md:grid-cols-6">
                            @csrf @method('PUT')
                            <input type="text" name="label" required maxlength="40" value="{{ $mark->label }}"
                                   class="md:col-span-2 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white outline-none ak-input">
                            <select name="group" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white outline-none ak-input">
                                @foreach($groups as $key => $label)
                                    <option value="{{ $key }}" @selected($mark->group === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <select name="icon" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white outline-none ak-input">
                                <option value="">No icon (text chip)</option>
                                @foreach($icons as $icon)
                                    <option value="{{ $icon['key'] }}" @selected($mark->icon === $icon['key'])>{{ $icon['label'] }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="color" maxlength="7" placeholder="#0a8f3c" value="{{ $mark->color }}"
                                   class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white placeholder:text-white/30 outline-none ak-input">
                            <label class="flex items-center gap-2 text-xs text-white/70">
                                <input type="checkbox" name="is_graded" value="1" @checked($mark->is_graded)> Graded
                            </label>
                            <div class="md:col-span-6 flex items-center gap-3">
                                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Save</button>
                                <span class="text-[11px] text-white/40">
                                    The key <code class="font-mono">{{ $mark->key }}</code> cannot change: every dish
                                    wearing this mark stores it, so renaming it would strip the mark off all of them.
                                </span>
                            </div>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-white/40">No marks yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
