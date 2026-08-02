@extends('admin.layouts.app')
@section('title', 'Zio Ad-Block Policy')
@section('page-title', 'Zio Browser Ad-Block Policy')

@section('content')
<div class="max-w-5xl space-y-6">

    <div class="glass rounded-2xl border border-white/10 p-6">
        <h2 class="text-lg font-semibold text-white/90 ak-strong">Admin-mandated ad-block policy</h2>
        <p class="text-xs text-white/50 mt-1 max-w-2xl ak-muted">
            Domains listed here are enforced in every Zio Browser install and cannot be overridden by
            users ("Managed by Sayzio"). <strong class="text-white/70 ak-strong">Block</strong> forces ad blocking on,
            <strong class="text-white/70 ak-strong">Allow</strong> forces ads to be allowed. Matching includes subdomains.
            Browsers fetch this policy on launch and every 6 hours (version {{ $policy['version'] }},
            @if($policy['updated_at']) last updated {{ \Illuminate\Support\Carbon::parse($policy['updated_at'])->diffForHumans() }}@else never updated @endif).
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-xl px-4 py-3 bg-emerald-500/10 border border-emerald-500/30 text-emerald-200 text-sm ak-green">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-xl px-4 py-3 bg-red-500/10 border border-red-500/30 text-red-200 text-sm">
            {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-xl px-4 py-3 bg-red-500/10 border border-red-500/30 text-red-200 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid md:grid-cols-2 gap-6">
        @foreach(['block' => ['Force ad blocking on', 'fa-ban', 'text-red-300'], 'allow' => ['Force ads allowed on', 'fa-circle-check', 'text-emerald-300']] as $list => [$label, $icon, $tint])
            <div class="glass rounded-2xl border border-white/10 p-5 space-y-4">
                <div class="flex items-center gap-2">
                    <i class="fas {{ $icon }} {{ $tint }} text-sm"></i>
                    <h3 class="text-sm font-semibold text-white/85 ak-strong">{{ $label }}</h3>
                    <span class="ml-auto text-xs text-white/40 ak-muted">{{ count($policy[$list]) }} domain{{ count($policy[$list]) === 1 ? '' : 's' }}</span>
                </div>

                <form method="POST" action="{{ route('admin.zio-adblock-policy.store') }}" class="flex gap-2">
                    @csrf
                    <input type="hidden" name="list" value="{{ $list }}">
                    <input type="text" name="domains" required
                           placeholder="example.com (comma or newline separated)"
                           class="flex-1 min-w-0 rounded-xl bg-white/5 border border-white/10 px-3 py-2 text-sm text-white/85 placeholder-white/30">
                    <button type="submit"
                            class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white shrink-0">
                        Add
                    </button>
                </form>

                @if(count($policy[$list]) === 0)
                    <p class="text-xs text-white/40 ak-muted">No domains yet.</p>
                @else
                    <ul class="space-y-1">
                        @foreach($policy[$list] as $domain)
                            <li class="flex items-center gap-2 rounded-lg bg-white/5 border border-white/10 px-3 py-1.5">
                                <span class="text-sm font-mono text-white/80 ak-strong break-all">{{ $domain }}</span>
                                <form method="POST" action="{{ route('admin.zio-adblock-policy.destroy') }}" class="ml-auto">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="list" value="{{ $list }}">
                                    <input type="hidden" name="domain" value="{{ $domain }}">
                                    <button type="submit" title="Remove {{ $domain }}"
                                            class="text-white/40 hover:text-red-300 text-xs px-1">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endforeach
    </div>

    @if(!empty($policy['audit']))
        <div class="glass rounded-2xl border border-white/10 p-5 space-y-3">
            <h3 class="text-sm font-semibold text-white/85 ak-strong">Recent changes</h3>
            <ul class="space-y-1.5">
                @foreach($policy['audit'] as $entry)
                    <li class="text-xs text-white/60 ak-muted flex flex-wrap items-center gap-x-2">
                        <span class="{{ ($entry['action'] ?? '') === 'add' ? 'text-emerald-300 ak-green' : 'text-red-300' }} font-medium">
                            {{ ($entry['action'] ?? '') === 'add' ? 'Added' : 'Removed' }}
                        </span>
                        <span class="font-mono text-white/75 ak-strong break-all">{{ implode(', ', (array) ($entry['domains'] ?? [])) }}</span>
                        <span>{{ ($entry['action'] ?? '') === 'add' ? 'to' : 'from' }} the {{ $entry['list'] ?? '?' }} list</span>
                        <span>by {{ $entry['admin'] ?? 'unknown' }}</span>
                        @if(!empty($entry['at']))
                            <span class="text-white/35">· {{ \Illuminate\Support\Carbon::parse($entry['at'])->diffForHumans() }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
@endsection
