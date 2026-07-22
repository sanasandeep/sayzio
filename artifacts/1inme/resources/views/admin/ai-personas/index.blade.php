@extends('admin.layouts.app')
@section('title', 'AI Personas')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
    @if(session('success'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm ak-green">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm ak-red">{{ session('error') }}</div>@endif

    <div>
        <h1 class="text-2xl font-bold text-white ak-strong">AI Personas</h1>
        <p class="text-sm text-white/50 mt-1 ak-muted">Per-user conversational agents. Cap, audit, and disable here.</p>
    </div>

    <div class="grid grid-cols-3 gap-3">
        @php($__personaCards = [
            ['label'=>'Personas','val'=>$totals['personas'],'tint'=>'pink'],
            ['label'=>'Disabled','val'=>$totals['disabled'],'tint'=>'red'],
            ['label'=>'Used at least once','val'=>$totals['active'],'tint'=>'emerald'],
        ])
        @foreach($__personaCards as $card)
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4">
                <p class="text-[10px] uppercase tracking-wider text-white/40 ak-note">{{ $card['label'] }}</p>
                <p class="text-2xl font-bold text-{{ $card['tint'] }}-300 mt-1">{{ number_format($card['val']) }}</p>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.ai-personas.caps.update') }}" class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
        @csrf @method('PUT')
        <h3 class="text-white font-semibold ak-strong">Caps</h3>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
            @foreach($caps as $k => $v)
                <div>
                    <label class="text-[11px] uppercase tracking-wider text-white/40 ak-note">{{ str_replace('_',' ', $k) }}</label>
                    <input type="number" min="0" name="caps[{{ $k }}]" value="{{ old('caps.'.$k, $v) }}"
                        class="mt-1 w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2 text-white text-sm ak-strong">
                </div>
            @endforeach
        </div>
        <div class="flex justify-end"><button class="px-4 py-2 rounded-xl bg-pink-600 hover:bg-pink-500 text-white text-sm">Save caps</button></div>
    </form>

    @if($topUsers->isNotEmpty())
        <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
            <h3 class="text-white font-semibold mb-3 ak-strong">Top users by Persona count</h3>
            <table class="w-full text-sm text-left">
                <thead class="text-[11px] uppercase tracking-wider text-white/40 ak-note">
                    <tr><th class="py-2">User #</th><th>Personas</th><th>Last used</th></tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @foreach($topUsers as $row)
                        <tr>
                            <td class="py-2 text-white ak-strong">#{{ $row->user_id }}</td>
                            <td class="text-white/80 ak-strong">{{ $row->personas_count }}</td>
                            <td class="text-white/60 ak-muted">{{ $row->last_used ? \Carbon\Carbon::parse($row->last_used)->diffForHumans() : '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-5">
        <h3 class="text-white font-semibold mb-3 ak-strong">All Personas</h3>
        <table class="w-full text-sm text-left">
            <thead class="text-[11px] uppercase tracking-wider text-white/40 ak-note">
                <tr>
                    <th class="py-2">Name</th><th>Owner</th><th>Model</th><th>Minds</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @forelse($personas as $p)
                    <tr>
                        <td class="py-2 text-white ak-strong">{{ $p->name }}<br><span class="text-[10px] text-white/40 ak-note">v{{ optional($p->activeVersion)->revision ?? '—' }} · {{ $p->updated_at?->diffForHumans() }}</span></td>
                        <td class="text-white/70 ak-strong">{{ $p->user?->email ?? '—' }}</td>
                        <td class="text-white/60 text-[12px] ak-muted">{{ $p->model }}</td>
                        <td class="text-white/60 ak-muted">{{ $p->minds_count }}@if($p->use_default_mind) +d @endif</td>
                        <td>
                            @if($p->is_disabled)
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-red-500/10 text-red-300 border border-red-500/20 ak-red">Disabled</span>
                            @else
                                <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-300 border border-emerald-500/20 ak-green">Active</span>
                            @endif
                        </td>
                        <td class="text-right">
                            @if($p->is_disabled)
                                <form method="POST" action="{{ route('admin.ai-personas.enable', $p) }}" class="inline">@csrf
                                    <button class="text-[11px] px-2 py-1 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 ak-green">Enable</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.ai-personas.disable', $p) }}" class="inline"
                                      onsubmit="const r=prompt('Reason for disabling?'); if(!r) return false; this.querySelector('[name=reason]').value=r; return true;">
                                    @csrf
                                    <input type="hidden" name="reason">
                                    <button class="text-[11px] px-2 py-1 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-300 ak-red">Disable</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-white/40 ak-note">No Personas yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-3">{{ $personas->links() }}</div>
    </div>
</div>
@endsection
