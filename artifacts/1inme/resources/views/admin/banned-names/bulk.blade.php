@extends('admin.layouts.app')
@section('title', 'Bulk Import Banned Names')
@section('page-title', 'Bulk Import Banned Names')

@section('content')
<div class="max-w-3xl">
    <div class="glass rounded-2xl border border-white/10 p-6">
        <form method="POST" action="{{ route('admin.banned-names.bulk.store') }}"
              enctype="multipart/form-data" class="space-y-5">
            @csrf

            @if ($errors->any())
                <div class="rounded-xl px-4 py-3 bg-rose-500/10 border border-rose-500/30 text-rose-200 text-sm ak-red">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <div>
                <h2 class="text-lg font-semibold text-white/90 ak-strong">Bulk import</h2>
                <p class="text-xs text-white/50 mt-1 ak-muted">
                    Paste names (one per line, or comma-separated) and/or upload a CSV/text file.
                    Duplicates are skipped, invalid entries are reported. Only letters, numbers,
                    hyphens and underscores are allowed; max 100 chars each.
                </p>
            </div>

            <div>
                <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5 ak-muted">
                    Names
                </label>
                <textarea name="names" rows="10"
                          class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white font-mono ak-strong"
                          placeholder="admin&#10;support&#10;login&#10;billing">{{ old('names') }}</textarea>
            </div>

            <div>
                <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5 ak-muted">
                    Or upload CSV / text file <span class="text-white/30 normal-case ak-note">(optional, max 2 MB)</span>
                </label>
                <input type="file" name="file" accept=".csv,.txt,text/csv,text/plain"
                       class="block w-full text-sm text-white/80 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-white/10 file:text-white/80 hover:file:bg-white/15 ak-strong ak-input">
                <p class="text-[11px] text-white/40 mt-1.5 ak-note">
                    Use a single-column CSV or plain text file (one name per line). Any commas
                    are treated as additional separators.
                </p>
            </div>

            <div>
                <label class="block text-[11px] uppercase tracking-wider text-white/50 font-bold mb-1.5 ak-muted">
                    Note for all imported entries <span class="text-white/30 normal-case ak-note">(optional)</span>
                </label>
                <input type="text" name="note" maxlength="500" value="{{ old('note') }}"
                       class="w-full bg-black/30 border border-white/15 rounded-lg px-3 py-2 text-sm text-white ak-strong"
                       placeholder="e.g. Seeded from common reserved words">
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit" class="px-4 py-2 rounded-xl text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white">
                    Import names
                </button>
                <a href="{{ route('admin.banned-names.index') }}" class="text-sm text-white/60 hover:text-white ak-muted">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
