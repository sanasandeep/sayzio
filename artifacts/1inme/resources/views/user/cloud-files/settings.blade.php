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
                        <div class="text-xs text-gray-400">Redirect URI: <code class="text-gray-300">{{ str_replace('__provider__', $provider, $callback) }}</code></div>
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
                    <label class="block text-xs text-gray-400 mb-1">Client ID</label>
                    <input type="text" name="client_id" value="{{ old('client_id', $row?->client_id) }}"
                           class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm" autocomplete="off">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1">Client Secret @if($row && $row->client_secret_encrypted)<span class="text-gray-500">(leave blank to keep {{ $row->maskedSecret() }})</span>@endif</label>
                    <input type="password" name="client_secret" value=""
                           class="w-full px-3 py-2 rounded-lg bg-white/5 border border-white/10 text-sm" autocomplete="off">
                </div>
            </div>

            <div class="mt-4 flex items-center justify-end gap-2">
                @if($row)
                    <button type="submit" formaction="{{ route('user.cloud-files.settings.destroy', $provider) }}"
                            formmethod="POST"
                            onclick="return confirm('Remove {{ $label }} OAuth app from this workspace? All connections will stop working.')"
                            class="px-3 py-2 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-200 text-sm">
                        @method('DELETE')
                        <i class="fas fa-trash mr-1"></i> Remove
                    </button>
                @endif
                <button type="submit" class="px-4 py-2 rounded-lg bg-cyan-500 hover:bg-cyan-600 text-white text-sm font-semibold">
                    <i class="fas fa-save mr-1"></i> Save
                </button>
            </div>
        </form>
    @endforeach
</div>
@endsection
