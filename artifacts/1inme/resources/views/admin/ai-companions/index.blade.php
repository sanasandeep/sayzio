@extends('admin.layouts.app')
@section('title', 'AI Companions')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    @if(session('success'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">{{ $errors->first() }}</div>@endif

    <div>
        <h1 class="text-2xl font-bold text-white">AI Companions</h1>
        <p class="text-sm text-white/50 mt-1">Placement-bound chatbots (biolink / external embed / inbox bot). Tune platform caps and disable abusive widgets here.</p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        @foreach([
            ['Companions',    $totals['companions']],
            ['Disabled',      $totals['disabled']],
            ['Conversations', $totals['conversations']],
            ['Turns / month', $totals['turns_month']],
            ['Credits / mo',  $totals['credits_month']],
        ] as [$lbl, $val])
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 text-center">
                <p class="text-[10px] uppercase tracking-wider text-white/40">{{ $lbl }}</p>
                <p class="text-xl font-bold text-white mt-1">{{ number_format($val) }}</p>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.ai-companions.caps.update') }}" class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-3">
        @csrf @method('PUT')
        <h2 class="text-sm font-bold text-white">Platform caps</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            @foreach(\App\Services\AI\CompanionSettings::capsDefault() as $key => $default)
                <div>
                    <label class="block text-[11px] font-semibold text-white/60 mb-1">{{ ucwords(str_replace('_', ' ', $key)) }}</label>
                    <input type="number" min="0" name="caps[{{ $key }}]" value="{{ $caps[$key] ?? $default }}"
                           class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white">
                </div>
            @endforeach
        </div>
        <div class="flex justify-end">
            <button class="px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-500 text-white text-sm">Save caps</button>
        </div>
    </form>

    <div class="rounded-2xl border border-white/10 bg-white/[0.03]">
        <div class="px-4 py-3 border-b border-white/10 flex items-center justify-between">
            <h2 class="text-sm font-bold text-white">All Companions</h2>
            <p class="text-[11px] text-white/40">{{ $companions->total() }} total</p>
        </div>
        <table class="w-full text-sm">
            <thead class="text-left text-[11px] text-white/50 uppercase tracking-wider">
                <tr>
                    <th class="px-4 py-2">Companion</th>
                    <th class="px-4 py-2">Owner</th>
                    <th class="px-4 py-2">Placement</th>
                    <th class="px-4 py-2">Persona</th>
                    <th class="px-4 py-2">Convs</th>
                    <th class="px-4 py-2">Last used</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
                @foreach($companions as $c)
                    <tr class="{{ $c->is_disabled ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3">
                            <div class="text-white font-medium">{{ $c->name }}</div>
                            <div class="text-[10px] text-white/40 font-mono">{{ $c->public_id }}</div>
                        </td>
                        <td class="px-4 py-3 text-white/80">
                            {{ optional($c->user)->name ?: '—' }}
                            <div class="text-[10px] text-white/40">{{ optional($c->user)->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-white/70">{{ \App\Modules\User\Models\AiCompanion::PLACEMENTS[$c->placement] ?? $c->placement }}</td>
                        <td class="px-4 py-3 text-white/70">{{ optional($c->persona)->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-white/70">{{ $c->conversations_count }}</td>
                        <td class="px-4 py-3 text-white/40 text-xs">{{ $c->last_used_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($c->is_disabled)
                                <form method="POST" action="{{ route('admin.ai-companions.enable', $c) }}" class="inline">@csrf
                                    <button class="px-2 py-1 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 text-xs"><i class="fas fa-check"></i> Enable</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.ai-companions.disable', $c) }}" class="inline" onsubmit="this.querySelector('input[name=reason]').value=prompt('Reason?','Abuse')||''; if(!this.querySelector('input[name=reason]').value){return false;}">
                                    @csrf <input type="hidden" name="reason">
                                    <button class="px-2 py-1 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-300 text-xs"><i class="fas fa-ban"></i> Disable</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-3">{{ $companions->links() }}</div>
    </div>
</div>
@endsection
