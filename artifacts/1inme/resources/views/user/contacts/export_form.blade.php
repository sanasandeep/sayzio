@extends('user.layouts.app')

@section('title', 'Export Contacts')

@section('content')
<div class="max-w-xl mx-auto">
    @include('user.partials.page-hero', [
        'title'    => 'Export Contacts',
        'subtitle' => 'Download your address book as a CSV or vCard file.',
        'icon'     => 'fa-file-export',
        'chips'    => [
            ['icon' => 'fa-database text-cyan-400', 'text' => $total . ' contacts in address book'],
        ],
    ])

    <div class="card-premium p-6">
        <form method="POST" action="{{ route('user.contacts.export.store') }}">
            @csrf

            {{-- Carry through any filter context if the user arrived from the list --}}
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="hidden" name="q"   value="{{ $q }}">

            @if($errors->any())
            <div class="mb-4 px-4 py-3 rounded-xl text-sm" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);color:#ef4444;">
                <i class="fas fa-exclamation-circle mr-1.5"></i>
                {{ $errors->first() }}
            </div>
            @endif

            {{-- Format --}}
            <div class="mb-6">
                <label class="block text-sm font-bold mb-3" style="color:var(--text-primary);">
                    Export format
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col items-start gap-2 cursor-pointer p-4 rounded-xl border transition"
                           style="background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.10);"
                           x-data x-on:click="$el.closest('.grid').querySelectorAll('label').forEach(l=>l.style.borderColor='rgba(255,255,255,.10)'); $el.style.borderColor='rgba(61,107,255,.60)'">
                        <div class="flex items-center gap-2.5 w-full">
                            <input type="radio" name="format" value="csv" class="accent-indigo-500" {{ old('format', 'csv') === 'csv' ? 'checked' : '' }}>
                            <i class="fas fa-file-csv text-emerald-400 text-lg"></i>
                            <span class="font-semibold text-sm" style="color:var(--text-primary);">CSV</span>
                        </div>
                        <p class="text-[11px] pl-6" style="color:var(--text-muted);">
                            Opens in Excel, Google Sheets. Re-importable — all fields preserved.
                        </p>
                    </label>
                    <label class="flex flex-col items-start gap-2 cursor-pointer p-4 rounded-xl border transition"
                           style="background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.10);"
                           x-data x-on:click="$el.closest('.grid').querySelectorAll('label').forEach(l=>l.style.borderColor='rgba(255,255,255,.10)'); $el.style.borderColor='rgba(61,107,255,.60)'">
                        <div class="flex items-center gap-2.5 w-full">
                            <input type="radio" name="format" value="vcf" class="accent-indigo-500" {{ old('format') === 'vcf' ? 'checked' : '' }}>
                            <i class="fas fa-address-card text-pink-400 text-lg"></i>
                            <span class="font-semibold text-sm" style="color:var(--text-primary);">vCard (.vcf)</span>
                        </div>
                        <p class="text-[11px] pl-6" style="color:var(--text-muted);">
                            Import into iPhone Contacts, Google Contacts, Outlook & more.
                        </p>
                    </label>
                </div>
            </div>

            {{-- Scope --}}
            <div class="mb-6">
                <label class="block text-sm font-bold mb-3" style="color:var(--text-primary);">
                    Which contacts?
                </label>
                <div class="flex flex-col gap-2">
                    <label class="flex items-center gap-3 p-3 rounded-xl cursor-pointer" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);">
                        <input type="radio" name="scope" value="all" class="accent-indigo-500" {{ old('scope', 'all') === 'all' ? 'checked' : '' }}>
                        <div>
                            <div class="text-sm font-medium" style="color:var(--text-primary);">All {{ number_format($total) }} contacts</div>
                            <div class="text-[11px]" style="color:var(--text-muted);">Your entire address book</div>
                        </div>
                    </label>
                    @if($q || $tab === 'biolink')
                    <label class="flex items-center gap-3 p-3 rounded-xl cursor-pointer" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.10);">
                        <input type="radio" name="scope" value="filtered" class="accent-indigo-500" {{ old('scope') === 'filtered' ? 'checked' : '' }}>
                        <div>
                            <div class="text-sm font-medium" style="color:var(--text-primary);">Current filtered view</div>
                            <div class="text-[11px]" style="color:var(--text-muted);">
                                @if($tab === 'biolink') With Link in Bio @endif
                                @if($q) · Search: "{{ $q }}" @endif
                            </div>
                        </div>
                    </label>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-white"
                        style="background:linear-gradient(135deg,#3d6bff,#ec4899);">
                    <i class="fas fa-download"></i> Export
                </button>
                <a href="{{ route('user.contacts.index', array_filter(['tab' => $tab, 'q' => $q])) }}"
                   class="px-4 py-2.5 rounded-xl text-sm font-medium"
                   style="background:rgba(255,255,255,.06);color:var(--text-muted);border:1px solid rgba(255,255,255,.08);">
                    Cancel
                </a>
            </div>

            <p class="mt-4 text-[11px]" style="color:var(--text-faint);">
                <i class="fas fa-info-circle mr-1"></i>
                Large address books (over 500 contacts) are generated in the background. You'll get a download link in seconds.
            </p>
        </form>
    </div>
</div>
@endsection
