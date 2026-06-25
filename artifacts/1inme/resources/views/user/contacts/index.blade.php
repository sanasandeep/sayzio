@extends('user.layouts.app')

@section('title', 'Contacts')

@section('content')
@include('user.partials._plan_lock', ['feature' => 'leads', 'kind' => 'flag', 'label' => 'Leads capture'])
<div class="max-w-7xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Contacts',
        'subtitle' => 'Your address book — synced two-way with Google Contacts and silently linked to 1INME Link in Bio pages.',
        'icon' => 'fa-address-book',
        'chips' => [
            ['icon' => 'fa-database text-cyan-400', 'text' => $stats['total'] . ' contacts'],
            ['icon' => 'fa-link text-pink-400',     'text' => $stats['biolink'] . ' linked to a Link in Bio'],
        ],
    ])

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
        <i class="fas fa-exclamation-circle mr-1.5"></i> {{ session('error') }}
    </div>
    @endif

    @isset($activeImport)
    @if($activeImport)
    <a href="{{ route('user.contacts.import.show', $activeImport) }}"
       class="block mb-6 px-4 py-3 rounded-xl text-sm transition"
       style="background: linear-gradient(135deg, rgba(124,58,237,0.10), rgba(236,72,153,0.08)); border: 1px solid rgba(124,58,237,0.30); color: var(--text-primary);">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2">
                <i class="fas fa-spinner fa-spin text-purple-400"></i>
                <span class="font-semibold">Import in progress</span>
                <span class="text-xs" style="color: var(--text-muted);">
                    {{ $activeImport->processed_rows }} / {{ $activeImport->total_rows }} rows ({{ $activeImport->progressPercent() }}%)
                </span>
            </div>
            <span class="text-xs font-medium" style="color:#a78bfa;">View summary <i class="fas fa-arrow-right ml-1 text-[10px]"></i></span>
        </div>
        <div class="mt-2 w-full h-1.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,.06);">
            <div class="h-full" style="width: {{ $activeImport->progressPercent() }}%; background:linear-gradient(135deg,#7c3aed,#ec4899);"></div>
        </div>
    </a>
    @endif
    @endisset

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {{-- Sidebar: Google Contacts connection --}}
        <div class="lg:col-span-1">
            <div class="card-premium p-5">
                <h3 class="text-sm font-bold mb-3" style="color: var(--text-primary);">Google Contacts</h3>
                @if($googleAccount)
                    <div class="text-xs mb-3" style="color: var(--text-muted);">
                        <i class="fab fa-google text-pink-400 mr-1"></i> {{ $googleAccount->account_email }}
                    </div>
                    <div class="text-[11px] mb-3" style="color: var(--text-faint);">
                        Last sync: {{ $googleAccount->last_synced_at ? $googleAccount->last_synced_at->diffForHumans() : 'never' }}
                        @if($googleAccount->last_sync_status === 'error')
                            <span class="text-red-400">— error</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('user.contacts.google.sync', $googleAccount) }}" class="mb-2">
                        @csrf
                        <button class="w-full px-3 py-2 rounded-lg text-xs font-medium transition" style="background:rgba(124,58,237,.15);color:#a78bfa;border:1px solid rgba(124,58,237,.30)">
                            <i class="fas fa-sync mr-1"></i> Sync now
                        </button>
                    </form>
                    <form method="POST" action="{{ route('user.contacts.google.destroy', $googleAccount) }}"
                          onsubmit="return window.themedConfirmSubmit(this, {title: 'Disconnect Google?', message: 'Existing contacts will stay; future changes will not sync.', confirmText: 'Disconnect', confirmIcon: 'fa-link-slash', iconClass: 'fa-link-slash'})">
                        @csrf @method('DELETE')
                        <button class="w-full px-3 py-2 rounded-lg text-xs font-medium transition" style="background:rgba(239,68,68,.10);color:#ef4444;border:1px solid rgba(239,68,68,.20)">
                            <i class="fas fa-unlink mr-1"></i> Disconnect
                        </button>
                    </form>
                @else
                    <p class="text-xs mb-3" style="color: var(--text-muted);">Connect to mirror your Google contacts here, two-way.</p>
                    <a href="{{ route('user.contacts.google.connect') }}" class="block w-full px-3 py-2 rounded-lg text-xs font-medium text-center transition" style="background:rgba(255,255,255,.06);color:var(--text-primary);border:1px solid rgba(255,255,255,.10)">
                        <i class="fab fa-google text-pink-400 mr-1"></i> Connect Google
                    </a>
                @endif
            </div>

            <div class="card-premium p-5 mt-4">
                <h3 class="text-sm font-bold mb-2" style="color: var(--text-primary);">Quick add</h3>
                <a href="{{ route('user.contacts.create') }}" class="block w-full px-3 py-2 rounded-lg text-xs font-medium text-center transition mb-2" style="background:rgba(34,211,238,.12);color:#22d3ee;border:1px solid rgba(34,211,238,.25)">
                    <i class="fas fa-user-plus mr-1"></i> New contact
                </a>
                <a href="{{ route('user.contacts.import') }}" class="block w-full px-3 py-2 rounded-lg text-xs font-medium text-center transition mb-2" style="background:rgba(124,58,237,.12);color:#a78bfa;border:1px solid rgba(124,58,237,.25)">
                    <i class="fas fa-file-import mr-1"></i> Import CSV / vCard
                </a>
                <a href="{{ route('user.contacts.scan.create') }}" class="block w-full px-3 py-2 rounded-lg text-xs font-medium text-center transition" style="background:rgba(236,72,153,.12);color:#ec4899;border:1px solid rgba(236,72,153,.25)">
                    <i class="fas fa-camera mr-1"></i> Scan card / brochure
                    <span class="ml-1 text-[10px] uppercase tracking-wide opacity-70">AI</span>
                </a>
            </div>
        </div>

        {{-- Main: list with tabs + search --}}
        <div class="lg:col-span-3">
            @php
                // Usage badge colour reflects how close the user is to the
                // plan cap so the warning stands out without a separate alert.
                $usageBarColor = $usage['at_cap']
                    ? 'linear-gradient(135deg,#ef4444,#f97316)'
                    : ($usage['near_cap']
                        ? 'linear-gradient(135deg,#f59e0b,#ec4899)'
                        : 'linear-gradient(135deg,#22d3ee,#7c3aed)');
            @endphp
            <div class="card-premium p-4 mb-4">
                <div class="flex items-center justify-between gap-3 mb-2 flex-wrap">
                    <div class="text-xs font-semibold flex items-center gap-2" style="color:var(--text-primary);">
                        <i class="fas fa-database text-cyan-400"></i>
                        Contacts used
                        @if($usage['unlimited'])
                            <span class="ml-1 font-mono" style="color:var(--text-muted);">Unlimited</span>
                            <span class="ml-1" style="color:var(--text-faint);">({{ number_format($usage['count']) }} contacts)</span>
                        @else
                            <span class="ml-1 font-mono" style="color:var(--text-muted);">{{ number_format($usage['count']) }} / {{ number_format($usage['cap']) }} contacts</span>
                        @endif
                    </div>
                    @if(!$usage['unlimited'] && ($usage['near_cap'] || $usage['at_cap']))
                        <a href="{{ route('user.upgrade') }}" class="px-3 py-1 rounded-lg text-[11px] font-bold text-white" style="background:linear-gradient(135deg,#f59e0b,#ec4899);">
                            <i class="fas fa-arrow-up mr-1"></i> Upgrade plan
                        </a>
                    @endif
                </div>
                @if(!$usage['unlimited'])
                    <div class="w-full h-1.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,.06);">
                        <div class="h-full rounded-full transition-all" style="width: {{ max(2, $usage['percent']) }}%; background: {{ $usageBarColor }};"></div>
                    </div>
                    @if($usage['at_cap'])
                        <p class="mt-2 text-[11px]" style="color:#fca5a5;">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            You've hit your plan's contact limit. New contacts and imports will be blocked until you upgrade or remove some.
                        </p>
                    @elseif($usage['near_cap'])
                        <p class="mt-2 text-[11px]" style="color:#fcd34d;">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            You're at {{ $usage['percent'] }}% of your plan's contact limit ({{ number_format($usage['cap'] - $usage['count']) }} left). Consider upgrading before you run out of room.
                        </p>
                    @endif
                @endif
            </div>
            <div class="card-premium p-5">
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <div class="inline-flex rounded-xl p-1" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);">
                        <a href="{{ route('user.contacts.index', ['tab' => 'all', 'q' => $search]) }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $tab === 'all' ? 'text-white' : '' }}"
                           style="{{ $tab === 'all' ? 'background:linear-gradient(135deg,#7c3aed,#ec4899);' : 'color:var(--text-muted);' }}">
                            All <span class="ml-1 opacity-70">({{ $stats['total'] }})</span>
                        </a>
                        <a href="{{ route('user.contacts.index', ['tab' => 'biolink', 'q' => $search]) }}"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold {{ $tab === 'biolink' ? 'text-white' : '' }}"
                           style="{{ $tab === 'biolink' ? 'background:linear-gradient(135deg,#7c3aed,#ec4899);' : 'color:var(--text-muted);' }}">
                            With Link in Bio <span class="ml-1 opacity-70">({{ $stats['biolink'] }})</span>
                        </a>
                    </div>

                    <form method="GET" action="{{ route('user.contacts.index') }}" class="flex-1 min-w-[200px]">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color:var(--text-faint);"></i>
                            <input type="text" name="q" value="{{ $search }}" placeholder="Search by name, phone, or email"
                                   class="w-full pl-9 pr-3 py-2 rounded-xl text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                        </div>
                    </form>
                </div>

                @if($contacts->isEmpty())
                    <div class="text-center py-16">
                        <div class="w-16 h-16 mx-auto rounded-2xl flex items-center justify-center mb-4" style="background: linear-gradient(135deg, rgba(34,211,238,0.18), rgba(124,58,237,0.18));">
                            <i class="fas fa-address-book text-2xl text-cyan-400"></i>
                        </div>
                        <p class="text-sm font-semibold mb-1" style="color: var(--text-primary);">No contacts yet</p>
                        <p class="text-xs mb-4" style="color: var(--text-muted);">Add one manually or connect your Google account.</p>
                        <a href="{{ route('user.contacts.create') }}" class="inline-block px-4 py-2 rounded-lg text-xs font-semibold" style="background:linear-gradient(135deg,#7c3aed,#ec4899);color:white">
                            <i class="fas fa-user-plus mr-1"></i> New contact
                        </a>
                    </div>
                @else
                    @if($tab === 'biolink')
                        {{-- Richer card view: includes the matched user's biolink URL inline. --}}
                        <div class="space-y-3">
                            @foreach($contacts as $c)
                                @php
                                    $bioLink = null;
                                    if ($c->biolinkUser) {
                                        $bioLink = \App\Modules\User\Models\Link::where('user_id', $c->biolink_user_id)
                                            ->where('type', 'biolink')->where('is_active', true)
                                            ->orderByDesc('id')->first();
                                    }
                                @endphp
                                <div class="px-4 py-4 rounded-xl" style="background:linear-gradient(135deg,rgba(236,72,153,.06),rgba(124,58,237,.06));border:1px solid rgba(236,72,153,.20);">
                                    <div class="flex items-start gap-3">
                                        <div class="w-12 h-12 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
                                            @if($c->photoUrl())
                                                <img src="{{ $c->photoUrl() }}" class="w-full h-full rounded-full object-cover">
                                            @else
                                                {{ $c->initials() }}
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <a href="{{ route('user.contacts.show', $c) }}" class="text-sm font-semibold truncate block" style="color:var(--text-primary);">
                                                {{ $c->nameForDisplay() }}
                                                <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase" style="background:rgba(236,72,153,.15);color:#f472b6">1INME</span>
                                            </a>
                                            <div class="text-xs truncate" style="color:var(--text-muted);">
                                                {{ $c->phones->first()?->value ?? $c->emails->first()?->value ?? '—' }}
                                            </div>
                                            <div class="mt-2 text-[11px] flex items-center gap-2 flex-wrap">
                                                <span class="px-2 py-0.5 rounded" style="background:rgba(255,255,255,.05);color:var(--text-muted);">{{ '@' . $c->biolinkUser?->publicHandle() }}</span>
                                                @if($bioLink)
                                                    <a href="{{ url('/' . $bioLink->alias) }}" target="_blank" class="font-medium" style="color:#a78bfa;">
                                                        {{ url('/' . $bioLink->alias) }} <i class="fas fa-external-link-alt ml-1 text-[9px]"></i>
                                                    </a>
                                                @else
                                                    <span style="color:var(--text-faint);">no published Link in Bio</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex flex-col gap-1.5">
                                            @if($c->phones->first())
                                                <a href="tel:{{ $c->phones->first()->value_e164 ?: $c->phones->first()->value }}" class="px-2.5 py-1 rounded-lg text-[10px] font-medium text-center" style="background:rgba(34,197,94,.12);color:#22c55e;border:1px solid rgba(34,197,94,.20)"><i class="fas fa-phone"></i></a>
                                            @endif
                                            @if($bioLink)
                                                <a href="{{ url('/' . $bioLink->alias) }}" target="_blank" class="px-2.5 py-1 rounded-lg text-[10px] font-bold text-white text-center" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">Open</a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            @foreach($contacts as $c)
                                <a href="{{ route('user.contacts.show', $c) }}" class="flex items-center gap-3 px-4 py-3 rounded-xl transition" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);" onmouseover="this.style.background='rgba(124,58,237,.08)'" onmouseout="this.style.background='rgba(255,255,255,.04)'">
                                    <div class="w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold text-white" style="background: linear-gradient(135deg,#7c3aed,#ec4899);">
                                        @if($c->photoUrl())
                                            <img src="{{ $c->photoUrl() }}" class="w-full h-full rounded-full object-cover">
                                        @else
                                            {{ $c->initials() }}
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-semibold truncate" style="color:var(--text-primary);">
                                            {{ $c->nameForDisplay() }}
                                            @if($c->biolink_user_id)
                                                <span class="ml-1 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase" style="background:rgba(236,72,153,.15);color:#f472b6">1INME</span>
                                            @endif
                                        </div>
                                        <div class="text-xs truncate" style="color:var(--text-muted);">
                                            {{ $c->phones->first()?->value ?? $c->emails->first()?->value ?? '—' }}
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right text-[10px] opacity-40"></i>
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <div class="mt-4">{{ $contacts->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
