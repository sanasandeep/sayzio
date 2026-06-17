@extends('user.layouts.app')
@section('title', 'My Cloud Connections')
@section('content')
@include('user.cloud-files._tabs')

@php
    use App\Modules\User\Models\CloudProviderApp;
    $byProvider = $connections->keyBy('provider');
@endphp

<div class="grid gap-4 md:grid-cols-3">
    @foreach(CloudProviderApp::PROVIDERS as $p)
        @php
            $label = CloudProviderApp::PROVIDER_LABELS[$p];
            $icon = CloudProviderApp::PROVIDER_ICONS[$p];
            $app = $apps->get($p);
            $conn = $byProvider->get($p);
            $configured = $app && $app->isConfigured();
        @endphp
        <div class="rounded-xl border border-white/10 p-5" style="background: var(--bg-card);">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center text-xl text-cyan-300">
                    <i class="{{ $icon }}"></i>
                </div>
                <div class="font-semibold">{{ $label }}</div>
            </div>

            @if(!$configured)
                <p class="text-xs mb-3" style="color: var(--text-faint);">Not configured for this workspace yet. The owner needs to add OAuth credentials.</p>
                <button disabled class="w-full px-3 py-2 rounded-lg bg-white/5 text-sm cursor-not-allowed" style="color: var(--text-muted);">
                    Unavailable
                </button>
            @elseif($conn)
                <div class="text-xs mb-1" style="color: var(--text-faint);">Connected as</div>
                <div class="text-sm font-medium mb-2">{{ $conn->account_label ?: $conn->account_email ?: '—' }}</div>
                @if($conn->isBroken())
                    <div class="mb-3 px-2 py-1 rounded bg-rose-500/15 text-rose-300 text-xs"><i class="fas fa-exclamation-triangle mr-1"></i> Reconnect needed</div>
                @endif
                @if($conn->last_synced_at)
                    <div class="text-[11px] mb-3" style="color: var(--text-muted);">Last synced {{ $conn->last_synced_at->diffForHumans() }}</div>
                @endif
                <div class="flex gap-2">
                    <a href="{{ route('user.cloud-oauth.start', $p) }}"
                       class="flex-1 px-3 py-2 rounded-lg bg-cyan-500/15 hover:bg-cyan-500/25 text-cyan-200 text-sm text-center">
                        <i class="fas fa-rotate mr-1"></i> Reconnect
                    </a>
                    <form method="POST" action="{{ route('user.cloud-files.connections.destroy', $conn) }}" class="flex-1"
                          onsubmit="return window.themedConfirmSubmit(this, {title: 'Disconnect {{ $label }}?', message: 'Files already in the library stay.', confirmText: 'Disconnect', confirmIcon: 'fa-link-slash', iconClass: 'fa-link-slash'})">
                        @csrf @method('DELETE')
                        <button class="w-full px-3 py-2 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-200 text-sm">
                            <i class="fas fa-unlink mr-1"></i> Disconnect
                        </button>
                    </form>
                </div>
            @else
                <p class="text-xs mb-3" style="color: var(--text-faint);">Connect your {{ $label }} account to browse and add files.</p>
                <a href="{{ route('user.cloud-oauth.start', $p) }}"
                   class="block w-full px-3 py-2 rounded-lg bg-cyan-500 hover:bg-cyan-600 text-white text-sm text-center font-semibold">
                    <i class="fas fa-link mr-1"></i> Connect {{ $label }}
                </a>
            @endif
        </div>
    @endforeach
</div>
@endsection
