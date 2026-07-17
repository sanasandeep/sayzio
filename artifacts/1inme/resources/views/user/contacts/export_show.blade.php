@extends('user.layouts.app')

@section('title', 'Contact Export')

@section('content')
<div class="max-w-2xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Contact Export',
        'subtitle' => 'Your address book export',
        'icon'     => 'fa-file-export',
    ])

    <div class="card-premium p-6" id="export-status"
         x-data="exportPoll(@js($export->id), @js($export->status), @js(route('user.contacts.export.status', $export)))">

        @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl text-sm font-medium" style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);color:#10b981;">
            <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
        </div>
        @endif

        {{-- Status badge --}}
        <div class="flex items-center gap-3 mb-6">
            <div class="flex items-center justify-center w-10 h-10 rounded-full" style="background:rgba(61,107,255,.15);">
                <i class="fas"
                   :class="{
                       'fa-spinner fa-spin text-indigo-400': status === 'pending' || status === 'processing',
                       'fa-check text-emerald-400':          status === 'completed',
                       'fa-times text-red-400':              status === 'failed',
                   }"></i>
            </div>
            <div>
                <div class="text-sm font-bold" style="color:var(--text-primary);">
                    <span x-show="status === 'pending' || status === 'processing'">Generating export…</span>
                    <span x-show="status === 'completed'" x-cloak>Export ready</span>
                    <span x-show="status === 'failed'" x-cloak>Export failed</span>
                </div>
                <div class="text-xs mt-0.5" style="color:var(--text-muted);">
                    {{ $export->formatLabel() }}
                    <span x-show="contactCount > 0" x-cloak> · <span x-text="contactCount"></span> contacts</span>
                </div>
            </div>
        </div>

        {{-- In-progress bar --}}
        <div x-show="status === 'pending' || status === 'processing'" class="mb-6">
            <div class="w-full h-1.5 rounded-full overflow-hidden" style="background:rgba(255,255,255,.06);">
                <div class="h-full rounded-full animate-pulse" style="width:60%;background:linear-gradient(135deg,#3d6bff,#ec4899);"></div>
            </div>
            <p class="mt-3 text-xs" style="color:var(--text-muted);">Large address books may take a few seconds. This page updates automatically.</p>
        </div>

        {{-- Download button --}}
        <div x-show="status === 'completed'" x-cloak class="mb-6">
            <a href="{{ route('user.contacts.export.download', $export) }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-white"
               style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
                <i class="fas fa-download"></i>
                Download {{ $export->formatLabel() }}
            </a>
            <p class="mt-2 text-[11px]" style="color:var(--text-faint);">
                <i class="fas fa-clock mr-1"></i>
                Download link expires in 24 hours.
            </p>
        </div>

        {{-- Error state --}}
        <div x-show="status === 'failed'" x-cloak class="mb-6">
            <p class="text-sm" style="color:#f87171;">
                <i class="fas fa-exclamation-circle mr-1.5"></i>
                Something went wrong generating your export. Please try again.
            </p>
        </div>

        <div class="flex gap-3 flex-wrap">
            <a href="{{ route('user.contacts.index') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-medium"
               style="background:rgba(255,255,255,.06);color:var(--text-muted);border:1px solid rgba(255,255,255,.08);">
                <i class="fas fa-arrow-left text-[10px]"></i> Back to Contacts
            </a>
            <a href="{{ route('user.contacts.export.request') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-medium"
               style="background:rgba(61,107,255,.12);color:#90acff;border:1px solid rgba(61,107,255,.25);">
                <i class="fas fa-redo text-[10px]"></i> New Export
            </a>
        </div>
    </div>
</div>

@push('scripts')
<script>
function exportPoll(id, initialStatus, statusUrl) {
    return {
        status: initialStatus,
        contactCount: @js($export->contact_count),

        init() {
            if (this.status === 'pending' || this.status === 'processing') {
                this.poll();
            }
        },

        poll() {
            const interval = setInterval(async () => {
                try {
                    const r = await fetch(statusUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!r.ok) return;
                    const data = await r.json();
                    this.status       = data.status;
                    this.contactCount = data.contact_count ?? this.contactCount;
                    if (this.status !== 'pending' && this.status !== 'processing') {
                        clearInterval(interval);
                    }
                } catch (_) {}
            }, 2000);
        },
    };
}
</script>
@endpush
@endsection
