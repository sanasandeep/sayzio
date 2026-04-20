@extends('admin.layouts.app')
@section('title', 'Social OAuth')
@section('page-title', 'Social OAuth Setup')

@section('content')
<div class="space-y-6">
    {{-- Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="glass rounded-xl p-4 border border-white/10">
            <div class="text-[11px] uppercase tracking-wider text-white/40 flex items-center gap-2"><i class="fas fa-plug"></i> Total providers</div>
            <div class="text-2xl font-bold text-white mt-1">{{ count($providers) }}</div>
        </div>
        <div class="glass rounded-xl p-4 border border-emerald-500/20">
            <div class="text-[11px] uppercase tracking-wider text-emerald-300/70 flex items-center gap-2"><i class="fas fa-check-circle"></i> Configured</div>
            <div class="text-2xl font-bold text-emerald-300 mt-1">{{ $configured }}</div>
        </div>
        <div class="glass rounded-xl p-4 border border-amber-500/20">
            <div class="text-[11px] uppercase tracking-wider text-amber-300/70 flex items-center gap-2"><i class="fas fa-triangle-exclamation"></i> Need setup</div>
            <div class="text-2xl font-bold text-amber-300 mt-1">{{ $unconfigured }}</div>
        </div>
    </div>

    <div class="glass rounded-2xl border border-white/10 p-5">
        <h2 class="text-sm font-semibold text-white">How this works</h2>
        <p class="text-xs text-white/50 mt-1 leading-relaxed">
            Each social platform below offers a one-click "Connect with…" button to creators when its OAuth credentials are present in the server environment.
            Until you set the listed environment variables, creators have to fall back to pasting an access token by hand. Set the variables, restart the app, and the buttons turn on automatically — no code changes needed.
        </p>
    </div>

    <div class="space-y-3">
        @foreach($providers as $p)
            <div class="glass rounded-2xl border {{ $p['configured'] ? 'border-emerald-500/30' : 'border-amber-500/30' }} p-5">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white/80 bg-white/5 border border-white/10">
                            <i class="{{ $p['icon'] }}"></i>
                        </div>
                        <div>
                            <div class="text-sm font-semibold text-white">{{ $p['label'] }}</div>
                            <div class="text-[11px] text-white/40 mt-0.5">key: <span class="font-mono">{{ $p['key'] }}</span></div>
                        </div>
                    </div>
                    @if($p['configured'])
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                            <i class="fas fa-check"></i> Configured
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-amber-500/15 text-amber-300 border border-amber-500/30">
                            <i class="fas fa-xmark"></i> Not configured
                        </span>
                    @endif
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                    <div class="rounded-lg bg-white/[0.03] border border-white/10 p-3">
                        <div class="text-[10px] uppercase tracking-wider text-white/40 mb-1">Client ID env var</div>
                        <div class="font-mono text-white/80 break-all">{{ $p['client_id_env'] }}</div>
                        <div class="mt-1 text-[11px] {{ !empty(env($p['client_id_env'])) ? 'text-emerald-300/80' : 'text-amber-300/80' }}">
                            <i class="fas {{ !empty(env($p['client_id_env'])) ? 'fa-check' : 'fa-xmark' }}"></i>
                            {{ !empty(env($p['client_id_env'])) ? 'Set' : 'Missing' }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-white/[0.03] border border-white/10 p-3">
                        <div class="text-[10px] uppercase tracking-wider text-white/40 mb-1">Client Secret env var</div>
                        <div class="font-mono text-white/80 break-all">{{ $p['client_secret_env'] }}</div>
                        <div class="mt-1 text-[11px] {{ !empty(env($p['client_secret_env'])) ? 'text-emerald-300/80' : 'text-amber-300/80' }}">
                            <i class="fas {{ !empty(env($p['client_secret_env'])) ? 'fa-check' : 'fa-xmark' }}"></i>
                            {{ !empty(env($p['client_secret_env'])) ? 'Set' : 'Missing' }}
                        </div>
                    </div>
                </div>

                <div class="mt-3 rounded-lg bg-white/[0.03] border border-white/10 p-3">
                    <div class="text-[10px] uppercase tracking-wider text-white/40 mb-1">Redirect URI to register with the provider</div>
                    <div class="flex items-center gap-2">
                        <code class="font-mono text-[12px] text-white/80 break-all flex-1">{{ $p['redirect_uri'] }}</code>
                        <button type="button"
                                class="text-[11px] px-2 py-1 rounded-md bg-white/5 hover:bg-white/10 border border-white/10 text-white/70"
                                onclick="navigator.clipboard.writeText(this.previousElementSibling.innerText); this.innerText='Copied'; setTimeout(()=>this.innerText='Copy',1500)">
                            Copy
                        </button>
                    </div>
                </div>

                @if($p['notes'])
                    <p class="mt-3 text-xs text-white/55 leading-relaxed">{{ $p['notes'] }}</p>
                @endif

                @if($p['register_at'])
                    <div class="mt-3">
                        <a href="{{ $p['register_at'] }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-300 hover:text-indigo-200">
                            <i class="fas fa-arrow-up-right-from-square"></i>
                            Register a developer app
                        </a>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
