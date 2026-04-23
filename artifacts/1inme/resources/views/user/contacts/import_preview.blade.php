@extends('user.layouts.app')

@section('title', 'Preview import')

@section('content')
<style>
    /* Edit rows are collapsed by default and revealed via :target when the
       user clicks the per-row "Edit" link (no JS needed). */
    .import-edit-row { display: none; }
    .import-edit-row:target { display: table-row; }
</style>
<div class="max-w-5xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Preview before importing',
        'subtitle' => 'Review the rows we parsed from ' . $originalName . '. Nothing has been saved yet — confirm to create the contacts, or cancel to discard the upload.',
        'icon' => 'fa-table',
        'chips' => [
            ['icon' => 'fa-list text-cyan-400',           'text' => $stats['total'] . ' rows parsed'],
            ['icon' => 'fa-triangle-exclamation text-amber-400', 'text' => $stats['warnings'] . ' with warnings'],
            ['icon' => 'fa-database text-cyan-400',       'text' => $stats['remaining'] . ' slots remaining'],
        ],
    ])

    @if(session('success'))
    <div class="mb-4 px-4 py-3 rounded-xl text-sm" style="background: rgba(16,185,129,0.10); border: 1px solid rgba(16,185,129,0.25); color: #34d399;">
        <i class="fas fa-check mr-1.5"></i>{{ session('success') }}
    </div>
    @endif
    @if($errors->any())
    <div class="mb-4 px-4 py-3 rounded-xl text-sm" style="background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.25); color: #f87171;">
        <i class="fas fa-exclamation-circle mr-1.5"></i>{{ $errors->first() }}
    </div>
    @endif

    @if($stats['overCap'] > 0)
    <div class="mb-6 px-4 py-3 rounded-xl text-sm" style="background: rgba(245,158,11,0.10); border: 1px solid rgba(245,158,11,0.25); color: #f59e0b;">
        <i class="fas fa-exclamation-triangle mr-1.5"></i>
        Only {{ $stats['remaining'] }} slot(s) left — {{ $stats['overCap'] }} row(s) at the bottom of the file will be skipped if you confirm.
    </div>
    @endif

    <div class="card-premium p-5 mb-4">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase" style="color:var(--text-faint);">
                        <th class="py-2 pr-3">Row</th>
                        <th class="py-2 pr-3">Name</th>
                        <th class="py-2 pr-3">Phone</th>
                        <th class="py-2 pr-3">Email</th>
                        <th class="py-2 pr-3">Organization</th>
                        <th class="py-2 pr-3">Notes</th>
                        <th class="py-2 text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    @php $startIndex = ($rows->currentPage() - 1) * $rows->perPage(); @endphp
                    @foreach($rows as $i => $row)
                        @php
                            $absIndex = $startIndex + $i;
                            $name = ($row['display_name'] ?? '') ?: trim(($row['given_name'] ?? '') . ' ' . ($row['family_name'] ?? ''));
                            $phones = collect($row['phones'] ?? [])->pluck('value')->filter()->values();
                            $emails = collect($row['emails'] ?? [])->pluck('value')->filter()->values();
                            $hasWarn = !empty($row['warnings']);
                            $editId = 'edit-row-' . $absIndex;
                            // Pad phones/emails arrays so the form has at least
                            // one blank slot for the user to add a new entry.
                            $phoneRows = array_values($row['phones'] ?? []);
                            $phoneRows[] = ['label' => null, 'value' => ''];
                            $emailRows = array_values($row['emails'] ?? []);
                            $emailRows[] = ['label' => null, 'value' => ''];
                        @endphp
                        <tr style="border-top:1px solid rgba(255,255,255,.06); {{ $hasWarn ? 'background:rgba(245,158,11,0.05);' : '' }}">
                            <td class="py-2 pr-3 font-mono text-xs align-top" style="color:var(--text-muted);">#{{ $row['source_line'] ?? '?' }}</td>
                            <td class="py-2 pr-3 align-top" style="color:var(--text-primary);">
                                {{ $name !== '' ? $name : '—' }}
                            </td>
                            <td class="py-2 pr-3 align-top text-xs" style="color:var(--text-muted);">
                                @forelse($phones as $p)
                                    <div>{{ $p }}</div>
                                @empty
                                    <span style="color:var(--text-faint);">—</span>
                                @endforelse
                            </td>
                            <td class="py-2 pr-3 align-top text-xs" style="color:var(--text-muted);">
                                @forelse($emails as $e)
                                    <div>{{ $e }}</div>
                                @empty
                                    <span style="color:var(--text-faint);">—</span>
                                @endforelse
                            </td>
                            <td class="py-2 pr-3 align-top text-xs" style="color:var(--text-muted);">
                                {{ $row['organization'] ?? '—' }}
                            </td>
                            <td class="py-2 pr-3 align-top text-xs">
                                @if($hasWarn)
                                    @foreach($row['warnings'] as $w)
                                        <div style="color:#f59e0b;"><i class="fas fa-triangle-exclamation mr-1"></i>{{ $w }}</div>
                                    @endforeach
                                @else
                                    <span style="color:#34d399;"><i class="fas fa-check mr-1"></i>Looks good</span>
                                @endif
                            </td>
                            <td class="py-2 align-top text-right whitespace-nowrap">
                                <a href="#{{ $editId }}" class="inline-flex items-center text-xs px-2 py-1 rounded-md mr-1" style="background:rgba(255,255,255,.06); color:var(--text-muted); border:1px solid rgba(255,255,255,.08);">
                                    <i class="fas fa-pen mr-1"></i>Edit
                                </a>
                                <form method="POST" action="{{ route('user.contacts.import.preview.row.skip', ['token' => $token, 'index' => $absIndex]) }}{{ $rows->currentPage() > 1 ? '?page=' . $rows->currentPage() : '' }}" class="inline" onsubmit="return window.themedConfirmSubmit(this, {title: 'Skip this row?', message: 'It will be removed from the preview and not imported.', confirmText: 'Skip row', confirmIcon: 'fa-forward', iconClass: 'fa-forward'})">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center text-xs px-2 py-1 rounded-md" style="background:rgba(239,68,68,0.10); color:#f87171; border:1px solid rgba(239,68,68,0.25);">
                                        <i class="fas fa-xmark mr-1"></i>Skip
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <tr id="{{ $editId }}" class="import-edit-row" style="border-top:1px solid rgba(255,255,255,.04);">
                            <td colspan="7" class="py-0">
                                <div class="px-3 py-4 rounded-lg my-2" style="background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.06);">
                                    <form method="POST" action="{{ route('user.contacts.import.preview.row.update', ['token' => $token, 'index' => $absIndex]) }}{{ $rows->currentPage() > 1 ? '?page=' . $rows->currentPage() : '' }}">
                                        @csrf
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                                            <label class="block">
                                                <span class="text-[11px] uppercase" style="color:var(--text-faint);">Display name</span>
                                                <input type="text" name="display_name" value="{{ $row['display_name'] ?? '' }}" maxlength="191" class="w-full mt-1 px-2 py-1.5 rounded-md text-sm" style="background:rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.08); color:var(--text-primary);">
                                            </label>
                                            <label class="block">
                                                <span class="text-[11px] uppercase" style="color:var(--text-faint);">Organization</span>
                                                <input type="text" name="organization" value="{{ $row['organization'] ?? '' }}" maxlength="191" class="w-full mt-1 px-2 py-1.5 rounded-md text-sm" style="background:rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.08); color:var(--text-primary);">
                                            </label>
                                            <label class="block">
                                                <span class="text-[11px] uppercase" style="color:var(--text-faint);">Given name</span>
                                                <input type="text" name="given_name" value="{{ $row['given_name'] ?? '' }}" maxlength="191" class="w-full mt-1 px-2 py-1.5 rounded-md text-sm" style="background:rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.08); color:var(--text-primary);">
                                            </label>
                                            <label class="block">
                                                <span class="text-[11px] uppercase" style="color:var(--text-faint);">Family name</span>
                                                <input type="text" name="family_name" value="{{ $row['family_name'] ?? '' }}" maxlength="191" class="w-full mt-1 px-2 py-1.5 rounded-md text-sm" style="background:rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.08); color:var(--text-primary);">
                                            </label>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <div class="text-[11px] uppercase mb-1" style="color:var(--text-faint);">Phones</div>
                                                @foreach($phoneRows as $idx => $p)
                                                    <div class="flex gap-2 mb-1.5">
                                                        <select name="phones[{{ $idx }}][label]" class="px-2 py-1.5 rounded-md text-xs" style="background:rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.08); color:var(--text-primary);">
                                                            <option value="">Label</option>
                                                            @foreach(\App\Modules\User\Controllers\ContactController::PHONE_LABELS as $lbl)
                                                                <option value="{{ $lbl }}" @selected(($p['label'] ?? null) === $lbl)>{{ $lbl }}</option>
                                                            @endforeach
                                                        </select>
                                                        <input type="text" name="phones[{{ $idx }}][value]" value="{{ $p['value'] ?? '' }}" maxlength="80" placeholder="+1 555-…" class="flex-1 px-2 py-1.5 rounded-md text-sm" style="background:rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.08); color:var(--text-primary);">
                                                    </div>
                                                @endforeach
                                                <div class="text-[11px]" style="color:var(--text-faint);">Leave a row blank to remove it.</div>
                                            </div>
                                            <div>
                                                <div class="text-[11px] uppercase mb-1" style="color:var(--text-faint);">Emails</div>
                                                @foreach($emailRows as $idx => $e)
                                                    <div class="flex gap-2 mb-1.5">
                                                        <select name="emails[{{ $idx }}][label]" class="px-2 py-1.5 rounded-md text-xs" style="background:rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.08); color:var(--text-primary);">
                                                            <option value="">Label</option>
                                                            @foreach(\App\Modules\User\Controllers\ContactController::EMAIL_LABELS as $lbl)
                                                                <option value="{{ $lbl }}" @selected(($e['label'] ?? null) === $lbl)>{{ $lbl }}</option>
                                                            @endforeach
                                                        </select>
                                                        <input type="text" name="emails[{{ $idx }}][value]" value="{{ $e['value'] ?? '' }}" maxlength="191" placeholder="name@example.com" class="flex-1 px-2 py-1.5 rounded-md text-sm" style="background:rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.08); color:var(--text-primary);">
                                                    </div>
                                                @endforeach
                                                <div class="text-[11px]" style="color:var(--text-faint);">Leave a row blank to remove it.</div>
                                            </div>
                                        </div>

                                        <div class="mt-3 flex items-center gap-2">
                                            <button type="submit" class="px-3 py-1.5 rounded-md text-xs font-semibold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
                                                <i class="fas fa-check mr-1"></i>Save row
                                            </button>
                                            <a href="{{ route('user.contacts.import.preview', ['token' => $token, 'page' => $rows->currentPage()]) }}" class="px-3 py-1.5 rounded-md text-xs" style="background:rgba(255,255,255,.06); color:var(--text-muted); border:1px solid rgba(255,255,255,.08);">Cancel</a>
                                        </div>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $rows->links() }}
        </div>
    </div>

    <div class="flex items-center gap-3">
        <form method="POST" action="{{ route('user.contacts.import.confirm', ['token' => $token]) }}">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background:linear-gradient(135deg,#7c3aed,#ec4899);">
                <i class="fas fa-check mr-1"></i> Confirm import
            </button>
        </form>
        <form method="POST" action="{{ route('user.contacts.import.cancel', ['token' => $token]) }}">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold" style="background:rgba(255,255,255,.06);color:var(--text-muted);border:1px solid rgba(255,255,255,.08);">
                Cancel
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    var WARNING = 'You have unsaved changes in a row edit form. Leave anyway and lose them?';
    var dirtyForms = new Set();

    function markClean(form) {
        dirtyForms.delete(form);
    }

    function markDirty(form) {
        dirtyForms.add(form);
    }

    function hasDirty() {
        return dirtyForms.size > 0;
    }

    function confirmLeave() {
        return !hasDirty() || window.confirm(WARNING);
    }

    document.querySelectorAll('.import-edit-row form').forEach(function (form) {
        form.addEventListener('input', function () { markDirty(form); });
        form.addEventListener('change', function () { markDirty(form); });
        form.addEventListener('submit', function () { markClean(form); });

        // The "Cancel" link inside the edit form discards changes for that row.
        var cancelLink = form.querySelector('a[href*="contacts/import/preview"]');
        if (cancelLink) {
            cancelLink.addEventListener('click', function (e) {
                if (dirtyForms.has(form) && !window.confirm(WARNING)) {
                    e.preventDefault();
                    return;
                }
                markClean(form);
            });
        }
    });

    // Opening another row's editor (the per-row "Edit" link) or pagination
    // links navigate via anchors/hrefs — guard those too.
    document.querySelectorAll('a[href]').forEach(function (link) {
        // Skip the in-form cancel links; already handled above.
        if (link.closest('.import-edit-row')) return;
        link.addEventListener('click', function (e) {
            if (!confirmLeave()) {
                e.preventDefault();
            }
        });
    });

    // Confirm / outer Cancel forms at the bottom.
    document.querySelectorAll('form').forEach(function (form) {
        if (form.closest('.import-edit-row')) return;
        form.addEventListener('submit', function (e) {
            if (!confirmLeave()) {
                e.preventDefault();
            }
        });
    });

    window.addEventListener('beforeunload', function (e) {
        if (hasDirty()) {
            e.preventDefault();
            e.returnValue = WARNING;
            return WARNING;
        }
    });
})();
</script>
@endsection
