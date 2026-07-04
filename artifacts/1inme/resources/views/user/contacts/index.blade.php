@extends('user.layouts.app')

@section('title', 'Contacts')

@section('content')
@include('user.partials._plan_lock', ['feature' => 'leads', 'kind' => 'flag', 'label' => 'Leads capture'])
<div class="max-w-7xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Contacts',
        'subtitle' => 'Your address book — synced two-way with Google Contacts and silently linked to Sayzio Link in Bio pages.',
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
       style="background: linear-gradient(135deg, rgba(61,107,255,0.10), rgba(236,72,153,0.08)); border: 1px solid rgba(61,107,255,0.30); color: var(--text-primary);">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2">
                <i class="fas fa-spinner fa-spin text-indigo-400"></i>
                <span class="font-semibold">Import in progress</span>
                <span class="text-xs" style="color: var(--text-muted);">
                    {{ $activeImport->processed_rows }} / {{ $activeImport->total_rows }} rows ({{ $activeImport->progressPercent() }}%)
                </span>
            </div>
            <span class="text-xs font-medium" style="color:#90acff;">View summary <i class="fas fa-arrow-right ml-1 text-[10px]"></i></span>
        </div>
        <div class="mt-2 w-full h-1.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,.06);">
            <div class="h-full" style="width: {{ $activeImport->progressPercent() }}%; background:linear-gradient(135deg,#3d6bff,#ec4899);"></div>
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
                        <button class="w-full px-3 py-2 rounded-lg text-xs font-medium transition" style="background:rgba(61,107,255,.15);color:#90acff;border:1px solid rgba(61,107,255,.30)">
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
                <a href="{{ route('user.contacts.import') }}" class="block w-full px-3 py-2 rounded-lg text-xs font-medium text-center transition mb-2" style="background:rgba(61,107,255,.12);color:#90acff;border:1px solid rgba(61,107,255,.25)">
                    <i class="fas fa-file-import mr-1"></i> Import CSV / vCard
                </a>
                <a href="{{ route('user.contacts.scan.create') }}" class="block w-full px-3 py-2 rounded-lg text-xs font-medium text-center transition mb-2" style="background:rgba(236,72,153,.12);color:#ec4899;border:1px solid rgba(236,72,153,.25)">
                    <i class="fas fa-camera mr-1"></i> Scan card / brochure
                    <span class="ml-1 text-[10px] uppercase tracking-wide opacity-70">AI</span>
                </a>
                <a href="{{ route('user.contacts.follow-ups') }}" class="relative flex items-center justify-center w-full px-3 py-2 rounded-lg text-xs font-medium text-center transition" style="background:rgba(245,158,11,.12);color:#f59e0b;border:1px solid rgba(245,158,11,.25)">
                    <i class="fas fa-bell mr-1"></i> Follow-ups
                    @if(($contactsOverdueFollowUps ?? 0) > 0)
                        <span class="ml-2 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full text-[10px] font-bold leading-none text-white" style="background:#ef4444;" title="{{ $contactsOverdueFollowUps }} overdue">{{ $contactsOverdueFollowUps > 99 ? '99+' : $contactsOverdueFollowUps }}</span>
                    @endif
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
                        : 'linear-gradient(135deg,#22d3ee,#3d6bff)');
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
            <div class="card-premium p-5"
                 x-data="contactsSearch({ index: '{{ route('user.contacts.index') }}', tab: '{{ $tab }}', q: @js($search) })">
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <div class="inline-flex rounded-xl p-1" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);">
                        <button type="button" @click="setTab('all')"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold"
                           :class="tab === 'all' ? 'text-white' : ''"
                           :style="tab === 'all' ? 'background:linear-gradient(135deg,#3d6bff,#ec4899);' : 'color:var(--text-muted);'">
                            All <span class="ml-1 opacity-70">({{ $stats['total'] }})</span>
                        </button>
                        <button type="button" @click="setTab('biolink')"
                           class="px-3 py-1.5 rounded-lg text-xs font-semibold"
                           :class="tab === 'biolink' ? 'text-white' : ''"
                           :style="tab === 'biolink' ? 'background:linear-gradient(135deg,#3d6bff,#ec4899);' : 'color:var(--text-muted);'">
                            With Link in Bio <span class="ml-1 opacity-70">({{ $stats['biolink'] }})</span>
                        </button>
                    </div>

                    <div class="flex-1 min-w-[200px]">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-xs" style="color:var(--text-faint);"></i>
                            <input type="text" x-model="q" @input="onInput()" placeholder="Search by name, phone, or email"
                                   autocomplete="off" spellcheck="false"
                                   class="w-full pl-9 pr-9 py-2 rounded-xl text-sm" style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.10);color:var(--text-primary);">
                            <i x-show="loading" x-cloak class="fas fa-spinner fa-spin absolute right-3 top-1/2 -translate-y-1/2 text-xs" style="color:var(--text-faint);"></i>
                        </div>
                    </div>
                </div>

                <div id="contacts-list" x-ref="list" :class="loading ? 'opacity-60 transition-opacity' : 'transition-opacity'">
                    @include('user.contacts._list')
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function contactsSearch(cfg) {
    return {
        indexUrl: cfg.index,
        tab: cfg.tab || 'all',
        q: cfg.q || '',
        loading: false,
        _t: null,
        _seq: 0,

        init() {
            // Intercept pagination links inside the swapped list so paging never
            // triggers a full page reload (and keeps the current query + tab).
            this.$refs.list.addEventListener('click', (e) => {
                const a = e.target.closest('.pagination a, nav[role="navigation"] a, .mt-4 a');
                if (!a || !this.$refs.list.contains(a)) return;
                const href = a.getAttribute('href');
                if (!href || href === '#') return;
                e.preventDefault();
                try {
                    const u = new URL(href, window.location.origin);
                    this.reload(u.searchParams.get('page') || '1');
                } catch (_) { this.reload('1'); }
            });
        },

        onInput() {
            clearTimeout(this._t);
            this._t = setTimeout(() => this.reload('1'), 220);
        },

        setTab(tab) {
            if (this.tab === tab) return;
            this.tab = tab;
            this.reload('1');
        },

        buildUrl(page) {
            const params = new URLSearchParams();
            params.set('tab', this.tab);
            if ((this.q || '').trim() !== '') params.set('q', this.q.trim());
            if (page && page !== '1') params.set('page', page);
            const qs = params.toString();
            return this.indexUrl + (qs ? ('?' + qs) : '');
        },

        async reload(page) {
            const url = this.buildUrl(page);
            const seq = ++this._seq;
            this.loading = true;
            try {
                const r = await fetch(url, {
                    headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (seq !== this._seq) return; // a newer request superseded this one
                const html = await r.text();
                this.$refs.list.innerHTML = html;
                // Keep the address bar in sync so refresh / share / back works.
                try { window.history.replaceState(null, '', url); } catch (_) {}
            } catch (e) {
                // Leave the current list in place on a transient failure.
            } finally {
                if (seq === this._seq) this.loading = false;
            }
        },
    };
}
</script>
@endpush
@endsection
