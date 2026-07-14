@extends('admin.layouts.app')
@section('title', 'Social OAuth')
@section('page-title', 'Social OAuth Setup')

@section('content')
<div class="space-y-6">
    @if(session('success'))
        <div class="glass rounded-xl p-3 border border-emerald-500/30 text-emerald-200 text-sm flex items-center gap-2">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

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

    <div class="glass rounded-2xl border border-white/10 p-5 space-y-3">
        <h2 class="text-sm font-semibold text-white">How this works</h2>
        <p class="text-xs text-white/50 leading-relaxed">
            Each social platform below offers a one-click "Continue with…" button to users when its OAuth credentials are configured.
            Enter the <strong>Client ID</strong> and <strong>Client Secret</strong> from the provider's developer console here — they are stored encrypted and take effect immediately, no restart or redeploy needed. If you leave a provider blank, the matching server environment variables are used as a fallback.
        </p>
        @include('admin.partials.help-note', [
            'body' => '<strong>General setup steps for each provider</strong>
                <ol class="list-decimal pl-4 mt-1 space-y-0.5">
                    <li>Click <em>Register a developer app</em> on any provider card below to open its developer console.</li>
                    <li>Create a new OAuth app (type: <strong>Web application</strong> or server-side app — not a CLI or mobile app).</li>
                    <li>Add the exact <strong>redirect URI</strong> shown on each card to the app\'s allowed redirect URIs. Even a trailing-slash mismatch will cause OAuth to fail.</li>
                    <li>Paste the <code>Client ID</code> and <code>Client Secret</code> into the card and click <strong>Save</strong>.</li>
                    <li>Values saved here are stored encrypted in the admin database and override any server environment variables.</li>
                </ol>',
        ])
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
                            <i class="fas fa-check"></i>
                            {{ $p['has_admin_value'] ? 'Configured' : 'Configured (env fallback)' }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-amber-500/15 text-amber-300 border border-amber-500/30">
                            <i class="fas fa-xmark"></i> Not configured
                        </span>
                    @endif
                </div>

                <div class="mt-4 rounded-lg bg-white/[0.03] border border-white/10 p-3">
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

                <form method="POST" action="{{ route('admin.social-oauth.update', $p['key']) }}" class="mt-4 space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">Client ID</label>
                            <input type="text" name="client_id" value="{{ old('client_id', $p['admin_client_id']) }}"
                                   autocomplete="off" spellcheck="false"
                                   placeholder="{{ $p['env_client_id_set'] ? 'Using env var '.$p['client_id_env'] : 'Paste the OAuth Client ID' }}"
                                   class="w-full rounded-lg bg-white/[0.04] border border-white/10 px-3 py-2 text-sm text-white/90 font-mono placeholder:text-white/25 focus:border-indigo-400/50 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wider text-white/40 mb-1">
                                Client Secret
                                @if($p['has_admin_secret'])
                                    <span class="text-emerald-300/70 normal-case tracking-normal">— saved</span>
                                @endif
                            </label>
                            @include('common.partials.password-field', [
                                'name' => 'client_secret',
                                'value' => '',
                                'autocomplete' => 'new-password',
                                'spellcheck' => false,
                                'placeholder' => $p['has_admin_secret'] ? '•••••••• (leave blank to keep)' : ($p['env_secret_set'] ? 'Using env var '.$p['client_secret_env'] : 'Paste the OAuth Client Secret'),
                                'inputClass' => 'w-full rounded-lg bg-white/[0.04] border border-white/10 px-3 py-2 text-sm text-white/90 font-mono placeholder:text-white/25 focus:border-indigo-400/50 focus:outline-none',
                            ])
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <label class="inline-flex items-center gap-2 text-[11px] text-white/50 select-none">
                            <input type="checkbox" name="clear" value="1" class="rounded border-white/20 bg-white/5">
                            Clear stored credentials (revert to env vars)
                        </label>
                        <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-2 rounded-lg bg-indigo-500/20 hover:bg-indigo-500/30 border border-indigo-400/30 text-indigo-200">
                            <i class="fas fa-floppy-disk text-[11px]"></i> Save
                        </button>
                    </div>
                    <p class="text-[10px] text-white/30 font-mono">env fallback: {{ $p['client_id_env'] }} / {{ $p['client_secret_env'] }}</p>
                </form>

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
