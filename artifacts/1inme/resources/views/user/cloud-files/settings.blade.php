@extends('user.layouts.app')
@section('title', 'Cloud File Apps')
@section('content')
@include('user.cloud-files._tabs')

<div class="mb-5 px-4 py-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-200 text-sm">
    <i class="fas fa-info-circle mr-1"></i>
    Each provider needs an OAuth app. Use this redirect URI when registering it:
    <code class="ml-1 px-1.5 py-0.5 rounded bg-black/30 text-amber-100">{{ str_replace('__provider__', '{provider}', $callback) }}</code>
</div>

<div class="space-y-4">
    @foreach($rows as $provider => $row)
        @php
            $label = \App\Modules\User\Models\CloudProviderApp::PROVIDER_LABELS[$provider];
            $icon = \App\Modules\User\Models\CloudProviderApp::PROVIDER_ICONS[$provider];
        @endphp
        <form method="POST" action="{{ route('user.cloud-files.settings.update', $provider) }}"
              class="rounded-xl border border-white/10 p-5" style="background: var(--bg-card);">
            @csrf @method('PUT')
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center text-xl text-cyan-300">
                        <i class="{{ $icon }}"></i>
                    </div>
                    <div>
                        <div class="font-semibold">{{ $label }}</div>
                        <div class="text-xs" style="color: var(--text-faint);">Redirect URI: <code class="text-gray-300">{{ str_replace('__provider__', $provider, $callback) }}</code></div>
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="hidden" name="enabled" value="0">
                    <input type="checkbox" name="enabled" value="1" {{ ($row && $row->enabled) ? 'checked' : '' }}
                           class="w-4 h-4 rounded">
                    <span class="text-sm text-gray-300">Enabled</span>
                </label>
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label class="block text-xs mb-1" style="color: var(--text-faint);">Client ID</label>
                    <input type="text" name="client_id" value="{{ old('client_id', $row?->client_id) }}"
                           class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm" autocomplete="off">
                </div>
                <div>
                    <label class="block text-xs mb-1" style="color: var(--text-faint);">Client Secret @if($row && $row->client_secret_encrypted)<span style="color: var(--text-muted);">(leave blank to keep {{ $row->maskedSecret() }})</span>@endif</label>
                    @include('common.partials.password-field', [
                        'name' => 'client_secret',
                        'value' => '',
                        'autocomplete' => 'off',
                        'inputClass' => 'w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm',
                    ])
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs mb-1" style="color: var(--text-faint);">Custom redirect URI <span style="color: var(--text-muted);">(optional, leave blank to use the default above)</span></label>
                    <input type="url" name="redirect_uri" value="{{ old('redirect_uri', $row?->redirect_uri) }}"
                           placeholder="{{ str_replace('__provider__', $provider, $callback) }}"
                           class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm" autocomplete="off">
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-2"
                 x-data="{ testing: false, result: null }">
                <span x-show="result" x-text="result?.message"
                      :class="result?.ok ? 'text-emerald-300 text-sm' : 'text-rose-300 text-sm'"></span>
                @if($row)
                    <button type="button" :disabled="testing"
                            @click="
                                testing = true; result = null;
                                fetch('{{ route('user.cloud-files.settings.test', $provider) }}', {
                                    method: 'POST',
                                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                                }).then(r => r.json()).then(j => result = j).finally(() => testing = false);
                            "
                            class="px-3 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-sm">
                        <span x-show="!testing"><i class="fas fa-stethoscope mr-1"></i> Test connection</span>
                        <span x-show="testing"><i class="fas fa-spinner fa-spin"></i> Testing…</span>
                    </button>
                @endif
                <button type="submit" class="px-4 py-2 rounded-lg bg-cyan-500 hover:bg-cyan-600 text-white text-sm font-semibold">
                    <i class="fas fa-save mr-1"></i> Save
                </button>
            </div>
        </form>

        @if($row)
            {{-- Separate form so the DELETE method-spoof input lives at form
                 level, not nested inside a <button>. --}}
            <form method="POST" action="{{ route('user.cloud-files.settings.destroy', $provider) }}"
                  onsubmit="return window.themedConfirmSubmit(this, {title: 'Remove the {{ $label }} OAuth app?', message: 'All connections to {{ $label }} for this workspace will stop working.', confirmText: 'Remove', confirmIcon: 'fa-link-slash', iconClass: 'fa-link-slash'})"
                  class="mt-2 text-right">
                @csrf @method('DELETE')
                <button type="submit" class="px-3 py-2 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-200 text-sm">
                    <i class="fas fa-trash mr-1"></i> Remove
                </button>
            </form>
        @endif
    @endforeach
</div>
@endsection
