@extends('user.layouts.app')
@section('title', 'Verification Tick Types')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('user.profile-verification.admin.index') }}" class="text-xs font-medium transition-colors hover:text-blue-400" style="color: var(--text-muted);">
            <i class="fas fa-arrow-left mr-1"></i>Back to Queue
        </a>
        <h1 class="text-2xl font-bold mt-2" style="color: var(--text-primary);">Verification Tick Types</h1>
        <p class="text-sm mt-1" style="color: var(--text-muted);">Manage the colored ticks that verified creators can be assigned.</p>
    </div>

    @if(session('success'))
    <div class="mb-4 p-4 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); color: #34d399; border: 1px solid rgba(16,185,129,0.2);">
        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    </div>
    @endif

    <div class="space-y-4">
        @foreach($tickTypes as $type)
        <div class="card-premium p-5" x-data="{ open: false }">
            <div class="flex items-center justify-between cursor-pointer" @click="open = !open">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background: {{ $type->color }}20;">
                        <i class="fas {{ $type->icon }} text-lg" style="color: {{ $type->color }};"></i>
                    </div>
                    <div>
                        <p class="text-sm font-bold" style="color: var(--text-primary);">{{ $type->name }}</p>
                        <p class="text-[11px]" style="color: var(--text-dimmed);">
                            Slug: <span class="font-mono">{{ $type->slug }}</span>
                            @if($type->admin_assigned_only) · <span class="text-amber-400">Admin-assigned only</span>@endif
                            @if(!$type->is_active) · <span class="text-red-400">Inactive</span>@endif
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="px-2 py-1 rounded-lg text-[10px] font-bold font-mono" style="background: {{ $type->color }}20; color: {{ $type->color }};">{{ $type->color }}</span>
                    <i class="fas fa-chevron-down text-xs transition-transform" :class="open ? 'rotate-180' : ''" style="color: var(--text-dimmed);"></i>
                </div>
            </div>

            <div x-show="open" x-transition class="mt-4 pt-4" style="border-top: 1px solid var(--border-glass);">
                <form action="{{ route('user.profile-verification.admin.tick-types.update', $type) }}" method="POST">
                    @csrf
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Display Name</label>
                            <input type="text" name="name" value="{{ $type->name }}" required maxlength="80" class="theme-input w-full text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Color (hex)</label>
                            <div class="flex gap-2 items-center">
                                <input type="color" name="color" value="{{ $type->color }}" class="w-10 h-10 rounded-lg cursor-pointer" style="background: transparent; border: 1px solid var(--border-glass);">
                                <input type="text" x-ref="colorText_{{ $type->id }}" value="{{ $type->color }}" class="theme-input flex-1 text-sm font-mono" @input="$el.previousElementSibling.value = $el.value" maxlength="7" pattern="#[0-9a-fA-F]{6}">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1.5" style="color: var(--text-muted);">Sort Order</label>
                            <input type="number" name="sort_order" value="{{ $type->sort_order }}" min="0" max="999" class="theme-input w-full text-sm">
                        </div>
                        <div class="flex items-center gap-3 pt-5">
                            <label class="flex items-center gap-2 cursor-pointer text-xs" style="color: var(--text-secondary);">
                                <input type="checkbox" name="is_active" value="1" {{ $type->is_active ? 'checked' : '' }} class="rounded">
                                Active
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 rounded-xl text-xs font-semibold text-white transition-all hover:-translate-y-0.5" style="background: linear-gradient(135deg, var(--color-primary-500, #3d6bff), var(--color-primary-400, #5c83ff));">
                            <i class="fas fa-save mr-1.5"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
