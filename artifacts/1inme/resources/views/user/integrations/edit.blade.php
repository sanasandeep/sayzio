@extends('user.layouts.app')
@section('title', 'Edit ' . $config->name)

@section('content')
<div class="max-w-4xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => $config->name,
        'subtitle' => $providerSchema['label'] . ' · ' . $kindMeta['label'] . ' configuration',
        'icon'     => $providerSchema['icon'],
        'back'     => route('user.integrations.index', ['tab' => $kind]),
    ])

    <form method="POST" action="{{ route('user.integrations.update', $config) }}" class="space-y-4">
        @csrf @method('PUT')

        <div class="card-premium p-6">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center"
                     style="background: {{ $providerSchema['color'] }}20;">
                    <i class="fas {{ $providerSchema['icon'] }} text-lg" style="color: {{ $providerSchema['color'] }};"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold" style="color: var(--text-primary);">{{ $providerSchema['label'] }}</h3>
                    <p class="text-[11px]" style="color: var(--text-muted);">Provider cannot be changed once saved, delete and re-create to switch.</p>
                </div>
            </div>

            <div class="mb-5">
                <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-primary);">
                    Configuration name <span class="text-red-400">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name', $config->name) }}" class="theme-input w-full" required>
                @error('name') <p class="text-[11px] mt-1 text-red-400">{{ $message }}</p> @enderror
            </div>

            @include('user.integrations._form-fields', ['providerSchema' => $providerSchema, 'config' => $config, 'masked' => $masked])

            <div class="mt-5 pt-4 border-t flex flex-wrap items-center gap-4" style="border-color: var(--border-glass);">
                <label class="inline-flex items-center gap-2 text-xs cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $config->is_active) ? 'checked' : '' }} class="rounded">
                    <span style="color: var(--text-primary);">Enabled</span>
                </label>
                <label class="inline-flex items-center gap-2 text-xs cursor-pointer">
                    <input type="hidden" name="is_default" value="0">
                    <input type="checkbox" name="is_default" value="1" {{ old('is_default', $config->is_default) ? 'checked' : '' }} class="rounded">
                    <span style="color: var(--text-primary);">Use as default for {{ strtolower($kindMeta['label']) }}</span>
                </label>
            </div>
        </div>

        <div class="flex items-center gap-3 justify-end">
            <a href="{{ route('user.integrations.index', ['tab' => $kind]) }}"
               class="px-4 py-2 text-sm font-semibold rounded-lg"
               style="background: var(--bg-glass-input); color: var(--text-primary);">Cancel</a>
            <button type="submit" class="px-5 py-2 text-sm font-semibold rounded-lg"
                    style="background: var(--accent); color: #fff;">
                <i class="fas fa-save mr-1.5"></i> Save changes
            </button>
        </div>
    </form>
</div>
@endsection
