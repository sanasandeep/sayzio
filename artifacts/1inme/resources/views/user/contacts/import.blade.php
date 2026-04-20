@extends('user.layouts.app')

@section('title', 'Import contacts')

@section('content')
<div class="max-w-3xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Import contacts',
        'subtitle' => 'Bring your list in from a CSV export or a vCard (.vcf) file. Each row is added the same way as a manual contact, so biolink auto-attach still runs.',
        'icon' => 'fa-file-import',
        'chips' => [
            ['icon' => 'fa-database text-cyan-400', 'text' => $remaining . ' slots remaining (cap ' . $softCap . ')'],
        ],
    ])

    @if(session('error'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
        <i class="fas fa-exclamation-circle mr-1.5"></i> {{ session('error') }}
    </div>
    @endif
    @if($errors->any())
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); color: #ef4444;">
        <i class="fas fa-exclamation-circle mr-1.5"></i> {{ $errors->first() }}
    </div>
    @endif

    <div class="card-premium p-6">
        <form method="POST" action="{{ route('user.contacts.import.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            <div>
                <label class="block text-xs font-semibold mb-2" style="color: var(--text-primary);">Choose a CSV or vCard file</label>
                <input type="file" name="file" accept=".csv,.txt,.vcf,.vcard" required
                       class="block w-full text-sm" style="color: var(--text-muted);">
                <p class="text-[11px] mt-2" style="color: var(--text-faint);">Up to 5&nbsp;MB. Larger lists can be split and uploaded in batches.</p>
            </div>

            <div class="text-xs space-y-2 px-4 py-3 rounded-xl" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);color:var(--text-muted);">
                <div><strong style="color:var(--text-primary);">CSV columns we recognise:</strong> Name (or First name + Last name), Phone, Email, Organization (Company). Header order doesn't matter and casing is ignored.</div>
                <div><strong style="color:var(--text-primary);">vCard:</strong> standard 3.0 / 4.0 .vcf files exported from Apple Contacts, Outlook, etc. Multiple cards in one file are fine.</div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
                    <i class="fas fa-upload mr-1"></i> Upload &amp; import
                </button>
                <a href="{{ route('user.contacts.index') }}" class="text-xs" style="color:var(--text-muted);">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
