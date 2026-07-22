@extends('admin.layouts.app')
@section('title', 'AI Companions')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">
    @if(session('success'))<div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm ak-green">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm ak-red">{{ $errors->first() }}</div>@endif

    <div>
        <h1 class="text-2xl font-bold text-white ak-strong">AI Companions</h1>
        <p class="text-sm text-white/50 mt-1 ak-muted">Placement-bound chatbots (biolink / external embed / inbox bot). Tune platform caps and disable abusive widgets here.</p>
    </div>

    @php
    $statTiles = [
        ['Companions',    $totals['companions'],    'Platform-wide total across all users'],
        ['Disabled',      $totals['disabled'],      'Platform-wide companions currently disabled'],
        ['Conversations', $totals['conversations'], 'Platform-wide all-time conversation count'],
        ['Turns / month', $totals['turns_month'],   'Platform-wide messages sent this calendar month'],
        ['Coins / mo',  $totals['credits_month'], 'Coins consumed platform-wide this calendar month'],
    ];
    @endphp

    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        @foreach($statTiles as [$lbl, $val, $sub])
            <div class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 text-center" title="{{ $sub }}">
                <p class="text-[10px] uppercase tracking-wider text-white/40 ak-note">{{ $lbl }}</p>
                <p class="text-xl font-bold text-white mt-1 ak-strong">{{ number_format($val) }}</p>
                <p class="text-[10px] text-white/30 mt-1 leading-tight ak-note">{{ $sub }}</p>
            </div>
        @endforeach
    </div>

    @php
    $capsDefaults = \App\Services\AI\CompanionSettings::capsDefault();
    $capsMeta = [
        'max_companions_per_user' => [
            'label' => 'Max Companions Per User',
            'scope' => 'Per user',
            'scopeColor' => 'blue',
            'help'  => 'Fallback companion limit per account when the user\'s plan doesn\'t set its own limit. Plan-level limits always win.',
        ],
        'max_allowed_domains' => [
            'label' => 'Max Allowed Domains',
            'scope' => 'Per companion',
            'scopeColor' => 'indigo',
            'help'  => 'Ceiling on the size of each companion\'s embed domain allow-list.',
        ],
        'visitor_rate_per_minute' => [
            'label' => 'Visitor Rate Per Minute',
            'scope' => 'Per visitor / companion',
            'scopeColor' => 'amber',
            'help'  => 'Anti-spam throttle: max messages a single visitor can send to one companion in any 60-second window.',
        ],
        'platform_hard_cap_per_month' => [
            'label' => 'Platform Hard Cap Per Month',
            'scope' => 'Per companion',
            'scopeColor' => 'red',
            'help'  => 'Absolute monthly message ceiling per companion. Overrides any higher limit set by the owner or their plan.',
        ],
        'default_free_turns_per_month' => [
            'label' => 'Default Free Turns Per Month',
            'scope' => 'Per companion',
            'scopeColor' => 'emerald',
            'help'  => 'Starting free-turn quota assigned to every newly created companion (coin-refunded turns each month).',
        ],
        'max_visitor_message_chars' => [
            'label' => 'Max Visitor Message Chars',
            'scope' => 'Per message',
            'scopeColor' => 'sky',
            'help'  => 'Character limit on a single visitor message. Longer input is truncated before being sent to the AI.',
        ],
    ];
    $scopeBadgeClasses = [
        'blue'   => 'bg-blue-500/10 text-blue-300 border-blue-500/20',
        'indigo' => 'bg-indigo-500/10 text-indigo-300 border-indigo-500/20',
        'amber'  => 'bg-amber-500/10 text-amber-300 border-amber-500/20',
        'red'    => 'bg-red-500/10 text-red-300 border-red-500/20',
        'emerald'=> 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20',
        'sky'    => 'bg-sky-500/10 text-sky-300 border-sky-500/20',
    ];
    @endphp

    <form method="POST" action="{{ route('admin.ai-companions.caps.update') }}" class="rounded-2xl border border-white/10 bg-white/[0.03] p-5 space-y-4">
        @csrf @method('PUT')
        <div>
            <h2 class="text-sm font-bold text-white ak-strong">Platform caps</h2>
            <p class="text-[11px] text-white/40 mt-0.5 ak-note">Scope badges show what each limit applies to. Plan-level overrides take precedence where noted.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($capsMeta as $key => $meta)
                @php $default = $capsDefaults[$key] ?? 0; @endphp
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <label for="cap_{{ $key }}" class="text-[11px] font-semibold text-white/70 ak-strong">{{ $meta['label'] }}</label>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-md border text-[10px] font-medium leading-none {{ $scopeBadgeClasses[$meta['scopeColor']] }}">{{ $meta['scope'] }}</span>
                    </div>
                    <input id="cap_{{ $key }}" type="number" min="0" name="caps[{{ $key }}]" value="{{ $caps[$key] ?? $default }}"
                           class="w-full bg-black/30 border border-white/10 rounded-lg px-3 py-2 text-sm text-white ak-strong">
                    <p class="text-[10px] text-white/40 leading-snug ak-note">{{ $meta['help'] }}</p>
                </div>
            @endforeach
        </div>
        <div class="flex justify-end">
            <button class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm">Save caps</button>
        </div>
    </form>

    <div class="rounded-2xl border border-white/10 bg-white/[0.03]">
        <div class="px-4 py-3 border-b border-white/10 flex items-center justify-between">
            <h2 class="text-sm font-bold text-white ak-strong">All Companions</h2>
            <p class="text-[11px] text-white/40 ak-note">{{ $companions->total() }} total</p>
        </div>
        <table class="w-full text-sm">
            <thead class="text-left text-[11px] text-white/50 uppercase tracking-wider ak-muted">
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
                            <div class="text-white font-medium ak-strong">{{ $c->name }}</div>
                            <div class="text-[10px] text-white/40 font-mono ak-note">{{ $c->public_id }}</div>
                        </td>
                        <td class="px-4 py-3 text-white/80 ak-strong">
                            {{ optional($c->user)->name ?: '—' }}
                            <div class="text-[10px] text-white/40 ak-note">{{ optional($c->user)->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-white/70 ak-strong">{{ \App\Modules\User\Models\AiCompanion::PLACEMENTS[$c->placement] ?? $c->placement }}</td>
                        <td class="px-4 py-3 text-white/70 ak-strong">{{ optional($c->persona)->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-white/70 ak-strong">{{ $c->conversations_count }}</td>
                        <td class="px-4 py-3 text-white/40 text-xs ak-note">{{ $c->last_used_at?->diffForHumans() ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($c->is_disabled)
                                <form method="POST" action="{{ route('admin.ai-companions.enable', $c) }}" class="inline">@csrf
                                    <button class="px-2 py-1 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 text-xs ak-green"><i class="fas fa-check"></i> Enable</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.ai-companions.disable', $c) }}" class="inline" onsubmit="this.querySelector('input[name=reason]').value=prompt('Reason?','Abuse')||''; if(!this.querySelector('input[name=reason]').value){return false;}">
                                    @csrf <input type="hidden" name="reason">
                                    <button class="px-2 py-1 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-300 text-xs ak-red"><i class="fas fa-ban"></i> Disable</button>
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
