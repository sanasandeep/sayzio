@extends('user.layouts.app')
@section('title', 'Leads')

@push('styles')
    @include('user.partials.bento-styles')
@endpush

@section('content')
@php
    $__sourceIcons = [
        'rsvp'             => 'fa-calendar-check',
        'form_submission'  => 'fa-file-signature',
        'subscriber'       => 'fa-envelope-open-text',
        'store_order'      => 'fa-bag-shopping',
        'restaurant_order' => 'fa-utensils',
        'service_booking'  => 'fa-clipboard-check',
        'review'           => 'fa-star',
        'event_interest'   => 'fa-bell',
    ];
@endphp
<div class="max-w-7xl mx-auto bento-stage" x-data="leadsQueue()">

    {{-- ===================== HERO ===================== --}}
    <div class="bento-hero">
        <div class="hero-grid">
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-2">
                    <span class="hero-chip"><i class="fas fa-user-plus"></i> {{ number_format($totalPending) }} pending</span>
                </div>
                <h1 class="hero-title gradient-text truncate" style="font-size: clamp(1.5rem, 3.2vw, 2.1rem);">Leads</h1>
                <p class="hero-subtitle">People captured across your links — review and add them to Contacts, or dismiss.</p>
            </div>
        </div>
    </div>

    {{-- ===================== SOURCE FILTER CHIPS ===================== --}}
    <div class="flex flex-wrap gap-2 my-6">
        <a href="{{ route('user.leads.index') }}" class="px-3 py-1.5 rounded-full text-xs font-medium transition"
           style="{{ !($filters['source'] ?? null) ? 'background: linear-gradient(135deg, #3d6bff, #5c83ff); color: #fff;' : 'background: var(--bg-input); color: var(--text-muted); border: 1px solid var(--border-subtle);' }}">
            All <span class="opacity-75">({{ number_format($totalPending) }})</span>
        </a>
        @foreach($sourceLabels as $key => $label)
        <a href="{{ route('user.leads.index', array_filter(['source' => $key, 'q' => $filters['q'] ?? null])) }}"
           class="px-3 py-1.5 rounded-full text-xs font-medium transition"
           style="{{ ($filters['source'] ?? null) === $key ? 'background: linear-gradient(135deg, #3d6bff, #5c83ff); color: #fff;' : 'background: var(--bg-input); color: var(--text-muted); border: 1px solid var(--border-subtle);' }}">
            <i class="fas {{ $__sourceIcons[$key] ?? 'fa-user' }} text-[10px]"></i>
            {{ $label }} <span class="opacity-75">({{ number_format($counts[$key] ?? 0) }})</span>
        </a>
        @endforeach
    </div>

    {{-- ===================== SEARCH + BULK ACTIONS ===================== --}}
    <div class="glass rounded-2xl p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            @if($filters['source'] ?? null)<input type="hidden" name="source" value="{{ $filters['source'] }}">@endif
            <div class="flex-1 min-w-[220px]">
                <label class="text-xs font-medium mb-1 block" style="color: var(--text-muted);">Search</label>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email, phone..." class="w-full px-3 py-2 rounded-xl text-sm outline-none" style="background: var(--bg-input); border: 1px solid var(--border-subtle); color: var(--text-primary);">
            </div>
            <button type="submit" class="px-4 py-2 rounded-xl text-sm font-medium text-white" style="background: linear-gradient(135deg, #3d6bff, #5c83ff);">Search</button>
            @if(($filters['q'] ?? '') !== '')
            <a href="{{ route('user.leads.index', array_filter(['source' => $filters['source'] ?? null])) }}" class="px-3 py-2 rounded-xl text-sm" style="color: var(--text-muted);">Clear</a>
            @endif

            <div class="ml-auto flex items-center gap-2">
                <button type="button" class="btn-ghost text-xs py-2" @click="bulkAct('approve')" :disabled="!selected.length">
                    <i class="fas fa-check text-[10px]"></i> Approve selected
                </button>
                <button type="button" class="btn-ghost text-xs py-2" @click="bulkAct('dismiss')" :disabled="!selected.length">
                    <i class="fas fa-xmark text-[10px]"></i> Dismiss selected
                </button>
            </div>
        </form>
    </div>

    @if(session('success'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm" style="background: rgba(16,185,129,0.12); color: #34d399; border: 1px solid rgba(16,185,129,0.25);">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm" style="background: rgba(239,68,68,0.12); color: #f87171; border: 1px solid rgba(239,68,68,0.25);">
        {{ session('error') }}
    </div>
    @endif

    {{-- Toasts for AJAX approve/dismiss results, incl. a link to the resulting contact --}}
    <div class="fixed bottom-6 right-6 z-50 flex flex-col gap-2 items-end" style="max-width: 22rem;" x-show="toasts.length" x-cloak>
        <template x-for="toast in toasts" :key="toast.id">
            <div class="px-4 py-3 rounded-xl text-sm shadow-lg"
                 :style="toast.ok ? 'background: rgba(16,185,129,0.16); color: #34d399; border: 1px solid rgba(16,185,129,0.3);' : 'background: rgba(239,68,68,0.16); color: #f87171; border: 1px solid rgba(239,68,68,0.3);'">
                <div class="flex items-start gap-2">
                    <span x-text="toast.message"></span>
                </div>
                <a :href="toast.contactUrl" x-show="toast.contactUrl" class="inline-block mt-1 font-medium underline">
                    <i class="fas fa-user text-[10px] mr-1"></i> View contact
                </a>
            </div>
        </template>
    </div>

    <div id="leads-list-wrap">
        @include('user.leads._list', ['leads' => $leads, 'sourceLabels' => $sourceLabels])
    </div>
</div>

@push('scripts')
<script>
function leadsQueue() {
    return {
        selected: [],
        toasts: [],
        toastId: 0,
        pushToast(ok, message, contactUrl = null) {
            const id = ++this.toastId;
            this.toasts.push({ id, ok, message, contactUrl });
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, contactUrl ? 8000 : 4000);
        },
        init() {
            document.addEventListener('change', (e) => {
                if (e.target.id === 'leads-select-all') {
                    const boxes = document.querySelectorAll('.lead-checkbox');
                    boxes.forEach(b => { b.checked = e.target.checked; });
                    this.syncSelected();
                } else if (e.target.classList.contains('lead-checkbox')) {
                    this.syncSelected();
                }
            });
            document.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-lead-action]');
                if (!btn) return;
                e.preventDefault();
                this.act(btn.dataset.leadAction, btn.dataset.sourceType, parseInt(btn.dataset.sourceId, 10), btn);
            });
        },
        syncSelected() {
            this.selected = Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(b => b.value);
        },
        csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        },
        act(action, sourceType, sourceId, btn) {
            if (btn) btn.disabled = true;
            fetch(`{{ url('/user/leads') }}/${action}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrf(),
                },
                body: JSON.stringify({ source_type: sourceType, source_id: sourceId }),
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const row = document.querySelector(`tr[data-source-type="${sourceType}"][data-source-id="${sourceId}"]`);
                    row?.remove();
                    this.pushToast(true, res.message || 'Done.', res.contact_url || null);
                } else {
                    this.pushToast(false, res.message || 'Something went wrong.');
                    if (btn) btn.disabled = false;
                }
            })
            .catch(() => { this.pushToast(false, 'Network error. Please try again.'); if (btn) btn.disabled = false; });
        },
        bulkAct(action) {
            if (!this.selected.length) return;
            const items = this.selected.map(v => {
                const [source_type, source_id] = v.split(':');
                return { source_type, source_id: parseInt(source_id, 10) };
            });
            fetch(`{{ url('/user/leads/bulk') }}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrf(),
                },
                body: JSON.stringify({ action, items }),
            })
            .then(r => r.json())
            .then(() => window.location.reload())
            .catch(() => alert('Network error. Please try again.'));
        },
    };
}
</script>
@endpush
@endsection
