@extends('user.layouts.app')
@section('title', 'New ' . $kindMeta['label'] . ' configuration')

@section('content')
<div class="max-w-4xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Add ' . $kindMeta['label'] . ' configuration',
        'subtitle' => 'Save credentials once, pick this configuration anywhere it is needed.',
        'icon'     => $kindMeta['icon'],
        'back'     => route('user.integrations.index', ['tab' => $kind]),
    ])

    @if(! $provider)
        {{-- Step 1: pick a provider --}}
        <div class="card-premium p-6 mb-4">
            <h3 class="text-base font-bold mb-1" style="color: var(--text-primary);">Choose a provider</h3>
            <p class="text-xs mb-4" style="color: var(--text-muted);">You can add as many configurations as you want, different accounts, modes, or environments.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($providers as $pKey => $pSchema)
                    <a href="{{ route('user.integrations.create', ['kind' => $kind, 'provider' => $pKey]) }}"
                       class="flex items-center gap-3 p-4 rounded-xl border transition hover:scale-[1.02]"
                       style="background: var(--bg-glass-input); border-color: var(--border-glass);">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                             style="background: {{ $pSchema['color'] }}20;">
                            <i class="fas {{ $pSchema['icon'] }} text-lg" style="color: {{ $pSchema['color'] }};"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-bold text-sm" style="color: var(--text-primary);">{{ $pSchema['label'] }}</div>
                            <div class="text-[11px]" style="color: var(--text-muted);">{{ count($pSchema['fields']) }} field{{ count($pSchema['fields']) === 1 ? '' : 's' }}</div>
                        </div>
                        <i class="fas fa-chevron-right text-xs" style="color: var(--text-faint);"></i>
                    </a>
                @endforeach
            </div>
        </div>
    @else
        {{-- Step 2: fill in credentials --}}
        <form method="POST" action="{{ route('user.integrations.store', $kind) }}" class="space-y-4">
            @csrf
            <input type="hidden" name="provider" value="{{ $provider }}">

            <div class="card-premium p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center"
                         style="background: {{ $providerSchema['color'] }}20;">
                        <i class="fas {{ $providerSchema['icon'] }} text-lg" style="color: {{ $providerSchema['color'] }};"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold" style="color: var(--text-primary);">{{ $providerSchema['label'] }} configuration</h3>
                        <p class="text-[11px]" style="color: var(--text-muted);">Credentials are encrypted at rest.</p>
                    </div>
                    <a href="{{ route('user.integrations.create', ['kind' => $kind]) }}"
                       class="ml-auto text-xs underline" style="color: var(--text-muted);">Change provider</a>
                </div>

                <div class="mb-5">
                    <label class="block text-xs font-semibold mb-1.5" style="color: var(--text-primary);">
                        Configuration name <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="name" value="{{ old('name') }}"
                           placeholder="e.g. Production · Stripe · USD"
                           class="theme-input w-full" required>
                    <p class="text-[11px] mt-1" style="color: var(--text-faint);">Internal label so you can tell this config apart from others of the same provider.</p>
                    @error('name') <p class="text-[11px] mt-1 text-red-400">{{ $message }}</p> @enderror
                </div>

                @include('user.integrations._form-fields', ['providerSchema' => $providerSchema])

                <div class="mt-5 pt-4 border-t flex flex-wrap items-center gap-4" style="border-color: var(--border-glass);">
                    <label class="inline-flex items-center gap-2 text-xs cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} class="rounded">
                        <span style="color: var(--text-primary);">Enabled</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-xs cursor-pointer">
                        <input type="hidden" name="is_default" value="0">
                        <input type="checkbox" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }} class="rounded">
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
                    <i class="fas fa-save mr-1.5"></i> Save configuration
                </button>
            </div>
        </form>
    @endif
</div>
@endsection
